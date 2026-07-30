<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            if (! Schema::hasColumn('departments', 'parent_id')) {
                $table->unsignedBigInteger('parent_id')->nullable()->after('name');
            }
            if (! Schema::hasColumn('departments', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('parent_id');
            }
            if (! Schema::hasColumn('departments', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('sort_order');
            }
        });

        Schema::table('positions', function (Blueprint $table) {
            if (! Schema::hasColumn('positions', 'department_id')) {
                $table->unsignedBigInteger('department_id')->nullable()->after('name');
            }
            if (! Schema::hasColumn('positions', 'approval_level')) {
                $table->integer('approval_level')->nullable()->after('level');
            }
            if (! Schema::hasColumn('positions', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('approval_level');
            }
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            foreach (['is_active', 'sort_order', 'parent_id'] as $col) {
                if (Schema::hasColumn('departments', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('positions', function (Blueprint $table) {
            foreach (['sort_order', 'approval_level', 'department_id'] as $col) {
                if (Schema::hasColumn('positions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
