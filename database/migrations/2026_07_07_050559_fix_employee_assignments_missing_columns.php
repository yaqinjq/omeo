<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Local DB drift repair: 2026_06_12_000006_create_employee_assignments_table
     * is recorded as ran, but the live table predates that schema and is missing
     * these columns. Additive-only, nullable, no data loss.
     */
    public function up(): void
    {
        Schema::table('employee_assignments', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_assignments', 'payroll_outlet_id')) {
                $table->unsignedBigInteger('payroll_outlet_id')->nullable()->after('outlet_id');
            }
            if (!Schema::hasColumn('employee_assignments', 'legal_entity_id')) {
                $table->unsignedBigInteger('legal_entity_id')->nullable()->after('payroll_outlet_id');
            }
            if (!Schema::hasColumn('employee_assignments', 'department')) {
                $table->string('department', 150)->nullable()->after('legal_entity_id');
            }
            if (!Schema::hasColumn('employee_assignments', 'effective_date')) {
                $table->date('effective_date')->nullable()->after('department');
            }
            if (!Schema::hasColumn('employee_assignments', 'reason')) {
                $table->string('reason', 255)->nullable()->after('end_date');
            }
        });

        if (Schema::hasColumn('employee_assignments', 'effective_date') && Schema::hasColumn('employee_assignments', 'start_date')) {
            DB::table('employee_assignments')
                ->whereNull('effective_date')
                ->update(['effective_date' => DB::raw('start_date')]);
        }
    }

    public function down(): void
    {
        Schema::table('employee_assignments', function (Blueprint $table) {
            foreach (['payroll_outlet_id', 'legal_entity_id', 'department', 'effective_date', 'reason'] as $column) {
                if (Schema::hasColumn('employee_assignments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
