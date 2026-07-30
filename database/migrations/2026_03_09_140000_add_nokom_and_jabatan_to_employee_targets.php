<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                if (!Schema::hasColumn('employees', 'nokom')) {
                    $table->string('nokom', 100)->nullable()->after('nik');
                }

                if (!Schema::hasColumn('employees', 'jabatan')) {
                    $table->string('jabatan', 150)->nullable()->after('position_id');
                }
            });
        }

        if (Schema::hasTable('employee_profiles')) {
            Schema::table('employee_profiles', function (Blueprint $table) {
                if (!Schema::hasColumn('employee_profiles', 'nokom')) {
                    $table->string('nokom', 100)->nullable();
                }

                if (!Schema::hasColumn('employee_profiles', 'jabatan')) {
                    $table->string('jabatan', 150)->nullable();
                }

                if (!Schema::hasColumn('employee_profiles', 'join_date')) {
                    $table->date('join_date')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // additive-only rollback intentionally no-op for safety in production
    }
};
