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

        if (! Schema::hasColumn('appraisals', 'included_in_score')) {
            Schema::table('appraisals', function (Blueprint $table) {
                // HRD can exclude a specific evaluator's submission from the combined
                // employee report average without deleting the record (e.g. evaluator
                // scored everything wrong/carelessly). Default true = business as usual.
                $table->boolean('included_in_score')->default(true)->after('final_result');
            });
        }
    }

    public function down(): void
    {
        // Compatibility-first migration: no destructive rollback.
    }
};
