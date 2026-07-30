<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('outlets')) {
            return;
        }

        Schema::table('outlets', function (Blueprint $table) {
            if (! Schema::hasColumn('outlets', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('location');
            }
            if (! Schema::hasColumn('outlets', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
            if (! Schema::hasColumn('outlets', 'geofence_radius_m')) {
                $table->unsignedSmallInteger('geofence_radius_m')->default(5)->after('longitude');
            }
            if (! Schema::hasColumn('outlets', 'timezone')) {
                $table->string('timezone', 64)->default('Asia/Jakarta')->after('geofence_radius_m');
            }
            if (! Schema::hasColumn('outlets', 'work_start_time')) {
                $table->time('work_start_time')->nullable()->after('timezone');
            }
            if (! Schema::hasColumn('outlets', 'work_end_time')) {
                $table->time('work_end_time')->nullable()->after('work_start_time');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('outlets')) {
            return;
        }

        Schema::table('outlets', function (Blueprint $table) {
            if (Schema::hasColumn('outlets', 'work_end_time')) {
                $table->dropColumn('work_end_time');
            }
            if (Schema::hasColumn('outlets', 'work_start_time')) {
                $table->dropColumn('work_start_time');
            }
            if (Schema::hasColumn('outlets', 'timezone')) {
                $table->dropColumn('timezone');
            }
            if (Schema::hasColumn('outlets', 'geofence_radius_m')) {
                $table->dropColumn('geofence_radius_m');
            }
            if (Schema::hasColumn('outlets', 'longitude')) {
                $table->dropColumn('longitude');
            }
            if (Schema::hasColumn('outlets', 'latitude')) {
                $table->dropColumn('latitude');
            }
        });
    }
};
