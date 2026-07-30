<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('app_name')->default('OMEO HR Suite');
            $table->string('app_logo_path')->nullable(); // Logo Sidebar/Login
            $table->string('app_favicon_path')->nullable(); // Icon Browser
            $table->string('meta_title')->nullable(); // Judul Tab Browser
            $table->text('meta_description')->nullable(); // SEO Description
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};