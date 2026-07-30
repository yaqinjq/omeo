<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('daily_worker_contract_status_histories');

        Schema::create('daily_worker_contract_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_worker_contract_id');
            $table->string('status', 30)->index();
            $table->foreignId('actor_user_id')->nullable();
            $table->text('note')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('daily_worker_contract_id', 'dwcsh_contract_fk')
                ->references('id')
                ->on('daily_worker_contracts')
                ->cascadeOnDelete();

            $table->foreign('actor_user_id', 'dwcsh_actor_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['daily_worker_contract_id', 'created_at'], 'dw_contract_status_timeline_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_worker_contract_status_histories');
    }
};
