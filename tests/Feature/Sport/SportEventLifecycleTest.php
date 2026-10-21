<?php

namespace Tests\Feature\Sport;

use App\Models\Employee;
use App\Models\Sport\Attendance;
use App\Models\Sport\BackdateAudit;
use App\Models\Sport\BackdateSetting;
use App\Models\Sport\CancellationRequest;
use App\Models\Sport\Event;
use App\Models\Sport\ImportBatch;
use App\Models\Sport\NonMkoAttendance;
use App\Models\Sport\Participant;
use App\Models\Sport\RewardConfig;
use App\Models\Sport\Registration;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Acceptance tests untuk OMEO Sport MVP slice 1 (Section J spesifikasi).
 *
 * CATATAN PENTING soal strategi testing: project ini punya migration lama
 * yang gagal di migrate:fresh dari kosong (lihat README modul Sport untuk
 * detail — bukan disebabkan kode Sport), jadi test ini TIDAK memakai
 * RefreshDatabase (yang migrate SELURUH riwayat migration). Sebagai
 * gantinya, skema minimal dibuat manual di sini (pola yang sama dipakai
 * AppraisalAccessVisibilityTest::ensureSupportTables()), lalu migration
 * Sport yang sebenarnya di-load langsung dari filenya supaya skema yang
 * diuji 100% sama dengan yang akan jalan di production.
 */
class SportEventLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureSchema();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // jangan biarkan waktu beku bocor ke test lain
        parent::tearDown();
    }

    public function test_coach_led_event_cannot_be_published_without_positive_capacity(): void
    {
        $coordinator = User::factory()->create(['role' => 'koordinator']);
        $event = Event::create([
            'sport' => 'tenis', 'location' => 'GOR A', 'event_date' => now()->addDay()->toDateString(),
            'start_at' => now()->addDay(), 'end_at' => now()->addDay()->addHours(2),
            'has_coach' => true, 'coach_name' => 'Coach Andi', 'capacity' => null,
            'coordinator_user_id' => $coordinator->id, 'status' => Event::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($coordinator)->post(route('sport.coordinator.publish', $event));

        $response->assertSessionHas('error');
        $this->assertSame(Event::STATUS_DRAFT, $event->refresh()->status);
    }

    public function test_no_coach_event_can_be_published_without_capacity(): void
    {
        $coordinator = User::factory()->create(['role' => 'koordinator']);
        $event = Event::create([
            'sport' => 'lari', 'location' => 'Taman Kota', 'event_date' => now()->addDay()->toDateString(),
            'start_at' => now()->addDay(), 'end_at' => now()->addDay()->addHours(1),
            'has_coach' => false, 'capacity' => null,
            'coordinator_user_id' => $coordinator->id, 'status' => Event::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($coordinator)->post(route('sport.coordinator.publish', $event));

        $response->assertSessionHas('success');
        $this->assertSame(Event::STATUS_PUBLISHED, $event->refresh()->status);
    }

    /**
     * Pembuktian konkret konversi WIB->UTC lewat alur HTTP sungguhan (bukan
     * cuma memanggil Carbon secara langsung). "Sekarang" dibekukan pada
     * 2026-10-21 00:05:00 UTC, yang secara WIB (UTC+7) adalah 07:05 pagi.
     * Koordinator mengisi form dengan jam mulai "07:00" — bermaksud WIB,
     * yaitu 5 MENIT YANG LALU dari sudut pandang WIB, jadi event ini
     * seharusnya SUDAH MULAI dan pendaftaran harus TERTUTUP.
     *
     * Kalau input "07:00" keliru diperlakukan sebagai literal UTC (bug yang
     * coba diperbaiki di sini), start_at akan tersimpan sebagai
     * 2026-10-21 07:00:00 UTC — yang MASIH DI MASA DEPAN relatif ke waktu
     * beku 00:05 UTC — sehingga pendaftaran keliru masih tampak TERBUKA.
     * Assersi di bawah membuktikan hasil yang BENAR (tertutup), yang hanya
     * mungkin terjadi kalau konversi WIB->UTC benar-benar dijalankan.
     */
    public function test_coordinator_datetime_local_input_is_interpreted_as_wib_not_literal_utc(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 21, 0, 5, 0, 'UTC'));

        $coordinator = User::factory()->create(['role' => 'koordinator']);

        $storeResponse = $this->actingAs($coordinator)->post(route('sport.coordinator.store'), [
            'sport' => 'badminton',
            'location' => 'GOR Jakarta',
            'event_date' => '2026-10-21',
            'start_at' => '2026-10-21T07:00', // dimaksudkan WIB, 5 menit SEBELUM waktu beku (dalam kerangka WIB)
            'end_at' => '2026-10-21T09:00',   // dimaksudkan WIB
            'has_coach' => '0',
        ]);
        $storeResponse->assertSessionHas('success');

        $event = Event::first();

        // Bukti matematis: 07:00 WIB == 00:00 UTC (WIB = UTC+7).
        $this->assertSame('2026-10-21 00:00:00', $event->start_at->copy()->setTimezone('UTC')->toDateTimeString());
        $this->assertSame('2026-10-21 02:00:00', $event->end_at->copy()->setTimezone('UTC')->toDateTimeString());

        $this->actingAs($coordinator)->post(route('sport.coordinator.publish', $event));
        $event->refresh();

        // Waktu beku (00:05 UTC) sudah LEWAT start_at (00:00 UTC) -> pendaftaran harus TERTUTUP.
        $this->assertFalse($event->isRegistrationOpen(), 'Pendaftaran seharusnya sudah tertutup — kalau ini gagal, input WIB kemungkinan kembali diperlakukan sebagai UTC literal.');
    }

    /**
     * Membuktikan sisi EDIT (bukan cuma create): (1) form edit menampilkan
     * ulang jam yang ASLI diketik Koordinator (WIB), bukan jam UTC yang
     * tersimpan — kalau tampilannya tidak dikonversi balik, Koordinator
     * akan melihat jam yang sudah bergeser 7 jam setiap kali membuka form
     * edit; (2) submit perubahan jam lewat form update() memakai jalur
     * konversi yang SAMA seperti store() (keduanya memanggil
     * EventController::validated() yang sama), dan hasil akhirnya tetap
     * benar dipakai untuk pengecekan batas waktu (isAttendanceWindowOpen()).
     */
    public function test_editing_an_event_round_trips_wib_time_correctly_in_form_and_in_boundary_checks(): void
    {
        $coordinator = User::factory()->create(['role' => 'koordinator']);

        // 1) Buat event jam 07:00 WIB.
        $this->actingAs($coordinator)->post(route('sport.coordinator.store'), [
            'sport' => 'badminton', 'location' => 'GOR Jakarta', 'event_date' => '2026-10-21',
            'start_at' => '2026-10-21T07:00', 'end_at' => '2026-10-21T09:00', 'has_coach' => '0',
        ]);
        $event = Event::first();
        $this->assertSame('2026-10-21 00:00:00', $event->start_at->copy()->setTimezone('UTC')->toDateTimeString());

        // 2) Buka form EDIT — harus menampilkan ulang "07:00" (WIB asli yang
        // diketik Koordinator), BUKAN "00:00" (nilai UTC mentah yang
        // tersimpan). Kalau tampilan tidak dikonversi balik ke WIB, assersi
        // ini akan gagal karena yang muncul di HTML adalah "T00:00", bukan
        // "T07:00".
        $editResponse = $this->actingAs($coordinator)->get(route('sport.coordinator.edit', $event));
        $editResponse->assertOk();
        $editResponse->assertSee('2026-10-21T07:00', false);
        $editResponse->assertDontSee('2026-10-21T00:00', false);

        // 3) Koordinator mengubah jam mulai jadi 08:30 WIB lewat form update().
        Carbon::setTestNow(Carbon::create(2026, 10, 21, 1, 0, 0, 'UTC')); // = 08:00 WIB
        $updateResponse = $this->actingAs($coordinator)->put(route('sport.coordinator.update', $event), [
            'sport' => 'badminton', 'location' => 'GOR Jakarta', 'event_date' => '2026-10-21',
            'start_at' => '2026-10-21T08:30', 'end_at' => '2026-10-21T10:00', 'has_coach' => '0',
        ]);
        $updateResponse->assertSessionHas('success');
        $event->refresh();

        // Bukti matematis: 08:30 WIB == 01:30 UTC.
        $this->assertSame('2026-10-21 01:30:00', $event->start_at->copy()->setTimezone('UTC')->toDateTimeString());

        // 4) Waktu beku (08:00 WIB / 01:00 UTC) masih SEBELUM start_at baru
        // (08:30 WIB / 01:30 UTC) -> pendaftaran masih harus TERBUKA setelah
        // diedit. Ini membuktikan jalur update() ikut terkonversi dengan
        // benar, bukan cuma store().
        $event->update(['status' => Event::STATUS_PUBLISHED]);
        $this->assertTrue($event->isRegistrationOpen(), 'Setelah edit, pendaftaran harus masih terbuka — kalau gagal, konversi WIB tidak ikut berlaku di jalur update().');

        // 5) Form edit dibuka lagi setelah update — harus menampilkan jam
        // BARU (08:30 WIB), bukan jam lama atau nilai UTC mentah.
        $editAgainResponse = $this->actingAs($coordinator)->get(route('sport.coordinator.edit', $event));
        $editAgainResponse->assertSee('2026-10-21T08:30', false);
    }

    public function test_employee_can_register_before_event_starts_and_duplicate_is_rejected(): void
    {
        [$employee, $user] = $this->makeEmployeeUser();
        $event = $this->publishedEvent();

        $first = $this->actingAs($user)->post(route('sport.register', $event));
        $first->assertSessionHas('success');
        $this->assertDatabaseCount('sport_registrations', 1);

        // Daftar lagi (klik dobel) — tidak boleh duplikat.
        $second = $this->actingAs($user)->post(route('sport.register', $event));
        $second->assertSessionHas('error');
        $this->assertDatabaseCount('sport_registrations', 1);
    }

    public function test_registration_is_rejected_after_event_starts(): void
    {
        [$employee, $user] = $this->makeEmployeeUser();
        $event = $this->publishedEvent(startAt: now()->subMinutes(5), endAt: now()->addHours(1));

        $response = $this->actingAs($user)->post(route('sport.register', $event));

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('sport_registrations', 0);
    }

    public function test_only_assigned_coordinator_can_manage_cancellation_and_registration_is_not_deleted(): void
    {
        [$employee, $participant] = $this->makeEmployeeUser();
        $coordinator = User::factory()->create(['role' => 'koordinator']);
        $otherCoordinator = User::factory()->create(['role' => 'koordinator']);
        $event = $this->publishedEvent(coordinator: $coordinator);

        $this->actingAs($participant)->post(route('sport.register', $event));
        $registration = Registration::first();

        $cancelResponse = $this->actingAs($participant)->post(route('sport.registrations.request-cancellation', $registration), ['reason' => 'Ada acara keluarga']);
        $cancelResponse->assertSessionHas('success');
        $this->assertSame(Registration::STATUS_CANCELLATION_REQUESTED, $registration->refresh()->status);

        $cancellationRequest = CancellationRequest::first();

        // Koordinator LAIN (bukan yang ditugaskan) tidak boleh memutuskan.
        $this->actingAs($otherCoordinator)
            ->post(route('sport.coordinator.cancellations.decide', $cancellationRequest))
            ->assertForbidden();
        $this->assertSame(CancellationRequest::STATUS_PENDING, $cancellationRequest->refresh()->status);

        // Koordinator yang ditugaskan BOLEH menyetujui.
        $this->actingAs($coordinator)
            ->post(route('sport.coordinator.cancellations.decide', $cancellationRequest))
            ->assertSessionHas('success');

        $this->assertSame(CancellationRequest::STATUS_APPROVED, $cancellationRequest->refresh()->status);
        $this->assertSame(Registration::STATUS_CANCELLED, $registration->refresh()->status);
        // Record TIDAK dihapus — cuma status berubah.
        $this->assertDatabaseHas('sport_registrations', ['id' => $registration->id]);
    }

    /**
     * Aturan yang dikonfirmasi: pengajuan pembatalan bisa diputuskan oleh
     * (a) Koordinator yang ditugaskan sebagai penanggung jawab utama, ATAU
     * (b) Admin/Finance sebagai CADANGAN selama pengajuan masih "pending"
     * (Koordinator belum memprosesnya). Salah satu keputusan sudah cukup —
     * tidak perlu urutan tertentu. Ini BUKAN bug, ini kebijakan yang
     * disengaja (lihat README modul Sport, bagian "Aturan Keputusan
     * Pembatalan").
     */
    public function test_admin_or_finance_can_decide_a_still_pending_cancellation_as_fallback(): void
    {
        [$employee, $participant] = $this->makeEmployeeUser();
        $coordinator = User::factory()->create(['role' => 'koordinator']);
        $financeUser = User::factory()->create(['role' => 'finance']);
        $event = $this->publishedEvent(coordinator: $coordinator);

        $this->actingAs($participant)->post(route('sport.register', $event));
        $registration = Registration::first();
        $this->actingAs($participant)->post(route('sport.registrations.request-cancellation', $registration));
        $cancellationRequest = CancellationRequest::first();

        // Koordinator BELUM memproses (masih pending) — Finance boleh memutuskan sebagai cadangan.
        $this->actingAs($financeUser)
            ->post(route('sport.coordinator.cancellations.decide', $cancellationRequest))
            ->assertSessionHas('success');

        $this->assertSame(CancellationRequest::STATUS_APPROVED, $cancellationRequest->refresh()->status);
        $this->assertSame((int) $financeUser->id, (int) $cancellationRequest->decided_by_user_id);
        $this->assertSame(Registration::STATUS_CANCELLED, $registration->refresh()->status);
    }

    /**
     * Setelah 1 keputusan tersimpan, keputusan kedua (oleh siapa pun —
     * termasuk Koordinator yang tadinya berhak) tidak boleh mengubah apa
     * pun atau membuat catatan ganda.
     */
    public function test_second_decision_on_an_already_decided_cancellation_is_a_no_op(): void
    {
        [$employee, $participant] = $this->makeEmployeeUser();
        $coordinator = User::factory()->create(['role' => 'koordinator']);
        $financeUser = User::factory()->create(['role' => 'finance']);
        $event = $this->publishedEvent(coordinator: $coordinator);

        $this->actingAs($participant)->post(route('sport.register', $event));
        $registration = Registration::first();
        $this->actingAs($participant)->post(route('sport.registrations.request-cancellation', $registration));
        $cancellationRequest = CancellationRequest::first();

        // Finance memutuskan duluan (approve).
        $this->actingAs($financeUser)->post(route('sport.coordinator.cancellations.decide', $cancellationRequest));
        $decidedAtFirst = $cancellationRequest->refresh()->decided_at;
        $decidedByFirst = $cancellationRequest->decided_by_user_id;

        // Koordinator mencoba memutuskan lagi (reject) setelah Finance sudah approve — harus ditolak, tidak mengubah apa pun.
        $retryResponse = $this->actingAs($coordinator)->post(route('sport.coordinator.cancellations.reject', $cancellationRequest));
        $retryResponse->assertSessionHas('error');

        $cancellationRequest->refresh();
        $this->assertSame(CancellationRequest::STATUS_APPROVED, $cancellationRequest->status); // tetap approved, tidak berubah jadi rejected
        $this->assertSame((int) $decidedByFirst, (int) $cancellationRequest->decided_by_user_id); // actor tidak berubah
        $this->assertSame($decidedAtFirst->toDateTimeString(), $cancellationRequest->decided_at->toDateTimeString()); // timestamp tidak berubah
        $this->assertDatabaseCount('sport_cancellation_requests', 1); // tidak ada catatan ganda
    }

    public function test_regular_employee_and_unassigned_coordinator_cannot_decide_cancellation(): void
    {
        [$employee, $participant] = $this->makeEmployeeUser();
        [, $otherEmployeeUser] = $this->makeEmployeeUser();
        $coordinator = User::factory()->create(['role' => 'koordinator']);
        $event = $this->publishedEvent(coordinator: $coordinator);

        $this->actingAs($participant)->post(route('sport.register', $event));
        $registration = Registration::first();
        $this->actingAs($participant)->post(route('sport.registrations.request-cancellation', $registration));
        $cancellationRequest = CancellationRequest::first();

        // Karyawan biasa (bukan Koordinator/Admin/Finance) tidak boleh akses rute Koordinator sama sekali.
        $this->actingAs($otherEmployeeUser)
            ->post(route('sport.coordinator.cancellations.decide', $cancellationRequest))
            ->assertForbidden();

        $this->assertSame(CancellationRequest::STATUS_PENDING, $cancellationRequest->refresh()->status);
    }

    public function test_employee_cannot_access_coordinator_only_routes(): void
    {
        [$employee, $user] = $this->makeEmployeeUser();
        $coordinator = User::factory()->create(['role' => 'koordinator']);
        $event = $this->publishedEvent(coordinator: $coordinator);

        $this->actingAs($user)->get(route('sport.coordinator.index'))->assertForbidden();
        $this->actingAs($user)->get(route('sport.coordinator.create'))->assertForbidden();
        $this->actingAs($user)->get(route('sport.coordinator.show', $event))->assertForbidden();
        $this->actingAs($user)->get(route('sport.coordinator.edit', $event))->assertForbidden();
        $this->actingAs($user)->post(route('sport.coordinator.publish', $event))->assertForbidden();
        $this->actingAs($user)->post(route('sport.coordinator.cancel', $event))->assertForbidden();
    }

    public function test_employee_cannot_view_or_modify_another_employees_registration(): void
    {
        [$employeeA, $userA] = $this->makeEmployeeUser();
        [$employeeB, $userB] = $this->makeEmployeeUser();
        $event = $this->publishedEvent(startAt: now()->subHours(2), endAt: now()->subMinutes(10));

        $registrationA = $this->registerDirectly($event, $employeeA, $userA);

        // B mencoba ajukan pembatalan atas pendaftaran milik A.
        $this->actingAs($userB)
            ->post(route('sport.registrations.request-cancellation', $registrationA))
            ->assertForbidden();
        $this->assertSame(Registration::STATUS_REGISTERED, $registrationA->refresh()->status);

        // B mencoba konfirmasi kehadiran atas pendaftaran milik A.
        $this->actingAs($userB)
            ->post(route('sport.registrations.confirm-attendance', $registrationA))
            ->assertForbidden();
        $this->assertDatabaseCount('sport_attendances', 0);

        // Halaman detail event (sport.show) hanya memuat registrasi MILIK SENDIRI,
        // tidak pernah membocorkan status pendaftaran orang lain lewat prop "myRegistration".
        $responseB = $this->actingAs($userB)->get(route('sport.show', $event));
        $responseB->assertOk();
        $responseB->assertViewHas('myRegistration', null);
    }

    /**
     * Kuota penuh untuk event ber-coach — diuji SEKUENSIAL (isi sampai
     * penuh dulu, lalu 1 request lagi), BUKAN pengujian race condition
     * sungguhan. Lihat docblock SportController::register() untuk alasan
     * keterbatasan SQLite.
     */
    public function test_coach_led_event_registration_is_rejected_once_capacity_is_reached(): void
    {
        $coordinator = User::factory()->create(['role' => 'koordinator']);
        $event = Event::create([
            'sport' => 'tenis', 'location' => 'GOR A', 'event_date' => now()->addDay()->toDateString(),
            'start_at' => now()->addDay(), 'end_at' => now()->addDay()->addHours(2),
            'has_coach' => true, 'coach_name' => 'Coach Andi', 'capacity' => 1,
            'coordinator_user_id' => $coordinator->id, 'status' => Event::STATUS_PUBLISHED,
        ]);

        [, $userA] = $this->makeEmployeeUser();
        [, $userB] = $this->makeEmployeeUser();

        $this->actingAs($userA)->post(route('sport.register', $event))->assertSessionHas('success');
        $this->assertDatabaseCount('sport_registrations', 1);

        $this->actingAs($userB)->post(route('sport.register', $event))->assertSessionHas('error');
        $this->assertDatabaseCount('sport_registrations', 1); // tetap 1, tidak overbook
    }

    public function test_attendance_confirmation_only_accepted_within_one_hour_after_event_ends(): void
    {
        [$employee, $user] = $this->makeEmployeeUser();

        // Event baru selesai 5 menit lalu — dalam jendela 1 jam. Registrasi
        // dibuat langsung (bukan lewat alur pendaftaran HTTP) karena pada
        // skenario ini event-nya memang sudah lama mulai — pendaftaran
        // normal sudah pasti ditutup, yang mau diuji di sini cuma jendela
        // konfirmasi kehadirannya.
        $withinWindow = $this->publishedEvent(startAt: now()->subHours(3), endAt: now()->subMinutes(5));
        $reg1 = $this->registerDirectly($withinWindow, $employee, $user);

        $okResponse = $this->actingAs($user)->post(route('sport.registrations.confirm-attendance', $reg1));
        $okResponse->assertSessionHas('success');
        $this->assertDatabaseCount('sport_attendances', 1);

        // Konfirmasi ganda tidak menambah record.
        $this->actingAs($user)->post(route('sport.registrations.confirm-attendance', $reg1));
        $this->assertDatabaseCount('sport_attendances', 1);

        // Event selesai lebih dari 1 jam lalu — jendela sudah tutup.
        $expired = $this->publishedEvent(startAt: now()->subHours(5), endAt: now()->subHours(2));
        $reg2 = $this->registerDirectly($expired, $employee, $user);

        $lateResponse = $this->actingAs($user)->post(route('sport.registrations.confirm-attendance', $reg2));
        $lateResponse->assertSessionHas('error');
        $this->assertDatabaseCount('sport_attendances', 1);
    }

    /**
     * Section J acceptance criterion: "Backdate tanpa permission/reason
     * ditolak; bila disabled, UI/API tidak mengizinkan perubahan." Backdate
     * belum dibangun sama sekali di slice ini (lihat README — tidak ada
     * route/controller action yang menulis is_backdated/backdated_by_user_id/
     * backdate_reason). Test ini membuktikan itu bukan cuma "belum
     * kebetulan dipakai" tapi benar-benar TIDAK BISA disuntik lewat payload
     * request: confirmAttendance() hardcode now(), apa pun yang dikirim
     * klien untuk confirmed_at/is_backdated/backdated_by_user_id diabaikan.
     */
    public function test_attendance_confirmation_ignores_any_backdate_fields_in_the_request_payload(): void
    {
        [$employee, $user] = $this->makeEmployeeUser();
        $attacker = User::factory()->create(['role' => 'admin']);

        $event = $this->publishedEvent(startAt: now()->subHours(2), endAt: now()->subMinutes(10));
        $registration = $this->registerDirectly($event, $employee, $user);

        $before = Carbon::now();

        $response = $this->actingAs($user)->post(route('sport.registrations.confirm-attendance', $registration), [
            // Percobaan injeksi backdate lewat payload — tidak ada field ini
            // di validasi/controller sama sekali, harus diabaikan total.
            'confirmed_at' => '2020-01-01 00:00:00',
            'is_backdated' => true,
            'backdated_by_user_id' => $attacker->id,
            'backdate_reason' => 'mencoba backdate tanpa izin',
        ]);
        $response->assertSessionHas('success');

        $attendance = Attendance::first();

        // Tersimpan sebagai waktu SEKARANG (server), bukan tanggal yang disuntikkan.
        // Toleransi 1 detik: kolom DB tidak menyimpan microsecond, sedangkan
        // $before ditangkap dengan microsecond — tanpa toleransi ini,
        // perbandingan bisa salah gagal akibat pembulatan microsecond, bukan
        // karena ada bug di kode (lihat README kalau butuh detail).
        $this->assertTrue($attendance->confirmed_at->greaterThanOrEqualTo($before->copy()->subSecond()));
        $this->assertNotSame('2020-01-01 00:00:00', $attendance->confirmed_at->toDateTimeString());
        $this->assertFalse((bool) $attendance->is_backdated);
        $this->assertNull($attendance->backdated_by_user_id);
        $this->assertNull($attendance->backdate_reason);
    }

    public function test_sport_attendance_does_not_touch_employee_timesheet_or_payroll_tables(): void
    {
        [$employee, $user] = $this->makeEmployeeUser();
        $event = $this->publishedEvent(startAt: now()->subHours(2), endAt: now()->subMinutes(10));
        $registration = $this->registerDirectly($event, $employee, $user);

        $this->actingAs($user)->post(route('sport.registrations.confirm-attendance', $registration));

        // Tidak ada tabel absensi/payroll kerja yang disentuh oleh modul Sport sama sekali.
        $this->assertFalse(Schema::hasTable('attendances'));
        $employee->refresh();
        $this->assertNull($employee->current_salary);
    }

    // ──────────────────────────────────────────────────────────────────
    // MVP slice 2 (Spesifikasi v1.1 20 Okt 2026) — Non-MKO, laporan,
    // import historis, draft reward, backdate. Lihat acceptance criteria
    // Section 12 dan README_SPORT_MODULE.md untuk pemetaan lengkap.
    // ──────────────────────────────────────────────────────────────────

    public function test_admin_can_record_non_mko_participation_without_creating_an_employee_record(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $event = $this->publishedEvent(startAt: now()->subHours(3), endAt: now()->subHour());
        $employeesBefore = Employee::count();

        $response = $this->actingAs($admin)->post(route('sport.admin.events.non-mko-attendances.store', $event), [
            'full_name' => 'Tamu Owner',
            'phone' => '0812xxxx',
        ]);

        $response->assertSessionHas('success');
        $this->assertSame($employeesBefore, Employee::count()); // Non-MKO TIDAK membuat record employees
        $this->assertDatabaseHas('sport_participants', ['full_name' => 'Tamu Owner']);
        $participant = Participant::where('full_name', 'Tamu Owner')->first();
        $this->assertTrue(NonMkoAttendance::where('sport_event_id', $event->id)->where('sport_participant_id', $participant->id)->exists());
    }

    public function test_koordinator_cannot_record_non_mko_participation(): void
    {
        $koordinator = User::factory()->create(['role' => 'koordinator']);
        $event = $this->publishedEvent();

        $response = $this->actingAs($koordinator)->post(route('sport.admin.events.non-mko-attendances.store', $event), [
            'full_name' => 'Tamu Owner',
        ]);

        $response->assertForbidden();
    }

    public function test_report_distinguishes_unique_participants_from_attendance_sessions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $eventA = $this->publishedEvent(startAt: now()->subDays(2)->subHours(3), endAt: now()->subDays(2)->subHour());
        $eventB = $this->publishedEvent(startAt: now()->subHour(), endAt: now()->subMinutes(5));

        // 1 karyawan hadir di 2 event berbeda => 1 peserta unik, 2 sesi kehadiran.
        [$employee, $user] = $this->makeEmployeeUser();
        $regA = $this->registerDirectly($eventA, $employee, $user);
        Attendance::create(['sport_event_id' => $eventA->id, 'sport_registration_id' => $regA->id, 'employee_id' => $employee->id, 'confirmed_at' => now()->subDays(2)]);
        $regB = $this->registerDirectly($eventB, $employee, $user);
        Attendance::create(['sport_event_id' => $eventB->id, 'sport_registration_id' => $regB->id, 'employee_id' => $employee->id, 'confirmed_at' => now()]);

        $response = $this->actingAs($admin)->get(route('sport.admin.reports.index', [
            'from' => now()->subDays(5)->toDateString(),
            'to' => now()->addDay()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertViewHas('summary', function ($summary) {
            return $summary['unique_participants_mko'] === 1 && $summary['attendance_sessions_mko'] === 2;
        });
    }

    public function test_reward_config_is_always_saved_as_draft_regardless_of_submitted_status(): void
    {
        $finance = User::factory()->create(['role' => 'finance']);

        $response = $this->actingAs($finance)->post(route('sport.admin.reward.store'), [
            'tier1_min_sessions' => 1,
            'tier1_max_sessions' => 1,
            'tier1_rate' => 20000,
            'status' => 'active', // mencoba mengirim status lain — harus diabaikan
        ]);

        $response->assertSessionHas('success');
        $config = RewardConfig::where('sport', 'badminton')->first();
        $this->assertSame(RewardConfig::STATUS_DRAFT, $config->status);
    }

    public function test_backdate_is_rejected_for_admin_finance_when_setting_is_empty(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$employee, $user] = $this->makeEmployeeUser();
        $event = $this->publishedEvent(startAt: now()->subDays(10)->subHours(2), endAt: now()->subDays(10));
        $registration = $this->registerDirectly($event, $employee, $user);

        $this->assertNull(BackdateSetting::query()->first());

        $response = $this->actingAs($admin)->post(route('sport.admin.backdate.store', $registration), [
            'confirmed_at' => now()->subDays(10)->toDateTimeString(),
            'reason' => 'Lupa konfirmasi',
        ]);

        $response->assertSessionHas('error');
        $this->assertFalse(Attendance::where('sport_registration_id', $registration->id)->exists());
    }

    public function test_backdate_succeeds_for_admin_finance_once_setting_enabled_and_writes_audit_trail(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        BackdateSetting::create(['max_backdate_days' => 30, 'updated_by_user_id' => $admin->id]);

        [$employee, $user] = $this->makeEmployeeUser();
        $event = $this->publishedEvent(startAt: now()->subDays(10)->subHours(2), endAt: now()->subDays(10));
        $registration = $this->registerDirectly($event, $employee, $user);
        $newConfirmedAt = now()->subDays(10)->addMinutes(30);

        $response = $this->actingAs($admin)->post(route('sport.admin.backdate.store', $registration), [
            'confirmed_at' => $newConfirmedAt->toDateTimeString(),
            'reason' => 'Lupa konfirmasi, dikonfirmasi lisan oleh Koordinator',
        ]);

        $response->assertSessionHas('success');
        $attendance = Attendance::where('sport_registration_id', $registration->id)->first();
        $this->assertNotNull($attendance);
        $this->assertTrue($attendance->is_backdated);
        $audit = BackdateAudit::where('sport_attendance_id', $attendance->id)->first();
        $this->assertNotNull($audit);
        $this->assertSame(BackdateAudit::ACTION_CREATE, $audit->action);
        $this->assertNull($audit->before_confirmed_at);
        $this->assertNotNull($audit->after_confirmed_at);
        $this->assertSame('Lupa konfirmasi, dikonfirmasi lisan oleh Koordinator', $audit->reason);
    }

    public function test_backdate_route_rejects_koordinator_and_employee_regardless_of_setting(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        BackdateSetting::create(['max_backdate_days' => 30, 'updated_by_user_id' => $admin->id]);

        $koordinator = User::factory()->create(['role' => 'koordinator']);
        [$employee, $employeeUser] = $this->makeEmployeeUser();
        $event = $this->publishedEvent(startAt: now()->subDays(1)->subHours(2), endAt: now()->subDays(1));
        $registration = $this->registerDirectly($event, $employee, $employeeUser);

        foreach ([$koordinator, $employeeUser] as $actor) {
            $response = $this->actingAs($actor)->post(route('sport.admin.backdate.store', $registration), [
                'confirmed_at' => now()->subDays(1)->toDateTimeString(),
                'reason' => 'Mencoba backdate tanpa otorisasi',
            ]);
            $response->assertForbidden();
        }
    }

    public function test_import_matches_only_by_stable_employee_identifier_and_flags_unmatched_and_invalid_rows(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $event = $this->publishedEvent(startAt: now()->subDays(3)->subHours(2), endAt: now()->subDays(3));

        $matchedEmployee = Employee::create(['employee_number' => 'EMP-001', 'full_name' => 'Budi Santoso', 'status_employment' => 'permanent']);
        User::factory()->create(['role' => 'employee', 'employee_id' => $matchedEmployee->id]);

        $employeeWithoutUser = Employee::create(['employee_number' => 'EMP-002', 'full_name' => 'Siti Tanpa Akun', 'status_employment' => 'permanent']);

        $csv = "ID,Nama,Tipe,Tanggal\n"
            . "EMP-001,Budi Santoso,,2026-10-17 18:00:00\n" // valid MKO
            . "ZZZ-999,Orang Tidak Dikenal,,2026-10-17 18:00:00\n" // unmatched — tidak boleh di-fallback jadi Non-MKO diam-diam
            . "EMP-002,Siti Tanpa Akun,,2026-10-17 18:00:00\n" // invalid — employee ada tapi tidak ada akun User
            . ",Tamu Eksternal,Non-MKO,2026-10-17 18:00:00\n"; // valid Non-MKO eksplisit

        $file = UploadedFile::fake()->createWithContent('historis.csv', $csv);

        $uploadResponse = $this->actingAs($admin)->post(route('sport.admin.import.store'), [
            'sport_event_id' => $event->id,
            'file' => $file,
        ]);
        $batch = ImportBatch::first();
        $uploadResponse->assertRedirect(route('sport.admin.import.map', $batch));

        $mapResponse = $this->actingAs($admin)->post(route('sport.admin.import.map.save', $batch), [
            'identifier_column' => 'ID',
            'name_column' => 'Nama',
            'participant_type_column' => 'Tipe',
            'attended_at_column' => 'Tanggal',
        ]);
        $mapResponse->assertRedirect(route('sport.admin.import.preview', $batch));

        $batch->refresh();
        $this->assertSame(2, $batch->valid_rows);
        $this->assertSame(1, $batch->invalid_rows);
        $this->assertSame(1, $batch->unmatched_rows);

        $commitResponse = $this->actingAs($admin)->post(route('sport.admin.import.commit', $batch));
        $commitResponse->assertSessionHas('success');

        $this->assertTrue(Attendance::where('employee_id', $matchedEmployee->id)->where('sport_event_id', $event->id)->exists());
        $this->assertFalse(Attendance::where('employee_id', $employeeWithoutUser->id)->exists());
        $this->assertTrue(NonMkoAttendance::whereHas('participant', fn ($q) => $q->where('full_name', 'Tamu Eksternal'))->where('sport_event_id', $event->id)->exists());
    }

    public function test_import_is_idempotent_for_committing_the_same_file_twice_to_the_same_event(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $event = $this->publishedEvent(startAt: now()->subDays(3)->subHours(2), endAt: now()->subDays(3));
        $employee = Employee::create(['employee_number' => 'EMP-010', 'full_name' => 'Rina', 'status_employment' => 'permanent']);
        User::factory()->create(['role' => 'employee', 'employee_id' => $employee->id]);

        $csv = "ID,Nama\nEMP-010,Rina\n";
        $file = UploadedFile::fake()->createWithContent('historis2.csv', $csv);

        $this->actingAs($admin)->post(route('sport.admin.import.store'), ['sport_event_id' => $event->id, 'file' => $file]);
        $batch = ImportBatch::first();
        $this->actingAs($admin)->post(route('sport.admin.import.map.save', $batch), ['identifier_column' => 'ID', 'name_column' => 'Nama']);
        $this->actingAs($admin)->post(route('sport.admin.import.commit', $batch));

        $this->assertSame(1, Attendance::where('sport_event_id', $event->id)->where('employee_id', $employee->id)->count());

        // Commit ulang batch yang sama -> ditolak, tidak membuat attendance kedua.
        $recommit = $this->actingAs($admin)->post(route('sport.admin.import.commit', $batch));
        $recommit->assertSessionHas('error');
        $this->assertSame(1, Attendance::where('sport_event_id', $event->id)->where('employee_id', $employee->id)->count());

        // Upload file IDENTIK lagi untuk event yang sama -> ditolak sebelum diproses sama sekali.
        $fileAgain = UploadedFile::fake()->createWithContent('historis2.csv', $csv);
        $reupload = $this->actingAs($admin)->post(route('sport.admin.import.store'), ['sport_event_id' => $event->id, 'file' => $fileAgain]);
        $reupload->assertSessionHas('error');
        $this->assertSame(1, ImportBatch::where('sport_event_id', $event->id)->count());
    }

    public function test_import_never_creates_or_activates_reward_config(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $event = $this->publishedEvent(startAt: now()->subDays(3)->subHours(2), endAt: now()->subDays(3));
        $employee = Employee::create(['employee_number' => 'EMP-020', 'full_name' => 'Joko', 'status_employment' => 'permanent']);
        User::factory()->create(['role' => 'employee', 'employee_id' => $employee->id]);

        $csv = "ID,Nama\nEMP-020,Joko\n";
        $file = UploadedFile::fake()->createWithContent('historis3.csv', $csv);
        $this->actingAs($admin)->post(route('sport.admin.import.store'), ['sport_event_id' => $event->id, 'file' => $file]);
        $batch = ImportBatch::first();
        $this->actingAs($admin)->post(route('sport.admin.import.map.save', $batch), ['identifier_column' => 'ID', 'name_column' => 'Nama']);
        $this->actingAs($admin)->post(route('sport.admin.import.commit', $batch));

        $this->assertSame(0, RewardConfig::count());
    }

    /**
     * Membuktikan import juga menerima upload .xlsx sungguhan (bukan hanya
     * .csv, yang dipakai test lain) — file dibuat dengan PhpSpreadsheet yang
     * sama dipakai ImportService::extractRows(), lalu di-upload sebagai
     * UploadedFile asli (bukan createWithContent, supaya ekstensi+mime+isi
     * biner benar-benar konsisten seperti upload form sungguhan).
     */
    public function test_import_accepts_a_real_xlsx_upload_not_just_csv(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $event = $this->publishedEvent(startAt: now()->subDays(3)->subHours(2), endAt: now()->subDays(3));
        $employee = Employee::create(['employee_number' => 'EMP-030', 'full_name' => 'Wulan', 'status_employment' => 'permanent']);
        User::factory()->create(['role' => 'employee', 'employee_id' => $employee->id]);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['ID', 'Nama'], null, 'A1');
        $sheet->fromArray(['EMP-030', 'Wulan'], null, 'A2');
        $tempPath = tempnam(sys_get_temp_dir(), 'sport_xlsx_test_') . '.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($tempPath);

        $file = new UploadedFile($tempPath, 'historis.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $this->actingAs($admin)->post(route('sport.admin.import.store'), ['sport_event_id' => $event->id, 'file' => $file]);
        $batch = ImportBatch::first();
        $this->assertSame(1, $batch->total_rows);

        $this->actingAs($admin)->post(route('sport.admin.import.map.save', $batch), ['identifier_column' => 'ID', 'name_column' => 'Nama']);
        $batch->refresh();
        $this->assertSame(1, $batch->valid_rows);

        $this->actingAs($admin)->post(route('sport.admin.import.commit', $batch));
        $this->assertTrue(Attendance::where('employee_id', $employee->id)->where('sport_event_id', $event->id)->exists());

        @unlink($tempPath);
    }

    /**
     * Membuktikan export .xlsx BENAR-BENAR diunduh dan isinya (sheet, header,
     * nilai) konsisten dengan data yang sama yang dipakai laporan on-screen —
     * bukan hanya "controller tidak error". File hasil download dibuka ulang
     * dengan PhpSpreadsheet dan nilai selnya diperiksa langsung.
     */
    public function test_report_export_xlsx_contains_correct_sheets_and_matches_report_numbers(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $eventA = $this->publishedEvent(startAt: now()->subDays(2)->subHours(3), endAt: now()->subDays(2)->subHour());

        [$employee, $user] = $this->makeEmployeeUser();
        $regA = $this->registerDirectly($eventA, $employee, $user);
        Attendance::create(['sport_event_id' => $eventA->id, 'sport_registration_id' => $regA->id, 'employee_id' => $employee->id, 'confirmed_at' => now()->subDays(2)]);

        $participant = Participant::create(['full_name' => 'Tamu Export Test', 'created_by_user_id' => $admin->id]);
        NonMkoAttendance::create(['sport_event_id' => $eventA->id, 'sport_participant_id' => $participant->id, 'recorded_by_user_id' => $admin->id, 'attended_at' => now()->subDays(2)]);

        $response = $this->actingAs($admin)->get(route('sport.admin.reports.export', [
            'from' => now()->subDays(5)->toDateString(),
            'to' => now()->addDay()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // BinaryFileResponse (response()->download()) — ambil path file sungguhan
        // dari response sebelum middleware "deleteFileAfterSend" membersihkannya,
        // lalu copy ke lokasi sendiri supaya aman dibaca ulang setelah request selesai.
        $downloadedFile = $response->baseResponse->getFile();
        $tempPath = tempnam(sys_get_temp_dir(), 'sport_export_test_') . '.xlsx';
        copy($downloadedFile->getPathname(), $tempPath);
        $this->assertGreaterThan(0, filesize($tempPath), 'File xlsx yang diunduh kosong.');

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tempPath);
        $sheetNames = $spreadsheet->getSheetNames();
        $this->assertSame(['Ringkasan', 'Bulanan', 'Per Event', 'Triwulanan', 'Reward (Draft)'], $sheetNames);

        $ringkasan = $spreadsheet->getSheetByName('Ringkasan');
        $this->assertSame('Total Event', $ringkasan->getCell('A4')->getValue());
        $this->assertSame(1, $ringkasan->getCell('B4')->getValue());
        $this->assertSame('Peserta Unik MKO', $ringkasan->getCell('A5')->getValue());
        $this->assertSame(1, $ringkasan->getCell('B5')->getValue());
        $this->assertSame('Peserta Unik Non-MKO', $ringkasan->getCell('A6')->getValue());
        $this->assertSame(1, $ringkasan->getCell('B6')->getValue());

        $perEvent = $spreadsheet->getSheetByName('Per Event');
        $this->assertSame('ID', $perEvent->getCell('A1')->getValue());
        $this->assertSame('Sport', $perEvent->getCell('B1')->getValue());
        $this->assertSame($eventA->id, $perEvent->getCell('A2')->getValue());
        $this->assertSame(1, $perEvent->getCell('H2')->getValue()); // Hadir MKO
        $this->assertSame(1, $perEvent->getCell('I2')->getValue()); // Hadir Non-MKO

        // Fiscal year belum disetel di test ini -> sheet Triwulanan harus
        // berlabel periode kustom, BUKAN kalender/fiskal/Q1-Q4.
        $triwulanan = $spreadsheet->getSheetByName('Triwulanan');
        $this->assertStringContainsString('belum disetel', (string) $triwulanan->getCell('A1')->getValue());
        $this->assertStringContainsString('PERIODE KUSTOM', (string) $triwulanan->getCell('A1')->getValue());
        // Baris DATA (bukan kalimat disclosure) tidak boleh berisi label kuartal/fiskal apa pun.
        $this->assertDoesNotMatchRegularExpression('/^FY\d{4}-Q[1-4]$/', (string) $triwulanan->getCell('A3')->getValue());

        $rewardSheet = $spreadsheet->getSheetByName('Reward (Draft)');
        $this->assertStringContainsString('DRAFT', (string) $rewardSheet->getCell('A1')->getValue());
        $this->assertStringContainsString('BELUM DISETUJUI', (string) $rewardSheet->getCell('A1')->getValue());

        @unlink($tempPath);
    }

    // ──────────────────────────────────────────────────────────────────
    // Item 1 — filter grup MKO/Non-MKO benar-benar mengubah data laporan
    // ──────────────────────────────────────────────────────────────────

    private function seedMixedParticipantEvent(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $event = $this->publishedEvent(startAt: now()->subDays(2)->subHours(3), endAt: now()->subDays(2)->subHour());

        [$employee, $user] = $this->makeEmployeeUser();
        $reg = $this->registerDirectly($event, $employee, $user);
        Attendance::create(['sport_event_id' => $event->id, 'sport_registration_id' => $reg->id, 'employee_id' => $employee->id, 'confirmed_at' => now()->subDays(2)]);

        $participant = Participant::create(['full_name' => 'Tamu Group Filter Test', 'created_by_user_id' => $admin->id]);
        NonMkoAttendance::create(['sport_event_id' => $event->id, 'sport_participant_id' => $participant->id, 'recorded_by_user_id' => $admin->id, 'attended_at' => now()->subDays(2)]);

        return [$admin, $event];
    }

    public function test_group_filter_all_shows_mko_and_non_mko_separately_and_total_events_unaffected(): void
    {
        [$admin, $event] = $this->seedMixedParticipantEvent();

        $response = $this->actingAs($admin)->get(route('sport.admin.reports.index', [
            'from' => now()->subDays(5)->toDateString(), 'to' => now()->addDay()->toDateString(), 'group' => 'all',
        ]));

        $response->assertOk();
        $response->assertViewHas('summary', function ($summary) {
            return $summary['total_events'] === 1
                && $summary['unique_participants_mko'] === 1
                && $summary['unique_participants_non_mko'] === 1
                && $summary['attendance_sessions_mko'] === 1
                && $summary['attendance_sessions_non_mko'] === 1;
        });
    }

    public function test_group_filter_mko_hides_non_mko_numbers_but_keeps_total_events(): void
    {
        [$admin, $event] = $this->seedMixedParticipantEvent();

        $response = $this->actingAs($admin)->get(route('sport.admin.reports.index', [
            'from' => now()->subDays(5)->toDateString(), 'to' => now()->addDay()->toDateString(), 'group' => 'mko',
        ]));

        $response->assertOk();
        $response->assertViewHas('summary', function ($summary) {
            return $summary['total_events'] === 1 // tetap 1, tidak berubah karena filter grup
                && $summary['unique_participants_mko'] === 1
                && $summary['unique_participants_non_mko'] === null
                && $summary['attendance_sessions_non_mko'] === null;
        });
        $response->assertViewHas('perEvent', function ($perEvent) {
            return $perEvent[0]['attendances_mko'] === 1 && $perEvent[0]['attendances_non_mko'] === null;
        });
    }

    public function test_group_filter_non_mko_hides_mko_numbers_but_keeps_total_events(): void
    {
        [$admin, $event] = $this->seedMixedParticipantEvent();

        $response = $this->actingAs($admin)->get(route('sport.admin.reports.index', [
            'from' => now()->subDays(5)->toDateString(), 'to' => now()->addDay()->toDateString(), 'group' => 'non_mko',
        ]));

        $response->assertOk();
        $response->assertViewHas('summary', function ($summary) {
            return $summary['total_events'] === 1
                && $summary['unique_participants_non_mko'] === 1
                && $summary['unique_participants_mko'] === null
                && $summary['attendance_sessions_mko'] === null
                && $summary['cancellations_approved'] === null // konsep MKO, ikut disembunyikan
                && $summary['not_confirmed'] === null;
        });
        $response->assertViewHas('perEvent', function ($perEvent) {
            return $perEvent[0]['attendances_non_mko'] === 1 && $perEvent[0]['attendances_mko'] === null && $perEvent[0]['registrations'] === null;
        });
    }

    public function test_group_filter_export_matches_on_screen_report_numbers(): void
    {
        [$admin, $event] = $this->seedMixedParticipantEvent();
        $queryParams = ['from' => now()->subDays(5)->toDateString(), 'to' => now()->addDay()->toDateString(), 'group' => 'mko'];

        $response = $this->actingAs($admin)->get(route('sport.admin.reports.export', $queryParams));
        $response->assertOk();

        $tempPath = tempnam(sys_get_temp_dir(), 'sport_export_group_test_') . '.xlsx';
        copy($response->baseResponse->getFile()->getPathname(), $tempPath);
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tempPath);

        $ringkasan = $spreadsheet->getSheetByName('Ringkasan');
        $this->assertSame(1, $ringkasan->getCell('B4')->getValue()); // Total Event tetap 1
        $this->assertSame(1, $ringkasan->getCell('B5')->getValue()); // Peserta Unik MKO
        $this->assertSame('n/a (difilter grup)', $ringkasan->getCell('B6')->getValue()); // Peserta Unik Non-MKO disembunyikan, bukan 0

        $perEvent = $spreadsheet->getSheetByName('Per Event');
        $this->assertSame(1, $perEvent->getCell('H2')->getValue()); // Hadir MKO
        $this->assertSame('n/a (difilter grup)', $perEvent->getCell('I2')->getValue()); // Hadir Non-MKO disembunyikan

        @unlink($tempPath);
    }

    // ──────────────────────────────────────────────────────────────────
    // Item 2 — fiscal quarter: tidak hard-code calendar, tidak default
    // ──────────────────────────────────────────────────────────────────

    public function test_fiscal_quarter_report_shows_unconfigured_message_and_never_calendar_default(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('sport_report_settings')->count());

        $response = $this->actingAs($admin)->get(route('sport.admin.reports.index'));

        $response->assertOk();
        $response->assertViewHas('quarterly', function ($quarterly) {
            return $quarterly['type'] === 'unconfigured'
                && str_contains($quarterly['note'], 'belum disetel')
                && ! str_contains($quarterly['rows'][0]['label'] ?? '', 'Q1')
                && ! str_contains(strtoupper($quarterly['note']), 'KALENDER'); // kalender default LAMA tidak boleh muncul lagi
        });
    }

    public function test_fiscal_year_start_month_rejects_out_of_range_values(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        foreach ([0, 13, -1] as $invalid) {
            $response = $this->actingAs($admin)->post(route('sport.admin.reports.fiscal-settings.update'), [
                'fiscal_year_start_month' => $invalid,
            ]);
            $response->assertSessionHasErrors('fiscal_year_start_month');
        }

        $this->assertSame(0, \App\Models\Sport\ReportSetting::query()->whereNotNull('fiscal_year_start_month')->count());
    }

    public function test_koordinator_cannot_set_fiscal_year_start_month(): void
    {
        $koordinator = User::factory()->create(['role' => 'koordinator']);

        $this->actingAs($koordinator)->get(route('sport.admin.reports.fiscal-settings'))->assertForbidden();
        $this->actingAs($koordinator)->post(route('sport.admin.reports.fiscal-settings.update'), ['fiscal_year_start_month' => 4])->assertForbidden();
    }

    public function test_fiscal_label_maps_quarters_correctly_at_boundaries_and_across_calendar_year(): void
    {
        $controller = new \App\Http\Controllers\Sport\Admin\ReportController();
        $startMonth = 4; // fiscal year dimulai April

        // Batas Q1/Q2: 30 Juni (Q1) vs 1 Juli (Q2) — sama tahun fiskal.
        $this->assertSame('FY2026-Q1', $controller->fiscalLabel(Carbon::parse('2026-06-30'), $startMonth));
        $this->assertSame('FY2026-Q2', $controller->fiscalLabel(Carbon::parse('2026-07-01'), $startMonth));

        // Lintas tahun kalender: Des 2025 dan Jan 2026 harus masuk FY2025 yang SAMA (Q3 dan Q4),
        // bukan FY berbeda hanya karena tahun kalendernya berbeda.
        $this->assertSame('FY2025-Q3', $controller->fiscalLabel(Carbon::parse('2025-12-31'), $startMonth));
        $this->assertSame('FY2025-Q4', $controller->fiscalLabel(Carbon::parse('2026-01-01'), $startMonth));

        // Awal tahun fiskal baru: 1 April 2026 -> FY2026-Q1.
        $this->assertSame('FY2026-Q1', $controller->fiscalLabel(Carbon::parse('2026-04-01'), $startMonth));
    }

    public function test_fiscal_quarterly_report_groups_cross_calendar_year_events_into_same_fiscal_year_once_configured(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        \App\Models\Sport\ReportSetting::create(['fiscal_year_start_month' => 4, 'updated_by_user_id' => $admin->id]);

        $coordinator = User::factory()->create(['role' => 'koordinator']);
        $decEvent = Event::create([
            'sport' => 'badminton', 'location' => 'GOR Des', 'event_date' => '2025-12-20',
            'start_at' => '2025-12-20 18:00:00', 'end_at' => '2025-12-20 20:00:00',
            'has_coach' => false, 'coordinator_user_id' => $coordinator->id, 'status' => Event::STATUS_PUBLISHED,
        ]);
        $janEvent = Event::create([
            'sport' => 'badminton', 'location' => 'GOR Jan', 'event_date' => '2026-01-20',
            'start_at' => '2026-01-20 18:00:00', 'end_at' => '2026-01-20 20:00:00',
            'has_coach' => false, 'coordinator_user_id' => $coordinator->id, 'status' => Event::STATUS_PUBLISHED,
        ]);

        $response = $this->actingAs($admin)->get(route('sport.admin.reports.index', [
            'from' => '2025-12-01', 'to' => '2026-01-31',
        ]));

        $response->assertOk();
        $response->assertViewHas('quarterly', function ($quarterly) use ($decEvent, $janEvent) {
            $labels = array_column($quarterly['rows'], 'label');

            return $quarterly['type'] === 'fiscal'
                && in_array('FY2025-Q3', $labels, true)
                && in_array('FY2025-Q4', $labels, true);
        });
    }

    // ──────────────────────────────────────────────────────────────────
    // Item 3 — retensi: tidak ada cascade-delete attendance/audit/history
    // ──────────────────────────────────────────────────────────────────

    public function test_cancelling_an_event_preserves_registrations_attendance_and_import_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $coordinator = User::factory()->create(['role' => 'koordinator']);
        $event = $this->publishedEvent(coordinator: $coordinator);

        [$employee, $user] = $this->makeEmployeeUser();
        $registration = $this->registerDirectly($event, $employee, $user);
        Attendance::create(['sport_event_id' => $event->id, 'sport_registration_id' => $registration->id, 'employee_id' => $employee->id, 'confirmed_at' => now()]);

        $this->actingAs($coordinator)->post(route('sport.coordinator.cancel', $event), ['cancel_reason' => 'Lapangan tidak tersedia']);

        $event->refresh();
        $this->assertSame(Event::STATUS_CANCELLED, $event->status);
        $this->assertSame($coordinator->id, $event->cancelled_by);
        $this->assertNotNull($event->cancelled_at);
        $this->assertSame('Lapangan tidak tersedia', $event->cancel_reason);

        // Riwayat TETAP ADA — cancel bukan delete.
        $this->assertTrue(Registration::where('id', $registration->id)->exists());
        $this->assertTrue(Attendance::where('sport_event_id', $event->id)->exists());
    }

    public function test_hard_deleting_event_with_registration_is_blocked_by_foreign_key(): void
    {
        [$employee, $user] = $this->makeEmployeeUser();
        $event = $this->publishedEvent();
        $registration = $this->registerDirectly($event, $employee, $user);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $event->forceDelete();

        $this->assertTrue(Registration::where('id', $registration->id)->exists());
    }

    public function test_hard_deleting_event_with_non_mko_attendance_is_blocked_by_foreign_key(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $event = $this->publishedEvent();
        $participant = Participant::create(['full_name' => 'Tamu Retensi Test', 'created_by_user_id' => $admin->id]);
        NonMkoAttendance::create(['sport_event_id' => $event->id, 'sport_participant_id' => $participant->id, 'recorded_by_user_id' => $admin->id, 'attended_at' => now()]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $event->forceDelete();
    }

    public function test_hard_deleting_event_with_import_batch_is_blocked_by_foreign_key(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $event = $this->publishedEvent();
        ImportBatch::create([
            'sport_event_id' => $event->id, 'uploaded_by_user_id' => $admin->id,
            'original_filename' => 'x.csv', 'file_hash' => hash('sha256', 'x'),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $event->forceDelete();
    }

    public function test_hard_deleting_event_with_backdate_audit_is_blocked_by_foreign_key(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$employee, $user] = $this->makeEmployeeUser();
        $event = $this->publishedEvent();

        BackdateAudit::create([
            'sport_event_id' => $event->id, 'employee_id' => $employee->id, 'action' => BackdateAudit::ACTION_CREATE,
            'reason' => 'test audit retensi', 'after_confirmed_at' => now(), 'actor_user_id' => $admin->id, 'performed_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $event->forceDelete();
    }

    /**
     * Regresi: pastikan tidak ada route hard-delete untuk riwayat Non-MKO
     * (sebelumnya ada NonMkoAttendanceController::destroy(), sudah dihapus
     * total — lihat README "Retensi & FK history").
     */
    public function test_no_hard_delete_route_exists_for_non_mko_attendance_history(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('sport.admin.non-mko-attendances.destroy'));
    }

    public function test_backdate_is_rejected_when_reason_is_missing_even_with_setting_enabled(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        BackdateSetting::create(['max_backdate_days' => 30, 'updated_by_user_id' => $admin->id]);

        [$employee, $user] = $this->makeEmployeeUser();
        $event = $this->publishedEvent(startAt: now()->subDays(5)->subHours(2), endAt: now()->subDays(5));
        $registration = $this->registerDirectly($event, $employee, $user);

        $response = $this->actingAs($admin)->post(route('sport.admin.backdate.store', $registration), [
            'confirmed_at' => now()->subDays(5)->toDateTimeString(),
            // 'reason' sengaja tidak diisi
        ]);

        $response->assertSessionHasErrors('reason');
        $this->assertFalse(Attendance::where('sport_registration_id', $registration->id)->exists());
    }

    public function test_backdate_setting_does_not_default_to_unlimited_when_no_row_exists_yet(): void
    {
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('sport_backdate_settings')->count());

        $setting = BackdateSetting::current();

        $this->assertNull($setting->max_backdate_days);
        $this->assertFalse($setting->isEnabled());
    }

    public function test_employee_cannot_access_sport_admin_routes(): void
    {
        [$employee, $user] = $this->makeEmployeeUser();

        $this->actingAs($user)->get(route('sport.admin.reports.index'))->assertForbidden();
        $this->actingAs($user)->get(route('sport.admin.participants.index'))->assertForbidden();
        $this->actingAs($user)->get(route('sport.admin.import.create'))->assertForbidden();
        $this->actingAs($user)->get(route('sport.admin.reward.index'))->assertForbidden();
        $this->actingAs($user)->get(route('sport.admin.backdate.settings'))->assertForbidden();
    }

    public function test_share_link_points_to_authenticated_event_route_and_guest_cannot_bypass_login(): void
    {
        $coordinator = User::factory()->create(['role' => 'koordinator']);
        $event = $this->publishedEvent(coordinator: $coordinator);

        // Tamu (belum login sama sekali di test ini) yang membuka link detail event TETAP diminta
        // login — membuktikan link share bukan bypass/auto-login/bearer token.
        $guestResponse = $this->get(route('sport.show', $event));
        $guestResponse->assertRedirect(route('login'));

        $response = $this->actingAs($coordinator)->get(route('sport.coordinator.show', $event));
        $response->assertOk();
        // Link WhatsApp berisi URL otentikasi biasa (url-encoded di dalam wa.me?text=...), bukan token terpisah.
        $response->assertSee(urlencode(route('sport.show', $event)), false);
    }

    /**
     * @return array{0: Employee, 1: User}
     */
    private function makeEmployeeUser(): array
    {
        $employee = Employee::create([
            'nik' => '3175000000' . random_int(1000, 9999),
            'full_name' => 'Karyawan Test',
            'status_employment' => 'permanent',
        ]);
        $user = User::factory()->create(['role' => 'employee', 'employee_id' => $employee->id]);

        return [$employee, $user];
    }

    private function registerDirectly(Event $event, Employee $employee, User $user): Registration
    {
        return Registration::create([
            'sport_event_id' => $event->id,
            'employee_id' => $employee->id,
            'user_id' => $user->id,
            'status' => Registration::STATUS_REGISTERED,
            'registered_at' => now(),
        ]);
    }

    private function publishedEvent(?\DateTimeInterface $startAt = null, ?\DateTimeInterface $endAt = null, ?User $coordinator = null): Event
    {
        $coordinator ??= User::factory()->create(['role' => 'koordinator']);

        return Event::create([
            'sport' => 'badminton',
            'location' => 'GOR Test',
            'event_date' => now()->addDay()->toDateString(),
            'start_at' => $startAt ?? now()->addDay(),
            'end_at' => $endAt ?? now()->addDay()->addHours(2),
            'has_coach' => false,
            'coordinator_user_id' => $coordinator->id,
            'status' => Event::STATUS_PUBLISHED,
        ]);
    }

    private function ensureSchema(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->string('role')->default('employee');
                $table->unsignedBigInteger('employee_id')->nullable();
                $table->boolean('is_super_admin')->default(false);
                $table->rememberToken();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('employees')) {
            Schema::create('employees', function (Blueprint $table) {
                $table->id();
                $table->string('nik')->nullable();
                $table->string('employee_number')->nullable();
                $table->string('external_id')->nullable();
                $table->string('full_name')->nullable();
                $table->string('status_employment')->nullable();
                $table->decimal('current_salary', 15, 2)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('sport_events')) {
            (require base_path('database/migrations/2026_10_20_090000_create_sport_tables.php'))->up();
        }

        if (! Schema::hasTable('sport_participants')) {
            (require base_path('database/migrations/2026_10_20_090200_create_sport_phase2_tables.php'))->up();
        }

        if (! Schema::hasTable('sport_report_settings')) {
            (require base_path('database/migrations/2026_10_20_090300_create_sport_report_settings_table.php'))->up();
        }
    }
}
