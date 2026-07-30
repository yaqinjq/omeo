<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PayrollBpjsReconciliationController extends Controller
{
    // ── Public actions ────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        [$result, $stats, $meta] = $this->resolveReconData($request);

        return view('finance.payroll_bpjs_reconciliation.index', array_merge($meta, [
            'result' => $result,
            'stats'  => $stats,
        ]));
    }

    public function export(Request $request)
    {
        [$result] = $this->resolveReconData($request);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekonsiliasi BPJS-Payroll');

        $headers = [
            'No', 'NIK', 'Nama (BPJS)', 'Nama (Payroll)',
            'No. Komp', 'Outlet', 'PT BPJS (NPP)', 'File BPJS',
            'Iuran BPJS', 'Iuran Payroll', 'Selisih', 'Status', 'Match Via',
        ];

        foreach ($headers as $i => $h) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($col . '1', $h);
            $sheet->getStyle($col . '1')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1a3c6e']],
            ]);
        }

        $statusLabels = [
            'cocok'               => 'Cocok',
            'minor'               => 'Selisih Minor',
            'selisih'             => 'Selisih Signifikan',
            'tidak_di_payroll'    => 'Tidak di Payroll',
            'tidak_di_bpjs'       => 'Tidak di BPJS',
            'nik_tidak_ditemukan' => 'NIK Tidak di OMEO',
            'no_nik'              => 'Tanpa NIK di PDF',
        ];

        $bgColors = [
            'cocok'               => 'E8F5E9',
            'minor'               => 'FFF9C4',
            'selisih'             => 'FFEBEE',
            'tidak_di_payroll'    => 'FFF3E0',
            'tidak_di_bpjs'       => 'F3E5F5',
            'nik_tidak_ditemukan' => 'E0F7FA',
            'no_nik'              => 'F3F4F6',
        ];

        foreach ($result as $i => $row) {
            $r = $i + 2;
            $vals = [
                $i + 1, $row->nik, $row->nama_bpjs, $row->nama_payroll,
                $row->no_komp, $row->outlet, $row->pt_bpjs, $row->file_bpjs,
                $row->iuran_bpjs, $row->iuran_payroll, $row->selisih,
                $statusLabels[$row->status_rekon] ?? $row->status_rekon,
                $row->match_via ?? '-',
            ];

            foreach ($vals as $ci => $v) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 1);
                $sheet->setCellValue($col . $r, $v);
                if (in_array($ci, [8, 9, 10])) {
                    $sheet->getStyle($col . $r)->getNumberFormat()->setFormatCode('#,##0');
                }
            }

            $bg = $bgColors[$row->status_rekon] ?? 'FFFFFF';
            $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($vals));
            $sheet->getStyle('A' . $r . ':' . $lastCol . $r)
                ->getFill()->setFillType('solid')
                ->getStartColor()->setRGB($bg);
        }

        foreach (range(1, count($headers)) as $ci) {
            $sheet->getColumnDimension(
                \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci)
            )->setAutoSize(true);
        }

        while (ob_get_level()) {
            ob_end_clean();
        }

        $tmp = tempnam(sys_get_temp_dir(), 'rekon_');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($tmp);

        $periode = $request->get('periode', 'export');

        return response()->download(
            $tmp,
            'Rekonsiliasi_BPJS_Payroll_' . $periode . '_' . date('YmdHis') . '.xlsx'
        )->deleteFileAfterSend(true);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Core reconciliation logic shared by index() and export().
     * Returns [$result, $stats, $meta].
     */
    private function resolveReconData(Request $request): array
    {
        $tahun    = $request->get('tahun', date('Y'));
        $periode  = $request->get('periode');
        $ptFilter = $request->get('pt');
        $status   = $request->get('status');

        $periodeList = Schema::hasTable('finance_bpjs_records')
            ? DB::table('finance_bpjs_records')
                ->where('periode', 'like', $tahun . '-%')
                ->whereNull('deleted_at')
                ->select('periode')
                ->distinct()
                ->orderBy('periode')
                ->pluck('periode')
            : collect();

        $ptList = Schema::hasTable('bpjs_official_bills')
            ? DB::table('bpjs_official_bills')
                ->whereNotNull('npp')
                ->select('npp', 'source_file_name', 'periode as bill_periode', 'id')
                ->orderByDesc('created_at')
                ->get()
            : collect();

        $result = collect();
        $stats  = [];

        if ($periode) {
            [$result, $stats] = $this->buildRecon($periode, $ptFilter, $status);
        }

        $bulanLabel = [
            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
            '04' => 'April',   '05' => 'Mei',       '06' => 'Juni',
            '07' => 'Juli',    '08' => 'Agustus',   '09' => 'September',
            '10' => 'Oktober', '11' => 'November',  '12' => 'Desember',
        ];

        $meta = compact('periodeList', 'ptList', 'periode', 'tahun', 'ptFilter', 'status', 'bulanLabel');

        return [$result, $stats, $meta];
    }

    /**
     * Build merged reconciliation collection for a given periode.
     * Returns [$result, $stats].
     */
    private function buildRecon(string $periode, ?string $ptFilter, ?string $statusFilter): array
    {
        if (! Schema::hasTable('finance_bpjs_records') || ! Schema::hasTable('bpjs_official_bills')) {
            return [collect(), []];
        }

        // ── Payroll data ──────────────────────────────────────────────────────
        $payrollData = DB::table('finance_bpjs_records')
            ->where('periode', $periode)
            ->whereNull('deleted_at')
            ->select(
                'no_komp', 'nama', 'outlet_name',
                DB::raw('SUM(bpjs_tk_employee + bpjs_jkes_employee) as total_payroll'),
                DB::raw('SUM(bpjs_tk_employee) as bpjs_tk'),
                DB::raw('SUM(bpjs_jkes_employee) as bpjs_kes')
            )
            ->groupBy('no_komp', 'nama', 'outlet_name')
            ->get()
            ->keyBy('no_komp');

        // ── BPJS bill rows ────────────────────────────────────────────────────
        $billQuery = DB::table('bpjs_official_bills')
            ->where('periode', $periode)
            ->whereNull('deleted_at');

        if ($ptFilter) {
            $billQuery->where('npp', $ptFilter);
        }

        $bills = $billQuery->get(['id', 'npp', 'source_file_name']);

        if ($bills->isEmpty()) {
            // Only payroll side — all rows go into "tidak_di_bpjs"
            $merged = $payrollData->map(fn ($p, $noKomp) => (object) [
                'sumber'        => 'payroll',
                'nik'           => '-',
                'nik_clean'     => '',
                'nama_bpjs'     => '-',
                'nama_payroll'  => $p->nama,
                'no_komp'       => $noKomp,
                'outlet'        => $p->outlet_name,
                'pt_bpjs'       => '-',
                'file_bpjs'     => '-',
                'iuran_bpjs'    => 0.0,
                'iuran_payroll' => (float) $p->total_payroll,
                'selisih'       => -(float) $p->total_payroll,
                'match_via'     => 'not_matched',
                'status_rekon'  => 'tidak_di_bpjs',
            ])->values();

            $stats = $this->computeStats($merged);
            if ($statusFilter) {
                $merged = $merged->where('status_rekon', $statusFilter)->values();
            }

            return [$merged, $stats];
        }

        $billRows = collect();
        foreach ($bills as $bill) {
            $rows = DB::table('bpjs_official_bill_rows')
                ->where('bill_id', $bill->id)
                ->get(['id', 'nik', 'nama', 'total_iuran', 'match_status', 'employee_id', 'match_confidence']);

            foreach ($rows as $row) {
                $row->bill_npp       = $bill->npp;
                $row->bill_file_name = $bill->source_file_name;
                $billRows->push($row);
            }
        }

        // ── NIK → employee lookup ─────────────────────────────────────────────
        $empByNik = [];
        if (Schema::hasTable('employees')) {
            DB::table('employees')
                ->whereNotNull('nik')
                ->whereNotNull('employee_number')
                ->where('employee_number', '!=', '')
                ->get(['id', 'nik', 'full_name', 'employee_number'])
                ->each(function ($e) use (&$empByNik) {
                    $key = preg_replace('/[\s\-]/', '', (string) $e->nik);
                    if ($key !== '') {
                        $empByNik[$key] = $e;
                    }
                });
        }

        // ── Merge (NIK-only matching) ─────────────────────────────────────────
        $merged      = collect();
        $seenNoKomps = [];

        foreach ($billRows as $brow) {
            $noKomp   = null;
            $payroll  = null;
            $matchVia = null;
            $emp      = null;

            $nikRaw   = (string) ($brow->nik ?? '');
            $nikClean = preg_replace('/[\s\-]/', '', $nikRaw);

            if ($nikClean !== '') {
                $emp = $empByNik[$nikClean] ?? null;

                if ($emp) {
                    $noKomp   = ltrim((string) $emp->employee_number, '0');
                    $matchVia = 'nik';
                    $payroll  = $payrollData->get($noKomp);
                }
            }

            $iuranBpjs    = (float) ($brow->total_iuran ?? 0);
            $iuranPayroll = $payroll ? (float) $payroll->total_payroll : 0.0;
            $selisih      = $iuranBpjs - $iuranPayroll;

            $rekonStatus = match (true) {
                $nikClean === ''         => 'no_nik',
                $emp === null            => 'nik_tidak_ditemukan',
                $payroll === null        => 'tidak_di_payroll',
                abs($selisih) < 1       => 'cocok',
                abs($selisih) <= 10_000 => 'minor',
                default                 => 'selisih',
            };

            $merged->push((object) [
                'sumber'        => 'bpjs',
                'nik'           => $nikRaw !== '' ? $nikRaw : '-',
                'nik_clean'     => $nikClean,
                'nama_bpjs'     => $brow->nama,
                'nama_payroll'  => $payroll->nama ?? ($emp ? $emp->full_name : '-'),
                'no_komp'       => $noKomp ?? '-',
                'outlet'        => $payroll->outlet_name ?? '-',
                'pt_bpjs'       => $brow->bill_npp ?? '-',
                'file_bpjs'     => $brow->bill_file_name ?? '-',
                'iuran_bpjs'    => $iuranBpjs,
                'iuran_payroll' => $iuranPayroll,
                'selisih'       => $selisih,
                'match_via'     => $matchVia ?? 'not_matched',
                'status_rekon'  => $rekonStatus,
            ]);

            if ($noKomp) {
                $seenNoKomps[] = $noKomp;
            }
        }

        // Employees in payroll but not in any BPJS bill
        foreach ($payrollData as $noKomp => $prow) {
            if (in_array($noKomp, $seenNoKomps, true)) {
                continue;
            }

            $merged->push((object) [
                'sumber'        => 'payroll',
                'nik'           => '-',
                'nik_clean'     => '',
                'nama_bpjs'     => '-',
                'nama_payroll'  => $prow->nama,
                'no_komp'       => $noKomp,
                'outlet'        => $prow->outlet_name,
                'pt_bpjs'       => '-',
                'file_bpjs'     => '-',
                'iuran_bpjs'    => 0.0,
                'iuran_payroll' => (float) $prow->total_payroll,
                'selisih'       => -(float) $prow->total_payroll,
                'match_via'     => 'not_matched',
                'status_rekon'  => 'tidak_di_bpjs',
            ]);
        }

        // ── Stats before status filter ─────────────────────────────────────────
        $stats = $this->computeStats($merged);

        // ── Apply status filter & sort ────────────────────────────────────────
        if ($statusFilter) {
            $merged = $merged->where('status_rekon', $statusFilter);
        }

        $result = $merged->sortByDesc(fn ($r) => abs($r->selisih))->values();

        return [$result, $stats];
    }

    private function computeStats(Collection $merged): array
    {
        return [
            'total'               => $merged->count(),
            'cocok'               => $merged->where('status_rekon', 'cocok')->count(),
            'minor'               => $merged->where('status_rekon', 'minor')->count(),
            'selisih'             => $merged->where('status_rekon', 'selisih')->count(),
            'tidak_di_payroll'    => $merged->where('status_rekon', 'tidak_di_payroll')->count(),
            'tidak_di_bpjs'       => $merged->where('status_rekon', 'tidak_di_bpjs')->count(),
            'nik_tidak_ditemukan' => $merged->where('status_rekon', 'nik_tidak_ditemukan')->count(),
            'no_nik'              => $merged->where('status_rekon', 'no_nik')->count(),
            'total_iuran_bpjs'    => $merged->sum('iuran_bpjs'),
            'total_iuran_pay'     => $merged->sum('iuran_payroll'),
            'total_selisih'       => $merged->sum('selisih'),
        ];
    }
}
