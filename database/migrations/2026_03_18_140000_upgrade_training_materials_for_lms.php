<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('training_materials')) {
            Schema::create('training_materials', function (Blueprint $table): void {
                $table->id();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('category', 100)->nullable();
                $table->integer('duration_minutes')->nullable();
                $table->string('youtube_url')->nullable();
                $table->string('slug')->nullable()->unique();
                $table->enum('audience_scope', ['general', 'department', 'position'])->default('general');
                $table->unsignedBigInteger('department_id')->nullable();
                $table->unsignedBigInteger('position_id')->nullable();
                $table->unsignedBigInteger('mentor_user_id')->nullable();
                $table->unsignedBigInteger('pretest_form_id')->nullable();
                $table->unsignedBigInteger('posttest_form_id')->nullable();
                $table->string('content_source_type', 30)->nullable();
                $table->text('content_source_url')->nullable();
                $table->string('thumbnail_path')->nullable();
                $table->unsignedInteger('pass_score')->nullable();
                $table->boolean('is_active')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamps();
            });

            return;
        }

        Schema::table('training_materials', function (Blueprint $table): void {
            if (! Schema::hasColumn('training_materials', 'slug')) {
                $table->string('slug')->nullable()->after('title');
            }
            if (! Schema::hasColumn('training_materials', 'audience_scope')) {
                $table->enum('audience_scope', ['general', 'department', 'position'])->default('general')->after('category');
            }
            if (! Schema::hasColumn('training_materials', 'department_id')) {
                $table->unsignedBigInteger('department_id')->nullable()->after('audience_scope');
            }
            if (! Schema::hasColumn('training_materials', 'position_id')) {
                $table->unsignedBigInteger('position_id')->nullable()->after('department_id');
            }
            if (! Schema::hasColumn('training_materials', 'mentor_user_id')) {
                $table->unsignedBigInteger('mentor_user_id')->nullable()->after('position_id');
            }
            if (! Schema::hasColumn('training_materials', 'pretest_form_id')) {
                $table->unsignedBigInteger('pretest_form_id')->nullable()->after('mentor_user_id');
            }
            if (! Schema::hasColumn('training_materials', 'posttest_form_id')) {
                $table->unsignedBigInteger('posttest_form_id')->nullable()->after('pretest_form_id');
            }
            if (! Schema::hasColumn('training_materials', 'content_source_type')) {
                $table->string('content_source_type', 30)->nullable()->after('youtube_url');
            }
            if (! Schema::hasColumn('training_materials', 'content_source_url')) {
                $table->text('content_source_url')->nullable()->after('content_source_type');
            }
            if (! Schema::hasColumn('training_materials', 'thumbnail_path')) {
                $table->string('thumbnail_path')->nullable()->after('content_source_url');
            }
            if (! Schema::hasColumn('training_materials', 'pass_score')) {
                $table->unsignedInteger('pass_score')->nullable()->after('duration_minutes');
            }
            if (! Schema::hasColumn('training_materials', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('pass_score');
            }
            if (! Schema::hasColumn('training_materials', 'metadata')) {
                $table->json('metadata')->nullable()->after('is_active');
            }
            if (! Schema::hasColumn('training_materials', 'created_at') && ! Schema::hasColumn('training_materials', 'updated_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        // Compatibility-first migration: no destructive rollback.
    }
};
