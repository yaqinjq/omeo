<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                if (!Schema::hasColumn('employees', 'sim_number')) $table->string('sim_number')->nullable();
                if (!Schema::hasColumn('employees', 'sim_file_path')) $table->string('sim_file_path')->nullable();
                if (!Schema::hasColumn('employees', 'npwp_number')) $table->string('npwp_number')->nullable();
                if (!Schema::hasColumn('employees', 'npwp_file_path')) $table->string('npwp_file_path')->nullable();
                if (!Schema::hasColumn('employees', 'bpjs_kes_number')) $table->string('bpjs_kes_number')->nullable();
                if (!Schema::hasColumn('employees', 'bpjs_kes_file_path')) $table->string('bpjs_kes_file_path')->nullable();
                if (!Schema::hasColumn('employees', 'bpjs_tk_number')) $table->string('bpjs_tk_number')->nullable();
                if (!Schema::hasColumn('employees', 'bpjs_tk_file_path')) $table->string('bpjs_tk_file_path')->nullable();
                if (!Schema::hasColumn('employees', 'passport_number')) $table->string('passport_number')->nullable();
                if (!Schema::hasColumn('employees', 'passport_file_path')) $table->string('passport_file_path')->nullable();
                if (!Schema::hasColumn('employees', 'kk_number')) $table->string('kk_number')->nullable();
                if (!Schema::hasColumn('employees', 'kk_file_path')) $table->string('kk_file_path')->nullable();
                if (!Schema::hasColumn('employees', 'payroll_verified_at')) $table->timestamp('payroll_verified_at')->nullable()->index();
            });
        }

        if (Schema::hasTable('employee_profiles')) {
            Schema::table('employee_profiles', function (Blueprint $table) {
                if (!Schema::hasColumn('employee_profiles', 'sim_number')) $table->string('sim_number')->nullable();
                if (!Schema::hasColumn('employee_profiles', 'sim_file_path')) $table->string('sim_file_path')->nullable();
                if (!Schema::hasColumn('employee_profiles', 'npwp_number')) $table->string('npwp_number')->nullable();
                if (!Schema::hasColumn('employee_profiles', 'npwp_file_path')) $table->string('npwp_file_path')->nullable();
                if (!Schema::hasColumn('employee_profiles', 'bpjs_kes_number')) $table->string('bpjs_kes_number')->nullable();
                if (!Schema::hasColumn('employee_profiles', 'bpjs_kes_file_path')) $table->string('bpjs_kes_file_path')->nullable();
                if (!Schema::hasColumn('employee_profiles', 'bpjs_tk_number')) $table->string('bpjs_tk_number')->nullable();
                if (!Schema::hasColumn('employee_profiles', 'bpjs_tk_file_path')) $table->string('bpjs_tk_file_path')->nullable();
                if (!Schema::hasColumn('employee_profiles', 'passport_number')) $table->string('passport_number')->nullable();
                if (!Schema::hasColumn('employee_profiles', 'passport_file_path')) $table->string('passport_file_path')->nullable();
                if (!Schema::hasColumn('employee_profiles', 'kk_number')) $table->string('kk_number')->nullable();
                if (!Schema::hasColumn('employee_profiles', 'kk_file_path')) $table->string('kk_file_path')->nullable();
                if (!Schema::hasColumn('employee_profiles', 'payroll_verified_at')) $table->timestamp('payroll_verified_at')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        // additive-only rollback intentionally no-op for safety in production
    }
};
