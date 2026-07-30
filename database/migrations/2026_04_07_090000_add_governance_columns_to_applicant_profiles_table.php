<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('applicant_profiles')) {
            return;
        }

        Schema::table('applicant_profiles', function (Blueprint $table): void {
            if (! Schema::hasColumn('applicant_profiles', 'governance_status')) {
                $table->string('governance_status', 30)->default('active')->after('completed_at');
            }

            if (! Schema::hasColumn('applicant_profiles', 'governance_reason')) {
                $table->text('governance_reason')->nullable()->after('governance_status');
            }

            if (! Schema::hasColumn('applicant_profiles', 'governed_at')) {
                $table->timestamp('governed_at')->nullable()->after('governance_reason');
            }

            if (! Schema::hasColumn('applicant_profiles', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('governed_at');
            }

            if (! Schema::hasColumn('applicant_profiles', 'blacklisted_at')) {
                $table->timestamp('blacklisted_at')->nullable()->after('rejected_at');
            }

            if (! Schema::hasColumn('applicant_profiles', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('blacklisted_at');
            }

            if (! Schema::hasColumn('applicant_profiles', 'governed_by')) {
                $table->foreignId('governed_by')->nullable()->after('archived_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('applicant_profiles', 'governance_meta')) {
                $table->json('governance_meta')->nullable()->after('governed_by');
            }

            if (! Schema::hasColumn('applicant_profiles', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });

        DB::table('applicant_profiles')
            ->whereNull('governance_status')
            ->update(['governance_status' => 'active']);
    }

    public function down(): void
    {
        // additive migration kept for production safety
    }
};
