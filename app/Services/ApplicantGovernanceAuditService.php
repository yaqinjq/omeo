<?php

namespace App\Services;

use App\Models\ApplicantProfile;
use App\Models\ApplicantProfileActivityLog;
use Illuminate\Support\Facades\Schema;

class ApplicantGovernanceAuditService
{
    public function log(ApplicantProfile $profile, string $actionType, ?string $oldStatus = null, ?string $newStatus = null, array $metadata = [], ?int $actorUserId = null): void
    {
        if (! Schema::hasTable('applicant_profile_activity_logs')) {
            return;
        }

        $request = request();

        ApplicantProfileActivityLog::query()->create([
            'applicant_profile_id' => $profile->id,
            'actor_user_id' => $actorUserId ?? $request?->user()?->id,
            'action_type' => $actionType,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'source_page' => $request?->fullUrl() ?: $request?->path(),
            'metadata' => $metadata !== [] ? $metadata : null,
        ]);
    }
}
