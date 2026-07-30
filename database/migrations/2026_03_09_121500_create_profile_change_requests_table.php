<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('profile_change_requests')) {
            Schema::create('profile_change_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('entity_type', 50)->index(); // user_profile / employee_profile / applicant_profile
                $table->json('changes_json');
                $table->json('attachments_json')->nullable();
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
                $table->dateTime('submitted_at')->nullable()->index();
                $table->unsignedBigInteger('reviewed_by')->nullable()->index();
                $table->dateTime('reviewed_at')->nullable()->index();
                $table->text('review_note')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_change_requests');
    }
};
