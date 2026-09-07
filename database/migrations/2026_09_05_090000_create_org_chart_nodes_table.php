<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('org_chart_nodes')) {
            return;
        }

        Schema::create('org_chart_nodes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->enum('node_type', ['department', 'brand', 'outlet', 'employee']);
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('outlet_id')->nullable();
            $table->string('brand_name', 100)->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->boolean('is_leader_override')->nullable();
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('org_chart_nodes')->nullOnDelete();
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
            $table->foreign('outlet_id')->references('id')->on('outlets')->nullOnDelete();
            $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_chart_nodes');
    }
};
