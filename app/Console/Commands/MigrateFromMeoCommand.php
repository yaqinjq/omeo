<?php

namespace App\Console\Commands;

use App\Models\Appraisal;
use App\Models\AppraisalDetail;
use App\Models\AppraisalIndicator;
use App\Models\AppraisalPeriod;
use App\Models\Employee;
use App\Models\User;
use App\Support\AppraisalGrading;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateFromMeoCommand extends Command
{
    protected $signature = 'appraisal:migrate-from-meo
                            {--dry-run : Preview saja — tidak ada yang disimpan ke database}
                            {--limit=0 : Batasi jumlah batch MEO yang diproses (0 = semua)}
                            {--force : Re-import meskipun migration_legacy_id sudah ada}';

    protected $description = 'Migrasi data appraisal dari database MEO lama (meo_mykopio) ke OMEO';

    private const CRITERIA = [
        'job_knowladge'     => 'Job Knowledge',
        'quality_of_work'   => 'Quality of Work',
        'productivity'      => 'Productivity',
        'reliability'       => 'Reliability',
        'communication'     => 'Communication',
        'work_relationship' => 'Work Relationship',
        'safety'            => 'Safety',
        'courtesy'          => 'Courtesy',
        'motivation'        => 'Motivation',
        'leadership'        => 'Leadership',
        'absence'           => 'Attendance / Absence',
    ];

    private const SCORE_MAP = [1 => 1, 2 => 2, 3 => 3, 4 => 5];

    private const PROPOSED_MAP = [
        1 => 're_contract',
        2 => 'discontinue',
        3 => 'promoted',
        4 => 'permanent',
    ];

    public function handle(): int
    {
        $isDry = (bool) $this->option('dry-run');
        $limit = (int)  $this->option('limit');
        $force = (bool) $this->option('force');

        if ($isDry) {
            $this->warn('============================================');
            $this->warn('[DRY RUN] TIDAK ADA yang disimpan ke database');
            $this->warn('============================================');
            $this->newLine();
        }

        // 1. Verifikasi akses MEO
        try {
            $total = DB::selectOne('SELECT COUNT(*) AS n FROM meo_mykopio.table_karyawan_training')->n;
            $this->info("Koneksi MEO OK — {$total} karyawan ditemukan di meo_mykopio.");
        } catch (\Throwable $e) {
            $this->error('Tidak dapat mengakses meo_mykopio: ' . $e->getMessage());
            return self::FAILURE;
        }

        // 2. Verifikasi kolom migration_source sudah ada
        if (! \Illuminate\Support\Facades\Schema::hasColumn('appraisals', 'migration_source')) {
            $this->error('Kolom migration_source belum ada. Jalankan: php artisan migrate');
            return self::FAILURE;
        }

        // 3. Siapkan indikator MEO (find or create)
        $indicators = $this->ensureIndicators($isDry);
        $this->info('Indikator siap: ' . count(array_filter($indicators)) . ' dari ' . count(self::CRITERIA) . ' kriteria.');

        // 4. Ambil semua batch dari MEO
        $sql = "
            SELECT
                b.id_appraisal_batch,
                b.id_karyawan_training,
                b.period_label,
                b.period_start,
                b.period_end,
                b.appraisal_date,
                k.nokom,
                k.nama_lengkap
            FROM meo_mykopio.table_apprasial_batch b
            JOIN meo_mykopio.table_karyawan_training k
                ON k.id_karyawan_training = b.id_karyawan_training
            ORDER BY b.id_appraisal_batch ASC
        ";
        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
        }

        // Manual mapping: nokom MEO lama → employee_id OMEO
        // Untuk karyawan yang nokomnya salah/beda di MEO
        $manualMapping = [
            '0044336' => 146,   // LARA DUTTA (typo: harusnya 004336)
            '001234'  => 9,     // SYALFA AZAHRA (nokom dummy di MEO)
            '0099'    => 1146,  // ANDRIANTO TERTIUS (nokom salah di MEO)
        ];

        // SKIP list: nokom yang sudah resign, jangan dimigrasi
        $skipNokom = ['004440', '004689']; // ROBBY, RAYMOND

        $batches     = DB::select($sql);
        $periodCache = [];

        $stats = ['processed' => 0, 'skipped' => 0, 'no_employee' => 0, 'errors' => 0];

        $this->info('Total batch MEO: ' . count($batches));
        $this->newLine();

        $skipNokoms = array_map(fn ($n) => ltrim($n, '0'), $skipNokom);

        foreach ($batches as $batch) {
            $this->line("<fg=cyan>Batch #{$batch->id_appraisal_batch}</> — {$batch->nama_lengkap} (nokom: {$batch->nokom})");

            try {
                // 4a. Cek skip list dulu
                if (in_array(ltrim((string) $batch->nokom, '0'), $skipNokoms)) {
                    $this->warn("  SKIP resign: {$batch->nokom} — {$batch->nama_lengkap}");
                    $stats['skipped']++;
                    continue;
                }

                // 4b. Cari employee: manual mapping → findEmployee fallback
                if (isset($manualMapping[$batch->nokom])) {
                    $employee = Employee::find($manualMapping[$batch->nokom]);
                } else {
                    $employee = $this->findEmployee((string) $batch->nokom);
                }

                if (! $employee) {
                    $this->warn("  SKIP — nokom [{$batch->nokom}] tidak ditemukan di employees OMEO.");
                    $stats['no_employee']++;
                    continue;
                }

                $via = isset($manualMapping[$batch->nokom]) ? ' [manual mapping]' : '';
                $this->line("  Employee: {$employee->full_name} (id={$employee->id}){$via}");

                // 4c. Siapkan period
                $period = $this->ensurePeriod($batch, $periodCache, $isDry);

                // 4d. Ambil semua performer (evaluator) untuk batch ini
                $performers = DB::select("
                    SELECT
                        p.id_performance_apprasial,
                        p.id_username,
                        p.tanggal_penilaian,
                        s.kelebihan,
                        s.kekurangan,
                        s.prestasi,
                        s.rekomendasi_training,
                        k.job_knowladge,
                        k.quality_of_work,
                        k.productivity,
                        k.reliability,
                        k.communication,
                        k.work_relationship,
                        k.safety,
                        k.courtesy,
                        k.motivation,
                        k.leadership,
                        k.absence,
                        k.id_proposed_apprasial
                    FROM meo_mykopio.table_apprasial_perform2 p
                    LEFT JOIN meo_mykopio.table_summary_perform_apprasial s
                        ON s.id_performance_apprasial = p.id_performance_apprasial
                    LEFT JOIN meo_mykopio.table_kriteria_nilai_perform k
                        ON k.id_performance_apprasial = p.id_performance_apprasial
                    WHERE p.id_appraisal_batch = ?
                ", [$batch->id_appraisal_batch]);

                if (empty($performers)) {
                    $this->warn('  SKIP — tidak ada performer di batch ini.');
                    $stats['skipped']++;
                    continue;
                }

                foreach ($performers as $perf) {
                    $legacyId = 'meo_perf_' . $perf->id_performance_apprasial;

                    if (! $force && Appraisal::where('migration_legacy_id', $legacyId)->exists()) {
                        $this->line("  SKIP [{$legacyId}] sudah pernah dimigrasi.");
                        $stats['skipped']++;
                        continue;
                    }

                    [$convertedScores, $avgConverted] = $this->calculateScores($perf);
                    $finalScore  = $avgConverted !== null ? round(($avgConverted / 5) * 100, 2) : null;
                    $finalResult = $avgConverted !== null ? $this->scoreToResult($avgConverted) : null;

                    $appraiserId    = $this->matchUser((string) ($perf->id_username ?? ''));
                    $proposedStatus = self::PROPOSED_MAP[(int) ($perf->id_proposed_apprasial ?? 0)] ?? null;
                    $dateAppraised  = $perf->tanggal_penilaian
                        ?? $batch->appraisal_date
                        ?? now()->toDateString();

                    $userLabel = $appraiserId ? "user_id={$appraiserId}" : 'NOT MATCHED';
                    $this->line(
                        "  [{$legacyId}] evaluator={$perf->id_username} {$userLabel} " .
                        "score={$finalScore} result={$finalResult} proposed={$proposedStatus}"
                    );

                    if ($isDry) {
                        $this->line("  [DRY] Akan dibuat: Appraisal + " . count($convertedScores) . ' detail skor.');
                        $stats['processed']++;
                        continue;
                    }

                    // Hapus jika force re-import
                    if ($force) {
                        Appraisal::where('migration_legacy_id', $legacyId)->delete();
                    }

                    $appraisal = Appraisal::create([
                        'employee_id'                      => $employee->id,
                        'appraiser_id'                     => $appraiserId,
                        'appraisal_period_id'              => $period->id,
                        'trigger_source'                   => 'legacy_migration',
                        'status'                           => 'approved',
                        'is_feedback_private'              => false,
                        'enable_skill_component'           => false,
                        'enable_position_component'        => false,
                        'enable_kpi_component'             => false,
                        'final_score'                      => $finalScore,
                        'final_result'                     => $finalResult,
                        'proposed_status'                  => $proposedStatus,
                        'date_appraised'                   => $dateAppraised,
                        'submitted_at'                     => $dateAppraised,
                        'feedback_strengths'               => $this->cleanHtml($perf->kelebihan),
                        'feedback_improvements'            => $this->cleanHtml($perf->kekurangan),
                        'feedback_achievements'            => $this->cleanHtml($perf->prestasi),
                        'feedback_training_recommendations' => $this->cleanHtml($perf->rekomendasi_training),
                        'notes_hrd'                        => 'Evaluator MEO: ' . trim($perf->id_username ?? ''),
                        'migration_source'                 => 'meo_legacy',
                        'migration_legacy_id'              => $legacyId,
                        'migrated_at'                      => now(),
                    ]);

                    foreach (self::CRITERIA as $field => $label) {
                        $rawVal = $perf->$field ?? null;
                        if (! isset($rawVal) || ! is_numeric($rawVal) || (int) $rawVal === 0) {
                            continue;
                        }
                        $converted   = self::SCORE_MAP[(int) $rawVal] ?? (int) $rawVal;
                        $indicatorId = $indicators[$field] ?? null;
                        if (! $indicatorId) {
                            continue;
                        }
                        AppraisalDetail::create([
                            'appraisal_id'           => $appraisal->id,
                            'appraisal_indicator_id' => $indicatorId,
                            'score'                  => $converted,
                        ]);
                    }

                    $stats['processed']++;
                }
            } catch (\Throwable $e) {
                $this->error("  ERROR batch #{$batch->id_appraisal_batch}: " . $e->getMessage());
                $stats['errors']++;
            }
        }

        $this->newLine();
        $this->line('<fg=white;bg=blue> HASIL MIGRASI </>');
        $this->info("  Processed   : {$stats['processed']}");
        $this->warn("  No employee : {$stats['no_employee']}");
        $this->warn("  Skipped     : {$stats['skipped']}");
        if ($stats['errors'] > 0) {
            $this->error("  Errors      : {$stats['errors']}");
        }

        if ($isDry) {
            $this->newLine();
            $this->warn('[DRY RUN] TIDAK ADA data yang disimpan ke database.');
        }

        return self::SUCCESS;
    }

    private function ensureIndicators(bool $isDry): array
    {
        $map = [];
        foreach (self::CRITERIA as $field => $label) {
            if ($isDry) {
                $ind = AppraisalIndicator::where('question', $label)
                    ->where('category', 'Historis MEO')
                    ->first();
                $map[$field] = $ind?->id;
            } else {
                $ind = AppraisalIndicator::firstOrCreate(
                    ['question' => $label, 'category' => 'Historis MEO'],
                    ['weight' => 1, 'max_score' => 5]
                );
                $map[$field] = $ind->id;
            }
        }
        return $map;
    }

    private function ensurePeriod(object $batch, array &$cache, bool $isDry): object
    {
        $label = trim($batch->period_label ?? 'Data Historis MEO');

        if (isset($cache[$label])) {
            return $cache[$label];
        }

        $start = $batch->period_start ?? now()->toDateString();
        $end   = $batch->period_end   ?? $start;

        if ($isDry) {
            $period = AppraisalPeriod::where('name', $label)->first()
                ?? (object) ['id' => null, 'name' => $label, '_new' => true];
        } else {
            $period = AppraisalPeriod::firstOrCreate(
                ['name' => $label],
                ['start_date' => $start, 'end_date' => $end, 'type' => 'annual', 'is_active' => false]
            );
        }

        $this->line("  Period: [{$label}] id=" . ($period->id ?? 'NEW-DRY'));
        $cache[$label] = $period;
        return $period;
    }

    private function findEmployee(string $nokom): ?Employee
    {
        if (empty($nokom)) {
            return null;
        }

        // Direct match
        $emp = Employee::where('nokom', $nokom)->first();
        if ($emp) {
            return $emp;
        }

        // Numeric match (handles leading zeros)
        $nokomInt = (int) $nokom;
        if ($nokomInt > 0) {
            $emp = Employee::whereRaw('CAST(nokom AS UNSIGNED) = ?', [$nokomInt])->first();
        }

        return $emp;
    }

    private function matchUser(string $meoName): ?int
    {
        if (empty(trim($meoName))) {
            return null;
        }

        $upper = strtoupper(trim($meoName));

        // Match by first word of name (e.g. "ARVIN" → "ARVIN DANU EGA")
        $user = User::whereRaw('UPPER(SUBSTRING_INDEX(name, " ", 1)) = ?', [$upper])->first();
        if ($user) {
            return $user->id;
        }

        // Full name match
        $user = User::whereRaw('UPPER(name) = ?', [$upper])->first();
        return $user?->id;
    }

    private function calculateScores(object $perf): array
    {
        $converted = [];
        foreach (array_keys(self::CRITERIA) as $field) {
            $raw = $perf->$field ?? null;
            if (isset($raw) && is_numeric($raw) && (int) $raw > 0) {
                $converted[$field] = self::SCORE_MAP[(int) $raw] ?? (int) $raw;
            }
        }

        $avg = count($converted) > 0
            ? round(array_sum($converted) / count($converted), 4)
            : null;

        return [$converted, $avg];
    }

    private function scoreToResult(float $score): string
    {
        return AppraisalGrading::classify($score);
    }

    private function cleanHtml(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim($text);
        return $text !== '' ? $text : null;
    }
}
