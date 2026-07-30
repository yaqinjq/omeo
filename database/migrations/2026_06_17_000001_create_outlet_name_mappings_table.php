<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('outlet_name_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('raw_name', 255)
                  ->comment('Nama outlet seperti yang muncul di file CSV/payroll');
            $table->foreignId('outlet_id')
                  ->constrained('outlets')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique('raw_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlet_name_mappings');
    }
};
