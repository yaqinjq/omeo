<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Make appraiser_id nullable — no FK constraint exists on this column
        DB::statement('ALTER TABLE appraisals MODIFY appraiser_id BIGINT UNSIGNED NULL');

        Schema::table('appraisals', function (Blueprint $table) {
            $table->string('migration_source', 50)->nullable()->after('status');
            $table->timestamp('migrated_at')->nullable()->after('migration_source');
            $table->string('migration_legacy_id', 50)->nullable()->unique()->after('migrated_at');
        });
    }

    public function down(): void
    {
        Schema::table('appraisals', function (Blueprint $table) {
            $table->dropUnique(['migration_legacy_id']);
            $table->dropColumn(['migration_source', 'migrated_at', 'migration_legacy_id']);
        });

        // Revert only if no NULLs exist
        if (DB::table('appraisals')->whereNull('appraiser_id')->count() === 0) {
            DB::statement('ALTER TABLE appraisals MODIFY appraiser_id BIGINT UNSIGNED NOT NULL');
        }
    }
};
