<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Sesi import annual summary (audit trail)
        Schema::create('payroll_annual_import_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('source_file_name', 255);
            $table->string('source_brand', 50)->default('tokio');
            $table->smallInteger('tahun');
            $table->string('sheet_name', 30)->default('SUMMARY TOKIO');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('new_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->string('status', 20)->default('completed');
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['tahun', 'source_brand']);
        });

        // Data per karyawan per tahun (12 bulan sekaligus)
        Schema::create('payroll_annual_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_session_id')
                  ->constrained('payroll_annual_import_sessions')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained('outlets')->nullOnDelete();

            // Data identitas dari file
            $table->string('no_komp', 30)->nullable();
            $table->string('nik', 20)->nullable();
            $table->string('nama', 255);
            $table->string('outlet_name_raw', 255)->nullable();
            $table->string('posisi', 150)->nullable();
            $table->date('join_date')->nullable();
            $table->smallInteger('tahun');

            // Gaji per bulan (null = tidak bertugas bulan itu)
            $table->decimal('gaji_jan', 15, 2)->nullable();
            $table->decimal('gaji_feb', 15, 2)->nullable();
            $table->decimal('gaji_mar', 15, 2)->nullable();
            $table->decimal('gaji_apr', 15, 2)->nullable();
            $table->decimal('gaji_mei', 15, 2)->nullable();
            $table->decimal('gaji_jun', 15, 2)->nullable();
            $table->decimal('gaji_jul', 15, 2)->nullable();
            $table->decimal('gaji_aug', 15, 2)->nullable();
            $table->decimal('gaji_sep', 15, 2)->nullable();
            $table->decimal('gaji_okt', 15, 2)->nullable();
            $table->decimal('gaji_nov', 15, 2)->nullable();
            $table->decimal('gaji_des', 15, 2)->nullable();
            $table->decimal('thr', 15, 2)->nullable();
            $table->decimal('total_tahunan', 15, 2)->nullable();

            // Berapa bulan aktif menerima gaji > 0
            $table->tinyInteger('bulan_aktif')->default(0);

            $table->softDeletes();
            $table->timestamps();

            // Unique: satu karyawan satu catatan per tahun per sesi
            $table->unique(['import_session_id', 'no_komp'], 'unique_session_nokomp');
            $table->index(['tahun', 'outlet_id']);
            $table->index(['no_komp', 'tahun']);
            $table->index('employee_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('payroll_annual_summaries');
        Schema::dropIfExists('payroll_annual_import_sessions');
    }
};
