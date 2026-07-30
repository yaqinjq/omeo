<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Models\Candidate;
use App\Models\CandidateRetentionHistory;
use App\Services\CandidateBlacklistService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupCandidateRetentionCommand extends Command
{
    protected $signature = 'candidates:retention-cleanup {--dry-run : Simulasi tanpa hapus data} {--chunk=100 : Ukuran batch per proses}';

    protected $description = 'Auto delete kandidat gagal sesuai aturan retensi + simpan riwayat + blacklist aman.';

    public function handle(CandidateBlacklistService $blacklistService): int
    {
        $setting = AppSetting::query()->first();

        $retentionEnabled = (bool) ($setting->retention_enabled ?? true);
        if (!$retentionEnabled) {
            $this->info('Retention cleanup tidak dijalankan: fitur dinonaktifkan di settings.');
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(20, (int) $this->option('chunk'));

        $rules = [
            Candidate::STATUS_REJECTED => max(1, (int) ($setting->retention_rejected_days ?? 30)),
            Candidate::STATUS_BLOCKED => max(1, (int) ($setting->retention_blocked_days ?? 365)),
        ];

        $stats = [
            'scanned' => 0,
            'deleted' => 0,
            'blacklisted' => 0,
            'history_written' => 0,
            'failed' => 0,
        ];

        foreach ($rules as $status => $days) {
            $decisionColumn = $status === Candidate::STATUS_REJECTED ? 'rejected_at' : 'blocked_at';
            $cutoff = now()->subDays($days);

            Candidate::query()
                ->where('status', $status)
                ->where(function ($query) use ($decisionColumn, $cutoff): void {
                    $query->where($decisionColumn, '<=', $cutoff)
                        ->orWhere(function ($nested) use ($decisionColumn, $cutoff): void {
                            $nested->whereNull($decisionColumn)
                                ->where('updated_at', '<=', $cutoff);
                        });
                })
                ->orderBy('id')
                ->chunkById($chunk, function ($candidates) use (
                    $status,
                    $days,
                    $decisionColumn,
                    $dryRun,
                    $blacklistService,
                    &$stats
                ): void {
                    foreach ($candidates as $candidate) {
                        $stats['scanned']++;

                        if ($dryRun) {
                            continue;
                        }

                        try {
                            DB::transaction(function () use (
                                $candidate,
                                $status,
                                $days,
                                $decisionColumn,
                                $blacklistService,
                                &$stats
                            ): void {
                                $locked = Candidate::query()
                                    ->whereKey($candidate->id)
                                    ->lockForUpdate()
                                    ->first();

                                if (!$locked || $locked->status !== $status) {
                                    return;
                                }

                                if ($status === Candidate::STATUS_BLOCKED) {
                                    $stats['blacklisted'] += $blacklistService->blacklistFromCandidate(
                                        $locked,
                                        'blocked_retention_cleanup',
                                        null,
                                        ['source' => 'retention_command']
                                    );
                                }

                                $decisionAt = data_get($locked, $decisionColumn) ?: $locked->updated_at;
                                $locked->loadMissing('assessment');

                                CandidateRetentionHistory::create([
                                    'original_candidate_id' => $locked->id,
                                    'user_id' => $locked->user_id,
                                    'full_name' => $locked->full_name,
                                    'email' => $locked->email,
                                    'phone' => $locked->phone,
                                    'nik' => $locked->nik,
                                    'status' => $locked->status,
                                    'decision_at' => $decisionAt,
                                    'deleted_at_retention' => now(),
                                    'retention_days' => $days,
                                    'delete_reason' => 'retention_expired',
                                    'snapshot' => [
                                        'candidate' => $locked->toArray(),
                                        'assessment' => $locked->assessment?->toArray(),
                                        'form_assignments_count' => $locked->formAssignments()->count(),
                                        'contracts_count' => $locked->contracts()->count(),
                                        'daily_worker_contracts_count' => $locked->dailyWorkerContracts()->count(),
                                    ],
                                ]);

                                $stats['history_written']++;

                                $locked->forceDelete();
                                $stats['deleted']++;
                            });
                        } catch (\Throwable $e) {
                            $stats['failed']++;
                            Log::error('Retention cleanup candidate failed', [
                                'candidate_id' => $candidate->id,
                                'status' => $status,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                });
        }

        if (!$dryRun && $setting) {
            $setting->forceFill(['retention_last_run_at' => now()])->save();
        }

        $summary = sprintf(
            'Retention cleanup selesai. scanned=%d deleted=%d history=%d blacklisted=%d failed=%d dry_run=%s',
            $stats['scanned'],
            $stats['deleted'],
            $stats['history_written'],
            $stats['blacklisted'],
            $stats['failed'],
            $dryRun ? 'yes' : 'no'
        );

        $this->info($summary);

        Log::info('Retention cleanup executed', array_merge($stats, ['dry_run' => $dryRun]));

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}

