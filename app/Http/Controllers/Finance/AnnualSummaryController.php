<?php
namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\FinanceBpjsRecord;
use App\Models\Outlet;
use App\Models\Employee;
use App\Models\PayrollAnnualImportSession;
use App\Models\PayrollAnnualSummary;
use App\Services\Finance\PayrollParserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AnnualSummaryController extends Controller
{
    public function __construct(
        private PayrollParserService $parser
    ) {}

    public function index(Request $request)
    {
        $tahun    = (int) $request->get('tahun', now()->year);
        $outletId = $request->get('outlet_id');
        $bulan    = $request->get('bulan');
        $perPage = $this->getPerPage(50);

        $availableYears = PayrollAnnualSummary::selectRaw('DISTINCT tahun')
            ->orderByDesc('tahun')->pluck('tahun');

        $query = PayrollAnnualSummary::with('outlet')
            ->where('tahun', $tahun)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId));

        if ($bulan) {
            $col = $this->bulanToColumn((int) $bulan);
            $query->where($col, '>', 0);
        }

        $records = $query->orderBy('outlet_name_raw')
            ->orderBy('nama')
            ->paginate($perPage)
            ->withQueryString();

        $outlets = Outlet::orderBy('name')->get();

        $statsQuery = PayrollAnnualSummary::where('tahun', $tahun)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId));

        $totalKaryawan    = $statsQuery->count();
        $totalGajiTahunan = $statsQuery->sum('total_tahunan');
        $totalThr         = $statsQuery->sum('thr');

        $monthlyTotals = [];
        $monthCols = [
            1 => 'gaji_jan',  2 => 'gaji_feb',  3 => 'gaji_mar',  4 => 'gaji_apr',
            5 => 'gaji_mei',  6 => 'gaji_jun',  7 => 'gaji_jul',  8 => 'gaji_aug',
            9 => 'gaji_sep', 10 => 'gaji_okt', 11 => 'gaji_nov', 12 => 'gaji_des',
        ];
        foreach ($monthCols as $m => $col) {
            $monthlyTotals[$m] = PayrollAnnualSummary::where('tahun', $tahun)
                ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
                ->whereNotNull($col)
                ->where($col, '>', 0)
                ->selectRaw("COUNT(*) as jumlah_karyawan, SUM($col) as total_gaji")
                ->first();
        }

        $importSessions = PayrollAnnualImportSession::with('importer')
            ->where('tahun', $tahun)
            ->orderByDesc('created_at')
            ->get();

        // Set no_komp|periode yang punya >1 outlet — untuk badge "multi" di UI
        $multiOutletSet = FinanceBpjsRecord::where('periode', 'like', $tahun . '-%')
            ->selectRaw('no_komp, periode, COUNT(*) as cnt')
            ->groupBy('no_komp', 'periode')
            ->having('cnt', '>', 1)
            ->get()
            ->mapWithKeys(fn ($r) => [$r->no_komp . '|' . $r->periode => true])
            ->all();

        // Breakdown gaji per kategori per bulan dari finance_bpjs_records
        $bulanCols  = [
            1=>'jan', 2=>'feb', 3=>'mar', 4=>'apr', 5=>'mei', 6=>'jun',
            7=>'jul', 8=>'aug', 9=>'sep', 10=>'okt', 11=>'nov', 12=>'des',
        ];
        $categories = ['bar', 'kitchen', 'service', 'office', 'prive', 'lainnya'];

        $bpjsRaw = FinanceBpjsRecord::whereNotNull('category')
            ->where('periode', 'like', $tahun . '-%')
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->selectRaw('category, periode, SUM(gaji_pokok) as total')
            ->groupBy('category', 'periode')
            ->get();

        $categoryBreakdown       = array_fill_keys($categories, array_fill_keys(array_values($bulanCols), 0.0));
        $monthlyTotalByCategory  = array_fill_keys(array_values($bulanCols), 0.0);

        foreach ($bpjsRaw as $row) {
            $cat      = $row->category;
            $bulanNum = (int) substr($row->periode, 5, 2);
            $label    = $bulanCols[$bulanNum] ?? null;
            if (! $label || ! isset($categoryBreakdown[$cat])) {
                continue;
            }
            $categoryBreakdown[$cat][$label]    += (float) $row->total;
            $monthlyTotalByCategory[$label]     += (float) $row->total;
        }

        return view('finance.annual_summary.index', compact(
            'records', 'outlets', 'tahun', 'outletId', 'bulan',
            'availableYears', 'totalKaryawan', 'totalGajiTahunan',
            'totalThr', 'monthlyTotals', 'importSessions', 'perPage',
            'categoryBreakdown', 'monthlyTotalByCategory', 'categories', 'bulanCols',
            'multiOutletSet'
        ));
    }

    public function detailView(Request $request)
    {
        $tahun    = (int) $request->get('tahun', now()->year);
        $outletId = $request->get('outlet_id') ? (int) $request->get('outlet_id') : null;
        $search   = $request->get('search', '');
        $perPage  = $this->getPerPage(50);

        $bulanLabels = [
            1=>'Januari',  2=>'Februari', 3=>'Maret',     4=>'April',
            5=>'Mei',      6=>'Juni',     7=>'Juli',      8=>'Agustus',
            9=>'September',10=>'Oktober', 11=>'November', 12=>'Desember',
        ];

        $availableYears = PayrollAnnualSummary::selectRaw('DISTINCT tahun')
            ->orderByDesc('tahun')->pluck('tahun');

        $activeBulans = FinanceBpjsRecord::where('periode', 'like', $tahun . '-%')
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->selectRaw("CAST(SUBSTRING(periode, 6, 2) AS UNSIGNED) as bulan")
            ->distinct()
            ->orderBy('bulan')
            ->pluck('bulan')
            ->map(fn ($b) => (int) $b)
            ->toArray();

        $periodeList = array_map(fn ($m) => sprintf('%04d-%02d', $tahun, $m), $activeBulans);

        $allRecs = DB::table('finance_bpjs_records')
            ->whereIn('periode', $periodeList)
            ->whereNull('deleted_at')
            ->get(['no_komp','periode','gaji_pokok','hr','total',
                   'raw_csv_json','s_expense','bpjs_tk_employee','bpjs_jkes_employee']);

        $lookup = [];
        $clean  = fn ($v) => (float) str_replace([',', ' '], '', $v ?? '0');
        foreach ($allRecs as $rec) {
            $raw = json_decode($rec->raw_csv_json ?? '[]', true) ?: [];
            if (!isset($lookup[$rec->no_komp][$rec->periode])) {
                $lookup[$rec->no_komp][$rec->periode] = [0, 0, 0, 0, 0, 0, 0, 0, 0];
            }
            $lookup[$rec->no_komp][$rec->periode][0] += (float) $rec->gaji_pokok;
            $lookup[$rec->no_komp][$rec->periode][1] += (float) $rec->hr;
            $lookup[$rec->no_komp][$rec->periode][2] += $clean($raw[9]  ?? '');
            $lookup[$rec->no_komp][$rec->periode][3] += $clean($raw[28] ?? '');
            $lookup[$rec->no_komp][$rec->periode][4] += $clean($raw[29] ?? '');
            $lookup[$rec->no_komp][$rec->periode][5] += $clean($raw[18] ?? '');
            $lookup[$rec->no_komp][$rec->periode][6] += (float) ($rec->bpjs_tk_employee  ?? 0);
            $lookup[$rec->no_komp][$rec->periode][7] += (float) ($rec->bpjs_jkes_employee ?? 0);
            $lookup[$rec->no_komp][$rec->periode][8] += (float) $rec->total;
        }

        $subCols = ['Gaji Pokok','HR','Uang Hadir','Tunjangan LK','Tunjangan RnD','Rapelan','BPJS TK','BPJS Kes','Total'];

        $outlets = Outlet::orderBy('name')->get();

        $employees = PayrollAnnualSummary::with('outlet')
            ->where('tahun', $tahun)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->when($search, fn ($q) => $q->where(function ($q2) use ($search) {
                $q2->where('no_komp', 'like', "%{$search}%")
                   ->orWhere('nama', 'like', "%{$search}%");
            }))
            ->orderBy('outlet_name_raw')
            ->orderBy('nama')
            ->paginate($perPage)
            ->withQueryString();

        return view('finance.annual_summary.detail_view', compact(
            'employees', 'outlets', 'tahun', 'outletId', 'search',
            'availableYears', 'activeBulans', 'bulanLabels', 'periodeList',
            'lookup', 'subCols', 'perPage'
        ));
    }

    public function create()
    {
        return view('finance.annual_summary.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'summary_file' => [
                'required', 'file', 'max:15360',
                function ($attr, $val, $fail) {
                    $ext = strtolower($val->getClientOriginalExtension());
                    if (!in_array($ext, ['xls', 'xlsx'])) {
                        $fail('File harus berformat XLS atau XLSX.');
                    }
                },
            ],
            'tahun' => 'required|integer|min:2020|max:2030',
        ], [
            'summary_file.required' => 'File wajib diupload.',
            'tahun.required'        => 'Tahun wajib diisi.',
        ]);

        $file  = $request->file('summary_file');
        $tahun = (int) $request->input('tahun');

        $storedPath = $file->store('finance/annual_imports', 'local');
        $fullPath   = Storage::disk('local')->path($storedPath);

        $session = PayrollAnnualImportSession::create([
            'source_file_name' => $file->getClientOriginalName(),
            'source_brand'     => 'tokio',
            'tahun'            => $tahun,
            'sheet_name'       => 'SUMMARY TOKIO',
            'status'           => 'processing',
            'imported_by'      => auth()->id(),
        ]);

        try {
            set_time_limit(180);
            $parsedRows = $this->parser->parseSummaryTokio($fullPath, $tahun);

            if (empty($parsedRows)) {
                throw new \RuntimeException(
                    'Tidak ada data yang berhasil dibaca dari sheet SUMMARY TOKIO. ' .
                    'Pastikan file dan sheet sudah benar.'
                );
            }

            $employeeMap = Employee::whereNotNull('nokom')
                ->select('id', 'nokom', 'nik')
                ->get()
                ->keyBy(fn ($e) => strtoupper(trim($e->nokom)));

            $outletMap = Outlet::select('id', 'name')
                ->get()
                ->keyBy(fn ($o) => strtoupper(trim($o->name)));

            $newCount     = 0;
            $updatedCount = 0;
            $now          = now()->toDateTimeString();
            $rowsToUpsert = [];

            foreach ($parsedRows as $row) {
                $noKompKey = strtoupper(trim($row['no_komp'] ?? ''));
                $outletKey = strtoupper(trim($row['outlet_name'] ?? ''));

                $employee = $employeeMap[$noKompKey] ?? null;
                $outlet   = $outletMap[$outletKey] ?? null;

                $existing = PayrollAnnualSummary::where('no_komp', $row['no_komp'])
                    ->where('tahun', $tahun)
                    ->whereNull('deleted_at')
                    ->exists();
                $existing ? $updatedCount++ : $newCount++;

                $rowsToUpsert[] = [
                    'import_session_id' => $session->id,
                    'employee_id'       => $employee?->id,
                    'outlet_id'         => $outlet?->id,
                    'no_komp'           => $row['no_komp'],
                    'nik'               => $row['nik'] ?: null,
                    'nama'              => $row['nama'],
                    'outlet_name_raw'   => $row['outlet_name'],
                    'posisi'            => $row['posisi'],
                    'join_date'         => $row['join_date'],
                    'tahun'             => $tahun,
                    'gaji_jan'          => $row['gaji']['jan'] ?? null,
                    'gaji_feb'          => $row['gaji']['feb'] ?? null,
                    'gaji_mar'          => $row['gaji']['mar'] ?? null,
                    'gaji_apr'          => $row['gaji']['apr'] ?? null,
                    'gaji_mei'          => $row['gaji']['mei'] ?? null,
                    'gaji_jun'          => $row['gaji']['jun'] ?? null,
                    'gaji_jul'          => $row['gaji']['jul'] ?? null,
                    'gaji_aug'          => $row['gaji']['aug'] ?? null,
                    'gaji_sep'          => $row['gaji']['sep'] ?? null,
                    'gaji_okt'          => $row['gaji']['okt'] ?? null,
                    'gaji_nov'          => $row['gaji']['nov'] ?? null,
                    'gaji_des'          => $row['gaji']['des'] ?? null,
                    'thr'               => $row['thr'],
                    'total_tahunan'     => $row['total'],
                    'bulan_aktif'       => $row['bulan_aktif'],
                    'deleted_at'        => null,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ];
            }

            foreach (array_chunk($rowsToUpsert, 50) as $chunk) {
                PayrollAnnualSummary::upsert(
                    $chunk,
                    ['no_komp', 'tahun'],
                    [
                        'import_session_id', 'employee_id', 'outlet_id', 'nik',
                        'nama', 'outlet_name_raw', 'posisi', 'join_date',
                        'gaji_jan', 'gaji_feb', 'gaji_mar', 'gaji_apr',
                        'gaji_mei', 'gaji_jun', 'gaji_jul', 'gaji_aug',
                        'gaji_sep', 'gaji_okt', 'gaji_nov', 'gaji_des',
                        'thr', 'total_tahunan', 'bulan_aktif',
                        'deleted_at', 'updated_at',
                    ]
                );
            }

            $session->update([
                'total_rows'    => count($parsedRows),
                'new_count'     => $newCount,
                'updated_count' => $updatedCount,
                'status'        => 'completed',
            ]);

            $total = count($parsedRows);
            return redirect()
                ->route('finance.annual-summary.index', ['tahun' => $tahun])
                ->with('success',
                    "Import berhasil! {$total} data karyawan tersimpan " .
                    "({$newCount} baru, {$updatedCount} diperbarui)."
                );

        } catch (\Exception $e) {
            $session->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            return back()->withErrors([
                'summary_file' => 'Gagal memproses file: ' . $e->getMessage(),
            ]);
        }
    }

    public function exportDetail(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        // LANGKAH 1: Override memory untuk export file besar
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '300');

        while (ob_get_level()) {
            ob_end_clean();
        }

        $tahun    = (int) $request->get('tahun', date('Y'));
        $outletId = $request->get('outlet_id') ? (int) $request->get('outlet_id') : null;

        $bulanLabels = [
            1=>'Januari',  2=>'Februari', 3=>'Maret',     4=>'April',
            5=>'Mei',      6=>'Juni',     7=>'Juli',      8=>'Agustus',
            9=>'September',10=>'Oktober', 11=>'November', 12=>'Desember',
        ];

        // Active months dari finance_bpjs_records (sama persis dengan export existing)
        $activeBulans = FinanceBpjsRecord::where('periode', 'like', $tahun . '-%')
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->selectRaw("CAST(SUBSTRING(periode, 6, 2) AS UNSIGNED) as bulan")
            ->distinct()
            ->orderBy('bulan')
            ->pluck('bulan')
            ->map(fn ($b) => (int) $b)
            ->toArray();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        // ── Sheet per bulan (identik dengan export existing) ─────────────────
        foreach ($activeBulans as $bulan) {
            $label   = $bulanLabels[$bulan] ?? "Bulan{$bulan}";
            $periode = sprintf('%04d-%02d', $tahun, $bulan);

            $records = FinanceBpjsRecord::where('periode', $periode)
                ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
                ->orderBy('outlet_name')
                ->orderBy('nama')
                ->get();

            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle(substr($label, 0, 31));

            $headers = [
                'No','No. Komp','NIK','Nama','Posisi','Sub Dept','Kategori','Outlet','Join Date',
                'Gaji Pokok','Attd','HR','S. Expense','OT','Tunjangan','Total',
            ];
            foreach ($headers as $i => $h) {
                $sheet->setCellValue(chr(65 + $i) . '1', $h);
            }
            $lastCol = chr(65 + count($headers) - 1);
            $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
                'font'      => ['bold'=>true,'color'=>['argb'=>'FFFFFFFF']],
                'fill'      => ['fillType'=>'solid','startColor'=>['argb'=>'FF4F46E5']],
                'alignment' => ['horizontal'=>'center'],
            ]);

            $row = 2;
            foreach ($records as $idx => $r) {
                $gajiPokok = (float) ($r->gaji_pokok ?? 0);
                $attd      = (float) ($r->attd      ?? 0);
                $hr        = (float) ($r->hr        ?? 0);
                $sExpense  = (float) ($r->s_expense ?? 0);
                $ot        = (float) ($r->ot1_amount ?? 0) + (float) ($r->ot2_amount ?? 0);
                $tunjangan = (float) ($r->tunjangan_total ?? 0);

                // GUNAKAN $r->total yang sudah benar dari DB (bukan hitung manual 4 komponen)
                $total = (float) ($r->total ?? 0);
                if ($total == 0) {
                    $total = $gajiPokok + $attd + $hr + $sExpense + $ot + $tunjangan;
                }

                $sheet->setCellValue("A{$row}", $idx + 1);
                $sheet->setCellValueExplicit("B{$row}", $r->no_komp ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit("C{$row}", $r->nik ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue("D{$row}", $r->nama);
                $sheet->setCellValue("E{$row}", $r->posisi);
                $sheet->setCellValue("F{$row}", $r->sub_dept);
                $sheet->setCellValue("G{$row}", $r->category);
                $sheet->setCellValue("H{$row}", $r->outlet_name);
                $sheet->setCellValue("I{$row}", $r->join_date_emp
                    ? \Carbon\Carbon::parse($r->join_date_emp)->format('d/m/Y') : '');
                $sheet->setCellValue("J{$row}", $gajiPokok ?: '');
                $sheet->setCellValue("K{$row}", $attd      ?: '');
                $sheet->setCellValue("L{$row}", $hr        ?: '');
                $sheet->setCellValue("M{$row}", $sExpense  ?: '');
                $sheet->setCellValue("N{$row}", $ot        ?: '');
                $sheet->setCellValue("O{$row}", $tunjangan ?: '');
                $sheet->setCellValue("P{$row}", $total     ?: '');
                $row++;
            }

            $sheet->setCellValue("D{$row}", 'TOTAL');
            $sheet->setCellValue("J{$row}", $records->sum(fn ($r) => (float) ($r->gaji_pokok ?? 0)));
            $sheet->setCellValue("K{$row}", $records->sum(fn ($r) => (float) ($r->attd      ?? 0)));
            $sheet->setCellValue("L{$row}", $records->sum(fn ($r) => (float) ($r->hr        ?? 0)));
            $sheet->setCellValue("M{$row}", $records->sum(fn ($r) => (float) ($r->s_expense ?? 0)));
            $sheet->setCellValue("N{$row}", $records->sum(fn ($r) =>
                (float) ($r->ot1_amount ?? 0) + (float) ($r->ot2_amount ?? 0)));
            $sheet->setCellValue("O{$row}", $records->sum(fn ($r) => (float) ($r->tunjangan_total ?? 0)));
            $sheet->setCellValue("P{$row}", $records->sum(function ($r) {
                $t = (float) ($r->total ?? 0);
                if ($t == 0) {
                    $t = (float) ($r->gaji_pokok ?? 0) + (float) ($r->attd ?? 0) +
                        (float) ($r->hr ?? 0) + (float) ($r->s_expense ?? 0) +
                        (float) ($r->ot1_amount ?? 0) + (float) ($r->ot2_amount ?? 0) +
                        (float) ($r->tunjangan_total ?? 0);
                }

                return $t;
            }));
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                'font' => ['bold'=>true],
                'fill' => ['fillType'=>'solid','startColor'=>['argb'=>'FFDBEAFE']],
            ]);

            foreach (range('A', $lastCol) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            foreach (['J','K','L','M','N','O','P'] as $col) {
                $sheet->getStyle("{$col}2:{$col}{$row}")->getNumberFormat()->setFormatCode('#,##0');
            }
        }

        // ── Sheet SUMMARY DETAIL (2-baris header + 6 sub-kolom per bulan) ───
        $periodeList = array_map(fn ($m) => sprintf('%04d-%02d', $tahun, $m), $activeBulans);

        // Ambil semua employee yang ada di finance_bpjs_records untuk periode dan
        // outlet yang difilter — BUKAN dari payroll_annual_summaries yang tidak lengkap
        // (karyawan seperti Septian, Eko, Friska ada di finance_bpjs_records tapi
        // tidak ada di payroll_annual_summaries untuk outlet ini).
        $allRecs = DB::table('finance_bpjs_records as fbr')
            ->leftJoin('employees as e', function ($join) {
                $join->whereRaw('CAST(e.employee_number AS UNSIGNED) = CAST(fbr.no_komp AS UNSIGNED)');
            })
            ->whereIn('fbr.periode', $periodeList)
            ->whereNull('fbr.deleted_at')
            ->when($outletId, fn ($q) => $q->where('fbr.outlet_id', $outletId))
            ->select(
                'fbr.no_komp',
                'fbr.nik as fbr_nik',
                'fbr.nama',
                'fbr.posisi',
                'fbr.outlet_name as outlet_name_raw',
                'fbr.periode',
                'fbr.gaji_pokok',
                'fbr.hr',
                'fbr.total',
                'fbr.raw_csv_json',
                'fbr.s_expense',
                'fbr.bpjs_tk_employee',
                'fbr.bpjs_jkes_employee',
                'e.nik as employee_nik'
            )
            ->get();

        // Bangun daftar employee unik dari $allRecs (bukan dari payroll_annual_summaries)
        $employeeMap = [];
        foreach ($allRecs as $rec) {
            $noKomp = $rec->no_komp;
            if (!isset($employeeMap[$noKomp])) {
                $employeeMap[$noKomp] = (object) [
                    'no_komp'         => $noKomp,
                    'nik'             => $rec->fbr_nik,
                    'employee_nik'    => $rec->employee_nik,
                    'nama'            => $rec->nama,
                    'posisi'          => $rec->posisi,
                    'outlet_name_raw' => $rec->outlet_name_raw,
                ];
            }
        }

        uasort($employeeMap, fn ($a, $b) => strcmp($a->nama, $b->nama));
        $employees = collect(array_values($employeeMap));

        $noKomps = $employees->pluck('no_komp')->filter()->values()->toArray();

        // Total per no_komp dihitung langsung dari $allRecs (sudah terfilter outlet + periode)
        $totalPerNoKomp = $allRecs
            ->groupBy('no_komp')
            ->map(fn ($recs) => $recs->sum(fn ($r) => (float) ($r->total ?? 0)));

        // Lookup [no_komp][periode] = [gaji_pokok, hr, uang_hadir, tunjangan_lk, tunjangan_rnd, rapelan, bpjs_tk, bpjs_kes, total]
        $lookup = [];
        $clean  = fn ($v) => (float) str_replace([',', ' '], '', $v ?? '0');
        foreach ($allRecs as $rec) {
            $raw = json_decode($rec->raw_csv_json ?? '[]', true) ?: [];
            if (!isset($lookup[$rec->no_komp][$rec->periode])) {
                $lookup[$rec->no_komp][$rec->periode] = [0, 0, 0, 0, 0, 0, 0, 0, 0];
            }
            $lookup[$rec->no_komp][$rec->periode][0] += (float) $rec->gaji_pokok;
            $lookup[$rec->no_komp][$rec->periode][1] += (float) $rec->hr;
            $lookup[$rec->no_komp][$rec->periode][2] += $clean($raw[9]  ?? '');
            $lookup[$rec->no_komp][$rec->periode][3] += $clean($raw[28] ?? '');
            $lookup[$rec->no_komp][$rec->periode][4] += $clean($raw[29] ?? '');
            $lookup[$rec->no_komp][$rec->periode][5] += $clean($raw[18] ?? '');           // Rapelan
            $lookup[$rec->no_komp][$rec->periode][6] += (float) ($rec->bpjs_tk_employee  ?? 0); // BPJS TK
            $lookup[$rec->no_komp][$rec->periode][7] += (float) ($rec->bpjs_jkes_employee ?? 0); // BPJS Kes
            $lookup[$rec->no_komp][$rec->periode][8] += (float) ($rec->total ?? 0);
        }

        $sheet     = $spreadsheet->createSheet();
        $sheet->setTitle('SUMMARY DETAIL');

        $subCols   = ['Gaji Pokok','HR','Uang Hadir','Tunjangan LK','Tunjangan RnD','Rapelan','BPJS TK','BPJS Kes','Total'];
        $fixedCols = 6; // No, No.Komp, NIK, Nama, Posisi, Outlet
        $subCount  = count($subCols); // 9

        // Baris 1: 6 kolom tetap + merged header bulan
        $sheet->setCellValue('A1', 'No');
        $sheet->setCellValue('B1', 'No. Komp');
        $sheet->setCellValue('C1', 'NIK');
        $sheet->setCellValue('D1', 'Nama');
        $sheet->setCellValue('E1', 'Posisi');
        $sheet->setCellValue('F1', 'Outlet');

        $colIdx = $fixedCols + 1;
        foreach ($activeBulans as $bulan) {
            $label    = ($bulanLabels[$bulan] ?? "Bulan{$bulan}") . ' ' . $tahun;
            $startCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
            $endCol   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + $subCount - 1);
            $sheet->setCellValue($startCol . '1', $label);
            $sheet->mergeCells($startCol . '1:' . $endCol . '1');
            $sheet->getStyle($startCol . '1:' . $endCol . '1')->applyFromArray([
                'font'      => ['bold'=>true,'color'=>['argb'=>'FFFFFFFF']],
                'fill'      => ['fillType'=>'solid','startColor'=>['argb'=>'FFC0392B']],
                'alignment' => ['horizontal'=>'center','vertical'=>'center'],
                'borders'   => ['allBorders'=>['borderStyle'=>'thin','color'=>['argb'=>'FFFFFFFF']]],
            ]);
            $colIdx += $subCount;
        }

        $totalCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
        $sheet->setCellValue($totalCol . '1', 'Total Tahunan');
        $sheet->mergeCells($totalCol . '1:' . $totalCol . '2');
        $sheet->getStyle($totalCol . '1:' . $totalCol . '2')->applyFromArray([
            'font'      => ['bold'=>true,'color'=>['argb'=>'FFFFFFFF']],
            'fill'      => ['fillType'=>'solid','startColor'=>['argb'=>'FF1A6B3C']],
            'alignment' => ['horizontal'=>'center','vertical'=>'center'],
            'borders'   => ['allBorders'=>['borderStyle'=>'thin','color'=>['argb'=>'FFFFFFFF']]],
        ]);

        // Baris 2: sub-header 6 kolom per bulan; 6 kolom tetap di-merge ke baris 1
        $sheet->mergeCells('A1:A2');
        $sheet->mergeCells('B1:B2');
        $sheet->mergeCells('C1:C2');
        $sheet->mergeCells('D1:D2');
        $sheet->mergeCells('E1:E2');
        $sheet->mergeCells('F1:F2');
        $sheet->getStyle('A1:F2')->applyFromArray([
            'font'      => ['bold'=>true,'color'=>['argb'=>'FFFFFFFF']],
            'fill'      => ['fillType'=>'solid','startColor'=>['argb'=>'FF2E4057']],
            'alignment' => ['horizontal'=>'center','vertical'=>'center'],
            'borders'   => ['allBorders'=>['borderStyle'=>'thin','color'=>['argb'=>'FFFFFFFF']]],
        ]);

        $colIdx = $fixedCols + 1;
        foreach ($activeBulans as $bulan) {
            foreach ($subCols as $sub) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
                $sheet->setCellValue($col . '2', $sub);
                $sheet->getStyle($col . '2')->applyFromArray([
                    'font'      => ['bold'=>true,'color'=>['argb'=>'FFFFFFFF']],
                    'fill'      => ['fillType'=>'solid','startColor'=>['argb'=>'FFE74C3C']],
                    'alignment' => ['horizontal'=>'center'],
                    'borders'   => ['allBorders'=>['borderStyle'=>'thin','color'=>['argb'=>'FFFFFFFF']]],
                ]);
                $colIdx++;
            }
        }

        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->getRowDimension(2)->setRowHeight(18);

        // Data rows mulai baris 3
        $rowNum = 3;
        $no     = 1;
        foreach ($employees as $emp) {
            $sheet->setCellValue('A' . $rowNum, $no);
            $sheet->setCellValueExplicit('B' . $rowNum, $emp->no_komp ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C' . $rowNum, $emp->employee_nik ?? $emp->nik ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue('D' . $rowNum, $emp->nama);
            $sheet->setCellValue('E' . $rowNum, $emp->posisi);
            $sheet->setCellValue('F' . $rowNum, $emp->outlet_name_raw);

            $colIdx = $fixedCols + 1;
            foreach ($activeBulans as $bulan) {
                $periode = sprintf('%04d-%02d', $tahun, $bulan);
                $d       = $lookup[$emp->no_komp][$periode] ?? null;
                $vals    = $d ?? ['', '', '', '', '', '', '', '', ''];

                foreach ($vals as $vi => $v) {
                    $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
                    $sheet->setCellValue($col . $rowNum, ($v !== '' && $v != 0) ? (float) $v : '');
                    if ($v !== '' && $v != 0) {
                        $sheet->getStyle($col . $rowNum)
                            ->getNumberFormat()
                            ->setFormatCode($vi === 1 ? '#,##0.00' : '#,##0');
                    }
                    $colIdx++;
                }
            }

            $totalTahunan = (float) ($totalPerNoKomp[$emp->no_komp] ?? 0);
            $sheet->setCellValue($totalCol . $rowNum, $totalTahunan);
            $sheet->getStyle($totalCol . $rowNum)->getNumberFormat()->setFormatCode('#,##0');

            $rowNum++;
            $no++;
        }

        // LANGKAH 3: Apply borders + zebra fill setelah semua data ditulis (lebih hemat memori)
        $lastDataRow = $rowNum - 1;
        if ($lastDataRow >= 3) {
            $sheet->getStyle('A3:' . $totalCol . $lastDataRow)
                ->getBorders()->getAllBorders()
                ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
                ->getColor()->setARGB('FFDDDDDD');
            for ($r = 3; $r <= $lastDataRow; $r++) {
                $fillArgb = ($r % 2 === 1) ? 'FFFFFFFF' : 'FFF2F2F2';
                $sheet->getStyle('A' . $r . ':' . $totalCol . $r)
                    ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB($fillArgb);
            }
        }

        // ── SECTION 1: GRAND TOTAL ──────────────────────────────────────────
        $grandTotalRow = $rowNum + 1;

        // Compute grand totals per periode from existing $lookup (no extra DB query)
        $grandByPeriode = array_fill_keys($periodeList, [0, 0, 0, 0, 0, 0, 0, 0, 0]);
        foreach ($lookup as $noKomp => $periods) {
            foreach ($periods as $p => $vals) {
                if (isset($grandByPeriode[$p])) {
                    foreach ($vals as $i => $v) {
                        $grandByPeriode[$p][$i] += $v;
                    }
                }
            }
        }

        $sheet->setCellValue('A' . $grandTotalRow, 'GRAND TOTAL');
        $sheet->mergeCells('A' . $grandTotalRow . ':F' . $grandTotalRow);
        $sheet->getStyle('A' . $grandTotalRow . ':' . $totalCol . $grandTotalRow)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF1A3C6E']],
            'alignment' => ['horizontal' => 'center'],
        ]);
        $colIdx = $fixedCols + 1;
        foreach ($activeBulans as $bulan) {
            $p    = sprintf('%04d-%02d', $tahun, $bulan);
            $vals = $grandByPeriode[$p] ?? [0, 0, 0, 0, 0, 0, 0, 0, 0];
            foreach ($vals as $vi => $v) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
                if ($v) {
                    $sheet->setCellValue($col . $grandTotalRow, (float) $v);
                    $sheet->getStyle($col . $grandTotalRow)->getNumberFormat()
                        ->setFormatCode($vi === 1 ? '#,##0.00' : '#,##0');
                }
                $colIdx++;
            }
        }
        $grandTahunan = $employees->sum(fn ($emp) => (float) ($totalPerNoKomp[$emp->no_komp] ?? 0));
        if ($grandTahunan) {
            $sheet->setCellValue($totalCol . $grandTotalRow, (float) $grandTahunan);
            $sheet->getStyle($totalCol . $grandTotalRow)->getNumberFormat()->setFormatCode('#,##0');
        }

        // ── SECTION 2: RINGKASAN PER KATEGORI ───────────────────────────────
        $numBulan     = count($activeBulans);
        $katTitleRow  = $grandTotalRow + 3;
        $katHeaderRow = $katTitleRow + 1;
        $katLastCol   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(1 + $numBulan + 1);

        $sheet->setCellValue('A' . $katTitleRow, 'RINGKASAN GAJI PER KATEGORI');
        $sheet->mergeCells('A' . $katTitleRow . ':' . $katLastCol . $katTitleRow);
        $sheet->getStyle('A' . $katTitleRow)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FF1E40AF']],
            'fill'      => ['fillType' => 'solid', 'startColor' => ['argb' => 'FFE0E7FF']],
            'alignment' => ['horizontal' => 'center'],
        ]);

        $bulanLabelShort = [
            '01'=>'Jan','02'=>'Feb','03'=>'Mar','04'=>'Apr','05'=>'Mei',
            '06'=>'Jun','07'=>'Jul','08'=>'Aug','09'=>'Sep','10'=>'Okt',
            '11'=>'Nov','12'=>'Des',
        ];
        $sheet->setCellValue('A' . $katHeaderRow, 'KATEGORI');
        $colIdxK = 2;
        foreach ($periodeList as $p) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdxK);
            $sheet->setCellValue($col . $katHeaderRow, $bulanLabelShort[substr($p, 5, 2)] ?? $p);
            $colIdxK++;
        }
        $katGrandTotalCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdxK);
        $sheet->setCellValue($katGrandTotalCol . $katHeaderRow, 'TOTAL');
        $sheet->getStyle('A' . $katHeaderRow . ':' . $katLastCol . $katHeaderRow)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF1E40AF']],
            'alignment' => ['horizontal' => 'center'],
        ]);

        $katRawData = DB::table('finance_bpjs_records')
            ->whereIn('periode', $periodeList)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->whereNull('deleted_at')
            ->select('category', 'periode', DB::raw('SUM(total) as total_sum'))
            ->groupBy('category', 'periode')
            ->get();

        $katLookup = [];
        foreach ($katRawData as $k) {
            $kat = $k->category ?: 'lainnya';
            $katLookup[$kat][$k->periode] = ($katLookup[$kat][$k->periode] ?? 0) + (float) $k->total_sum;
        }

        $catDefinitions = [
            'bar'     => ['label' => 'BAR',     'argb' => 'FFFED7AA'],
            'kitchen' => ['label' => 'KITCHEN', 'argb' => 'FFFEF08A'],
            'service' => ['label' => 'SERVICE', 'argb' => 'FFBAE6FD'],
            'office'  => ['label' => 'OFFICE',  'argb' => 'FFC7D2FE'],
            'prive'   => ['label' => 'PRIVE',   'argb' => 'FFE9D5FF'],
            'lainnya' => ['label' => 'LAINNYA', 'argb' => 'FFF3F4F6'],
        ];

        $katDataRow = $katHeaderRow + 1;
        foreach ($catDefinitions as $catKey => $catMeta) {
            if (!isset($katLookup[$catKey])) {
                continue;
            }
            $sheet->setCellValue('A' . $katDataRow, $catMeta['label']);
            $sheet->getStyle('A' . $katDataRow)->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => $catMeta['argb']]],
            ]);
            $colIdxK  = 2;
            $rowTotal = 0;
            foreach ($periodeList as $p) {
                $val = $katLookup[$catKey][$p] ?? 0;
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdxK);
                if ($val) {
                    $sheet->setCellValue($col . $katDataRow, $val);
                    $sheet->getStyle($col . $katDataRow)->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle($col . $katDataRow)->getFill()
                        ->setFillType('solid')->getStartColor()->setARGB($catMeta['argb']);
                }
                $rowTotal += $val;
                $colIdxK++;
            }
            $katGrandTotalCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdxK);
            if ($rowTotal) {
                $sheet->setCellValue($katGrandTotalCol . $katDataRow, $rowTotal);
                $sheet->getStyle($katGrandTotalCol . $katDataRow)->applyFromArray([
                    'font'         => ['bold' => true],
                    'fill'         => ['fillType' => 'solid', 'startColor' => ['argb' => $catMeta['argb']]],
                    'numberFormat' => ['formatCode' => '#,##0'],
                ]);
            }
            $katDataRow++;
        }

        // Grand total semua kategori
        $sheet->setCellValue('A' . $katDataRow, 'TOTAL');
        $sheet->getStyle('A' . $katDataRow)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF059669']],
        ]);
        $colIdxK    = 2;
        $grandKatSum = 0;
        foreach ($periodeList as $p) {
            $colSum = 0;
            foreach (array_keys($catDefinitions) as $catKey) {
                $colSum += $katLookup[$catKey][$p] ?? 0;
            }
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdxK);
            if ($colSum) {
                $sheet->setCellValue($col . $katDataRow, $colSum);
                $sheet->getStyle($col . $katDataRow)->applyFromArray([
                    'font'         => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill'         => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF059669']],
                    'numberFormat' => ['formatCode' => '#,##0'],
                ]);
            }
            $grandKatSum += $colSum;
            $colIdxK++;
        }
        $katGrandTotalCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdxK);
        if ($grandKatSum) {
            $sheet->setCellValue($katGrandTotalCol . $katDataRow, $grandKatSum);
            $sheet->getStyle($katGrandTotalCol . $katDataRow)->applyFromArray([
                'font'         => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill'         => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF059669']],
                'numberFormat' => ['formatCode' => '#,##0'],
            ]);
        }

        // ── SECTION 3: RINGKASAN PER OUTLET x KATEGORI x BULAN ──────────────
        $outletTotalCols = 2 + $numBulan + 1;
        $outletLastCol   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($outletTotalCols);

        $outletSectionRow = $katDataRow + 3;
        $sheet->setCellValue('A' . $outletSectionRow,
            'RINGKASAN GAJI PER OUTLET PER KATEGORI PER BULAN');
        $sheet->mergeCells('A' . $outletSectionRow . ':' . $outletLastCol . $outletSectionRow);
        $sheet->getStyle('A' . $outletSectionRow)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FF1E40AF']],
            'fill'      => ['fillType' => 'solid', 'startColor' => ['argb' => 'FFE0E7FF']],
            'alignment' => ['horizontal' => 'center'],
        ]);

        $outletRawData = DB::table('finance_bpjs_records')
            ->whereIn('periode', $periodeList)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->whereNotNull('outlet_name')->where('outlet_name', '!=', '')
            ->whereNotNull('category')->where('category', '!=', '')
            ->whereNull('deleted_at')
            ->select('outlet_name', 'category', 'periode', DB::raw('SUM(total) as sum_total'))
            ->groupBy('outlet_name', 'category', 'periode')
            ->orderBy('outlet_name')
            ->get();

        $outletPivot = [];
        $allOutlets  = [];
        foreach ($outletRawData as $r) {
            $m = (int) explode('-', $r->periode)[1];
            $outletPivot[$r->outlet_name][$m][$r->category] =
                ($outletPivot[$r->outlet_name][$m][$r->category] ?? 0) + $r->sum_total;
            $allOutlets[$r->outlet_name] = true;
        }
        ksort($allOutlets);

        $categories = ['bar', 'kitchen', 'service', 'office', 'prive', 'lainnya'];
        $catLabels  = ['BAR', 'KITCHEN', 'SERVICE', 'OFFICE', 'PRIVE', 'LAINNYA'];
        $catColors  = [
            'bar'     => 'FFFED7AA', 'kitchen' => 'FFFEF08A',
            'service' => 'FFBAE6FD', 'office'  => 'FFC7D2FE',
            'prive'   => 'FFE9D5FF', 'lainnya' => 'FFF3F4F6',
        ];

        $currentRow = $outletSectionRow + 2;
        foreach (array_keys($allOutlets) as $outletName) {
            // Header nama outlet
            $sheet->setCellValue('A' . $currentRow, $outletName);
            $sheet->mergeCells('A' . $currentRow . ':' . $outletLastCol . $currentRow);
            $sheet->getStyle('A' . $currentRow)->applyFromArray([
                'font'      => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FFFFFFFF']],
                'fill'      => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF1E3A5F']],
                'alignment' => ['horizontal' => 'left', 'vertical' => 'center'],
            ]);
            $sheet->getRowDimension($currentRow)->setRowHeight(20);
            $currentRow++;

            // Header kolom: NO. | KATEGORI | Bulan... | TOTAL
            $sheet->setCellValue('A' . $currentRow, 'NO.');
            $sheet->setCellValue('B' . $currentRow, 'KATEGORI');
            $colIdxO = 3;
            foreach ($activeBulans as $m) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdxO);
                $sheet->setCellValue($col . $currentRow,
                    strtoupper(mb_substr($bulanLabels[$m] ?? "Bln{$m}", 0, 3)));
                $colIdxO++;
            }
            $sheet->setCellValue(
                \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdxO) . $currentRow,
                'TOTAL'
            );
            $sheet->getStyle('A' . $currentRow . ':' . $outletLastCol . $currentRow)->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill'      => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF374151']],
                'alignment' => ['horizontal' => 'center'],
            ]);
            $currentRow++;

            // Baris per kategori
            $colTotals  = array_fill(0, $numBulan, 0.0);
            $grandTotal = 0.0;
            foreach ($categories as $catIdx => $cat) {
                $sheet->setCellValue('A' . $currentRow, $catIdx + 1);
                $sheet->setCellValue('B' . $currentRow, $catLabels[$catIdx]);
                $sheet->getStyle('A' . $currentRow . ':B' . $currentRow)->applyFromArray([
                    'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => $catColors[$cat]]],
                ]);
                $sheet->getStyle('B' . $currentRow)->getFont()->setBold(true);

                $colIdxO  = 3;
                $rowTotal = 0.0;
                foreach ($activeBulans as $bulanIdx => $m) {
                    $val = $outletPivot[$outletName][$m][$cat] ?? 0;
                    if ($val > 0) {
                        $cl = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdxO);
                        $sheet->setCellValue($cl . $currentRow, $val);
                        $sheet->getStyle($cl . $currentRow)->getNumberFormat()->setFormatCode('#,##0');
                    }
                    $rowTotal             += $val;
                    $colTotals[$bulanIdx] += $val;
                    $colIdxO++;
                }
                $totalCl = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdxO);
                if ($rowTotal > 0) {
                    $sheet->setCellValue($totalCl . $currentRow, $rowTotal);
                    $sheet->getStyle($totalCl . $currentRow)->applyFromArray([
                        'font'         => ['bold' => true],
                        'numberFormat' => ['formatCode' => '#,##0'],
                    ]);
                }
                $grandTotal += $rowTotal;
                $currentRow++;
            }

            // Baris TOTAL per outlet
            $sheet->setCellValue('B' . $currentRow, 'TOTAL');
            $sheet->getStyle('A' . $currentRow . ':B' . $currentRow)->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FFFFF3CD']],
            ]);
            $colIdxO = 3;
            foreach ($activeBulans as $bulanIdx => $m) {
                $val = $colTotals[$bulanIdx];
                if ($val > 0) {
                    $cl = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdxO);
                    $sheet->setCellValue($cl . $currentRow, $val);
                    $sheet->getStyle($cl . $currentRow)->applyFromArray([
                        'font'         => ['bold' => true],
                        'fill'         => ['fillType' => 'solid', 'startColor' => ['argb' => 'FFFFF3CD']],
                        'numberFormat' => ['formatCode' => '#,##0'],
                    ]);
                }
                $colIdxO++;
            }
            $totalCl = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdxO);
            $sheet->setCellValue($totalCl . $currentRow, $grandTotal);
            $sheet->getStyle($totalCl . $currentRow)->applyFromArray([
                'font'         => ['bold' => true, 'size' => 11],
                'fill'         => ['fillType' => 'solid', 'startColor' => ['argb' => 'FFFBBF24']],
                'numberFormat' => ['formatCode' => '#,##0'],
            ]);
            $currentRow += 3;
        }

        // Catatan known limitation: karyawan yang belum ter-import payroll-nya untuk
        // outlet ini (mis. belum ada baris finance_bpjs_records dengan outlet_id terkait)
        // tidak akan muncul di sheet ini walau terdaftar di outlet tersebut.
        $currentRow++;
        $sheet->setCellValue('A' . $currentRow,
            'CATATAN: Karyawan yang belum di-import payroll-nya untuk outlet ini tidak akan muncul di sheet ini. '
            . 'Jika ada karyawan yang seharusnya ada namun tidak tampil, lakukan re-import payroll untuk outlet tersebut.');
        $sheet->getStyle('A' . $currentRow)->applyFromArray([
            'font' => ['italic' => true, 'size' => 9, 'color' => ['argb' => 'FF6B7280']],
        ]);
        $currentRow++;

        // Lebar kolom
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(10);
        $sheet->getColumnDimension('C')->setWidth(16);
        $sheet->getColumnDimension('D')->setWidth(28);
        $sheet->getColumnDimension('E')->setWidth(22);
        $sheet->getColumnDimension('F')->setWidth(24);
        $totalDataCols = count($activeBulans) * $subCount + 1;
        for ($i = 0; $i < $totalDataCols; $i++) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($fixedCols + 1 + $i);
            $sheet->getColumnDimension($col)->setWidth(14);
        }

        $sheet->freezePane('G3');

        // Output — sama dengan export existing: temp file + response()->download()
        $filename = 'Annual_Detail_' . $tahun . '.xlsx';
        $writer   = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'omeo_detail_') . '.xlsx';
        $writer->save($tempFile);

        return response()->download(
            $tempFile,
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function detailByOutlet(Request $request, string $noKomp, string $periode): \Illuminate\Http\JsonResponse
    {
        $records = FinanceBpjsRecord::where('no_komp', $noKomp)
            ->where('periode', $periode)
            ->orderByDesc('total')
            ->get();

        $grandTotal = (float) $records->sum('total');

        return response()->json([
            'no_komp'     => $noKomp,
            'periode'     => $periode,
            'records'     => $records->map(fn ($r) => [
                'outlet_name' => $r->outlet_name,
                'posisi'      => $r->posisi,
                'gaji_pokok'  => (float) $r->gaji_pokok,
                'total'       => (float) $r->total,
            ]),
            'grand_total' => $grandTotal,
        ]);
    }

    private function bulanToColumn(int $bulan): string
    {
        return [
            1  => 'gaji_jan',  2 => 'gaji_feb',  3 => 'gaji_mar',  4 => 'gaji_apr',
            5  => 'gaji_mei',  6 => 'gaji_jun',  7 => 'gaji_jul',  8 => 'gaji_aug',
            9  => 'gaji_sep', 10 => 'gaji_okt', 11 => 'gaji_nov', 12 => 'gaji_des',
        ][$bulan] ?? 'gaji_jan';
    }

    private function getPerPage(int $default = 50): int
    {
        $perPage = (int) request('per_page', $default);
        return in_array($perPage, [20, 50, 100, 200, 9999]) ? $perPage : $default;
    }
}
