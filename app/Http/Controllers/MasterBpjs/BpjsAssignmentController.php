<?php

namespace App\Http\Controllers\MasterBpjs;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeBpjsAssignment;
use App\Models\LegalEntity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;

class BpjsAssignmentController extends Controller
{
    // ═══ HALAMAN 1: MASTER ASSIGNMENT ═══

    public function index(Request $request)
    {
        $legalEntities = LegalEntity::where('is_active', true)->orderBy('name')->get();

        $query = EmployeeBpjsAssignment::with([
            'employee:id,full_name,nik,employee_number,nokom',
            'legalEntity:id,name,short_name',
        ])->latest('effective_date');

        if ($request->filled('legal_entity_id')) {
            $query->where('legal_entity_id', $request->legal_entity_id);
        }

        if ($request->input('status') === 'active') {
            $query->active();
        } elseif ($request->input('status') === 'ended') {
            $query->ended();
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('full_name', 'LIKE', "%{$search}%")
                  ->orWhere('nik', 'LIKE', "%{$search}%")
                  ->orWhere('employee_number', 'LIKE', "%{$search}%")
                  ->orWhere('nokom', 'LIKE', "%{$search}%");
            });
        }

        $assignments = $query->paginate(25)->withQueryString();

        return view('finance.bpjs_assignments.index', compact(
            'assignments', 'legalEntities'
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id'     => 'required|integer|exists:employees,id',
            'legal_entity_id' => 'required|integer|exists:legal_entities,id',
            'effective_date'  => 'required|date',
            'end_date'        => 'nullable|date|after_or_equal:effective_date',
            'reason'          => 'required|in:join,transfer,resign',
            'notes'           => 'nullable|string|max:255',
        ]);

        EmployeeBpjsAssignment::create([
            ...$data,
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'Assignment BPJS berhasil ditambahkan.');
    }

    public function update(Request $request, EmployeeBpjsAssignment $assignment)
    {
        $data = $request->validate([
            'legal_entity_id' => 'required|integer|exists:legal_entities,id',
            'effective_date'  => 'required|date',
            'end_date'        => 'nullable|date|after_or_equal:effective_date',
            'reason'          => 'required|in:join,transfer,resign',
            'notes'           => 'nullable|string|max:255',
        ]);

        $assignment->update($data);

        return back()->with('success', 'Assignment BPJS berhasil diupdate.');
    }

    public function destroy(EmployeeBpjsAssignment $assignment)
    {
        $assignment->delete();
        return back()->with('success', 'Assignment BPJS berhasil dihapus.');
    }

    // ═══ HALAMAN 2: IMPORT EXCEL ═══

    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Import');

        $headers = [
            'A1' => 'employee_number (No. Karyawan/Nokom)',
            'B1' => 'nama_karyawan (referensi saja)',
            'C1' => 'nama_pt_bpjs (sesuai master PT aktif)',
            'D1' => 'effective_date (YYYY-MM-DD)',
            'E1' => 'end_date (YYYY-MM-DD, boleh kosong)',
            'F1' => 'reason (join / transfer / resign)',
            'G1' => 'notes',
        ];

        foreach ($headers as $cell => $val) {
            $sheet->setCellValue($cell, $val);
        }

        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
        $sheet->getStyle('A1:G1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('7C3AED');
        $sheet->getStyle('A1:G1')->getFont()->getColor()->setRGB('FFFFFF');

        // Contoh baris
        $sheet->setCellValue('A2', '001234');
        $sheet->setCellValue('B2', 'NAMA KARYAWAN CONTOH');
        $sheet->setCellValue('C2', 'PT Makmur Ramenjaya Mandiri');
        $sheet->setCellValue('D2', now()->format('Y-m-d'));
        $sheet->setCellValue('F2', 'join');

        // Sheet 2: Daftar PT valid
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Daftar PT');
        $sheet2->setCellValue('A1', 'ID');
        $sheet2->setCellValue('B1', 'Nama PT');
        $sheet2->setCellValue('C1', 'Short Name');
        $sheet2->getStyle('A1:C1')->getFont()->setBold(true);

        $pts = LegalEntity::where('is_active', true)->orderBy('name')->get();
        $row = 2;
        foreach ($pts as $pt) {
            $sheet2->setCellValue("A{$row}", $pt->id);
            $sheet2->setCellValue("B{$row}", $pt->name);
            $sheet2->setCellValue("C{$row}", $pt->short_name ?? '');
            $row++;
        }

        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet2->getColumnDimension('A')->setWidth(8);
        $sheet2->getColumnDimension('B')->setWidth(40);
        $sheet2->getColumnDimension('C')->setWidth(20);

        $spreadsheet->setActiveSheetIndex(0);

        $writer  = new XlsxWriter($spreadsheet);
        $tmpFile = tempnam(sys_get_temp_dir(), 'bpjs_assign_tmpl_');
        $writer->save($tmpFile);

        return response()->download(
            $tmpFile,
            'Template_Import_BPJS_Assignment.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:5120',
        ]);

        set_time_limit(120);

        $reader = new XlsxReader();
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($request->file('file')->getPathname());
        $sheet = $spreadsheet->getActiveSheet();

        // Buat lookup PT nama → id (case-insensitive)
        $legalEntities = LegalEntity::where('is_active', true)
            ->pluck('id', 'name')
            ->mapWithKeys(fn($id, $name) => [strtolower(trim($name)) => $id]);

        $success = 0;
        $errors  = [];
        $row     = 2;

        while ($sheet->getCellByColumnAndRow(1, $row)->getValue() !== null
               && $sheet->getCellByColumnAndRow(1, $row)->getValue() !== '') {

            $empNo   = trim((string) $sheet->getCellByColumnAndRow(1, $row)->getValue());
            $ptName  = trim((string) $sheet->getCellByColumnAndRow(3, $row)->getValue());
            $effDate = trim((string) $sheet->getCellByColumnAndRow(4, $row)->getValue());
            $endDate = trim((string) $sheet->getCellByColumnAndRow(5, $row)->getValue());
            $reason  = strtolower(trim((string) $sheet->getCellByColumnAndRow(6, $row)->getValue()));
            $notes   = trim((string) $sheet->getCellByColumnAndRow(7, $row)->getValue());

            $employee = Employee::where('employee_number', $empNo)
                ->orWhere('nokom', $empNo)
                ->first();

            if (!$employee) {
                $errors[] = "Baris {$row}: Karyawan '{$empNo}' tidak ditemukan.";
                $row++;
                continue;
            }

            $ptId = $legalEntities[strtolower($ptName)] ?? null;
            if (!$ptId) {
                $errors[] = "Baris {$row}: PT '{$ptName}' tidak ditemukan di master.";
                $row++;
                continue;
            }

            if (!in_array($reason, ['join', 'transfer', 'resign'])) {
                $reason = 'join';
            }

            try {
                EmployeeBpjsAssignment::create([
                    'employee_id'     => $employee->id,
                    'legal_entity_id' => $ptId,
                    'effective_date'  => $effDate ?: now()->toDateString(),
                    'end_date'        => $endDate ?: null,
                    'reason'          => $reason,
                    'notes'           => $notes ?: null,
                    'created_by'      => auth()->id(),
                ]);
                $success++;
            } catch (\Throwable $e) {
                $errors[] = "Baris {$row}: Error — " . $e->getMessage();
            }

            $row++;
        }

        $msg = "Import selesai: {$success} baris berhasil diproses.";
        if (count($errors)) {
            $msg .= ' ' . count($errors) . ' baris gagal.';
            session()->flash('import_errors', $errors);
        }

        return back()->with('success', $msg);
    }

    // ═══ HALAMAN 3: CROSS-BILLING (dengan nominal iuran) ═══

    public function crossBilling(Request $request): mixed
    {
        $legalEntities = LegalEntity::orderBy('name')->get();
        $selectedPt    = $request->input('legal_entity_id');
        $periode       = $request->input('periode', now()->format('Y-m'));

        if (!preg_match('/^\d{4}-\d{2}$/', $periode)) {
            $periode = now()->format('Y-m');
        }

        [$year, $month] = explode('-', $periode);
        $periodeStart   = "{$year}-{$month}-01";
        $periodeEnd     = "{$year}-{$month}-31";

        // Pre-agregasi per no_komp SEBELUM join — karyawan multi-outlet bisa
        // punya lebih dari 1 baris finance_bpjs_records untuk periode & no_komp
        // yang sama, jadi join langsung akan fan-out (duplikat baris karyawan).
        $fbrAgg = DB::table('finance_bpjs_records')
            ->where('periode', $periode)
            ->selectRaw('CAST(no_komp AS UNSIGNED) as no_komp_int')
            ->selectRaw('SUM(bpjs_tk_employee) as bpjs_tk_employee')
            ->selectRaw('SUM(bpjs_jkes_employee) as bpjs_jkes_employee')
            ->selectRaw('SUM(bpjs_tk_employer) as bpjs_tk_employer')
            ->selectRaw('SUM(bpjs_jkes_employer) as bpjs_jkes_employer')
            ->groupBy(DB::raw('CAST(no_komp AS UNSIGNED)'));

        $query = DB::table('employee_bpjs_assignments as eba')
            ->join('employees as e', 'eba.employee_id', '=', 'e.id')
            ->join('legal_entities as le', 'eba.legal_entity_id', '=', 'le.id')
            ->leftJoinSub($fbrAgg, 'fbr', function ($join) {
                $join->whereRaw('fbr.no_komp_int = CAST(e.nokom AS UNSIGNED)');
            })
            ->where('eba.effective_date', '<=', $periodeEnd)
            ->where(function ($q) use ($periodeStart) {
                $q->whereNull('eba.end_date')
                  ->orWhere('eba.end_date', '>=', $periodeStart);
            })
            ->select([
                'eba.id',
                'eba.employee_id',
                'eba.legal_entity_id',
                'eba.effective_date',
                'eba.end_date',
                'eba.reason',
                'le.name as pt_name',
                'e.full_name',
                'e.nik',
                'e.nokom as no_komp',
                DB::raw('COALESCE(fbr.bpjs_tk_employee, 0)  as bpjs_tk_employee'),
                DB::raw('COALESCE(fbr.bpjs_jkes_employee, 0) as bpjs_jkes_employee'),
                DB::raw('COALESCE(fbr.bpjs_tk_employer, 0)  as bpjs_tk_employer'),
                DB::raw('COALESCE(fbr.bpjs_jkes_employer, 0) as bpjs_jkes_employer'),
                DB::raw('COALESCE(fbr.bpjs_tk_employee, 0)
                         + COALESCE(fbr.bpjs_jkes_employee, 0)
                         + COALESCE(fbr.bpjs_tk_employer, 0)
                         + COALESCE(fbr.bpjs_jkes_employer, 0) as total_iuran'),
                DB::raw('CASE WHEN fbr.no_komp_int IS NOT NULL THEN 1 ELSE 0 END as has_bpjs_data'),
            ]);

        if ($selectedPt) {
            $query->where('eba.legal_entity_id', $selectedPt);
        }

        $rows        = $query->orderBy('le.name')->orderBy('e.full_name')->get();
        $groupedByPt = $rows->groupBy('legal_entity_id');

        $summaryByPt = $groupedByPt->map(fn($group) => [
            'pt_name'        => $group->first()->pt_name,
            'total_karyawan' => $group->count(),
            'total_iuran'    => $group->sum('total_iuran'),
            'missing_data'   => $group->where('has_bpjs_data', 0)->count(),
        ]);

        $grandTotal = [
            'karyawan' => $rows->count(),
            'iuran'    => $rows->sum('total_iuran'),
            'pt'       => $groupedByPt->count(),
        ];

        if ($request->input('export') === 'excel') {
            return $this->exportCrossBillingExcel($groupedByPt, $summaryByPt, $periode);
        }

        return view('finance.bpjs_assignments.cross_billing', compact(
            'groupedByPt', 'summaryByPt', 'grandTotal',
            'legalEntities', 'selectedPt', 'periode'
        ));
    }

    // ═══ HALAMAN 4: CROSS-BILLING PER OUTLET (siapa bayar siapa, siapa nagih siapa) ═══

    public function crossBillingByOutlet(Request $request): mixed
    {
        $periode = $request->input('periode', now()->format('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $periode)) {
            $periode = now()->format('Y-m');
        }

        [$year, $month] = explode('-', $periode);
        $periodeStart = "{$year}-{$month}-01";
        $periodeEnd   = "{$year}-{$month}-31";

        // Agregasi iuran per no_komp (hindari fan-out karena karyawan multi-outlet)
        $fbrAgg = DB::table('finance_bpjs_records')
            ->where('periode', $periode)
            ->selectRaw('CAST(no_komp AS UNSIGNED) as no_komp_int')
            ->selectRaw('SUM(bpjs_tk_employee + bpjs_jkes_employee + bpjs_tk_employer + bpjs_jkes_employer) as total_iuran')
            ->groupBy(DB::raw('CAST(no_komp AS UNSIGNED)'));

        // 1 outlet perwakilan per PT (hindari fan-out kalau 1 PT punya >1 outlet)
        $payerOutletSub = DB::table('outlets')
            ->whereNotNull('legal_entity_id')
            ->select('legal_entity_id', DB::raw('MIN(id) as payer_outlet_id'))
            ->groupBy('legal_entity_id');

        $rows = DB::table('employee_bpjs_assignments as eba')
            ->join('employees as e', 'eba.employee_id', '=', 'e.id')
            ->join('legal_entities as payer_le', 'eba.legal_entity_id', '=', 'payer_le.id')
            ->join('outlets as origin_outlet', 'e.outlet_id', '=', 'origin_outlet.id')
            ->leftJoinSub($payerOutletSub, 'po', 'po.legal_entity_id', '=', 'eba.legal_entity_id')
            ->leftJoin('outlets as payer_outlet', 'payer_outlet.id', '=', 'po.payer_outlet_id')
            ->leftJoinSub($fbrAgg, 'fbr', function ($join) {
                $join->whereRaw('fbr.no_komp_int = CAST(e.nokom AS UNSIGNED)');
            })
            ->where('eba.effective_date', '<=', $periodeEnd)
            ->where(function ($q) use ($periodeStart) {
                $q->whereNull('eba.end_date')
                  ->orWhere('eba.end_date', '>=', $periodeStart);
            })
            ->whereNotNull('origin_outlet.legal_entity_id')
            ->whereColumn('origin_outlet.legal_entity_id', '!=', 'eba.legal_entity_id')
            ->select([
                'e.id as employee_id',
                'e.full_name',
                'e.nokom as no_komp',
                'origin_outlet.id as origin_outlet_id',
                'origin_outlet.name as origin_outlet_name',
                'payer_outlet.id as payer_outlet_id',
                'payer_outlet.name as payer_outlet_name',
                'payer_le.name as payer_pt_name',
                DB::raw('COALESCE(fbr.total_iuran, 0) as total_iuran'),
            ])
            ->orderBy('payer_outlet.name')
            ->orderBy('origin_outlet.name')
            ->get();

        $grouped = $rows->groupBy(fn ($r) => ($r->payer_outlet_id ?? 'pt-' . $r->payer_pt_name) . '|' . $r->origin_outlet_id);

        $pairs = $grouped->map(function ($group) {
            $first = $group->first();

            return (object) [
                'payer_outlet_id'    => $first->payer_outlet_id,
                'payer_outlet_name'  => $first->payer_outlet_name ?? ($first->payer_pt_name . ' (outlet belum di-set)'),
                'origin_outlet_id'   => $first->origin_outlet_id,
                'origin_outlet_name' => $first->origin_outlet_name,
                'employees'          => $group->values(),
                'total_karyawan'     => $group->count(),
                'total_iuran'        => $group->sum('total_iuran'),
            ];
        })->values();

        $grandTotal = [
            'pairs'    => $pairs->count(),
            'karyawan' => $rows->count(),
            'iuran'    => $rows->sum('total_iuran'),
        ];

        // Rekap per outlet: outlet ini bayar untuk siapa (rolling-in), dan ditagih oleh siapa (rolling-out)
        $byOutlet = DB::table('outlets')
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(fn ($outlet) => (object) [
                'id'        => $outlet->id,
                'name'      => $outlet->name,
                'pays'      => $pairs->where('payer_outlet_id', $outlet->id)->values(),
                'billed_by' => $pairs->where('origin_outlet_id', $outlet->id)->values(),
            ])
            ->filter(fn ($o) => $o->pays->isNotEmpty() || $o->billed_by->isNotEmpty())
            ->values();

        return view('finance.bpjs_assignments.cross_billing_outlet', compact(
            'pairs', 'grandTotal', 'byOutlet', 'periode'
        ));
    }

    private function exportCrossBillingExcel(
        \Illuminate\Support\Collection $groupedByPt,
        \Illuminate\Support\Collection $summaryByPt,
        string $periode
    ): mixed {
        set_time_limit(120);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $firstSheet  = true;

        foreach ($groupedByPt as $ptId => $rows) {
            $ptName = $rows->first()->pt_name;

            $sheet = $firstSheet
                ? $spreadsheet->getActiveSheet()
                : $spreadsheet->createSheet();
            $firstSheet = false;

            $sheetName = substr(preg_replace('/[\/\\\?\*\[\]:]/', '', $ptName), 0, 31);
            $sheet->setTitle($sheetName);

            $sheet->setCellValue('A1', "Laporan Cross-billing BPJS — {$ptName}");
            $sheet->setCellValue('A2', "Periode: {$periode}");
            $sheet->mergeCells('A1:H1');
            $sheet->mergeCells('A2:H2');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);

            foreach ([
                'A4' => 'No', 'B4' => 'Nama Karyawan', 'C4' => 'NIK',
                'D4' => 'No. Komp',
                'E4' => 'BPJS TK (Pekerja)', 'F4' => 'BPJS TK (Perusahaan)',
                'G4' => 'BPJS JKES', 'H4' => 'Total Iuran',
            ] as $cell => $val) {
                $sheet->setCellValue($cell, $val);
            }

            $sheet->getStyle('A4:H4')->getFont()->setBold(true);
            $sheet->getStyle('A4:H4')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('7C3AED');
            $sheet->getStyle('A4:H4')->getFont()->getColor()->setRGB('FFFFFF');

            $row = 5;
            $no  = 1;
            foreach ($rows as $r) {
                $sheet->fromArray([
                    $no++,
                    $r->full_name,
                    $r->nik ?? '-',
                    $r->no_komp ?? '-',
                    (float) $r->bpjs_tk_employee,
                    (float) $r->bpjs_tk_employer,
                    (float) ($r->bpjs_jkes_employee + $r->bpjs_jkes_employer),
                    (float) $r->total_iuran,
                ], null, "A{$row}");
                $row++;
            }

            $sheet->setCellValue("G{$row}", 'TOTAL');
            $sheet->setCellValue("H{$row}", (float) $rows->sum('total_iuran'));
            $sheet->getStyle("G{$row}:H{$row}")->getFont()->setBold(true);

            if ($row > 5) {
                $sheet->getStyle("E5:H{$row}")->getNumberFormat()->setFormatCode('#,##0');
            }

            foreach (range('A', 'H') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }

        $filename = "CrossBilling_BPJS_{$periode}.xlsx";
        $writer   = new XlsxWriter($spreadsheet);
        $writer->setPreCalculateFormulas(false);
        $tmpFile  = tempnam(sys_get_temp_dir(), 'crossbilling_');
        $writer->save($tmpFile);

        return response()->download($tmpFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    // ═══ AJAX: Search Karyawan ═══

    public function searchEmployee(Request $request)
    {
        $q = $request->input('q', '');
        $employees = Employee::where(function ($query) use ($q) {
            $query->where('full_name', 'LIKE', "%{$q}%")
                  ->orWhere('nik', 'LIKE', "%{$q}%")
                  ->orWhere('employee_number', 'LIKE', "%{$q}%")
                  ->orWhere('nokom', 'LIKE', "%{$q}%");
        })
        ->whereNull('deleted_at')
        ->select('id', 'full_name', 'nik', 'employee_number', 'nokom')
        ->orderBy('full_name')
        ->limit(10)
        ->get();

        return response()->json($employees);
    }
}
