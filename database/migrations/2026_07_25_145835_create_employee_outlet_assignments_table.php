<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel khusus Sprint K (outlet/entitas legal untuk BPJS-payroll), TERPISAH dari
     * employee_assignments lama yang ternyata dipakai bersama sistem mutasi/rotasi
     * karyawan lain (assignment_type di sana: mutasi/rotasi/penugasan/promosi/demosi).
     * Jangan disatukan lagi supaya tidak bentrok dengan fitur mutasi/rotasi tersebut.
     */
    public function up(): void
    {
        Schema::create('employee_outlet_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedBigInteger('outlet_id');
            $table->unsignedBigInteger('payroll_outlet_id')->nullable();
            $table->unsignedBigInteger('legal_entity_id')->nullable();
            $table->string('department', 150)->nullable();
            $table->date('effective_date');
            $table->date('end_date')->nullable();
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
        Schema::dropIfExists('employee_outlet_assignments');
    }
};
