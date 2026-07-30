<?php

namespace App\Observers;

use App\Models\Candidate;
use App\Services\CandidateAuditService;
use App\Services\Notifications\UnifiedNotificationService;

class CandidateObserver
{
    public function created(Candidate $candidate): void
    {
        app(CandidateAuditService::class)->log(
            $candidate,
            'candidate_created',
            null,
            (string) $candidate->status,
            ['full_name' => $candidate->full_name]
        );
    }

    public function updated(Candidate $candidate): void
    {
        if (! $candidate->wasChanged(['status', 'accepted_at', 'rejected_at', 'blocked_at'])) {
            return;
        }

        $oldStatus = (string) $candidate->getOriginal('status');
        $newStatus = (string) $candidate->status;
        $actionType = $this->resolveActionType($oldStatus, $newStatus);

        app(CandidateAuditService::class)->log(
            $candidate,
            $actionType,
            $oldStatus !== '' ? $oldStatus : null,
            $newStatus !== '' ? $newStatus : null,
            [
                'accepted_at' => optional($candidate->accepted_at)?->toIso8601String(),
                'rejected_at' => optional($candidate->rejected_at)?->toIso8601String(),
                'blocked_at' => optional($candidate->blocked_at)?->toIso8601String(),
            ]
        );

        app(UnifiedNotificationService::class)->notifyCandidateStatusChanged($candidate, $oldStatus, $newStatus);
    }

    private function resolveActionType(string $oldStatus, string $newStatus): string
    {
        if (in_array($oldStatus, [Candidate::STATUS_REJECTED, Candidate::STATUS_BLOCKED], true) && $newStatus === Candidate::STATUS_APPLIED) {
            return 'candidate_restored';
        }

        if ($newStatus === Candidate::STATUS_SHORTLISTED && $oldStatus !== Candidate::STATUS_SHORTLISTED) {
            return 'administration_shortlisted';
        }

        return match ($newStatus) {
            Candidate::STATUS_ACCEPTED => 'candidate_accepted',
            Candidate::STATUS_REJECTED => 'candidate_rejected',
            Candidate::STATUS_BLOCKED => 'candidate_blocked',
            default => 'candidate_status_updated',
        };
    }
}
