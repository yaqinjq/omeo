<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Selfie wajib dari live camera untuk konfirmasi kehadiran Sport —
 * mengikuti pola yang sudah ada di AttendanceController (presensi kerja),
 * tapi versi sederhana (1 foto saja, tanpa foto lingkungan/geolocation,
 * karena ini presensi event olahraga, bukan presensi kerja harian).
 *
 * sport_attendances sudah live di production sejak migration
 * 2026_10_20_090000_create_sport_tables.php — kolom baru ditambahkan di
 * sini (bukan mengedit migration yang sudah applied).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sport_attendances', 'selfie_photo_path')) {
            Schema::table('sport_attendances', function (Blueprint $table) {
                $table->string('selfie_photo_path', 255)->nullable()->after('confirmed_at');
                // NULL dipakai untuk kehadiran yang diisi lewat backdate (Admin
                // Finance, tidak lewat kamera karyawan) atau import historis —
                // keduanya tetap sah tanpa foto, hanya konfirmasi mandiri
                // karyawan sendiri yang mewajibkan foto.
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sport_attendances', 'selfie_photo_path')) {
            Schema::table('sport_attendances', function (Blueprint $table) {
                $table->dropColumn('selfie_photo_path');
            });
        }
    }
};
