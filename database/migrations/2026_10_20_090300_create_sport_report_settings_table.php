<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Setting tunggal (1 baris) untuk bulan mulai tahun fiskal OMEO, dipakai
 * laporan triwulanan Owner (Spesifikasi v1.1 Section 8, Open Decision #7).
 * fiscal_year_start_month SENGAJA NULL secara default — TIDAK di-default
 * ke Januari/Agustus/kalender apa pun. Selama NULL, laporan triwulanan
 * tidak boleh memakai label Q1-Q4 atau disebut "laporan fiskal" sama sekali
 * (lihat ReportController::buildQuarterly()). File ini belum pernah
 * dijalankan via `php artisan migrate` terhadap database manapun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sport_report_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('fiscal_year_start_month')->nullable(); // 1-12, NULL = belum dikonfigurasi
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('updated_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sport_report_settings');
    }
};
