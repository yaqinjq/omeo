<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('appraisal_indicators')) {
            return;
        }

        if (! Schema::hasColumn('appraisal_indicators', 'template_id')) {
            Schema::table('appraisal_indicators', function (Blueprint $table) {
                $table->unsignedBigInteger('template_id')->nullable()->after('id');
                $table->index('template_id');
            });
        }

        if (Schema::hasTable('appraisal_criteria_templates')) {
            $defaultTemplateId = DB::table('appraisal_criteria_templates')->where('is_default', true)->value('id');

            if ($defaultTemplateId) {
                DB::table('appraisal_indicators')->whereNull('template_id')->update(['template_id' => $defaultTemplateId]);
            }
        }
    }

    public function down(): void
    {
        // Compatibility-first migration: no destructive rollback.
    }
};
