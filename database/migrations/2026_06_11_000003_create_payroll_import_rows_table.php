<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('payroll_import_sessions')->cascadeOnDelete();

            // Raw data from file
            $table->string('no_komp', 30)->nullable();
            $table->string('nik', 20)->nullable();
            $table->string('nama', 255);
            $table->string('outlet_name_raw', 255)->nullable(); // as-is from file
            $table->string('posisi', 150)->nullable();
            $table->date('join_date')->nullable();
            $table->decimal('gaji_pokok', 15, 2)->default(0);
            $table->decimal('bpjs_tk_raw', 15, 2)->default(0);   // negative = deduction
            $table->decimal('bpjs_jkes_raw', 15, 2)->default(0); // negative = deduction
            $table->decimal('total_gaji', 15, 2)->default(0);
            $table->string('bank_name', 100)->nullable();
            $table->string('no_rekening', 50)->nullable();
            $table->date('tanggal_resign')->nullable();
            $table->string('source_format', 10)->default('csv'); // 'csv' | 'xlsx'

            // Matching result
            $table->foreignId('matched_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('matched_outlet_id')->nullable()->constrained('outlets')->nullOnDelete();

            // Review state
            $table->string('row_status', 20)->default('new');
            // row_status: new | update_pending | confirmed | skipped | unmatched
            $table->json('diff_snapshot')->nullable(); // ['field' => ['old' => x, 'new' => y]]
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index(['session_id', 'row_status']);
            $table->index('no_komp');
            $table->index('matched_employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_import_rows');
    }
};
