<?php

namespace App\Observers;

use App\Models\FormAssignment;
use App\Services\CandidateAuditService;

class FormAssignmentObserver
{
    public function created(FormAssignment $assignment): void
    {
        if (!$assignment->candidate) {
            return;
        }

        app(CandidateAuditService::class)->log(
            $assignment->candidate,
            $this->resolveActionType(null, $assignment->status),
            null,
            $assignment->status,
            $this->buildMetadata($assignment)
        );
    }

    public function updated(FormAssignment $assignment): void
    {
        if (!$assignment->candidate || !$assignment->wasChanged(['status', 'opened_at', 'expires_at', 'closed_at'])) {
            return;
        }

        $oldStatus = $assignment->getOriginal('status');
        $newStatus = $assignment->status;

        app(CandidateAuditService::class)->log(
            $assignment->candidate,
            $this->resolveActionType($oldStatus, $newStatus),
            $oldStatus,
            $newStatus,
            $this->buildMetadata($assignment)
        );
    }

    private function resolveActionType(?string $oldStatus, ?string $newStatus): string
    {
        $routeName = request()?->route()?->getName();

        if ($routeName === 'candidates.tests.reset') {
            return 'test_reset';
        }

        if ($routeName === 'candidates.tests.lock') {
            return 'test_locked';
        }

        if ($routeName === 'candidates.bulk-activate-tests') {
            return 'test_opened_bulk';
        }

        if ($newStatus === FormAssignment::STATUS_OPENED) {
            return 'test_opened';
        }

        if ($newStatus === FormAssignment::STATUS_SUBMITTED) {
            return 'test_submitted';
        }

        if ($newStatus === FormAssignment::STATUS_EXPIRED) {
            return 'test_expired';
        }

        if ($newStatus === FormAssignment::STATUS_LOCKED) {
            return 'test_locked';
        }

        return 'test_assignment_updated';
    }

    private function buildMetadata(FormAssignment $assignment): array
    {
        return [
            'assignment_id' => $assignment->id,
            'form_id' => $assignment->form_id,
            'form_type' => $assignment->form?->type,
            'form_name' => $assignment->form?->name,
            'opened_at' => optional($assignment->opened_at)?->toIso8601String(),
            'expires_at' => optional($assignment->expires_at)?->toIso8601String(),
            'closed_at' => optional($assignment->closed_at)?->toIso8601String(),
            'created_by' => $assignment->created_by,
            'previous_status' => $assignment->getOriginal('status'),
        ];
    }
}
