<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('app_settings')) {
            return;
        }

        Schema::table('app_settings', function (Blueprint $table): void {
            if (!Schema::hasColumn('app_settings', 'retention_enabled')) {
                $table->boolean('retention_enabled')->default(true)->after('meta_description');
            }

            if (!Schema::hasColumn('app_settings', 'retention_rejected_days')) {
                $table->unsignedSmallInteger('retention_rejected_days')->default(30)->after('retention_enabled');
            }

            if (!Schema::hasColumn('app_settings', 'retention_blocked_days')) {
                $table->unsignedSmallInteger('retention_blocked_days')->default(365)->after('retention_rejected_days');
            }

            if (!Schema::hasColumn('app_settings', 'retention_last_run_at')) {
                $table->timestamp('retention_last_run_at')->nullable()->after('retention_blocked_days');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('app_settings')) {
            return;
        }

        Schema::table('app_settings', function (Blueprint $table): void {
            $drops = [];

            if (Schema::hasColumn('app_settings', 'retention_last_run_at')) {
                $drops[] = 'retention_last_run_at';
            }
            if (Schema::hasColumn('app_settings', 'retention_blocked_days')) {
                $drops[] = 'retention_blocked_days';
            }
            if (Schema::hasColumn('app_settings', 'retention_rejected_days')) {
                $drops[] = 'retention_rejected_days';
            }
            if (Schema::hasColumn('app_settings', 'retention_enabled')) {
                $drops[] = 'retention_enabled';
            }

            if (!empty($drops)) {
                $table->dropColumn($drops);
            }
        });
    }
};
