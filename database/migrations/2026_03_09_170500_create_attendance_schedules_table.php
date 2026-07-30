<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_schedules')) {
            return;
        }

        Schema::create('attendance_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('outlet_id')->nullable();
            $table->unsignedBigInteger('master_shift_id')->nullable();
            $table->string('shift_code', 50)->nullable();
            $table->date('work_date');
            $table->timestamps();

            $table->unique(['user_id', 'work_date']);
            $table->index(['outlet_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_schedules');
    }
};
