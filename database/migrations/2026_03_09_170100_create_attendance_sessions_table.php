<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_sessions')) {
            return;
        }

        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('outlet_id')->nullable();
            $table->string('shift_code', 50)->nullable();
            $table->date('work_date');
            $table->time('scheduled_in_local')->nullable();
            $table->time('scheduled_out_local')->nullable();
            $table->dateTime('first_in_at_utc')->nullable();
            $table->dateTime('last_out_at_utc')->nullable();
            $table->integer('total_work_minutes')->default(0);
            $table->integer('late_minutes')->default(0);
            $table->integer('early_leave_minutes')->default(0);
            $table->integer('overtime_minutes')->default(0);
            $table->enum('status', ['present', 'absent', 'leave', 'off', 'incomplete', 'schedule_mismatch', 'outlet_mismatch'])->default('incomplete');
            $table->text('notes')->nullable();
            $table->json('summary_json')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'work_date']);
            $table->index(['work_date', 'outlet_id']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
    }
};
