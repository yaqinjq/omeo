<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_bpjs_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained('outlets')->nullOnDelete();
            $table->foreignId('import_session_id')->nullable()->constrained('payroll_import_sessions')->nullOnDelete();

            // Employee snapshot (in case employee deleted later)
            $table->string('no_komp', 30)->nullable();
            $table->string('nik', 20)->nullable();
            $table->string('nama', 255);
            $table->string('outlet_name', 255)->nullable();
            $table->string('posisi', 150)->nullable();

            $table->string('periode', 7); // 'YYYY-MM'
            $table->decimal('gaji_pokok', 15, 2)->default(0);
            $table->decimal('bpjs_tk_employee', 15, 2)->default(0);   // potongan karyawan (abs)
            $table->decimal('bpjs_jkes_employee', 15, 2)->default(0); // potongan karyawan (abs)
            $table->decimal('bpjs_tk_employer', 15, 2)->default(0);   // tanggungan perusahaan (future)
            $table->decimal('bpjs_jkes_employer', 15, 2)->default(0); // tanggungan perusahaan (future)
            $table->boolean('is_bpjs_registered')->default(false);    // true jika ada potongan
            $table->string('source_format', 10)->default('csv');

            $table->softDeletes();
            $table->timestamps();

            $table->unique(['employee_id', 'periode'], 'unique_employee_periode');
            $table->index(['outlet_id', 'periode']);
            $table->index(['periode', 'is_bpjs_registered']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_bpjs_records');
    }
};
