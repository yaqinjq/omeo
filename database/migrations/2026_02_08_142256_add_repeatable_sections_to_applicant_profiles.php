<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('applicant_profiles', function (Blueprint $table) {
            $table->json('educations')->nullable();
            $table->json('courses')->nullable();
            $table->json('organizations')->nullable();
            $table->json('work_experiences')->nullable();
            $table->json('medical_histories')->nullable();
            $table->json('social_medias')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('applicant_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'educations',
                'courses',
                'organizations',
                'work_experiences',
                'medical_histories',
                'social_medias',
            ]);
        });
    }
};
