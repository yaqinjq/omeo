<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Perluas finance_bpjs_records — skip kolom yang sudah ada (idempotent)
        Schema::table('finance_bpjs_records', function (Blueprint $table) {
            if (! Schema::hasColumn('finance_bpjs_records', 'sub_dept')) {
                $table->string('sub_dept', 100)->nullable()->after('posisi');
            }
            if (! Schema::hasColumn('finance_bpjs_records', 'category')) {
                $table->string('category', 30)->nullable()->after('sub_dept')
                      ->comment('bar/kitchen/service/office/prive/lainnya');
            }
            if (! Schema::hasColumn('finance_bpjs_records', 'join_date_emp')) {
                $table->date('join_date_emp')->nullable()->after('category')
                      ->comment('Tanggal bergabung karyawan dari file payroll');
            }
            if (! Schema::hasColumn('finance_bpjs_records', 'attd')) {
                $table->decimal('attd', 6, 2)->nullable()->after('join_date_emp')
                      ->comment('Hari kerja standar periode (kolom Attd CSV)');
            }
            if (! Schema::hasColumn('finance_bpjs_records', 'hr')) {
                $table->decimal('hr', 6, 2)->nullable()->after('attd')
                      ->comment('Hari kerja aktual karyawan (kolom HR CSV)');
            }
            if (! Schema::hasColumn('finance_bpjs_records', 's_expense')) {
                $table->decimal('s_expense', 15, 2)->nullable()->after('hr')
                      ->comment('Service Charge Unallocated (kolom S.Expense CSV)');
            }
        });

        // 2. Alter payroll_annual_summaries: drop FK → nullable → drop old unique →
        //    new unique → re-add FK  (urutan penting: FK harus dihapus sebelum unique)
        Schema::table('payroll_annual_summaries', function (Blueprint $table) {
            $table->dropForeign(['import_session_id']);
        });
        Schema::table('payroll_annual_summaries', function (Blueprint $table) {
            $table->unsignedBigInteger('import_session_id')->nullable()->change();
        });

        // 3. Ganti unique constraint sebelum FK di-attach kembali
        $indexes = collect(\DB::select("SHOW INDEX FROM payroll_annual_summaries"))
            ->pluck('Key_name')->unique()->values();

        if ($indexes->contains('unique_session_nokomp')) {
            Schema::table('payroll_annual_summaries', function (Blueprint $table) {
                $table->dropUnique('unique_session_nokomp');
            });
        }
        if (! $indexes->contains('unique_nokomp_tahun')) {
            Schema::table('payroll_annual_summaries', function (Blueprint $table) {
                $table->unique(['no_komp', 'tahun'], 'unique_nokomp_tahun');
            });
        }

        Schema::table('payroll_annual_summaries', function (Blueprint $table) {
            $table->foreign('import_session_id')
                  ->references('id')
                  ->on('payroll_annual_import_sessions')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('finance_bpjs_records', function (Blueprint $table) {
            $table->dropColumn(['sub_dept', 'category', 'join_date_emp', 'attd', 'hr', 's_expense']);
        });

        Schema::table('payroll_annual_summaries', function (Blueprint $table) {
            $table->dropUnique('unique_nokomp_tahun');
            $table->dropForeign(['import_session_id']);
        });
        Schema::table('payroll_annual_summaries', function (Blueprint $table) {
            $table->unsignedBigInteger('import_session_id')->nullable(false)->change();
        });
        Schema::table('payroll_annual_summaries', function (Blueprint $table) {
            $table->foreign('import_session_id')
                  ->references('id')
                  ->on('payroll_annual_import_sessions')
                  ->cascadeOnDelete();
            $table->unique(['import_session_id', 'no_komp'], 'unique_session_nokomp');
        });
    }
};
