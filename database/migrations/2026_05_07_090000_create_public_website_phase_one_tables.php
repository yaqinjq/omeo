<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('landing_page_settings', function (Blueprint $table) {
            $table->id();
            $table->string('website_name')->default('OMEO HR Suite');
            $table->string('short_tagline')->nullable();
            $table->text('footer_description')->nullable();
            $table->string('office_email')->nullable();
            $table->string('office_address')->nullable();
            $table->string('copyright_text')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('hero_badge')->nullable();
            $table->string('hero_headline')->nullable();
            $table->string('hero_highlight')->nullable();
            $table->text('hero_subheadline')->nullable();
            $table->string('hero_image_path')->nullable();
            $table->string('hero_background_path')->nullable();
            $table->string('primary_button_label')->nullable();
            $table->string('primary_button_url')->nullable();
            $table->string('secondary_button_label')->nullable();
            $table->string('secondary_button_url')->nullable();
            $table->string('cta_title')->nullable();
            $table->text('cta_description')->nullable();
            $table->string('cta_button_label')->nullable();
            $table->string('cta_button_url')->nullable();
            $table->timestamps();
        });

        Schema::create('hr_team_members', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('position');
            $table->string('company_email');
            $table->string('photo_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('career_departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('career_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('career_department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('location')->nullable();
            $table->string('employment_type')->default('full-time');
            $table->longText('description')->nullable();
            $table->longText('qualifications')->nullable();
            $table->longText('benefits')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->date('closing_at')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description')->nullable();
            $table->string('apply_button_label')->default('Lamar Posisi');
            $table->string('apply_url')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index(['location', 'employment_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('career_posts');
        Schema::dropIfExists('career_departments');
        Schema::dropIfExists('hr_team_members');
        Schema::dropIfExists('landing_page_settings');
    }
};
