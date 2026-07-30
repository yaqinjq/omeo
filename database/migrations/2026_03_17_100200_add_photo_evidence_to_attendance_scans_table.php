<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('attendance_scans')) {
            return;
        }

        Schema::table('attendance_scans', function (Blueprint $table): void {
            if (!Schema::hasColumn('attendance_scans', 'selfie_photo_path')) {
                $table->string('selfie_photo_path')->nullable()->after('is_within_geofence');
            }

            if (!Schema::hasColumn('attendance_scans', 'environment_photo_path')) {
                $table->string('environment_photo_path')->nullable()->after('selfie_photo_path');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('attendance_scans')) {
            return;
        }

        Schema::table('attendance_scans', function (Blueprint $table): void {
            if (Schema::hasColumn('attendance_scans', 'environment_photo_path')) {
                $table->dropColumn('environment_photo_path');
            }

            if (Schema::hasColumn('attendance_scans', 'selfie_photo_path')) {
                $table->dropColumn('selfie_photo_path');
            }
        });
    }
};
