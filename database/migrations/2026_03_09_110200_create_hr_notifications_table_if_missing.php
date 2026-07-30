<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hr_notifications')) {
            return;
        }

        Schema::create('hr_notifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('type', 50)->index();
            $table->string('title', 255);
            $table->text('body')->nullable();
            $table->date('due_date')->nullable()->index();
            $table->boolean('is_read')->default(false)->index();
            $table->string('unique_key', 191)->unique();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_notifications');
    }
};
