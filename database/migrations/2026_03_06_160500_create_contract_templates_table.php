<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 50)->default('daily_worker');
            $table->boolean('is_active')->default(false);
            $table->string('logo_path')->nullable();
            $table->string('numbering_prefix')->nullable();
            $table->string('numbering_format')->nullable();
            $table->unsignedInteger('next_sequence')->default(1);
            $table->longText('body_html');
            $table->json('placeholders_json')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_templates');
    }
};
