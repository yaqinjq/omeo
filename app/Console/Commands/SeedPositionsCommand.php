<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\Position;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedPositionsCommand extends Command
{
    protected $signature = 'positions:seed {--dry-run : Preview tanpa menyimpan}';
    protected $description = 'Seed tabel positions dari 12 distinct jabatan di tabel employees';

    private const DEPT_KEYWORD_MAP = [
        'BAR'                       => 'BAR',
        'SERVICE'                   => 'SERVICE',
        'KITCHEN OUTLET'            => 'KITCHEN',
        'KITCHEN PRODUCTION'        => 'KITCHEN',
        'FINANCE AND ACCOUNTING'    => 'FINANCE',
        'PURCHASING'                => 'PURCHASING',
        'DESIGN MARKETING'          => 'MARKETING',
        'OFFICE PRODUCTION'         => 'OFFICE',
        'HUMAN RESOURCE DEPARTMENT' => 'HRD',
        'MANAGEMENT'                => 'MANAGEMENT',
        'IT'                        => 'IT',
        'MAINTENANCE'               => 'MAINTENANCE',
    ];

    public function handle(): int
    {
        $jabatans = DB::table('employees')
            ->selectRaw('jabatan, COUNT(*) as total')
            ->whereNotNull('jabatan')
            ->where('jabatan', '!=', '')
            ->groupBy('jabatan')
            ->orderByDesc('total')
            ->get();

        if ($jabatans->isEmpty()) {
            $this->warn('Tidak ada jabatan di tabel employees.');
            return 0;
        }

        $isDryRun = $this->option('dry-run');

        // Build lookup: department name (lowercase) → id
        $deptLookup = Department::all()
            ->keyBy(fn ($d) => strtolower(trim($d->name)));

        $this->info(($isDryRun ? '[DRY RUN] ' : '') . "Memproses {$jabatans->count()} jabatan unik...");
        $this->newLine();

        $created = 0;
        $updated = 0;

        foreach ($jabatans as $row) {
            $jabatan  = trim($row->jabatan);
            $jabatanUp = strtoupper($jabatan);
            $keyword   = self::DEPT_KEYWORD_MAP[$jabatanUp] ?? null;

            $deptId = null;
            $deptName = '—';
            if ($keyword) {
                $keywordLower = strtolower($keyword);
                $dept = $deptLookup->first(fn ($d) => str_contains(
                    strtolower($d->name),
                    $keywordLower
                ));
                if ($dept) {
                    $deptId   = $dept->id;
                    $deptName = $dept->name;
                }
            }

            $existing = Position::whereRaw('LOWER(name) = ?', [strtolower($jabatan)])->first();

            if ($existing) {
                $this->line("  SKIP (ada): {$jabatan} (karyawan: {$row->total}) → dept: {$deptName}");
                $updated++;
            } else {
                $this->line("  CREATE: {$jabatan} (karyawan: {$row->total}) → dept: {$deptName}");
                if (! $isDryRun) {
                    Position::create([
                        'name'          => $jabatan,
                        'department_id' => $deptId,
                        'is_active'     => true,
                        'level'         => 1,
                        'description'   => null,
                    ]);
                }
                $created++;
            }
        }

        $this->newLine();
        $this->info("Dibuat: {$created} | Sudah ada: {$updated}");

        if ($deptLookup->isEmpty()) {
            $this->warn('departments kosong → semua department_id = null. Jalankan departments:seed dulu.');
        }

        if ($isDryRun) {
            $this->warn('[DRY RUN] Tidak ada yang disimpan. Jalankan tanpa --dry-run untuk eksekusi.');
        }

        return 0;
    }
}
