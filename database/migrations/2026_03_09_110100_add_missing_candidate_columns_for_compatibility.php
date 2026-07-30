<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('candidates')) {
            return;
        }

        Schema::table('candidates', function (Blueprint $table) {
            if (!Schema::hasColumn('candidates', 'notes')) {
                $table->text('notes')->nullable();
            }
            if (!Schema::hasColumn('candidates', 'applied_at')) {
                $table->timestamp('applied_at')->nullable();
            }
            if (!Schema::hasColumn('candidates', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable();
            }
            if (!Schema::hasColumn('candidates', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable();
            }
            if (!Schema::hasColumn('candidates', 'blocked_at')) {
                $table->timestamp('blocked_at')->nullable();
            }
            if (!Schema::hasColumn('candidates', 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('candidates')) {
            return;
        }

        Schema::table('candidates', function (Blueprint $table) {
            foreach (['notes', 'applied_at', 'accepted_at', 'rejected_at', 'blocked_at', 'deleted_at'] as $column) {
                if (Schema::hasColumn('candidates', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
