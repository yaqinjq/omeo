<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedBigInteger('outlet_id')->nullable();         // outlet operasional
            $table->unsignedBigInteger('payroll_outlet_id')->nullable(); // cost center gaji
            $table->unsignedBigInteger('legal_entity_id')->nullable();   // PT BPJS terkait
            $table->string('department', 150)->nullable();               // dari CSV/input
            $table->date('effective_date');
            $table->date('end_date')->nullable();                        // NULL = masih aktif
            $table->enum('assignment_type', ['primary', 'secondary'])->default('primary');
            $table->string('reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'effective_date']);
            $table->index('outlet_id');
            $table->index('legal_entity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_assignments');
    }
};
