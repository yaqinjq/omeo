<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                if (!Schema::hasColumn('employees', 'employee_number')) {
                    $table->string('employee_number', 100)->nullable()->unique()->after('nik');
                }

                if (!Schema::hasColumn('employees', 'join_date')) {
                    $table->date('join_date')->nullable();
                }
            });
        }

        if (Schema::hasTable('employee_profiles')) {
            Schema::table('employee_profiles', function (Blueprint $table) {
                if (!Schema::hasColumn('employee_profiles', 'employee_number')) {
                    $table->string('employee_number', 100)->nullable()->unique();
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
