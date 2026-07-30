<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('applicant_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();

            // JSON blocks (lebih fleksibel, bisa berkembang tanpa ubah schema)
            $table->json('personal_json')->nullable();
            $table->json('family_json')->nullable();
            $table->json('address_json')->nullable();
            $table->json('education_json')->nullable();
            $table->json('language_json')->nullable();
            $table->json('work_json')->nullable();
            $table->json('organization_json')->nullable();
            $table->json('course_json')->nullable();
            $table->json('medical_json')->nullable();
            $table->json('social_json')->nullable();

            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_profiles');
    }
};
