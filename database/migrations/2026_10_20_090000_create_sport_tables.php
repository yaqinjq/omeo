<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OMEO Sport — MVP slice 1 (lifecycle inti): event, pendaftaran, pembatalan,
 * konfirmasi kehadiran. Reward badminton, import historis, dan backdate
 * audit trail ditunda ke slice berikutnya (lihat README modul Sport) karena
 * masih menunggu keputusan owner (threshold reward, format import, window
 * backdate) — sengaja TIDAK dibuat tabelnya dulu supaya tidak ada struktur
 * yang mengasumsikan kebijakan yang belum ditetapkan.
 *
 * RETENTION FIX (ditambahkan setelah keputusan retensi event terbaru —
 * lihat README bagian "Retensi & FK history"): sport_registrations,
 * sport_cancellation_requests, dan sport_attendances tadinya
 * cascadeOnDelete() dari sport_events/sport_registrations. Diubah ke
 * restrictOnDelete() supaya hard-delete (forceDelete()/raw SQL — bukan
 * soft-delete biasa, karena Event sudah pakai SoftDeletes) tidak bisa diam-
 * diam menghapus riwayat pendaftaran/pembatalan/kehadiran. Event yang sudah
 * punya aktivitas harus dibatalkan (EventController::cancel(), sudah ada
 * sejak slice 1) bukan dihapus. File ini belum pernah dijalankan via
 * `php artisan migrate` terhadap database manapun (hanya di-require
 * langsung di skema test PHPUnit SQLite :memory:), jadi aman diedit
 * in-place tanpa membuat migration koreksi baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sport_events', function (Blueprint $table) {
            $table->id();
            $table->string('sport', 50); // badminton, tenis, padel, dst — bebas teks, bukan enum, biar tidak membatasi cabang baru
            $table->string('title', 150)->nullable();
            $table->string('location', 255);
            $table->date('event_date');
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->boolean('has_coach')->default(false);
            $table->string('coach_name', 150)->nullable();
            $table->unsignedInteger('capacity')->nullable(); // WAJIB diisi kalau has_coach=true (divalidasi di controller, bukan di skema)
            $table->unsignedBigInteger('coordinator_user_id');
            $table->string('status', 30)->default('draft');
            // draft|published|registration_closed|in_progress|attendance_open|completed|cancelled
            $table->string('share_token', 40)->unique()->nullable(); // dipakai di deep-link share WhatsApp, bukan kredensial
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('coordinator_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('cancelled_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['status', 'event_date']);
        });

        Schema::create('sport_registrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sport_event_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('user_id'); // pendaftar (akun login), employee_id dicatat terpisah supaya konsisten dgn pola Task/Appraisal
            $table->string('status', 30)->default('registered');
            // registered|cancellation_requested|cancelled|cancellation_rejected
            $table->dateTime('registered_at');
            $table->timestamps();

            $table->foreign('sport_event_id')->references('id')->on('sport_events')->restrictOnDelete();
            $table->foreign('employee_id')->references('id')->on('employees')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['sport_event_id', 'employee_id']); // satu karyawan tidak bisa daftar 2x di event yang sama
        });

        Schema::create('sport_cancellation_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sport_registration_id');
            $table->unsignedBigInteger('requested_by_user_id');
            $table->text('reason')->nullable(); // wajib/tidaknya alasan — Needs owner decision, sementara opsional
            $table->string('status', 30)->default('pending'); // pending|approved|rejected
            $table->unsignedBigInteger('decided_by_user_id')->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();

            $table->foreign('sport_registration_id')->references('id')->on('sport_registrations')->restrictOnDelete();
            $table->foreign('requested_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('decided_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('sport_attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sport_event_id');
            $table->unsignedBigInteger('sport_registration_id');
            $table->unsignedBigInteger('employee_id');
            $table->dateTime('confirmed_at'); // timestamp SERVER, bukan jam browser
            $table->boolean('is_backdated')->default(false);
            $table->unsignedBigInteger('backdated_by_user_id')->nullable();
            $table->string('backdate_reason', 255)->nullable();
            $table->timestamps();

            $table->foreign('sport_event_id')->references('id')->on('sport_events')->restrictOnDelete();
            $table->foreign('sport_registration_id')->references('id')->on('sport_registrations')->restrictOnDelete();
            $table->foreign('employee_id')->references('id')->on('employees')->restrictOnDelete();
            $table->foreign('backdated_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->unique(['sport_event_id', 'employee_id']); // satu peserta cuma bisa 1 record kehadiran per event
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sport_attendances');
        Schema::dropIfExists('sport_cancellation_requests');
        Schema::dropIfExists('sport_registrations');
        Schema::dropIfExists('sport_events');
    }
};
