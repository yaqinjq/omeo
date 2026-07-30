<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_scans')) {
            return;
        }

        Schema::create('attendance_scans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attendance_session_id');
            $table->enum('scan_type', ['in', 'out', 'other'])->default('other');
            $table->dateTime('scanned_at_utc');
            $table->dateTime('scanned_at_local')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('accuracy_meters')->nullable();
            $table->unsignedInteger('distance_meters')->nullable();
            $table->boolean('is_within_geofence')->default(false);
            $table->json('device_json')->nullable();
            $table->enum('source', ['web_gps'])->default('web_gps');
            $table->timestamps();

            $table->index(['attendance_session_id', 'scanned_at_utc']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_scans');
    }
};
