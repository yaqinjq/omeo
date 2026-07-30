<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Tambah 2 kolom legal entity ke employees
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('legal_entity_kontrak_id')
                  ->nullable()
                  ->after('outlet_penugasan_id')
                  ->constrained('legal_entities')
                  ->nullOnDelete()
                  ->comment('PT/CV yang menandatangani kontrak kerja karyawan');

            $table->foreignId('legal_entity_bpjs_id')
                  ->nullable()
                  ->after('legal_entity_kontrak_id')
                  ->constrained('legal_entities')
                  ->nullOnDelete()
                  ->comment('PT/CV yang mendaftarkan karyawan ke BPJS (bisa berbeda dari kontrak)');
        });

        // Tambah NPP dan basis perhitungan ke bpjs_legal_accounts
        // NOTE: kolom aktual adalah account_number, bukan nomor_kepesertaan
        Schema::table('bpjs_legal_accounts', function (Blueprint $table) {
            $table->string('npp', 20)->nullable()->after('account_number')
                  ->comment('Nomor Pendaftaran Perusahaan BPJS');
            $table->decimal('upah_basis_perhitungan', 15, 2)->nullable()
                  ->after('npp')
                  ->comment('UMR/basis upah yang digunakan untuk perhitungan iuran');
        });

        // Tambah basis perhitungan ke bpjs_rate_configs
        Schema::table('bpjs_rate_configs', function (Blueprint $table) {
            $table->enum('basis_perhitungan', ['umr', 'gaji_aktual', 'capped_umr'])
                  ->default('umr')
                  ->after('bpjskes_salary_cap')
                  ->comment('Dasar perhitungan iuran BPJS');
        });
    }

    public function down(): void {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['legal_entity_kontrak_id']);
            $table->dropForeign(['legal_entity_bpjs_id']);
            $table->dropColumn(['legal_entity_kontrak_id', 'legal_entity_bpjs_id']);
        });
        Schema::table('bpjs_legal_accounts', function (Blueprint $table) {
            $table->dropColumn(['npp', 'upah_basis_perhitungan']);
        });
        Schema::table('bpjs_rate_configs', function (Blueprint $table) {
            $table->dropColumn('basis_perhitungan');
        });
    }
};
