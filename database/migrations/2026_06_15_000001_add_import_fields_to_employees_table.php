<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('npk', 50)->nullable()->after('employee_number')
                  ->comment('Kode internal tambahan (NPK) dari sistem payroll HRD');
            $table->enum('lokasi_kerja', ['outlet','office','production'])
                  ->nullable()->after('outlet_penugasan_id')
                  ->comment('Kategori lokasi kerja (Bisnis Division dari import HRD)');
            $table->string('payroll_session_tag', 150)->nullable()
                  ->after('legal_entity_bpjs_id')
                  ->comment('Tag/catatan Payroll Session dari import HRD - Finance only, belum auto-link ke legal_entity_bpjs');
        });
    }
    public function down(): void {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['npk','lokasi_kerja','payroll_session_tag']);
        });
    }
};
