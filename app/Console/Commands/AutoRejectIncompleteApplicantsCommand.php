<?php

namespace App\Console\Commands;

use App\Models\ApplicantProfile;
use App\Models\RecruitmentSetting;
use App\Services\ApplicantGovernanceService;
use App\Services\ApplicantTalentPoolQuery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoRejectIncompleteApplicantsCommand extends Command
{
    protected $signature = 'applicants:auto-reject-incomplete {--dry-run : Simulasi tanpa perubahan data} {--chunk=100 : Ukuran batch}';

    protected $description = 'Auto reject applicant Talent Pool yang tidak melengkapi profil melebihi batas waktu.';

    public function handle(ApplicantTalentPoolQuery $talentPoolQuery, ApplicantGovernanceService $governanceService): int
    {
        if (! ApplicantProfile::supportsGovernancePersistence()) {
            $message = 'applicants:auto-reject-incomplete dilewati karena kolom governance applicant belum tersedia. Jalankan php artisan migrate terlebih dahulu.';
            $this->warn($message);
            Log::warning($message);

            return self::SUCCESS;
        }

        $days = RecruitmentSetting::getInt('applicant_incomplete_auto_reject_days', 14);
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(20, (int) $this->option('chunk'));
        $cutoff = now()->subDays($days);

        $stats = [
            'scanned' => 0,
            'rejected' => 0,
            'skipped' => 0,
        ];

        ApplicantProfile::query()
            ->with('user')
            ->where('governance_status', ApplicantProfile::GOVERNANCE_STATUS_ACTIVE)
            ->whereNull('completed_at')
            ->where('created_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById($chunk, function ($profiles) use ($talentPoolQuery, $governanceService, $dryRun, $days, &$stats): void {
                foreach ($profiles as $profile) {
                    $stats['scanned']++;

                    if ($talentPoolQuery->resolveCandidateForProfile($profile) !== null) {
                        $stats['skipped']++;
                        continue;
                    }

                    if ($dryRun) {
                        $stats['rejected']++;
                        continue;
                    }

                    $ok = $governanceService->reject(
                        $profile,
                        actor: null,
                        reason: 'auto_reject_incomplete_timeout',
                        meta: [
                            'source' => 'applicants:auto-reject-incomplete',
                            'days_threshold' => $days,
                            'selection_scope' => 'system',
                        ]
                    );

                    if ($ok) {
                        $stats['rejected']++;
                    } else {
                        $stats['skipped']++;
                    }
                }
            });

        $message = sprintf(
            'applicants:auto-reject-incomplete selesai | scanned=%d rejected=%d skipped=%d dry_run=%s',
            $stats['scanned'],
            $stats['rejected'],
            $stats['skipped'],
            $dryRun ? 'yes' : 'no'
        );

        $this->info($message);
        Log::info($message, $stats + ['days' => $days, 'dry_run' => $dryRun]);

        return self::SUCCESS;
    }
}
