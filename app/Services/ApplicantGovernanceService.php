<?php

namespace App\Services;

use App\Models\ApplicantProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class ApplicantGovernanceService
{
    public function __construct(
        private readonly ApplicantTalentPoolQuery $talentPoolQuery,
        private readonly ApplicantGovernanceAuditService $auditService,
        private readonly CandidateBlacklistService $blacklistService,
    ) {
    }

    public function isAvailable(): bool
    {
        return ApplicantProfile::supportsGovernancePersistence();
    }

    public function reject(ApplicantProfile $profile, ?User $actor = null, ?string $reason = null, array $meta = []): bool
    {
        if (! $this->isAvailable()) {
            return false;
        }

        return $this->transition($profile, ApplicantProfile::GOVERNANCE_STATUS_REJECTED, $actor, $reason ?: 'manual_reject_from_talent_pool', $meta, 'applicant_rejected');
    }

    public function blacklist(ApplicantProfile $profile, ?User $actor = null, ?string $reason = null, array $meta = []): bool
    {
        if (! $this->isAvailable()) {
            return false;
        }

        try {
            return DB::transaction(function () use ($profile, $actor, $reason, $meta): bool {
                $locked = ApplicantProfile::withTrashed()->whereKey($profile->id)->lockForUpdate()->with('user')->first();
                if (! $locked || ! $this->canGovern($locked)) {
                    return false;
                }

                $personal = $locked->normalizedPersonalJson();
                $nik = data_get($personal, 'ktp_number');
                $email = $locked->user?->email ?? data_get($personal, 'email');
                $phone = data_get($personal, 'whatsapp');
                $position = data_get($personal, 'applied_position')
                    ?? data_get($personal, 'position_applied')
                    ?? data_get($personal, 'position');

                $blockedAt = now();
                $this->blacklistService->upsertBlockedIdentity(
                    $nik,
                    $email,
                    $phone,
                    $reason ?: 'manual_blacklist_from_talent_pool',
                    $position ? (string) $position : null,
                    array_merge($meta, [
                        'applicant_profile_id' => $locked->id,
                        'actor_user_id' => $actor?->id,
                    ]),
                    'talent_pool_applicant',
                    blockedAt: $blockedAt
                );

                $oldStatus = (string) ($locked->governance_status ?: ApplicantProfile::GOVERNANCE_STATUS_ACTIVE);

                $locked->forceFill([
                    'governance_status' => ApplicantProfile::GOVERNANCE_STATUS_BLACKLISTED,
                    'governance_reason' => $reason ?: 'manual_blacklist_from_talent_pool',
                    'governed_at' => $blockedAt,
                    'blacklisted_at' => $blockedAt,
                    'rejected_at' => null,
                    'archived_at' => null,
                    'governed_by' => $actor?->id,
                    'governance_meta' => $meta !== [] ? $meta : null,
                ])->save();

                $this->auditService->log(
                    $locked,
                    'applicant_blacklisted',
                    $oldStatus,
                    ApplicantProfile::GOVERNANCE_STATUS_BLACKLISTED,
                    array_merge($meta, ['reason' => $reason ?: 'manual_blacklist_from_talent_pool']),
                    $actor?->id
                );

                return true;
            });
        } catch (Throwable) {
            return false;
        }
    }

    public function archive(ApplicantProfile $profile, ?User $actor = null, ?string $reason = null, array $meta = []): bool
    {
        if (! $this->isAvailable()) {
            return false;
        }

        try {
            return DB::transaction(function () use ($profile, $actor, $reason, $meta): bool {
                $locked = ApplicantProfile::withTrashed()->whereKey($profile->id)->lockForUpdate()->with('user')->first();
                if (! $locked || ! $this->canGovern($locked)) {
                    return false;
                }

                $oldStatus = (string) ($locked->governance_status ?: ApplicantProfile::GOVERNANCE_STATUS_ACTIVE);
                $archivedAt = now();

                $locked->forceFill([
                    'governance_status' => ApplicantProfile::GOVERNANCE_STATUS_ARCHIVED,
                    'governance_reason' => $reason ?: 'manual_archive_from_talent_pool',
                    'governed_at' => $archivedAt,
                    'archived_at' => $archivedAt,
                    'rejected_at' => null,
                    'blacklisted_at' => null,
                    'governed_by' => $actor?->id,
                    'governance_meta' => $meta !== [] ? $meta : null,
                ])->save();

                if (ApplicantProfile::supportsSoftDeleteColumn()) {
                    $locked->delete();
                }

                $this->auditService->log(
                    $locked,
                    'applicant_archived',
                    $oldStatus,
                    ApplicantProfile::GOVERNANCE_STATUS_ARCHIVED,
                    array_merge($meta, ['reason' => $reason ?: 'manual_archive_from_talent_pool']),
                    $actor?->id
                );

                return true;
            });
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param array<int,int> $profileIds
     * @return array{processed:int,succeeded:int,skipped:int,failed:int}
     */
    public function bulkReject(array $profileIds, ?User $actor = null, ?string $reason = null, array $meta = []): array
    {
        return $this->bulk($profileIds, fn (ApplicantProfile $profile) => $this->reject($profile, $actor, $reason, $meta));
    }

    /**
     * @param array<int,int> $profileIds
     * @return array{processed:int,succeeded:int,skipped:int,failed:int}
     */
    public function bulkBlacklist(array $profileIds, ?User $actor = null, ?string $reason = null, array $meta = []): array
    {
        return $this->bulk($profileIds, fn (ApplicantProfile $profile) => $this->blacklist($profile, $actor, $reason, $meta));
    }

    /**
     * @param array<int,int> $profileIds
     * @return array{processed:int,succeeded:int,skipped:int,failed:int}
     */
    public function bulkArchive(array $profileIds, ?User $actor = null, ?string $reason = null, array $meta = []): array
    {
        return $this->bulk($profileIds, fn (ApplicantProfile $profile) => $this->archive($profile, $actor, $reason, $meta));
    }

    public function canGovern(ApplicantProfile $profile): bool
    {
        if (! $this->isAvailable()) {
            return false;
        }

        return $profile->isGovernanceActive() && $this->talentPoolQuery->resolveCandidateForProfile($profile) === null;
    }

    private function transition(ApplicantProfile $profile, string $newStatus, ?User $actor, string $reason, array $meta, string $actionType): bool
    {
        try {
            return DB::transaction(function () use ($profile, $newStatus, $actor, $reason, $meta, $actionType): bool {
                $locked = ApplicantProfile::withTrashed()->whereKey($profile->id)->lockForUpdate()->with('user')->first();
                if (! $locked || ! $this->canGovern($locked)) {
                    return false;
                }

                $oldStatus = (string) ($locked->governance_status ?: ApplicantProfile::GOVERNANCE_STATUS_ACTIVE);
                $governedAt = now();

                $locked->forceFill([
                    'governance_status' => $newStatus,
                    'governance_reason' => $reason,
                    'governed_at' => $governedAt,
                    'rejected_at' => $newStatus === ApplicantProfile::GOVERNANCE_STATUS_REJECTED ? $governedAt : null,
                    'blacklisted_at' => null,
                    'archived_at' => null,
                    'governed_by' => $actor?->id,
                    'governance_meta' => $meta !== [] ? $meta : null,
                ])->save();

                $this->auditService->log(
                    $locked,
                    $actionType,
                    $oldStatus,
                    $newStatus,
                    array_merge($meta, ['reason' => $reason]),
                    $actor?->id
                );

                return true;
            });
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param array<int,int> $profileIds
     * @param callable(ApplicantProfile):bool $callback
     * @return array{processed:int,succeeded:int,skipped:int,failed:int}
     */
    private function bulk(array $profileIds, callable $callback): array
    {
        $stats = ['processed' => 0, 'succeeded' => 0, 'skipped' => 0, 'failed' => 0];

        ApplicantProfile::query()
            ->whereIn('id', $profileIds)
            ->with('user')
            ->get()
            ->each(function (ApplicantProfile $profile) use (&$stats, $callback): void {
                $stats['processed']++;

                try {
                    $ok = $callback($profile);
                    if ($ok) {
                        $stats['succeeded']++;
                    } else {
                        $stats['skipped']++;
                    }
                } catch (Throwable) {
                    $stats['failed']++;
                }
            });

        return $stats;
    }
}
