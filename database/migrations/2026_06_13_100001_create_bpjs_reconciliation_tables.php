<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tagihan resmi dari BPJS (per-upload Formulir 2a PU)
        Schema::create('bpjs_official_bills', function (Blueprint $table) {
            $table->id();
            $table->string('npp', 30)->nullable()->comment('Nomor Pendaftaran Perusahaan dari tagihan');
            $table->string('nama_perusahaan', 200)->nullable();
            $table->foreignId('legal_entity_id')->nullable()->constrained('legal_entities')->nullOnDelete();
            $table->foreignId('bpjs_legal_account_id')->nullable()->constrained('bpjs_legal_accounts')->nullOnDelete();
            $table->string('periode', 7)->comment('YYYY-MM');
            $table->enum('source_format', ['pdf_digital', 'pdf_scan', 'excel', 'csv'])->default('excel');
            $table->string('source_file_name', 255);
            $table->unsignedInteger('total_rows_parsed')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('unmatched_count')->default(0);
            $table->decimal('total_iuran_official', 18, 2)->default(0);
            $table->enum('status', ['draft', 'matching', 'matched', 'reconciled'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['periode', 'legal_entity_id']);
            $table->index('status');
        });

        // Baris per-karyawan dalam tagihan resmi BPJS
        Schema::create('bpjs_official_bill_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained('bpjs_official_bills')->cascadeOnDelete();
            // data dari tagihan
            $table->string('nik', 20)->nullable();
            $table->string('no_peserta', 30)->nullable()->comment('No kepesertaan BPJS');
            $table->string('nama', 150);
            // iuran BPJS TK
            $table->decimal('iuran_jkk', 15, 2)->default(0);
            $table->decimal('iuran_jkm', 15, 2)->default(0);
            $table->decimal('iuran_jht_employee', 15, 2)->default(0);
            $table->decimal('iuran_jht_employer', 15, 2)->default(0);
            $table->decimal('iuran_jp_employee', 15, 2)->default(0);
            $table->decimal('iuran_jp_employer', 15, 2)->default(0);
            $table->decimal('total_iuran', 15, 2)->default(0);
            // matching result
            $table->enum('match_status', ['pending', 'auto', 'manual_confirmed', 'not_found', 'flagged'])->default('pending');
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->decimal('match_confidence', 5, 2)->nullable()->comment('0-100');
            $table->string('match_note', 255)->nullable();
            $table->timestamps();

            $table->index(['bill_id', 'match_status']);
            $table->index(['bill_id', 'employee_id']);
            $table->index('nik');
        });

        // Hasil rekonsiliasi: tagihan resmi vs kalkulasi OMEO
        Schema::create('bpjs_reconciliation_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained('bpjs_official_bills')->cascadeOnDelete();
            $table->foreignId('bill_row_id')->constrained('bpjs_official_bill_rows')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('periode', 7);
            // dari tagihan resmi
            $table->decimal('official_jkk', 15, 2)->default(0);
            $table->decimal('official_jkm', 15, 2)->default(0);
            $table->decimal('official_jht_employee', 15, 2)->default(0);
            $table->decimal('official_jht_employer', 15, 2)->default(0);
            $table->decimal('official_jp_employee', 15, 2)->default(0);
            $table->decimal('official_jp_employer', 15, 2)->default(0);
            $table->decimal('official_total', 15, 2)->default(0);
            // dari OMEO (finance_bpjs_records)
            $table->decimal('omeo_gaji_pokok', 15, 2)->default(0);
            $table->decimal('omeo_bpjs_tk_employee', 15, 2)->default(0);
            $table->decimal('omeo_bpjs_tk_employer', 15, 2)->default(0);
            $table->decimal('omeo_bpjs_jkes_employee', 15, 2)->default(0);
            $table->decimal('omeo_bpjs_jkes_employer', 15, 2)->default(0);
            $table->decimal('omeo_total', 15, 2)->default(0);
            // selisih
            $table->decimal('selisih_total', 15, 2)->default(0)->comment('official - omeo');
            $table->enum('penyebab', [
                'ok',
                'tidak_terdaftar_omeo',
                'tidak_di_tagihan',
                'beda_gaji',
                'beda_tarif',
                'karyawan_baru',
                'karyawan_resign',
                'lainnya',
            ])->default('lainnya');
            $table->enum('status', ['ok', 'selisih_minor', 'selisih_signifikan', 'perlu_review'])->default('perlu_review');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['bill_row_id']);
            $table->index(['bill_id', 'status']);
            $table->index(['employee_id', 'periode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bpjs_reconciliation_results');
        Schema::dropIfExists('bpjs_official_bill_rows');
        Schema::dropIfExists('bpjs_official_bills');
    }
};
