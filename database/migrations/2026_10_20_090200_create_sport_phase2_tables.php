<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OMEO Sport — MVP slice 2, mengikuti spesifikasi v1.1 (20 Okt 2026):
 * Non-MKO, laporan, import historis, draft reward badminton, backdate
 * Admin Finance. Semua tabel di sini ADDITIF — tidak mengubah tabel slice 1
 * (sport_events/sport_registrations/sport_cancellation_requests/
 * sport_attendances) yang sudah direview di ronde 1-5.
 *
 * Keputusan desain yang DIBUAT di sini (bukan menebak kebijakan owner,
 * tapi keputusan struktur teknis supaya fitur bisa dibangun tanpa
 * mengaktifkan kebijakan yang belum disetujui):
 * - Non-MKO TIDAK memakai tabel employees/sport_registrations — peserta luar
 *   dicatat di tabel sport_participants sendiri, dan partisipasinya di
 *   sport_non_mko_attendances sendiri (bukan Koordinator yg input, tapi Admin
 *   Finance — tidak ada alur self-service/registrasi untuk mereka).
 * - Reward config (sport_reward_configs) hanya field DRAFT. Kolom
 *   tier3_comparison (gte/gt) SENGAJA dibuat nullable tanpa default —
 *   ambiguitas >=4 vs >4 di spesifikasi belum diputuskan, jadi tidak diisi
 *   otomatis. Tidak ada kolom status selain 'draft' yang dipakai di MVP ini.
 * - Backdate (MKO attendance correction only — lihat README) digerbangi oleh
 *   sport_backdate_settings.max_backdate_days. NULL = fitur nonaktif total,
 *   termasuk untuk Admin Finance. Tidak diisi default apa pun di migration
 *   ini — harus diisi manual oleh Admin Finance dulu.
 * - Import historis dibatasi ke 1 event tujuan per batch (bukan membuat event
 *   baru dari file) — menyederhanakan validasi tanpa mengarang kebijakan
 *   soal pembuatan event otomatis dari spreadsheet, yang tidak disebut
 *   spesifikasi.
 *
 * RETENTION FIX (keputusan retensi event terbaru — lihat README "Retensi &
 * FK history"): sport_non_mko_attendances, sport_import_batches, dan
 * sport_backdate_audits tadinya cascadeOnDelete() dari sport_events.
 * Diubah ke restrictOnDelete() — attendance Non-MKO dan audit backdate
 * adalah riwayat, tidak boleh ikut terhapus kalau event-nya di-force-delete.
 * sport_import_rows TETAP cascadeOnDelete() dari sport_import_batches —
 * ini aman karena resulting_attendance_id/resulting_non_mko_attendance_id
 * di bawah hanya kolom referensi biasa (bukan foreign key), jadi menghapus
 * baris import tidak pernah ikut menghapus Attendance/NonMkoAttendance yang
 * sudah dibuat. File ini belum pernah dijalankan via `php artisan migrate`
 * terhadap database manapun, jadi aman diedit in-place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sport_participants', function (Blueprint $table) {
            $table->id();
            $table->string('full_name', 150);
            $table->string('phone', 30)->nullable();
            $table->string('external_reference', 100)->nullable(); // id/nomor eksternal bebas (bukan NIK karyawan)
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index('full_name');
        });

        Schema::create('sport_non_mko_attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sport_event_id');
            $table->unsignedBigInteger('sport_participant_id');
            $table->unsignedBigInteger('recorded_by_user_id'); // Admin Finance yang menginput (bukan self-service)
            $table->dateTime('attended_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('sport_event_id')->references('id')->on('sport_events')->restrictOnDelete();
            $table->foreign('sport_participant_id')->references('id')->on('sport_participants')->restrictOnDelete();
            $table->foreign('recorded_by_user_id')->references('id')->on('users')->restrictOnDelete();
            // Nama constraint diberi eksplisit - nama auto-generate Laravel
            // ("sport_non_mko_attendances_sport_event_id_sport_participant_id_unique",
            // 68 karakter) melebihi batas identifier MySQL (64 karakter).
            // SQLite tidak punya batas ini sehingga lolos di test lokal.
            $table->unique(['sport_event_id', 'sport_participant_id'], 'sport_non_mko_att_event_participant_unique'); // 1 peserta Non-MKO = 1 record per event
        });

        Schema::create('sport_import_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sport_event_id'); // import historis selalu ditarget ke 1 event tujuan
            $table->unsignedBigInteger('uploaded_by_user_id');
            $table->string('original_filename', 255);
            $table->string('file_hash', 64); // sha256 isi file — dasar idempotensi
            $table->json('column_mapping')->nullable();
            $table->string('status', 20)->default('uploaded'); // uploaded|mapped|previewed|committed|failed
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('duplicate_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedInteger('unmatched_rows')->default(0);
            $table->dateTime('committed_at')->nullable();
            $table->unsignedBigInteger('committed_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('sport_event_id')->references('id')->on('sport_events')->restrictOnDelete();
            $table->foreign('uploaded_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('committed_by_user_id')->references('id')->on('users')->nullOnDelete();
            // Hash yang SAMA dan SUDAH committed tidak boleh di-commit ulang (dicek di controller,
            // bukan unique index DB, karena hash boleh sama kalau batch sebelumnya gagal/batal).
            $table->index(['file_hash', 'status']);
        });

        Schema::create('sport_import_rows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sport_import_batch_id');
            $table->unsignedInteger('row_number');
            $table->json('raw_data');
            $table->string('participant_type', 20)->nullable(); // mko|non_mko, ditentukan saat preview
            $table->unsignedBigInteger('matched_employee_id')->nullable();
            $table->unsignedBigInteger('matched_participant_id')->nullable();
            $table->string('row_status', 20)->default('pending'); // pending|valid|duplicate|invalid|unmatched
            $table->string('error_message', 255)->nullable();
            $table->unsignedBigInteger('resulting_attendance_id')->nullable();
            $table->unsignedBigInteger('resulting_non_mko_attendance_id')->nullable();
            $table->timestamps();

            $table->foreign('sport_import_batch_id')->references('id')->on('sport_import_batches')->cascadeOnDelete();
            $table->foreign('matched_employee_id')->references('id')->on('employees')->nullOnDelete();
            $table->foreign('matched_participant_id')->references('id')->on('sport_participants')->nullOnDelete();
        });

        Schema::create('sport_reward_configs', function (Blueprint $table) {
            $table->id();
            $table->string('sport', 50)->default('badminton');
            $table->unsignedInteger('tier1_min_sessions')->nullable();
            $table->unsignedInteger('tier1_max_sessions')->nullable();
            $table->decimal('tier1_rate', 12, 2)->nullable();
            $table->unsignedInteger('tier2_min_sessions')->nullable();
            $table->unsignedInteger('tier2_max_sessions')->nullable();
            $table->decimal('tier2_rate', 12, 2)->nullable();
            $table->unsignedInteger('tier3_min_sessions')->nullable();
            $table->string('tier3_comparison', 10)->nullable(); // 'gte' atau 'gt' — Needs owner decision, sengaja NULL
            $table->decimal('tier3_rate', 12, 2)->nullable();
            $table->string('period_label', 50)->nullable(); // periode reward (mingguan/bulanan dst) — belum disepakati, teks bebas
            $table->date('effective_date')->nullable();
            $table->unsignedBigInteger('approver_user_id')->nullable();
            $table->string('status', 20)->default('draft'); // HANYA 'draft' dipakai di MVP ini (lihat RewardConfigController)
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('approver_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('sport_backdate_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('max_backdate_days')->nullable(); // NULL = backdate nonaktif total
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('updated_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('sport_backdate_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sport_attendance_id')->nullable();
            $table->unsignedBigInteger('sport_event_id');
            $table->unsignedBigInteger('employee_id');
            $table->string('action', 10); // create|update
            $table->text('reason');
            $table->dateTime('before_confirmed_at')->nullable();
            $table->dateTime('after_confirmed_at');
            $table->unsignedBigInteger('actor_user_id');
            $table->dateTime('performed_at');
            $table->timestamps();

            $table->foreign('sport_attendance_id')->references('id')->on('sport_attendances')->restrictOnDelete();
            $table->foreign('sport_event_id')->references('id')->on('sport_events')->restrictOnDelete();
            $table->foreign('employee_id')->references('id')->on('employees')->restrictOnDelete();
            $table->foreign('actor_user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sport_backdate_audits');
        Schema::dropIfExists('sport_backdate_settings');
        Schema::dropIfExists('sport_reward_configs');
        Schema::dropIfExists('sport_import_rows');
        Schema::dropIfExists('sport_import_batches');
        Schema::dropIfExists('sport_non_mko_attendances');
        Schema::dropIfExists('sport_participants');
    }
};
