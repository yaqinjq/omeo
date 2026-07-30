<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_import_rows', function (Blueprint $table) {
            // null = normal, 'recovered' = scientific notation berhasil dikonversi,
            // 'broken' = scientific notation tidak bisa direcovery (perlu input ulang)
            $table->string('rekening_flag', 20)->nullable()->after('no_rekening');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_import_rows', function (Blueprint $table) {
            $table->dropColumn('rekening_flag');
        });
    }
};
