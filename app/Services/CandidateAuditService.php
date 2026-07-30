<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\CandidateActivityLog;
use Illuminate\Support\Facades\Schema;

class CandidateAuditService
{
    public function log(Candidate $candidate, string $actionType, ?string $oldStatus = null, ?string $newStatus = null, array $metadata = []): void
    {
        if (!Schema::hasTable('candidate_activity_logs')) {
            return;
        }

        $request = request();
        $actorId = $request?->user()?->id;

        CandidateActivityLog::query()->create([
            'candidate_id' => $candidate->id,
            'actor_user_id' => $actorId,
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
