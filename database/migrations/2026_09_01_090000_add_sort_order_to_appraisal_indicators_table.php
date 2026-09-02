<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('appraisal_indicators')) {
            return;
        }

        Schema::table('appraisal_indicators', function (Blueprint $table): void {
            if (! Schema::hasColumn('appraisal_indicators', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('category');
            }
        });
    }

    public function down(): void
    {
        // Compatibility-first migration: no destructive rollback.
    }
};
