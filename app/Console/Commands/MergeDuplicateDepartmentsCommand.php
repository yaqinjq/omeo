<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\Position;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MergeDuplicateDepartmentsCommand extends Command
{
    protected $signature = 'departments:merge-duplicates {--dry-run : Preview tanpa menyimpan}';

    protected $description = 'Gabungkan departemen duplikat hasil auto-create seed lama ke departemen kanonik, dan nesting unit operasional di bawah DEPARTEMEN OPERATIONAL';

    /** code kanonik => [code duplikat yang di-merge ke kanonik] */
    private const MERGE_MAP = [
        'MGR003' => ['HRD'],  // DEPARTEMEN HRGA <- HUMAN RESOURCE DEPARTMEN
        'MGR009' => ['FA'],   // DEPARTEMEN FINANCE ACCOUNTING <- FINANCE AND ACCOUNTING
        'MGR008' => ['PUR'],  // DEPARTEMEN PURCHASING <- PURCHASING
        'MGR002' => ['DM'],   // DEPARTEMEN MARKETING <- DESIGN MARKETING
    ];

    /** code parent (payung) => [code child yang di-nesting di bawahnya via parent_id] */
    private const NEST_UNDER_PARENT = [
        'MGR005' => ['BAR', 'KOT', 'KPR', 'OPROD', 'SERV', 'MAIN'], // DEPARTEMEN OPERATIONAL
    ];

    /** tabel lain yang punya FK ke departments selain positions.department_id */
    private const REFERENCING_TABLES = [
        'forms'               => 'department_id',
        'training_materials'  => 'department_id',
        'candidates'          => 'applied_department_id',
    ];

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $this->info(($isDryRun ? '[DRY RUN] ' : '') . 'Memproses merge & nesting departemen...');

        DB::beginTransaction();

        try {
            $this->newLine();
            $this->info('== MERGE (duplikat -> kanonik) ==');
            foreach (self::MERGE_MAP as $canonicalCode => $duplicateCodes) {
                $canonical = Department::where('code', $canonicalCode)->first();
                if (! $canonical) {
                    $this->warn("  SKIP: kanonik {$canonicalCode} tidak ditemukan.");
                    continue;
                }

                foreach ($duplicateCodes as $dupCode) {
                    $duplicate = Department::where('code', $dupCode)->first();
                    if (! $duplicate) {
                        $this->line("  SKIP: duplikat {$dupCode} tidak ditemukan (mungkin sudah digabung sebelumnya).");
                        continue;
                    }

                    $this->mergeDepartment($duplicate, $canonical, $isDryRun);
                }
            }

            $this->newLine();
            $this->info('== NESTING (unit operasional di bawah payung, parent_id saja — tidak dihapus) ==');
            foreach (self::NEST_UNDER_PARENT as $parentCode => $childCodes) {
                $parent = Department::where('code', $parentCode)->first();
                if (! $parent) {
                    $this->warn("  SKIP: parent {$parentCode} tidak ditemukan.");
                    continue;
                }

                foreach ($childCodes as $childCode) {
                    $child = Department::where('code', $childCode)->first();
                    if (! $child) {
                        $this->line("  SKIP: child {$childCode} tidak ditemukan.");
                        continue;
                    }
                    if ($child->id === $parent->id) {
                        continue;
                    }
                    if ($child->parent_id === $parent->id) {
                        $this->line("  SKIP: {$child->code} sudah jadi child {$parent->code}.");
                        continue;
                    }

                    $this->line("  NEST: {$child->code} ({$child->name}) -> parent {$parent->code} ({$parent->name})");
                    if (! $isDryRun) {
                        $child->update(['parent_id' => $parent->id]);
                    }
                }
            }

            if ($isDryRun) {
                DB::rollBack();
                $this->newLine();
                $this->warn('[DRY RUN] Tidak ada yang disimpan. Jalankan tanpa --dry-run untuk eksekusi.');
            } else {
                DB::commit();
                $this->newLine();
                $this->info('Selesai. Perubahan disimpan.');
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Gagal: ' . $e->getMessage());

            return 1;
        }

        return 0;
    }

    private function mergeDepartment(Department $duplicate, Department $canonical, bool $isDryRun): void
    {
        $positionCount = Position::where('department_id', $duplicate->id)->count();
        $this->line("  MERGE: {$duplicate->code} ({$duplicate->name}) -> {$canonical->code} ({$canonical->name}) — {$positionCount} posisi ikut dipindah");

        foreach (self::REFERENCING_TABLES as $table => $column) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                $count = DB::table($table)->where($column, $duplicate->id)->count();
                if ($count > 0) {
                    $this->line("    - {$table}.{$column}: {$count} baris ikut dipindah");
                }
            }
        }

        if ($isDryRun) {
            return;
        }

        Position::where('department_id', $duplicate->id)->update(['department_id' => $canonical->id]);

        foreach (self::REFERENCING_TABLES as $table => $column) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                DB::table($table)->where($column, $duplicate->id)->update([$column => $canonical->id]);
            }
        }

        $duplicate->delete();
    }
}
