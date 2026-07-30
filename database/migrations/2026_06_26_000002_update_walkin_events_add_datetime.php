<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('walkin_events', function (Blueprint $table): void {
            $table->dateTime('event_start_datetime')->nullable()->after('title');
            $table->dateTime('event_end_datetime')->nullable()->after('event_start_datetime');
            $table->string('timezone', 50)->default('Asia/Jakarta')->after('event_end_datetime');
        });

        if (Schema::hasColumn('walkin_events', 'event_date')) {
            Schema::table('walkin_events', function (Blueprint $table): void {
                $table->dropColumn('event_date');
            });
        }
    }

    public function down(): void
    {
        Schema::table('walkin_events', function (Blueprint $table): void {
            $table->date('event_date')->nullable()->after('title');
        });

        Schema::table('walkin_events', function (Blueprint $table): void {
            $table->dropColumn(['event_start_datetime', 'event_end_datetime', 'timezone']);
        });
    }
};
