<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('outlet_permit_attachments')) {
            return;
        }

        Schema::create('outlet_permit_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('outlet_permit_id')->constrained('outlet_permits')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlet_permit_attachments');
    }
};
