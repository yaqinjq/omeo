<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('appraisals')) {
            return;
        }

        if (! Schema::hasColumn('appraisals', 'criteria_template_id')) {
            Schema::table('appraisals', function (Blueprint $table) {
                // Snapshot of which criteria template was active when this appraisal
                // was created — keeps historical appraisals stable even if templates
                // change later. Null = legacy appraisal, falls back to showing all
                // indicators (pre-template behaviour).
                $table->unsignedBigInteger('criteria_template_id')->nullable()->after('appraisal_period_id');
                $table->index('criteria_template_id');
            });
        }
    }

    public function down(): void
    {
        // Compatibility-first migration: no destructive rollback.
    }
};
