<?php

namespace App\Console\Commands;

use App\Models\ApplicantProfile;
use App\Models\Candidate;
use App\Models\CandidateAssessment;
use App\Models\RecruitmentCredentialLog;
use App\Models\RecruitmentSetting;
use App\Services\CandidateBlacklistService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class RecruitmentPurgeCommand extends Command
{
    protected $signature = 'recruitment:purge {--dry-run : Simulasi tanpa perubahan data} {--chunk=100 : Ukuran batch}';

    protected $description = 'Auto purge kandidat recruitment sesuai retention settings.';

    public function handle(CandidateBlacklistService $blacklistService): int
    {
        $retentionFailedTestDays = RecruitmentSetting::getInt('retention_failed_test_days', 14);
        $retentionRejectedDays = RecruitmentSetting::getInt('retention_rejected_days', 14);
        $retentionBlacklistDays = RecruitmentSetting::getInt('retention_blacklist_days', 7);

        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(20, (int) $this->option('chunk'));

        $stats = [
            'purged_failed_test' => 0,
            'purged_rejected' => 0,
            'purged_blocked' => 0,
            'blacklist_upsert' => 0,
            'errors' => 0,
        ];

        $processedCandidateIds = [];

        $this->purgeRejectedCandidates($retentionRejectedDays, $chunk, $dryRun, $blacklistService, $stats, $processedCandidateIds);
        $this->purgeFailedTestCandidates($retentionFailedTestDays, $chunk, $dryRun, $blacklistService, $stats, $processedCandidateIds);
        $this->purgeBlockedCandidates($retentionBlacklistDays, $chunk, $dryRun, $blacklistService, $stats, $processedCandidateIds);

        $message = sprintf(
            'recruitment:purge selesai | failed_test=%d rejected=%d blocked=%d blacklist_upsert=%d errors=%d dry_run=%s',
            $stats['purged_failed_test'],
            $stats['purged_rejected'],
            $stats['purged_blocked'],
            $stats['blacklist_upsert'],
            $stats['errors'],
            $dryRun ? 'yes' : 'no'
        );

        $this->info($message);
        Log::info($message, $stats);

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function purgeRejectedCandidates(
        int $retentionDays,
        int $chunk,
        bool $dryRun,
        CandidateBlacklistService $blacklistService,
        array &$stats,
        array &$processedCandidateIds
    ): void {
        $cutoff = now()->subDays($retentionDays);

        Candidate::query()
            ->where(function ($query): void {
                $query->where('status', Candidate::STATUS_REJECTED)
                    ->orWhereHas('assessment', function ($assessment): void {
                        $assessment->where('status', CandidateAssessment::STATUS_REJECTED);
                    });
            })
            ->where(function ($query): void {
                $query->whereDoesntHave('assessment', function ($assessment): void {
                    $assessment->whereIn('status', [CandidateAssessment::STATUS_PASSED, CandidateAssessment::STATUS_RESERVE]);
                });
            })
            ->where(function ($query) use ($cutoff): void {
                $query->where('rejected_at', '<=', $cutoff)
                    ->orWhere(function ($nested) use ($cutoff): void {
                        $nested->whereNull('rejected_at')->where('updated_at', '<=', $cutoff);
                    });
            })
            ->orderBy('id')
            ->chunkById($chunk, function ($rows) use (&$processedCandidateIds, $dryRun, $blacklistService, &$stats): void {
                foreach ($rows as $candidate) {
                    if (in_array($candidate->id, $processedCandidateIds, true)) {
                        continue;
                    }

                    $processedCandidateIds[] = $candidate->id;
                    $ok = $this->purgeCandidate($candidate, 'rejected', $dryRun, $blacklistService, $stats);
                    if ($ok) {
                        $stats['purged_rejected']++;
                    }
                }
            });
    }

    private function purgeFailedTestCandidates(
        int $retentionDays,
        int $chunk,
        bool $dryRun,
        CandidateBlacklistService $blacklistService,
        array &$stats,
        array &$processedCandidateIds
    ): void {
        $cutoff = now()->subDays($retentionDays);

        Candidate::query()
            ->whereNotIn('status', [Candidate::STATUS_ACCEPTED, Candidate::STATUS_REJECTED, Candidate::STATUS_BLOCKED])
            ->whereHas('formAssignments', function ($query): void {
                $query->where('status', 'expired');
            })
            ->whereDoesntHave('assessment', function ($assessment): void {
                $assessment->whereIn('status', [
                    CandidateAssessment::STATUS_PASSED,
                    CandidateAssessment::STATUS_RESERVE,
                    CandidateAssessment::STATUS_REJECTED,
                    CandidateAssessment::STATUS_BLOCKED,
                ]);
            })
            ->where('updated_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById($chunk, function ($rows) use (&$processedCandidateIds, $dryRun, $blacklistService, &$stats): void {
                foreach ($rows as $candidate) {
                    if (in_array($candidate->id, $processedCandidateIds, true)) {
                        continue;
                    }

                    $processedCandidateIds[] = $candidate->id;
                    $ok = $this->purgeCandidate($candidate, 'failed_test', $dryRun, $blacklistService, $stats);
                    if ($ok) {
                        $stats['purged_failed_test']++;
                    }
                }
            });
    }

    private function purgeBlockedCandidates(
        int $retentionDays,
        int $chunk,
        bool $dryRun,
        CandidateBlacklistService $blacklistService,
        array &$stats,
        array &$processedCandidateIds
    ): void {
        $cutoff = now()->subDays($retentionDays);

        Candidate::query()
            ->where(function ($query): void {
                $query->where('status', Candidate::STATUS_BLOCKED)
                    ->orWhereHas('assessment', function ($assessment): void {
                        $assessment->where('status', CandidateAssessment::STATUS_BLOCKED);
                    });
            })
            ->where(function ($query) use ($cutoff): void {
                $query->where('blocked_at', '<=', $cutoff)
                    ->orWhere(function ($nested) use ($cutoff): void {
                        $nested->whereNull('blocked_at')->where('updated_at', '<=', $cutoff);
                    });
            })
            ->orderBy('id')
            ->chunkById($chunk, function ($rows) use (&$processedCandidateIds, $dryRun, $blacklistService, &$stats): void {
                foreach ($rows as $candidate) {
                    if (in_array($candidate->id, $processedCandidateIds, true)) {
                        continue;
                    }

                    $processedCandidateIds[] = $candidate->id;
                    $ok = $this->purgeCandidate($candidate, 'blocked', $dryRun, $blacklistService, $stats);
                    if ($ok) {
                        $stats['purged_blocked']++;
                    }
                }
            });
    }

    private function purgeCandidate(
        Candidate $candidate,
        string $category,
        bool $dryRun,
        CandidateBlacklistService $blacklistService,
        array &$stats
    ): bool {
        if ($dryRun) {
            return true;
        }

        try {
            DB::transaction(function () use ($candidate, $category, $blacklistService, &$stats): void {
                $locked = Candidate::query()->whereKey($candidate->id)->lockForUpdate()->first();
                if (!$locked) {
                    return;
                }

                $profile = $this->findApplicantProfileForCandidate($locked);
                $lastPosition = $this->extractLastAppliedPosition($profile);

                RecruitmentCredentialLog::create([
                    'candidate_id' => $locked->id,
                    'user_id' => $locked->user_id,
                    'nik' => $blacklistService->normalizeNik($locked->nik),
                    'email' => $blacklistService->normalizeEmail($locked->email),
                    'phone' => $blacklistService->normalizePhone($locked->phone),
                    'last_applied_position' => $lastPosition,
                    'status' => $category,
                    'applied_at' => $locked->applied_at,
                    'purged_at' => now(),
                    'meta_json' => [
                        'candidate_status' => $locked->status,
                        'assessment_status' => $locked->assessment?->status,
                    ],
                ]);

                if ($category === 'blocked') {
                    $upserted = $blacklistService->upsertBlockedCandidate(
                        $locked,
                        'Purge blocked candidate',
                        $lastPosition,
                        [
                            'status' => $locked->status,
                            'applied_at' => optional($locked->applied_at)->toDateTimeString(),
                            'purged_at' => now()->toDateTimeString(),
                        ],
                        'system'
                    );
                    if ($upserted) {
                        $stats['blacklist_upsert']++;
                    }
                }

                $this->purgeSensitiveFiles($locked, $profile);
                $this->purgeCandidateRelations($locked);

                if ($profile) {
                    $profile->delete();
                }

                $locked->delete();
            });

            return true;
        } catch (\Throwable $e) {
            $stats['errors']++;
            Log::error('recruitment:purge candidate error', [
                'candidate_id' => $candidate->id,
                'category' => $category,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function purgeCandidateRelations(Candidate $candidate): void
    {
        $assignmentIds = DB::table('form_assignments')
            ->where('candidate_id', $candidate->id)
            ->pluck('id')
            ->all();

        if (!empty($assignmentIds)) {
            $attemptIds = DB::table('form_attempts')
                ->whereIn('form_assignment_id', $assignmentIds)
                ->pluck('id')
                ->all();

            if (!empty($attemptIds)) {
                DB::table('form_answers')->whereIn('form_attempt_id', $attemptIds)->delete();
            }

            DB::table('form_attempts')->whereIn('form_assignment_id', $assignmentIds)->delete();
            DB::table('form_assignments')->whereIn('id', $assignmentIds)->delete();
        }

        DB::table('candidate_assessments')->where('candidate_id', $candidate->id)->delete();
        DB::table('candidate_documents')->where('candidate_id', $candidate->id)->delete();
    }

    private function purgeSensitiveFiles(Candidate $candidate, ?ApplicantProfile $profile): void
    {
        $paths = [];

        $docPaths = DB::table('candidate_documents')
            ->where('candidate_id', $candidate->id)
            ->pluck('file_path')
            ->filter()
            ->all();

        $paths = array_merge($paths, $docPaths);

        $personal = $profile?->personal_json ?? [];
        foreach (['photo_path', 'ktp_path', 'cv_path'] as $key) {
            $value = trim((string) data_get($personal, $key, ''));
            if ($value !== '') {
                $paths[] = $value;
            }
        }

        $paths = array_values(array_unique($paths));
        foreach ($paths as $path) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    private function findApplicantProfileForCandidate(Candidate $candidate): ?ApplicantProfile
    {
        if ($candidate->user_id) {
            $profile = ApplicantProfile::query()->where('user_id', $candidate->user_id)->first();
            if ($profile) {
                return $profile;
            }
        }

        $email = mb_strtolower(trim((string) $candidate->email));
        if ($email !== '') {
            $profile = ApplicantProfile::query()
                ->whereRaw("LOWER(COALESCE(json_unquote(json_extract(personal_json, '$.email')), '')) = ?", [$email])
                ->first();
            if ($profile) {
                return $profile;
            }
        }

        $nik = trim((string) $candidate->nik);
        if ($nik !== '') {
            return ApplicantProfile::query()
                ->whereRaw("COALESCE(json_unquote(json_extract(personal_json, '$.ktp_number')), '') = ?", [$nik])
                ->first();
        }

        return null;
    }

    private function extractLastAppliedPosition(?ApplicantProfile $profile): ?string
    {
        if (!$profile) {
            return null;
        }

        $personal = $profile->personal_json ?? [];

        $position = trim((string) (
            data_get($personal, 'applied_position')
            ?? data_get($personal, 'position_applied')
            ?? data_get($personal, 'position')
            ?? ''
        ));

        return $position !== '' ? $position : null;
    }
}

