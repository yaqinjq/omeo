<?php

namespace App\Http\Controllers\Master;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

class PositionController extends Controller
{
    // ── INDEX ──────────────────────────────────────────────────────────────

    public function departmentIndex(): \Illuminate\View\View
    {
        $departments = Department::withCount('positions')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $positions = Position::with('department')
            ->withCount('employees')
            ->orderBy('department_id')
            ->orderBy('name')
            ->get();

        $groupedPositions = $positions->groupBy(
            fn ($p) => $p->department_id ?? ''
        );

        $totalEmployeesMapped = Employee::whereNotNull('position_id')->count();

        return view('positions.index', compact(
            'departments',
            'positions',
            'groupedPositions',
            'totalEmployeesMapped',
        ));
    }

    // ── DEPARTMENT CRUD ────────────────────────────────────────────────────

    public function storeDepartment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:150|unique:departments,name',
            'code'       => 'nullable|string|max:30|unique:departments,code',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        Department::create([
            'name'       => trim($data['name']),
            'code'       => $data['code'] ? strtoupper(trim($data['code'])) : null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active'  => true,
        ]);

        return back()->with('success', 'Departemen berhasil ditambahkan.');
    }

    public function updateDepartment(Request $request, Department $department): RedirectResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:150|unique:departments,name,' . $department->id,
            'code'       => 'nullable|string|max:30|unique:departments,code,' . $department->id,
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $department->update([
            'name'       => trim($data['name']),
            'code'       => $data['code'] ? strtoupper(trim($data['code'])) : null,
            'sort_order' => (int) ($data['sort_order'] ?? $department->sort_order ?? 0),
        ]);

        return back()->with('success', 'Departemen berhasil diupdate.');
    }

    public function destroyDepartment(Department $department): RedirectResponse
    {
        if ($department->positions()->count() > 0) {
            return back()->with('error', 'Departemen masih memiliki posisi aktif, tidak bisa dihapus.');
        }

        $department->delete();

        return back()->with('success', 'Departemen berhasil dihapus.');
    }

    // ── POSITION CRUD ──────────────────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'           => 'required|string|max:100',
            'department_id'  => 'nullable|exists:departments,id',
            'level'          => 'nullable|integer|min:1|max:10',
            'approval_level' => 'nullable|integer|min:0',
            'salary_min'     => 'nullable|numeric|min:0',
            'salary_max'     => 'nullable|numeric|min:0',
            'is_active'      => 'nullable|boolean',
            'description'    => 'nullable|string|max:1000',
        ]);

        Position::create([
            'name'           => trim($data['name']),
            'department_id'  => $data['department_id'] ?? null,
            'level'          => $data['level'] ?? null,
            'approval_level' => $data['approval_level'] ?? null,
            'salary_min'     => $data['salary_min'] ?? null,
            'salary_max'     => $data['salary_max'] ?? null,
            'is_active'      => isset($data['is_active']) ? (bool) $data['is_active'] : true,
            'description'    => $data['description'] ?? null,
        ]);

        return back()->with('success', 'Posisi berhasil ditambahkan.');
    }

    public function update(Request $request, Position $position): RedirectResponse
    {
        $data = $request->validate([
            'name'           => 'required|string|max:100',
            'department_id'  => 'nullable|exists:departments,id',
            'level'          => 'nullable|integer|min:1|max:10',
            'approval_level' => 'nullable|integer|min:0',
            'salary_min'     => 'nullable|numeric|min:0',
            'salary_max'     => 'nullable|numeric|min:0',
            'is_active'      => 'nullable|boolean',
            'description'    => 'nullable|string|max:1000',
        ]);

        $position->update([
            'name'           => trim($data['name']),
            'department_id'  => $data['department_id'] ?? null,
            'level'          => $data['level'] ?? null,
            'approval_level' => $data['approval_level'] ?? null,
            'salary_min'     => $data['salary_min'] ?? null,
            'salary_max'     => $data['salary_max'] ?? null,
            'is_active'      => isset($data['is_active']) ? (bool) $data['is_active'] : true,
            'description'    => $data['description'] ?? null,
        ]);

        return back()->with('success', 'Posisi berhasil diupdate.');
    }

    public function destroy(Position $position): RedirectResponse
    {
        $count = Employee::where('position_id', $position->id)->count();

        if ($count > 0) {
            return back()->with('error', "Posisi ini dipakai {$count} karyawan, tidak bisa dihapus.");
        }

        $position->delete();

        return back()->with('success', 'Posisi berhasil dihapus.');
    }

    // ── ORG CHART ─────────────────────────────────────────────────────────

    public function orgChart(): \Illuminate\View\View
    {
        $departments = Department::with([
            'positions' => function ($q) {
                $q->withCount('employees')->orderBy('level')->orderBy('name');
            },
        ])->orderBy('sort_order')->orderBy('name')->get();

        $unassigned = Position::whereNull('department_id')
            ->withCount('employees')
            ->orderBy('level')
            ->get();

        $stats = [
            'total_dept'      => $departments->count(),
            'total_positions' => Position::count(),
            'total_mapped'    => Employee::whereNotNull('position_id')->count(),
            'total_unmapped'  => Employee::whereNull('position_id')->count(),
        ];

        return view('positions.org_chart', compact('departments', 'unassigned', 'stats'));
    }

    public function positionEmployees(Position $position, Request $request): \Illuminate\Http\JsonResponse
    {
        $employees = Employee::where('position_id', $position->id)
            ->with([
                'outlet:id,name',
                'user:id,employee_id',
                'user.applicantProfile:id,user_id,photo_path',
            ])
            ->select(['id', 'full_name', 'nik', 'employee_number', 'nokom', 'outlet_id', 'status_employment', 'join_date'])
            ->orderBy('full_name')
            ->get()
            ->map(fn ($e) => [
                'id'              => $e->id,
                'full_name'       => $e->full_name,
                'nik'             => $e->nik ?? '-',
                'employee_number' => $e->employee_number ?? $e->nokom ?? '-',
                'outlet'          => $e->outlet->name ?? '-',
                'status'          => $e->status_employment ?? '-',
                'join_date'       => $e->join_date
                    ? \Carbon\Carbon::parse($e->join_date)->format('d M Y')
                    : '-',
                'profile_url'     => route('employees.show', $e->id),
                'photo_url'       => $e->user?->applicantProfile?->photo_path
                                     ? asset('storage/' . $e->user->applicantProfile->photo_path)
                                     : null,
            ]);

        return response()->json([
            'position'  => [
                'id'    => $position->id,
                'name'  => $position->name,
                'level' => $position->level,
                'count' => $employees->count(),
            ],
            'employees' => $employees,
        ]);
    }

    // ── TEMPLATE DOWNLOAD ──────────────────────────────────────────────────

    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet();

        // Helper: apply purple header style to a range
        $applyHeaderStyle = function ($ws, string $range): void {
            $ws->getStyle($range)->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7C3AED']],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
                'borders'   => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            ]);
        };

        // ═══ SHEET 1: Template Import Posisi ═══
        $ws = $spreadsheet->getActiveSheet();
        $ws->setTitle('Template Import Posisi');

        // Header row
        foreach (['A' => 'nama_posisi', 'B' => 'nama_departemen', 'C' => 'level (1-10)', 'D' => 'salary_min', 'E' => 'salary_max', 'F' => 'approval_level', 'G' => 'keterangan'] as $col => $val) {
            $ws->setCellValue("{$col}1", $val);
        }
        $applyHeaderStyle($ws, 'A1:G1');
        $ws->getRowDimension(1)->setRowHeight(22);

        // 12 sample data rows
        $sampleData = [
            ['MANAGEMENT',                'MANAGEMENT',  2, 8000000,  25000000, 1, 'Direktur/GM/Manager level atas'],
            ['HUMAN RESOURCE DEPARTMENT', 'HRD',         3, 5000000,  15000000, 2, 'Staff & Head HRD'],
            ['FINANCE AND ACCOUNTING',    'FINANCE',     3, 5000000,  15000000, 2, 'Staff & Head Finance'],
            ['PURCHASING',                'PURCHASING',  4, 4000000,  10000000, 3, 'Staff & Supervisor Purchasing'],
            ['DESIGN MARKETING',          'MARKETING',   4, 4000000,  10000000, 3, 'Staff & Head Marketing'],
            ['IT',                        'IT',          4, 4000000,  10000000, 3, 'Staff IT & Developer'],
            ['OFFICE PRODUCTION',         'OFFICE',      5, 3500000,   8000000, 4, 'Staff Office & Admin Produksi'],
            ['MAINTENANCE',               'MAINTENANCE', 6, 3000000,   6000000, 4, 'Teknisi & Staff Maintenance'],
            ['KITCHEN PRODUCTION',        'KITCHEN',     6, 3000000,   6000000, 4, 'Staff Kitchen Produksi'],
            ['SERVICE',                   'OPERASIONAL', 7, 2500000,   5000000, 5, 'Crew Service di outlet'],
            ['BAR',                       'OPERASIONAL', 7, 2500000,   5000000, 5, 'Crew Bar di outlet'],
            ['KITCHEN OUTLET',            'OPERASIONAL', 7, 2500000,   5000000, 5, 'Crew Kitchen di outlet'],
        ];

        foreach ($sampleData as $i => $row) {
            $excelRow = $i + 2;
            foreach ($row as $colIdx => $val) {
                $col = chr(65 + $colIdx);
                $ws->setCellValue("{$col}{$excelRow}", $val);
                $ws->getStyle("{$col}{$excelRow}")->getBorders()->getAllBorders()
                   ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            }
            if ($excelRow % 2 === 0) {
                $ws->getStyle("A{$excelRow}:G{$excelRow}")
                   ->getFill()->setFillType(Fill::FILL_SOLID)
                   ->getStartColor()->setRGB('F9F5FF');
            }
        }

        foreach (['A' => 28, 'B' => 18, 'C' => 14, 'D' => 14, 'E' => 14, 'F' => 16, 'G' => 40] as $col => $w) {
            $ws->getColumnDimension($col)->setWidth($w);
        }

        // ═══ SHEET 2: Panduan Level & Departemen ═══
        $ws2 = $spreadsheet->createSheet();
        $ws2->setTitle('Panduan Level & Departemen');

        // Title row
        $ws2->setCellValue('A1', 'PANDUAN LEVEL HIERARKI JABATAN');
        $ws2->mergeCells('A1:C1');
        $ws2->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '7C3AED']],
        ]);

        // Header
        $ws2->setCellValue('A2', 'Level');
        $ws2->setCellValue('B2', 'Nama Level');
        $ws2->setCellValue('C2', 'Contoh Jabatan');
        $applyHeaderStyle($ws2, 'A2:C2');

        // Level data rows 3–12
        $levels = [
            [1,  'Pemilik / Owner',           'Owner, Founder'],
            [2,  'Direktur / Top Management', 'Direktur, GM, CEO'],
            [3,  'Manager / Head',            'Head HRD, Head Finance, Manager'],
            [4,  'Supervisor / Koordinator',  'Supervisor, Koordinator, Lead'],
            [5,  'Senior Staff',              'Senior Staff, Admin Senior'],
            [6,  'Staff / Crew Senior',       'Staff, Teknisi, Cook Senior'],
            [7,  'Crew / Karyawan Reguler',   'Crew BAR, SERVICE, KITCHEN OUTLET'],
            [8,  'Daily Worker',              'DW, Part Time'],
            [9,  'Magang / Intern',           'Peserta Magang'],
            [10, 'Cadangan',                  '-'],
        ];

        foreach ($levels as $i => $lvl) {
            $excelRow = $i + 3;
            $ws2->setCellValue("A{$excelRow}", $lvl[0]);
            $ws2->setCellValue("B{$excelRow}", $lvl[1]);
            $ws2->setCellValue("C{$excelRow}", $lvl[2]);
            foreach (['A', 'B', 'C'] as $col) {
                $ws2->getStyle("{$col}{$excelRow}")->getBorders()->getAllBorders()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            }
            if ($excelRow % 2 === 0) {
                $ws2->getStyle("A{$excelRow}:C{$excelRow}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F9F5FF');
            }
        }

        // Catatan section (rows 14–17)
        $catatan = [
            14 => ['bold' => true,  'text' => 'CATATAN:'],
            15 => ['bold' => false, 'text' => 'Semakin KECIL angka level = semakin TINGGI jabatan'],
            16 => ['bold' => false, 'text' => 'approval_level = posisi level berapa yang bisa approve jabatan ini'],
            17 => ['bold' => false, 'text' => 'Kolom salary_min & salary_max boleh dikosongkan'],
        ];
        foreach ($catatan as $row => $item) {
            $ws2->setCellValue("A{$row}", $item['text']);
            $ws2->mergeCells("A{$row}:C{$row}");
            if ($item['bold']) {
                $ws2->getStyle("A{$row}")->getFont()->setBold(true);
            }
        }

        $ws2->getColumnDimension('A')->setWidth(10);
        $ws2->getColumnDimension('B')->setWidth(30);
        $ws2->getColumnDimension('C')->setWidth(45);

        // ═══ SAVE & DOWNLOAD ═══
        $spreadsheet->setActiveSheetIndex(0);
        $writer = new XlsxWriter($spreadsheet);
        $writer->setPreCalculateFormulas(false);

        $tmp = tempnam(sys_get_temp_dir(), 'pos_tmpl_');
        $writer->save($tmp);

        return response()->download(
            $tmp,
            'Template_Import_Posisi_MKO.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    // ── IMPORT ─────────────────────────────────────────────────────────────

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        $file = $request->file('file');

        try {
            $reader = IOFactory::createReaderForFile($file->getPathname());
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($file->getPathname());
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal membaca file: ' . $e->getMessage());
        }

        $sheet       = $spreadsheet->getActiveSheet();
        $departments = Department::all()->keyBy(fn ($d) => strtolower(trim($d->name)));

        $success  = 0;
        $warnings = [];
        $row      = 2;

        while (true) {
            $name = trim((string) ($sheet->getCell('A' . $row)->getValue() ?? ''));
            if ($name === '') {
                break;
            }

            $deptRaw  = trim((string) ($sheet->getCell('B' . $row)->getValue() ?? ''));
            $levelRaw = (string) ($sheet->getCell('C' . $row)->getValue() ?? '');
            $salMin   = $sheet->getCell('D' . $row)->getValue();
            $salMax   = $sheet->getCell('E' . $row)->getValue();
            $appLvl   = $sheet->getCell('F' . $row)->getValue();
            $desc     = trim((string) ($sheet->getCell('G' . $row)->getValue() ?? ''));

            $deptId = $departments[strtolower($deptRaw)]->id ?? null;
            if ($deptId === null && $deptRaw !== '') {
                $warnings[] = "Baris {$row}: Departemen '{$deptRaw}' tidak ditemukan — diisi null.";
            }

            $level = $levelRaw !== '' ? (int) $levelRaw : null;
            if ($level !== null && ($level < 1 || $level > 10)) {
                $warnings[] = "Baris {$row}: Level '{$level}' tidak valid (harus 1–10) — diisi null.";
                $level = null;
            }

            Position::updateOrCreate(
                ['name' => $name],
                [
                    'department_id'  => $deptId,
                    'level'          => $level,
                    'salary_min'     => ($salMin !== null && $salMin !== '') ? (float) $salMin : null,
                    'salary_max'     => ($salMax !== null && $salMax !== '') ? (float) $salMax : null,
                    'approval_level' => ($appLvl !== null && $appLvl !== '') ? (int) $appLvl : null,
                    'description'    => $desc !== '' ? $desc : null,
                    'is_active'      => true,
                ]
            );

            $success++;
            $row++;
        }

        $msg = "Import selesai: {$success} posisi diproses.";
        if ($warnings) {
            $msg .= ' ' . count($warnings) . ' warning (lihat di bawah).';
            session()->flash('import_warnings', $warnings);
        }

        return back()->with('success', $msg);
    }
}
