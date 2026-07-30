<?php

namespace App\Observers;

use App\Models\CandidateAssessment;
use App\Services\CandidateAuditService;

class CandidateAssessmentObserver
{
    public function created(CandidateAssessment $assessment): void
    {
        if (!$assessment->candidate) {
            return;
        }

        app(CandidateAuditService::class)->log(
            $assessment->candidate,
            'assessment_created',
            null,
            $assessment->status,
            [
                'assessment_status' => $assessment->status,
                'iq_score' => $assessment->iq_score,
                'interview_score' => $assessment->interview_score,
                'disc_result' => $assessment->disc_result,
            ]
        );
    }

    public function updated(CandidateAssessment $assessment): void
    {
        if (!$assessment->candidate) {
            return;
        }

        $changes = [];
        foreach (['iq_score', 'disc_result', 'interview_score', 'interview_notes', 'status'] as $field) {
            if ($assessment->wasChanged($field)) {
                $changes[$field] = [
                    'old' => $assessment->getOriginal($field),
                    'new' => $assessment->{$field},
                ];
            }
        }

        if ($changes === []) {
            return;
        }

        app(CandidateAuditService::class)->log(
            $assessment->candidate,
            'assessment_updated',
            (string) ($assessment->getOriginal('status') ?? ''),
            (string) ($assessment->status ?? ''),
            ['changes' => $changes]
        );
    }
}
