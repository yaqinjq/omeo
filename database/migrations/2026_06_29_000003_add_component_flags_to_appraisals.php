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

        Schema::table('appraisals', function (Blueprint $table): void {
            if (! Schema::hasColumn('appraisals', 'enable_skill_component')) {
                $table->boolean('enable_skill_component')->default(false)->after('feedback_training_recommendations');
            }
            if (! Schema::hasColumn('appraisals', 'enable_position_component')) {
                $table->boolean('enable_position_component')->default(false)->after('enable_skill_component');
            }
            if (! Schema::hasColumn('appraisals', 'enable_kpi_component')) {
                $table->boolean('enable_kpi_component')->default(true)->after('enable_position_component');
            }
        });
    }

    public function down(): void
    {
        // Compatibility-first migration: no destructive rollback.
    }
};
