<?php

namespace App\Http\Controllers\Sport;

use App\Http\Controllers\Controller;
use App\Models\Sport\Attendance;
use App\Models\Sport\CancellationRequest;
use App\Models\Sport\Event;
use App\Models\Sport\Registration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Sisi karyawan/peserta umum (MKO) — lihat event, daftar, ajukan
 * pembatalan, konfirmasi kehadiran. Non-MKO (peserta luar) sengaja TIDAK
 * ditangani di sini — belum ada keputusan owner soal cara mencatatnya
 * (lihat README modul Sport), jadi tidak dibangun pendaftaran mandiri
 * untuk mereka.
 */
class SportController extends Controller
{
    public function index(Request $request): View
    {
        $employeeId = $this->requireEmployeeId($request);

        $events = Event::query()
            ->whereIn('status', [Event::STATUS_PUBLISHED, Event::STATUS_REGISTRATION_CLOSED, Event::STATUS_IN_PROGRESS, Event::STATUS_ATTENDANCE_OPEN, Event::STATUS_COMPLETED])
            ->withCount('registrations')
            ->with(['registrations' => fn ($q) => $q->where('employee_id', $employeeId)])
            ->orderBy('start_at')
            ->get();

        return view('sport.index', ['events' => $events]);
    }

    public function show(Request $request, Event $event): View
    {
        $employeeId = $this->requireEmployeeId($request);
        $event->load(['registrations' => fn ($q) => $q->where('employee_id', $employeeId)->with(['cancellationRequests' => fn ($qq) => $qq->latest(), 'attendance'])]);

        return view('sport.show', [
            'event' => $event,
            'myRegistration' => $event->registrations->first(),
        ]);
    }

    /**
     * Pengecekan kuota + pembuatan registrasi dibungkus 1 transaksi dengan
     * row-lock di baris event (lockForUpdate) supaya 2 request bersamaan
     * untuk slot terakhir tidak bisa sama-sama lolos pengecekan sebelum
     * salah satu commit — mencegah overbook pada event ber-coach.
     *
     * CATATAN KETERBATASAN: lockForUpdate() menegakkan row-lock sungguhan di
     * MySQL/InnoDB (dipakai production OMEO). SQLite (dipakai test suite,
     * phpunit.xml) tidak punya row-level locking yang sama — ia memakai
     * whole-database lock per transaksi tulis, dan test PHPUnit berjalan di
     * 1 koneksi/proses saja, jadi TIDAK ADA cara menyimulasikan 2 request
     * benar-benar bersamaan dalam test ini. Test di bawah (lihat
     * SportEventLifecycleTest) hanya memverifikasi LOGIKA penolakan saat
     * kuota sudah penuh (sekuensial, bukan konkuren) — bukan bukti bahwa
     * race condition tertutup di production. Verifikasi race condition
     * sungguhan butuh pengujian terpisah terhadap MySQL asli (di luar
     * cakupan unit/feature test PHPUnit yang ada di repo ini).
     */
    public function register(Request $request, Event $event): RedirectResponse
    {
        $employeeId = $this->requireEmployeeId($request);

        if (! $event->isRegistrationOpen()) {
            return back()->with('error', 'Pendaftaran untuk event ini sudah ditutup (event sudah mulai, atau belum dipublish).');
        }

        try {
            $errorMessage = DB::transaction(function () use ($event, $employeeId, $request) {
                $lockedEvent = Event::where('id', $event->id)->lockForUpdate()->first();

                if (Registration::where('sport_event_id', $lockedEvent->id)->where('employee_id', $employeeId)->exists()) {
                    return 'Anda sudah terdaftar di event ini.';
                }

                if ($lockedEvent->isFull()) {
                    return 'Kuota event ini sudah penuh.';
                }

                Registration::create([
                    'sport_event_id' => $lockedEvent->id,
                    'employee_id' => $employeeId,
                    'user_id' => $request->user()->id,
                    'status' => Registration::STATUS_REGISTERED,
                    'registered_at' => now(),
                ]);

                return null;
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // Unique constraint (sport_event_id, employee_id) — retry/double klik tidak membuat duplikat.
            $errorMessage = 'Anda sudah terdaftar di event ini.';
        }

        if ($errorMessage !== null) {
            return back()->with('error', $errorMessage);
        }

        return back()->with('success', 'Berhasil mendaftar. Sampai jumpa di event!');
    }

    public function requestCancellation(Request $request, Registration $registration): RedirectResponse
    {
        $this->authorizeOwnRegistration($request, $registration);

        if ($registration->status !== Registration::STATUS_REGISTERED) {
            return back()->with('error', 'Pendaftaran ini tidak dalam status yang bisa diajukan pembatalan.');
        }

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        CancellationRequest::create([
            'sport_registration_id' => $registration->id,
            'requested_by_user_id' => $request->user()->id,
            'reason' => $data['reason'] ?? null,
            'status' => CancellationRequest::STATUS_PENDING,
        ]);
        $registration->update(['status' => Registration::STATUS_CANCELLATION_REQUESTED]);

        return back()->with('success', 'Permintaan pembatalan dikirim, menunggu keputusan Koordinator.');
    }

    /**
     * Konfirmasi kehadiran WAJIB selfie dari live camera — mengikuti pola
     * yang sudah ada di AttendanceController (presensi kerja), versi
     * sederhana (1 foto saja, tanpa foto lingkungan/geolocation, karena ini
     * presensi event olahraga, bukan presensi kerja harian). Upload file
     * biasa (bukan capture langsung dari kamera) ditolak di sisi validasi,
     * sama seperti presensi kerja — "capture_mode" dikirim oleh JS kamera
     * di view, bukan diisi manual oleh user.
     */
    public function confirmAttendance(Request $request, Registration $registration): RedirectResponse
    {
        $this->authorizeOwnRegistration($request, $registration);
        $event = $registration->event;

        if (! $event->isAttendanceWindowOpen()) {
            return back()->with('error', 'Jendela konfirmasi kehadiran untuk event ini sudah tutup atau belum dibuka.');
        }

        if ($registration->status === Registration::STATUS_CANCELLED) {
            return back()->with('error', 'Pendaftaran ini sudah dibatalkan, tidak bisa konfirmasi kehadiran.');
        }

        $validated = $request->validate([
            'capture_mode' => ['required', 'string', 'in:live_camera'],
            'selfie_photo_data' => ['required', 'string'],
        ], [
            'capture_mode.required' => 'Konfirmasi kehadiran wajib menggunakan live camera.',
            'capture_mode.in' => 'Konfirmasi kehadiran wajib menggunakan live camera, bukan upload file.',
            'selfie_photo_data.required' => 'Selfie wajib diambil langsung dari kamera.',
        ]);

        try {
            $selfiePath = $this->storeSelfiePhoto(
                (string) $validated['selfie_photo_data'],
                $event->id,
                $registration->employee_id
            );
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        try {
            Attendance::create([
                'sport_event_id' => $event->id,
                'sport_registration_id' => $registration->id,
                'employee_id' => $registration->employee_id,
                'confirmed_at' => now(),
                'selfie_photo_path' => $selfiePath,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Unique constraint (sport_event_id, employee_id) — konfirmasi ganda tidak menambah record.
            Storage::disk('public')->delete($selfiePath);

            return back()->with('error', 'Kehadiran Anda untuk event ini sudah dikonfirmasi sebelumnya.');
        }

        return back()->with('success', 'Kehadiran berhasil dikonfirmasi. Terima kasih!');
    }

    /**
     * Sama persis prinsip validasinya dengan
     * AttendanceController::storeBase64EvidencePhoto() (format data URI,
     * batas ukuran 4MB) — path disimpan terpisah di bawah "sport-attendance/"
     * supaya tidak tercampur dengan foto presensi kerja.
     */
    private function storeSelfiePhoto(string $dataUri, int $eventId, int $employeeId): string
    {
        if (! preg_match('/^data:image\/(png|jpeg|jpg|webp);base64,(.+)$/', $dataUri, $matches)) {
            throw new \InvalidArgumentException('Format data kamera tidak valid.');
        }

        $extension = strtolower($matches[1] === 'jpeg' ? 'jpg' : $matches[1]);
        $binary = base64_decode($matches[2], true);
        if ($binary === false || strlen($binary) === 0) {
            throw new \InvalidArgumentException('File kamera tidak dapat diproses.');
        }

        if (strlen($binary) > (4 * 1024 * 1024)) {
            throw new \InvalidArgumentException('Ukuran file kamera terlalu besar.');
        }

        $filename = now()->format('Ymd_His') . '_' . Str::uuid() . '.' . $extension;
        $path = "sport-attendance/{$eventId}/{$employeeId}/{$filename}";

        if (! Storage::disk('public')->put($path, $binary)) {
            throw new \RuntimeException('Gagal menyimpan selfie ke storage.');
        }

        return $path;
    }

    private function authorizeOwnRegistration(Request $request, Registration $registration): void
    {
        abort_unless((int) $registration->user_id === (int) $request->user()->id, 403);
    }

    private function requireEmployeeId(Request $request): int
    {
        $employeeId = (int) ($request->user()->employee_id ?? 0);
        abort_if($employeeId <= 0, 403, 'Fitur OMEO Sport hanya untuk karyawan OMEO (MKO).');

        return $employeeId;
    }
}
