<?php

namespace App\Console\Commands;

use App\Models\Department;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SeedDepartmentsCommand extends Command
{
    protected $signature = 'departments:seed {--dry-run : Preview tanpa menyimpan}';
    protected $description = 'Seed tabel departments dari table_company_departemen (legacy)';

    public function handle(): int
    {
        if (! Schema::hasTable('table_company_departemen')) {
            $this->error('Tabel table_company_departemen tidak ditemukan.');
            return 1;
        }

        $rows = DB::table('table_company_departemen')
            ->orderBy('id_departemen')
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('table_company_departemen kosong, tidak ada yang di-seed.');
            return 0;
        }

        $isDryRun = $this->option('dry-run');
        $created  = 0;
        $updated  = 0;
        $skipped  = 0;

        $this->info(($isDryRun ? '[DRY RUN] ' : '') . "Memproses {$rows->count()} departemen...");

        foreach ($rows as $row) {
            $code = trim($row->kode_departemen);
            $name = trim($row->nama_departemen);

            if ($code === '' || $name === '') {
                $skipped++;
                continue;
            }

            $existing = Department::withTrashed()->where('code', $code)->first();

            if ($existing) {
                $this->line("  SKIP (ada): {$code} – {$name}");
                $skipped++;

                if (! $isDryRun && $existing->trashed()) {
                    $existing->restore();
                    $existing->update(['name' => $name, 'is_active' => true]);
                    $this->line("    → Restored dari soft-delete.");
                    $updated++;
                    $skipped--;
                }
            } else {
                $this->line("  CREATE: {$code} – {$name}");
                if (! $isDryRun) {
                    Department::create([
                        'code'      => $code,
                        'name'      => $name,
                        'is_active' => true,
                        'sort_order' => (int) $row->id_departemen,
                    ]);
                }
                $created++;
            }
        }

        $this->newLine();
        $this->info("Dibuat: {$created} | Diupdate: {$updated} | Dilewati: {$skipped}");

        if ($isDryRun) {
            $this->warn('[DRY RUN] Tidak ada yang disimpan. Jalankan tanpa --dry-run untuk eksekusi.');
        }

        return 0;
    }
}
