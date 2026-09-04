<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            if (! Schema::hasColumn('positions', 'parent_position_id')) {
                $table->unsignedBigInteger('parent_position_id')->nullable()->after('department_id');
                $table->foreign('parent_position_id')->references('id')->on('positions')->nullOnDelete();
            }
            if (! Schema::hasColumn('positions', 'representative_employee_id')) {
                $table->unsignedBigInteger('representative_employee_id')->nullable()->after('parent_position_id');
                $table->foreign('representative_employee_id')->references('id')->on('employees')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            if (Schema::hasColumn('positions', 'representative_employee_id')) {
                $table->dropForeign(['representative_employee_id']);
                $table->dropColumn('representative_employee_id');
            }
            if (Schema::hasColumn('positions', 'parent_position_id')) {
                $table->dropForeign(['parent_position_id']);
                $table->dropColumn('parent_position_id');
            }
        });
    }
};
