<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Candidate;
use App\Models\CandidateAssessment;
use App\Models\RecruitmentSetting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CandidateWorkflowService
{
    public function __construct(
        private readonly CandidatePromotionService $promotionService,
        private readonly CandidateBlacklistService $blacklistService
    ) {
    }

    public function accept(Candidate $candidate, ?User $actor = null): array
    {
        $employee = DB::transaction(function () use ($candidate) {
            $candidate->update([
                'status' => Candidate::STATUS_ACCEPTED,
                'accepted_at' => now(),
                'rejected_at' => null,
                'blocked_at' => null,
            ]);

            CandidateAssessment::updateOrCreate(
                ['candidate_id' => $candidate->id],
                ['status' => CandidateAssessment::STATUS_PASSED]
            );

            return $this->promotionService->promoteCandidateToEmployee($candidate->fresh('user'));
        });

        return ['employee' => $employee];
    }

    public function reject(Candidate $candidate, ?User $actor = null, array $meta = []): void
    {
        DB::transaction(function () use ($candidate): void {
            $candidate->update([
                'status' => Candidate::STATUS_REJECTED,
                'rejected_at' => now(),
                'accepted_at' => null,
                'blocked_at' => null,
            ]);

            CandidateAssessment::updateOrCreate(
                ['candidate_id' => $candidate->id],
                ['status' => CandidateAssessment::STATUS_REJECTED]
            );
        });
    }

    public function block(Candidate $candidate, ?User $actor = null, array $meta = []): void
    {
        DB::transaction(function () use ($candidate, $actor, $meta): void {
            $candidate->update([
                'status' => Candidate::STATUS_BLOCKED,
                'blocked_at' => now(),
                'accepted_at' => null,
                'rejected_at' => null,
            ]);

            CandidateAssessment::updateOrCreate(
                ['candidate_id' => $candidate->id],
                ['status' => CandidateAssessment::STATUS_BLOCKED]
            );

            $this->blacklistService->upsertBlockedCandidate(
                $candidate,
                reason: 'blocked_by_hrd',
                lastAppliedPosition: null,
                meta: array_filter([
                    'actor_user_id' => $actor?->id,
                    'source' => $meta['source'] ?? request()?->route()?->getName(),
                ]),
                source: 'candidate_block_action'
            );
        });
    }

    public function restore(Candidate $candidate, ?User $actor = null, array $meta = []): bool
    {
        if (! $this->canRestore($candidate)) {
            return false;
        }

        DB::transaction(function () use ($candidate): void {
            $previousStatus = (string) $candidate->status;

            $candidate->update([
                'status' => Candidate::STATUS_APPLIED,
                'accepted_at' => null,
                'rejected_at' => null,
                'blocked_at' => null,
            ]);

            if ($candidate->assessment && in_array($candidate->assessment->status, [CandidateAssessment::STATUS_REJECTED, CandidateAssessment::STATUS_BLOCKED], true)) {
                $candidate->assessment->update(['status' => CandidateAssessment::STATUS_IN_PROCESS]);
            }

            if ($previousStatus === Candidate::STATUS_BLOCKED) {
                $this->blacklistService->removeBlockedCandidate($candidate);
            }
        });

        return true;
    }

    public function canRestore(Candidate $candidate): bool
    {
        return $this->restoreDeadline($candidate) !== null;
    }

    public function restoreDeadline(Candidate $candidate): ?Carbon
    {
        $status = (string) $candidate->status;
        if (!in_array($status, [Candidate::STATUS_REJECTED, Candidate::STATUS_BLOCKED], true)) {
            return null;
        }

        $decisionAt = $status === Candidate::STATUS_REJECTED ? $candidate->rejected_at : $candidate->blocked_at;
        if (!$decisionAt) {
            return null;
        }

        $days = $status === Candidate::STATUS_REJECTED
            ? $this->getRetentionDays('retention_rejected_days', 'retention_rejected_days', 14)
            : $this->getRetentionDays('retention_blacklist_days', 'retention_blocked_days', 7);

        $deadline = $decisionAt->copy()->addDays($days);

        return now()->lte($deadline) ? $deadline : null;
    }

    /**
     * @param array<int,int> $candidateIds
     * @return array{processed:int,succeeded:int,skipped:int,failed:int}
     */
    public function bulkUpdateStatus(array $candidateIds, string $action, ?User $actor = null): array
    {
        $stats = ['processed' => 0, 'succeeded' => 0, 'skipped' => 0, 'failed' => 0];

        $candidates = Candidate::query()->whereIn('id', $candidateIds)->get();
        foreach ($candidates as $candidate) {
            $stats['processed']++;

            try {
                if ($action === 'accept') {
                    $this->accept($candidate, $actor);
                    $stats['succeeded']++;
                    continue;
                }

                if ($action === 'reject') {
                    $this->reject($candidate, $actor, ['source' => 'bulk_status']);
                    $stats['succeeded']++;
                    continue;
                }

                if ($action === 'block') {
                    $this->block($candidate, $actor, ['source' => 'bulk_status']);
                    $stats['succeeded']++;
                    continue;
                }

                if ($action === 'restore') {
                    if ($this->restore($candidate, $actor, ['source' => 'bulk_status'])) {
                        $stats['succeeded']++;
                    } else {
                        $stats['skipped']++;
                    }
                }
            } catch (Throwable) {
                $stats['failed']++;
            }
        }

        return $stats;
    }

    private function getRetentionDays(string $recruitmentKey, string $appSettingColumn, int $default): int
    {
        if (Schema::hasTable('recruitment_settings')) {
            return RecruitmentSetting::getInt($recruitmentKey, $default);
        }

        if (Schema::hasTable('app_settings')) {
            $setting = AppSetting::query()->first();
            $value = (int) ($setting?->{$appSettingColumn} ?? 0);
            if ($value > 0) {
                return $value;
            }
        }

        return $default;
    }
}

