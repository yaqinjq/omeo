<?php

namespace App\Console\Commands;

use App\Models\Position;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateEmployeePositionsCommand extends Command
{
    protected $signature = 'employees:migrate-positions {--dry-run : Preview tanpa menyimpan}';
    protected $description = 'Migrasi employees.jabatan → employees.position_id + department_id';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        $positions = Position::all()->keyBy(
            fn ($p) => strtoupper(trim($p->name))
        );

        if ($positions->isEmpty()) {
            $this->error('Tabel positions kosong. Jalankan positions:seed dulu.');
            return 1;
        }

        $employees = DB::table('employees')
            ->whereNotNull('jabatan')
            ->where('jabatan', '!=', '')
            ->whereNull('deleted_at')
            ->select(['id', 'jabatan', 'position_id'])
            ->get();

        $this->info(($isDryRun ? '[DRY RUN] ' : '') . "Memproses {$employees->count()} karyawan...");

        $matched   = 0;
        $skipped   = 0;
        $unmatched = [];

        foreach ($employees as $emp) {
            $key = strtoupper(trim($emp->jabatan));
            $pos = $positions[$key] ?? null;

            if (! $pos) {
                $unmatched[] = $emp->jabatan;
                continue;
            }

            if ($emp->position_id === $pos->id) {
                $skipped++;
                continue;
            }

            if (! $isDryRun) {
                DB::table('employees')
                    ->where('id', $emp->id)
                    ->update([
                        'position_id'   => $pos->id,
                        'department_id' => $pos->department_id,
                    ]);
            }
            $matched++;
        }

        $this->newLine();
        $this->info("Match & update: {$matched}");
        $this->info("Sudah mapped (skip): {$skipped}");

        $unmatchedUniq = array_unique($unmatched);
        if (count($unmatchedUniq) > 0) {
            $this->warn('Tidak match ke positions:');
            foreach ($unmatchedUniq as $u) {
                $cnt = count(array_keys($unmatched, $u));
                $this->line("  - {$u} ({$cnt} karyawan)");
            }
        } else {
            $this->info('Semua jabatan berhasil di-match! ✓');
        }

        if ($isDryRun) {
            $this->warn('[DRY RUN] Tidak ada yang diubah. Jalankan tanpa --dry-run untuk eksekusi.');
        }

        return 0;
    }
}
