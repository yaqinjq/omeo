<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
            if (! Schema::hasColumn('outlets', 'radius_meters')) {
                $table->unsignedSmallInteger('radius_meters')->default(5)->after('longitude');
            }
            if (! Schema::hasColumn('outlets', 'timezone')) {
                $table->string('timezone', 64)->default('Asia/Jakarta')->after('radius_meters');
            }
        });

        if (Schema::hasColumn('outlets', 'geofence_radius_m') && Schema::hasColumn('outlets', 'radius_meters')) {
            DB::statement("UPDATE outlets SET radius_meters = COALESCE(radius_meters, geofence_radius_m, 5)");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('outlets')) {
            return;
        }

        Schema::table('outlets', function (Blueprint $table) {
            if (Schema::hasColumn('outlets', 'radius_meters')) {
                $table->dropColumn('radius_meters');
            }
        });
    }
};
