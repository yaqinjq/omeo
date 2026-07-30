<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL tidak bisa drop unique index yang dipakai foreign key sebagai satu-satunya index.
        // Solusi: tambah plain index pada employee_id dulu, baru drop unique, lalu add unique baru.

        Schema::table('finance_bpjs_records', function (Blueprint $table) {
            // Tambah plain index dulu agar FK masih punya index setelah unique di-drop
            $table->index('employee_id', 'idx_employee_id_bpjs');
        });

        Schema::table('finance_bpjs_records', function (Blueprint $table) {
            $table->dropUnique('unique_employee_periode');
        });

        Schema::table('finance_bpjs_records', function (Blueprint $table) {
            $table->unique(['no_komp', 'periode'], 'unique_nokom_periode');
        });
    }

    public function down(): void
    {
        Schema::table('finance_bpjs_records', function (Blueprint $table) {
            $table->dropUnique('unique_nokom_periode');
        });

        Schema::table('finance_bpjs_records', function (Blueprint $table) {
            $table->unique(['employee_id', 'periode'], 'unique_employee_periode');
        });

        Schema::table('finance_bpjs_records', function (Blueprint $table) {
            $table->dropIndex('idx_employee_id_bpjs');
        });
    }
};
