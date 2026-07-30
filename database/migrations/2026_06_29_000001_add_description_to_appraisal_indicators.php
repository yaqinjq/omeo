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
            if (! Schema::hasColumn('appraisal_indicators', 'description')) {
                $table->text('description')->nullable()->after('question');
            }
            if (! Schema::hasColumn('appraisal_indicators', 'scale_labels')) {
                $table->json('scale_labels')->nullable()->after('description');
            }
            if (! Schema::hasColumn('appraisal_indicators', 'max_score')) {
                $table->unsignedTinyInteger('max_score')->default(4)->after('scale_labels');
            }
        });
    }

    public function down(): void
    {
        // Compatibility-first migration: no destructive rollback.
    }
};
