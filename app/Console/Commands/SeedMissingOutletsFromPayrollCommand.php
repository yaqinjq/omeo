<?php

namespace App\Console\Commands;

use App\Models\Outlet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedMissingOutletsFromPayrollCommand extends Command
{
    protected $signature = 'outlets:seed-missing-from-payroll {--dry-run : Preview tanpa menyimpan}';

    protected $description = 'Buat outlet di Master Outlet untuk outlet_name di finance_bpjs_records yang belum ter-mapping (outlet_id NULL), lalu backfill outlet_id-nya';

    private const DEFAULT_BRAND = 'AH PEK KOPITIAM';
    private const DEFAULT_TYPE = 'operational';
    private const DEFAULT_TIMEZONE = 'Asia/Jakarta';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        $missingNames = DB::table('finance_bpjs_records')
            ->whereNull('outlet_id')
            ->whereNotNull('outlet_name')
            ->where('outlet_name', '!=', '')
            ->select('outlet_name', DB::raw('COUNT(*) as cnt'))
            ->groupBy('outlet_name')
            ->get();

        if ($missingNames->isEmpty()) {
            $this->info('Tidak ada outlet_name di finance_bpjs_records yang belum ter-mapping.');

            return 0;
        }

        $this->info(($isDryRun ? '[DRY RUN] ' : '') . "Memproses {$missingNames->count()} outlet_name yang belum ter-mapping...");

        DB::beginTransaction();

        try {
            $created = 0;
            $skipped = 0;

            foreach ($missingNames as $row) {
                $name = trim($row->outlet_name);
                $existing = Outlet::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();

                if ($existing) {
                    $this->line("  SKIP (sudah ada di Master Outlet): {$name} -> id={$existing->id}");
                    $skipped++;

                    if (! $isDryRun) {
                        DB::table('finance_bpjs_records')
                            ->whereNull('outlet_id')
                            ->where('outlet_name', $row->outlet_name)
                            ->update(['outlet_id' => $existing->id]);
                    }

                    continue;
                }

                $this->line("  CREATE: {$name} ({$row->cnt} baris payroll menunggu di-mapping)");

                if (! $isDryRun) {
                    $outlet = Outlet::create([
                        'name'              => $name,
                        'brand_name'        => self::DEFAULT_BRAND,
                        'outlet_type'       => self::DEFAULT_TYPE,
                        'timezone'          => self::DEFAULT_TIMEZONE,
                        'radius_meters'     => 5,
                        'geofence_radius_m' => 5,
                        'is_active'         => true,
                    ]);

                    DB::table('finance_bpjs_records')
                        ->whereNull('outlet_id')
                        ->where('outlet_name', $row->outlet_name)
                        ->update(['outlet_id' => $outlet->id]);
                }

                $created++;
            }

            if ($isDryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Gagal: ' . $e->getMessage());

            return 1;
        }

        $this->newLine();
        $this->info("Outlet dibuat: {$created} | Sudah ada (di-mapping ulang): {$skipped}");
        $this->warn('Catatan: alamat, koordinat GPS, jam kerja, dan PT/Legal Entity belum diisi untuk outlet baru — lengkapi manual di Master Outlet.');

        if ($isDryRun) {
            $this->warn('[DRY RUN] Tidak ada yang disimpan. Jalankan tanpa --dry-run untuk eksekusi.');
        }

        return 0;
    }
}
