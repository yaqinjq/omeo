<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('short_name', 50)->nullable();
            $table->text('description')->nullable();
            $table->string('logo_path', 512)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('headquarters_city', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_groups');
    }
};
