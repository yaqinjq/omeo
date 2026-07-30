<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('candidate_blacklists')) {
            Schema::create('candidate_blacklists', function (Blueprint $table): void {
                $table->id();
                $table->string('nik', 50)->unique();
                $table->string('email')->nullable();
                $table->string('phone', 30)->nullable();
                $table->text('reason')->nullable();
                $table->string('last_applied_position', 150)->nullable();
                $table->dateTime('blocked_at')->nullable();
                $table->string('source', 30)->default('system');
                $table->json('meta_json')->nullable();
                $table->timestamps();
            });

            return;
        }

        Schema::table('candidate_blacklists', function (Blueprint $table): void {
            if (!Schema::hasColumn('candidate_blacklists', 'nik')) {
                $table->string('nik', 50)->nullable()->after('id');
            }
            if (!Schema::hasColumn('candidate_blacklists', 'email')) {
                $table->string('email')->nullable()->after('nik');
            }
            if (!Schema::hasColumn('candidate_blacklists', 'phone')) {
                $table->string('phone', 30)->nullable()->after('email');
            }
            if (!Schema::hasColumn('candidate_blacklists', 'reason')) {
                $table->text('reason')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('candidate_blacklists', 'last_applied_position')) {
                $table->string('last_applied_position', 150)->nullable()->after('reason');
            }
            if (!Schema::hasColumn('candidate_blacklists', 'blocked_at')) {
                $table->dateTime('blocked_at')->nullable()->after('last_applied_position');
            }
            if (!Schema::hasColumn('candidate_blacklists', 'source')) {
                $table->string('source', 30)->default('system')->after('blocked_at');
            }
            if (!Schema::hasColumn('candidate_blacklists', 'meta_json')) {
                $table->json('meta_json')->nullable()->after('source');
            }
        });

        try {
            Schema::table('candidate_blacklists', function (Blueprint $table): void {
                $table->unique('nik', 'candidate_blacklists_nik_unique');
            });
        } catch (\Throwable $e) {
            // index mungkin sudah ada
        }
    }

    public function down(): void
    {
        // additive migration: no destructive rollback for production safety
    }
};
