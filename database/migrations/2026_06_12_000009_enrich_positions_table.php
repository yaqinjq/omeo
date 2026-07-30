<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('positions')) {
            Schema::create('positions', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150);
                $table->string('code', 30)->nullable()->unique();
                $table->enum('level', ['staff', 'supervisor', 'manager', 'director', 'other'])->default('staff');
                $table->decimal('salary_min', 15, 2)->nullable();
                $table->decimal('salary_max', 15, 2)->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('description')->nullable();
                $table->timestamps();
            });
            return;
        }

        Schema::table('positions', function (Blueprint $table) {
            if (!Schema::hasColumn('positions', 'code')) {
                $table->string('code', 30)->nullable()->unique()->after('name');
            }
            if (!Schema::hasColumn('positions', 'level')) {
                $table->enum('level', ['staff', 'supervisor', 'manager', 'director', 'other'])
                      ->default('staff')->after('code');
            }
            if (!Schema::hasColumn('positions', 'salary_min')) {
                $table->decimal('salary_min', 15, 2)->nullable()->after('level');
            }
            if (!Schema::hasColumn('positions', 'salary_max')) {
                $table->decimal('salary_max', 15, 2)->nullable()->after('salary_min');
            }
            if (!Schema::hasColumn('positions', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('salary_max');
            }
            if (!Schema::hasColumn('positions', 'description')) {
                $table->text('description')->nullable()->after('is_active');
            }
        });
    }

    public function down(): void
    {
        // Only drop columns if they were added (not drop the whole table)
        Schema::table('positions', function (Blueprint $table) {
            $toDrop = [];
            foreach (['code', 'level', 'salary_min', 'salary_max', 'is_active', 'description'] as $col) {
                if (Schema::hasColumn('positions', $col)) {
                    $toDrop[] = $col;
                }
            }
            if ($toDrop) {
                $table->dropColumn($toDrop);
            }
        });
    }
};
