<?php

namespace App\Services\Finance;

use App\Models\DepartmentCategoryMapping;
use App\Models\Employee;
use App\Models\FinanceBpjsOutletSummary;
use App\Models\FinanceBpjsRecord;
use App\Models\Outlet;
use App\Models\OutletNameMapping;
use App\Models\PayrollAnnualSummary;
use App\Models\PayrollImportRow;
use App\Models\PayrollImportSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PayrollImportService
{
    public function __construct(
        private PayrollParserService $parser
    ) {}

    /**
     * Main entry: upload file, parse, stage rows, return session.
     */
    public function processUpload(UploadedFile $file, int $userId): PayrollImportSession
    {
        set_time_limit(180);

        $originalName = $file->getClientOriginalName();
        $ext          = strtolower($file->getClientOriginalExtension());
        $fileType     = in_array($ext, ['xlsx', 'xls']) ? $ext : 'csv';

        $storedPath = $file->store('finance/imports', 'local');
        $fullPath   = Storage::disk('local')->path($storedPath);

        $brand   = $this->parser->detectBrand($fullPath, $fileType);
        $periode = $this->parser->detectPeriode($fullPath, $fileType, $originalName);

        $session = PayrollImportSession::create([
            'source_brand'     => $brand,
            'source_file_name' => $originalName,
            'source_file_type' => $fileType,
            'periode'          => $periode ?? now()->format('Y-m'),
            'status'           => 'processing',
            'file_path'        => $storedPath,
            'imported_by'      => $userId,
        ]);

        try {
            if (in_array($fileType, ['xlsx', 'xls'])) {
                $parsedRows = $this->parser->parseXlsx($fullPath, $session->periode);
            } else {
                $csvResult  = $this->parser->parseCsv($fullPath);
                $parsedRows = $csvResult['rows'];
            }

            $this->stageRows($session, $parsedRows);
        } catch (\Exception $e) {
            $session->update(['status' => 'draft', 'error_message' => $e->getMessage()]);
            throw new \RuntimeException('Gagal memproses file payroll: ' . $e->getMessage(), 0, $e);
        }

        return $session->fresh();
    }

    private function stageRows(PayrollImportSession $session, array $parsedRows): void
    {
        set_time_limit(180);
        ini_set('memory_limit', '256M');

        // ── PRELOAD ke memory — cukup 1 query per tabel ──────────────────────
        $employeeByNokom = Employee::whereNotNull('nokom')
            ->select('id', 'nokom', 'nik')
            ->get()
            ->keyBy(fn ($e) => strtoupper(trim($e->nokom)));

        $outletByName = Outlet::select('id', 'name')
            ->get()
            ->keyBy(fn ($o) => strtoupper(trim($o->name)));

        // Preload outlet_name_mappings — resolusi nama non-standar dari CSV
        $outletMappingMap = OutletNameMapping::where('is_active', true)
            ->get(['raw_name', 'outlet_id'])
            ->keyBy(fn ($m) => strtoupper(trim($m->raw_name)))
            ->map(fn ($m) => (int) $m->outlet_id);

        $existingBpjs = FinanceBpjsRecord::where('periode', $session->periode)
            ->select('id', 'employee_id', 'outlet_name', 'gaji_pokok', 'bpjs_tk_employee', 'bpjs_jkes_employee')
            ->get()
            ->keyBy(fn ($r) => ($r->employee_id ?? '') . '|' . ($r->outlet_name ?? ''));

        // ── Proses semua baris dalam memory — tidak ada query di dalam loop ──
        $rowsToInsert = [];
        $bpjsToUpsert = [];
        $counts       = ['new' => 0, 'updated' => 0, 'unmatched' => 0, 'skipped' => 0];
        $now          = now()->toDateTimeString();

        foreach ($parsedRows as $raw) {
            $noKomp    = trim($raw['no_komp'] ?? '');
            $noKompKey = strtoupper($noKomp);
            $outletKey = strtoupper(trim($raw['outlet_name'] ?? ''));

            $employee  = $employeeByNokom[$noKompKey] ?? null;
            $outletObj = $outletByName[$outletKey] ?? null;
            $outletId  = $outletObj?->id ?? $outletMappingMap[$outletKey] ?? null;

            $rowStatus    = 'new';
            $diffSnapshot = null;

            if ($employee) {
                $existing = $existingBpjs[$employee->id . '|' . ($raw['outlet_name'] ?? '')] ?? null;

                if ($existing) {
                    $newTk   = abs((float) ($raw['bpjs_tk_raw'] ?? 0));
                    $newJkes = abs((float) ($raw['bpjs_jkes_raw'] ?? 0));
                    $diff    = [];

                    if (abs((float) $existing->bpjs_tk_employee - $newTk) > 0.01) {
                        $diff['bpjs_tk_employee'] = ['old' => (float) $existing->bpjs_tk_employee, 'new' => $newTk];
                    }
                    if (abs((float) $existing->bpjs_jkes_employee - $newJkes) > 0.01) {
                        $diff['bpjs_jkes_employee'] = ['old' => (float) $existing->bpjs_jkes_employee, 'new' => $newJkes];
                    }
                    if (abs((float) $existing->gaji_pokok - (float) ($raw['gaji_pokok'] ?? 0)) > 0.01) {
                        $diff['gaji_pokok'] = ['old' => (float) $existing->gaji_pokok, 'new' => (float) ($raw['gaji_pokok'] ?? 0)];
                    }

                    if (! empty($diff)) {
                        $rowStatus    = 'update_pending';
                        $diffSnapshot = $diff;
                        $counts['updated']++;
                    } else {
                        $rowStatus = 'skipped';
                        $counts['skipped']++;
                    }
                } else {
                    $rowStatus = 'new';
                    $counts['new']++;
                }
            } else {
                $rowStatus = 'unmatched';
                $counts['unmatched']++;
            }

            $rowsToInsert[] = [
                'session_id'          => $session->id,
                'no_komp'             => $noKomp,
                'nik'                 => $raw['nik'] ?? null,
                'nama'                => $raw['nama'] ?? '',
                'outlet_name_raw'     => $raw['outlet_name'] ?? null,
                'posisi'              => $raw['posisi'] ?? null,
                'join_date'           => $raw['join_date'] ?? null,
                'gaji_pokok'          => $raw['gaji_pokok'] ?? 0,
                'bpjs_tk_raw'         => $raw['bpjs_tk_raw'] ?? 0,
                'bpjs_jkes_raw'       => $raw['bpjs_jkes_raw'] ?? 0,
                'total_gaji'          => $raw['total_gaji'] ?? 0,
                'bank_name'           => $raw['bank_name'] ?? null,
                'no_rekening'         => $raw['no_rekening'] ?? null,
                'tanggal_resign'      => $raw['tanggal_resign'] ?? null,
                'source_format'       => $raw['source_format'] ?? 'csv',
                'matched_employee_id' => $employee?->id,
                'matched_outlet_id'   => $outletId,
                'row_status'          => $rowStatus,
                'diff_snapshot'       => $diffSnapshot ? json_encode($diffSnapshot) : null,
                'reviewed_by'         => null,
                'reviewed_at'         => null,
                'created_at'          => $now,
                'updated_at'          => $now,
            ];

            // Simpan ke BPJS records untuk semua baris kecuali 'skipped' dan 'update_pending'.
            // 'new' dengan employee match → employee_id terisi
            // 'unmatched' tanpa employee match → employee_id = null (nullable di schema)
            if (in_array($rowStatus, ['new', 'unmatched'])) {
                $tkAmt    = abs((float) ($raw['bpjs_tk_raw'] ?? 0));
                $jkesAmt  = abs((float) ($raw['bpjs_jkes_raw'] ?? 0));
                $category = DepartmentCategoryMapping::resolveCategory(
                    $raw['posisi'] ?? null,
                    $raw['sub_dept'] ?? null
                );

                $bpjsToUpsert[] = [
                    'employee_id'        => $employee?->id,
                    'outlet_id'          => $outletId,
                    'import_session_id'  => $session->id,
                    'no_komp'            => $noKomp,
                    'nik'                => $raw['nik'] ?? null,
                    'nama'               => $raw['nama'] ?? '',
                    'outlet_name'        => $raw['outlet_name'] ?? null,
                    'posisi'             => $raw['posisi'] ?? null,
                    'sub_dept'           => $raw['sub_dept'] ?? null,
                    'category'           => $category,
                    'join_date_emp'      => $raw['join_date'] ?? null,
                    'attd'               => $raw['attd'] ?? null,
                    'hr'                 => $raw['hr'] ?? null,
                    's_expense'          => $raw['s_expense'] ?? null,
                    'total'              => $raw['total'] ?? 0,
                    'ot1_amount'         => $raw['ot1_amount'] ?? 0,
                    'ot2_amount'         => $raw['ot2_amount'] ?? 0,
                    'tunjangan_total'    => $raw['tunjangan_total'] ?? 0,
                    'potongan_total'     => $raw['potongan_total'] ?? 0,
                    'raw_csv_json'       => $raw['raw_csv_json'] ?? null,
                    'periode'            => $session->periode,
                    'gaji_pokok'         => $raw['gaji_pokok'] ?? 0,
                    'bpjs_tk_employee'   => $tkAmt,
                    'bpjs_jkes_employee' => $jkesAmt,
                    'bpjs_tk_employer'   => 0,
                    'bpjs_jkes_employer' => 0,
                    'is_bpjs_registered' => ($tkAmt > 0 || $jkesAmt > 0) ? 1 : 0,
                    'source_format'      => $raw['source_format'] ?? 'csv',
                    'deleted_at'         => null,
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ];
            }
        }

        // ── BULK INSERT payroll_import_rows (100 baris per batch) ────────────
        foreach (array_chunk($rowsToInsert, 100) as $chunk) {
            DB::table('payroll_import_rows')->insert($chunk);
        }

        // ── BULK UPSERT finance_bpjs_records untuk baris 'new' ───────────────
        if (! empty($bpjsToUpsert)) {
            foreach (array_chunk($bpjsToUpsert, 100) as $chunk) {
                FinanceBpjsRecord::upsert(
                    $chunk,
                    ['no_komp', 'periode', 'outlet_name'],
                    [
                        'employee_id', 'outlet_id', 'import_session_id', 'nik',
                        'nama', 'outlet_name', 'posisi',
                        'sub_dept', 'category', 'join_date_emp',
                        'attd', 'hr', 's_expense',
                        'total', 'ot1_amount', 'ot2_amount',
                        'tunjangan_total', 'potongan_total', 'raw_csv_json',
                        'gaji_pokok', 'bpjs_tk_employee', 'bpjs_jkes_employee',
                        'is_bpjs_registered', 'source_format', 'updated_at',
                    ]
                );
            }
        }

        // ── Update status sesi ───────────────────────────────────────────────
        // Unmatched kini disimpan otomatis — tidak butuh review.
        // Hanya update_pending (data berubah dari sebelumnya) yang butuh konfirmasi.
        $finalStatus = $counts['updated'] > 0
            ? 'needs_review'
            : 'completed';

        $session->update([
            'total_rows'      => array_sum($counts),
            'new_count'       => $counts['new'],
            'updated_count'   => $counts['updated'],
            'unmatched_count' => $counts['unmatched'],
            'skipped_count'   => $counts['skipped'],
            'status'          => $finalStatus,
            'completed_at'    => $finalStatus === 'completed' ? now() : null,
        ]);

        if ($counts['new'] > 0 || $counts['unmatched'] > 0) {
            $this->recalculateOutletSummary($session);
        }
    }

    public function confirmRow(PayrollImportRow $row, int $reviewerId): void
    {
        FinanceBpjsRecord::updateOrCreate(
            ['no_komp' => $row->no_komp, 'periode' => $row->session->periode],
            [
                'employee_id'        => $row->matched_employee_id,
                'outlet_id'          => $row->matched_outlet_id,
                'import_session_id'  => $row->session_id,
                'nik'                => $row->nik,
                'nama'               => $row->nama,
                'outlet_name'        => $row->outlet_name_raw,
                'posisi'             => $row->posisi,
                'gaji_pokok'         => $row->gaji_pokok,
                'total'              => $row->total_gaji ?? $row->gaji_pokok,
                'bpjs_tk_employee'   => abs((float) $row->bpjs_tk_raw),
                'bpjs_jkes_employee' => abs((float) $row->bpjs_jkes_raw),
                'is_bpjs_registered' => abs((float) $row->bpjs_tk_raw) > 0
                                    || abs((float) $row->bpjs_jkes_raw) > 0,
                'source_format'      => $row->source_format,
            ]
        );

        $row->update(['row_status' => 'confirmed', 'reviewed_by' => $reviewerId, 'reviewed_at' => now()]);
        $this->checkAndCompleteSession($row->session);
    }

    public function skipRow(PayrollImportRow $row, int $reviewerId): void
    {
        $row->update(['row_status' => 'skipped', 'reviewed_by' => $reviewerId, 'reviewed_at' => now()]);
        $this->checkAndCompleteSession($row->session);
    }

    public function confirmAll(PayrollImportSession $session, int $reviewerId): void
    {
        $now = now();

        foreach ($session->rows()->where('row_status', 'update_pending')->get() as $row) {
            FinanceBpjsRecord::updateOrCreate(
                ['no_komp' => $row->no_komp, 'periode' => $session->periode],
                [
                    'employee_id'        => $row->matched_employee_id,
                    'outlet_id'          => $row->matched_outlet_id,
                    'import_session_id'  => $session->id,
                    'nik'                => $row->nik,
                    'nama'               => $row->nama,
                    'outlet_name'        => $row->outlet_name_raw,
                    'posisi'             => $row->posisi,
                    'gaji_pokok'         => $row->gaji_pokok,
                    'total'              => $row->total_gaji ?? $row->gaji_pokok,
                    'bpjs_tk_employee'   => abs((float) $row->bpjs_tk_raw),
                    'bpjs_jkes_employee' => abs((float) $row->bpjs_jkes_raw),
                    'is_bpjs_registered' => abs((float) $row->bpjs_tk_raw) > 0
                                        || abs((float) $row->bpjs_jkes_raw) > 0,
                    'source_format'      => $row->source_format,
                ]
            );

            $row->update(['row_status' => 'confirmed', 'reviewed_by' => $reviewerId, 'reviewed_at' => $now]);
        }

        $session->update(['status' => 'completed', 'completed_at' => $now]);
        $this->recalculateOutletSummary($session);
    }

    private function checkAndCompleteSession(PayrollImportSession $session): void
    {
        $stillPending = $session->rows()->where('row_status', 'update_pending')->exists();

        if (! $stillPending) {
            $session->update(['status' => 'completed', 'completed_at' => now()]);
            $this->recalculateOutletSummary($session);
        }
    }

    public function syncToAnnualSummary(int $importSessionId, int $tahun, int $bulan): void
    {
        $gajiBulanCol = 'gaji_' . match ($bulan) {
            1  => 'jan', 2  => 'feb', 3  => 'mar', 4  => 'apr',
            5  => 'mei', 6  => 'jun', 7  => 'jul', 8  => 'aug',
            9  => 'sep', 10 => 'okt', 11 => 'nov', 12 => 'des',
            default => throw new \InvalidArgumentException("Bulan tidak valid: $bulan"),
        };

        $allGajiCols = [
            'gaji_jan', 'gaji_feb', 'gaji_mar', 'gaji_apr',
            'gaji_mei', 'gaji_jun', 'gaji_jul', 'gaji_aug',
            'gaji_sep', 'gaji_okt', 'gaji_nov', 'gaji_des',
        ];

        $records = FinanceBpjsRecord::where('import_session_id', $importSessionId)->get();

        // GROUP per no_komp — jumlahkan total dari semua outlet
        $groupedByNoKomp = $records->groupBy('no_komp');

        foreach ($groupedByNoKomp as $noKomp => $outletRecords) {
            if (blank($noKomp)) {
                continue;
            }

            $firstRecord = $outletRecords->first();

            // SUM total dari SEMUA outlet untuk no_komp ini bulan ini
            $sumTotal = (float) $outletRecords->sum('total');
            if ($sumTotal == 0) {
                $sumTotal = (float) $outletRecords->sum('gaji_pokok');
            }

            // Outlet dominan = yang punya total terbesar (outlet utama bulan itu)
            $dominantRecord = $outletRecords->sortByDesc('total')->first();
            $outletId       = $dominantRecord->outlet_id
                ?? OutletNameMapping::resolveOutletId($dominantRecord->outlet_name ?? '');

            $existing = PayrollAnnualSummary::where('no_komp', $noKomp)
                ->where('tahun', $tahun)
                ->first();

            if ($existing) {
                $existing->$gajiBulanCol = $sumTotal > 0 ? $sumTotal : null;
                $existing->outlet_id     = $existing->outlet_id ?? $outletId;
                $existing->posisi        = $existing->posisi ?: $firstRecord->posisi;

                // Recompute bulan_aktif & total_tahunan dari semua 12 kolom
                $bulanAktif   = 0;
                $totalTahunan = 0.0;
                foreach ($allGajiCols as $col) {
                    $val = (float) ($existing->$col ?? 0);
                    if ($val > 0) {
                        $bulanAktif++;
                        $totalTahunan += $val;
                    }
                }
                $existing->bulan_aktif   = $bulanAktif;
                $existing->total_tahunan = $totalTahunan > 0 ? $totalTahunan : null;
                $existing->save();
            } else {
                PayrollAnnualSummary::create([
                    'import_session_id' => null,
                    'employee_id'       => $firstRecord->employee_id,
                    'outlet_id'         => $outletId,
                    'no_komp'           => $noKomp,
                    'nik'               => $firstRecord->nik,
                    'nama'              => $firstRecord->nama,
                    'outlet_name_raw'   => $dominantRecord->outlet_name,
                    'posisi'            => $firstRecord->posisi,
                    'tahun'             => $tahun,
                    $gajiBulanCol       => $sumTotal > 0 ? $sumTotal : null,
                    'bulan_aktif'       => $sumTotal > 0 ? 1 : 0,
                    'total_tahunan'     => $sumTotal > 0 ? $sumTotal : null,
                ]);
            }
        }
    }

    public function recalculateOutletSummary(PayrollImportSession $session): void
    {
        // Resolve outlet_id untuk records yang belum ter-link
        $needsResolve = FinanceBpjsRecord::where('periode', $session->periode)
            ->whereNull('outlet_id')
            ->whereNotNull('outlet_name')
            ->get();

        foreach ($needsResolve as $record) {
            $resolvedId = OutletNameMapping::resolveOutletId($record->outlet_name);
            if ($resolvedId) {
                $record->update(['outlet_id' => $resolvedId]);
            }
        }

        // Group semua records yang punya outlet_id dan hitung summary
        $allRecords = FinanceBpjsRecord::where('periode', $session->periode)
            ->whereNotNull('outlet_id')
            ->get()
            ->groupBy('outlet_id');

        foreach ($allRecords as $outletId => $outletRecords) {
            $total      = $outletRecords->count();
            $registered = $outletRecords->where('is_bpjs_registered', true)->count();
            $totalTk    = $outletRecords->sum('bpjs_tk_employee');
            $totalJkes  = $outletRecords->sum('bpjs_jkes_employee');

            FinanceBpjsOutletSummary::updateOrCreate(
                ['outlet_id' => $outletId, 'periode' => $session->periode],
                [
                    'total_karyawan'           => $total,
                    'karyawan_terdaftar_bpjs'  => $registered,
                    'karyawan_tidak_terdaftar' => $total - $registered,
                    'total_bpjs_tk'            => $totalTk,
                    'total_bpjs_jkes'          => $totalJkes,
                    'total_bpjs_keseluruhan'   => $totalTk + $totalJkes,
                ]
            );
        }
    }
}
