<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appraisal_batch_signatures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('appraisal_period_id')->nullable();

            $table->unsignedBigInteger('signer_employee_id')->nullable();
            $table->text('sig_employee')->nullable();
            $table->timestamp('sig_employee_at')->nullable();

            $table->unsignedBigInteger('signer_hrd_id')->nullable();
            $table->text('sig_hrd')->nullable();
            $table->timestamp('sig_hrd_at')->nullable();

            $table->unsignedBigInteger('signer_supervisor_id')->nullable();
            $table->text('sig_supervisor')->nullable();
            $table->timestamp('sig_supervisor_at')->nullable();

            $table->unsignedBigInteger('signer_manager_id')->nullable();
            $table->text('sig_manager')->nullable();
            $table->timestamp('sig_manager_at')->nullable();

            $table->unsignedBigInteger('signer_director_id')->nullable();
            $table->text('sig_director')->nullable();
            $table->timestamp('sig_director_at')->nullable();

            $table->timestamps();

            $table->index(['employee_id', 'appraisal_period_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appraisal_batch_signatures');
    }
};
