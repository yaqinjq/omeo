<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('finance_bpjs_records', function (Blueprint $table) {
            if (! Schema::hasColumn('finance_bpjs_records', 'total')) {
                $table->decimal('total', 15, 2)->nullable()->after('s_expense')
                      ->comment('Kolom AK/Total dari CSV — gaji bersih akhir');
            }
            if (! Schema::hasColumn('finance_bpjs_records', 'ot1_amount')) {
                $table->decimal('ot1_amount', 15, 2)->nullable()->after('total')
                      ->comment('Uang Lembur 1 dari CSV');
            }
            if (! Schema::hasColumn('finance_bpjs_records', 'ot2_amount')) {
                $table->decimal('ot2_amount', 15, 2)->nullable()->after('ot1_amount')
                      ->comment('Uang Lembur 2 dari CSV');
            }
            if (! Schema::hasColumn('finance_bpjs_records', 'tunjangan_total')) {
                $table->decimal('tunjangan_total', 15, 2)->nullable()->after('ot2_amount')
                      ->comment('Total semua tunjangan (jabatan, masa kerja, dll)');
            }
            if (! Schema::hasColumn('finance_bpjs_records', 'potongan_total')) {
                $table->decimal('potongan_total', 15, 2)->nullable()->after('tunjangan_total')
                      ->comment('Total potongan (pinjaman, GP/UK, dll)');
            }
            if (! Schema::hasColumn('finance_bpjs_records', 'raw_csv_json')) {
                $table->json('raw_csv_json')->nullable()->after('potongan_total')
                      ->comment('Backup SELURUH kolom CSV mentah per baris — antisipasi kebutuhan masa depan tanpa re-upload');
            }
        });
    }

    public function down(): void
    {
        Schema::table('finance_bpjs_records', function (Blueprint $table) {
            $table->dropColumn([
                'total', 'ot1_amount', 'ot2_amount',
                'tunjangan_total', 'potongan_total', 'raw_csv_json',
            ]);
        });
    }
};
