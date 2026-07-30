<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bpjs_rate_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->string('periode_mulai', 7); // format: YYYY-MM
            $table->string('periode_selesai', 7)->nullable(); // null = berlaku sekarang
            // BPJS Ketenagakerjaan
            $table->decimal('jkk_rate', 5, 4)->default(0.0024); // Jaminan Kecelakaan Kerja (0.24%)
            $table->decimal('jkm_rate', 5, 4)->default(0.0030); // Jaminan Kematian (0.30%)
            $table->decimal('jht_employer_rate', 5, 4)->default(0.0370); // JHT perusahaan (3.70%)
            $table->decimal('jht_employee_rate', 5, 4)->default(0.0200); // JHT karyawan (2.00%)
            $table->decimal('jp_employer_rate', 5, 4)->default(0.0200); // JP perusahaan (2.00%)
            $table->decimal('jp_employee_rate', 5, 4)->default(0.0100); // JP karyawan (1.00%)
            // BPJS Kesehatan
            $table->decimal('bpjskes_employer_rate', 5, 4)->default(0.0400); // BPJS Kes perusahaan (4.00%)
            $table->decimal('bpjskes_employee_rate', 5, 4)->default(0.0100); // BPJS Kes karyawan (1.00%)
            $table->decimal('bpjskes_salary_cap', 15, 2)->nullable(); // batas upah maksimum
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['region_id', 'is_active']);
            $table->index('periode_mulai');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bpjs_rate_configs');
    }
};
