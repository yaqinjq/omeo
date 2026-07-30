<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Appraisal;
use App\Models\AppraisalBatchSignature;
use App\Models\AppraisalDetail;
use App\Models\AppraisalIndicator;
use App\Models\AppraisalInvitationLog;
use App\Models\AppraisalWeightConfig;
use App\Models\Employee;
use App\Models\HrNotification;
use App\Models\AppraisalPeriod;
use App\Models\User;
use App\Services\AppraisalComponentService;
use App\Support\AppraisalGrading;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AppraisalController extends Controller
{
    public function __construct(private readonly AppraisalComponentService $componentService)
    {
    }

    public function report(Request $request)
    {
        if (! Schema::hasTable('appraisals')) {
            $empty = new LengthAwarePaginator([], 0, 20, 1, ['path' => $request->url()]);
            return view('appraisals.report', [
                'paginator'     => $empty,
                'stats'         => ['total_employees' => 0, 'total_appraisals' => 0, 'approved' => 0, 'submitted' => 0, 'draft' => 0],
                'distribution'  => collect(),
                'avgScore'      => null,
                'periods'       => collect(),
                'periodId'      => null,
                'status'        => null,
                'search'        => null,
                'moduleWarning' => 'Tabel appraisal belum tersedia di environment ini.',
            ]);
        }

        abort_if(
            ! in_array((string) auth()->user()->role, ['admin', 'hrd', 'manager'], true),
            403
        );

        $periodId = $request->input('period_id');
        $status   = $request->input('status');
        $search   = trim((string) $request->input('search', ''));

        // HRD privacy: HRD yang berperan sebagai karyawan yang dinilai tidak boleh melihat appraisalnya sendiri
        $selfExcludeId = null;
        if ((string) auth()->user()->role === 'hrd' && (int) auth()->user()->employee_id > 0) {
            $selfExcludeId = (int) auth()->user()->employee_id;
        }

        $allAppraisals = Appraisal::query()
            ->with(['employee:id,full_name,employee_number,jabatan', 'period:id,name', 'appraiser:id,name'])
            ->when($periodId, fn ($q) => $q->where('appraisal_period_id', $periodId))
            ->when($status,   fn ($q) => $q->where('status', $status))
            ->when($search,   fn ($q) => $q->whereHas('employee', fn ($eq) =>
                $eq->where('full_name', 'like', "%{$search}%")
                   ->orWhere('jabatan', 'like', "%{$search}%")
                   ->orWhere('employee_number', 'like', "%{$search}%")
            ))
            ->when($selfExcludeId, fn ($q) => $q->where('employee_id', '!=', $selfExcludeId))
            ->orderBy('employee_id')
            ->orderBy('id')
            ->get();

        $groupedAll = $allAppraisals->groupBy('employee_id')
            ->map(function ($items, $empId) {
                $emp    = $items->first()->employee;
                $latest = $items->sortByDesc('id')->first();

                return (object) [
                    'employee_id'     => $empId,
                    'employee_name'   => $emp?->full_name ?? '-',
                    'employee_number' => $emp?->employee_number ?? '-',
                    'jabatan'         => $emp?->jabatan ?? '-',
                    'period_name'     => $latest->period?->name ?? '-',
                    'evaluator_count' => $items->count(),
                    'avg_score'       => $items->whereNotNull('final_score')->avg('final_score'),
                    'latest_result'   => $latest->final_result,
                    'approved_count'  => $items->where('status', 'approved')->count(),
                    'submitted_count' => $items->where('status', 'submitted')->count(),
                    'draft_count'     => $items->where('status', 'draft')->count(),
                    'is_legacy'       => $items->contains(fn ($a) => $a->migration_source === 'meo_legacy'),
                    'appraisals'      => $items->map(fn ($a) => [
                        'id'     => $a->id,
                        'score'  => $a->final_score,
                        'result' => $a->final_result,
                        'status' => $a->status,
                        'url'    => route('appraisals.show', $a->id),
                    ])->values()->toArray(),
                    'latest_url' => route('appraisals.show', $latest->id),
                ];
            })
            ->sortByDesc('avg_score')
            ->values();

        $stats = [
            'total_employees'  => $groupedAll->count(),
            'total_appraisals' => $allAppraisals->count(),
            'approved'         => $allAppraisals->where('status', 'approved')->count(),
            'submitted'        => $allAppraisals->where('status', 'submitted')->count(),
            'draft'            => $allAppraisals->where('status', 'draft')->count(),
        ];

        $distribution = $allAppraisals
            ->whereNotNull('final_result')
            ->where('final_result', '!=', '')
            ->groupBy('final_result')
            ->map(fn ($g) => $g->count())
            ->sortDesc();

        $avgScore = $allAppraisals
            ->whereNotNull('final_score')
            ->where('final_score', '>', 0)
            ->avg('final_score');

        $periods = AppraisalPeriod::orderByDesc('created_at')->get();

        $perPage     = 20;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $pageItems   = $groupedAll->slice(($currentPage - 1) * $perPage, $perPage)->values();
        $paginator   = new LengthAwarePaginator(
            $pageItems,
            $groupedAll->count(),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->except('page')]
        );

        return view('appraisals.report', compact(
            'paginator', 'stats', 'distribution', 'avgScore', 'periods', 'periodId', 'status', 'search'
        ));
    }

    public function exportReport(Request $request)
    {
        abort_if(
            ! in_array((string) auth()->user()->role, ['admin', 'hrd', 'manager'], true),
            403
        );

        $periodId = $request->input('period_id');
        $status   = $request->input('status');
        $search   = trim((string) $request->input('search', ''));

        $allAppraisals = Appraisal::query()
            ->with(['employee:id,full_name,employee_number,jabatan', 'period:id,name'])
            ->when($periodId, fn ($q) => $q->where('appraisal_period_id', $periodId))
            ->when($status,   fn ($q) => $q->where('status', $status))
            ->when($search,   fn ($q) => $q->whereHas('employee', fn ($eq) =>
                $eq->where('full_name', 'like', "%{$search}%")
                   ->orWhere('jabatan', 'like', "%{$search}%")
                   ->orWhere('employee_number', 'like', "%{$search}%")
            ))
            ->orderBy('employee_id')
            ->orderBy('id')
            ->get();

        $grouped = $allAppraisals->groupBy('employee_id')
            ->map(function ($items) {
                $emp    = $items->first()->employee;
                $latest = $items->sortByDesc('id')->first();
                return (object) [
                    'employee_number' => $emp?->employee_number ?? '-',
                    'employee_name'   => $emp?->full_name ?? '-',
                    'jabatan'         => $emp?->jabatan ?? '-',
                    'period_name'     => $latest->period?->name ?? '-',
                    'evaluator_count' => $items->count(),
                    'avg_score'       => $items->whereNotNull('final_score')->avg('final_score'),
                    'latest_result'   => $latest->final_result ?? '-',
                    'approved_count'  => $items->where('status', 'approved')->count(),
                    'submitted_count' => $items->where('status', 'submitted')->count(),
                    'draft_count'     => $items->where('status', 'draft')->count(),
                ];
            })
            ->sortByDesc('avg_score')
            ->values();

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan Appraisal');

        $headers = ['No', 'No. Karyawan', 'Nama Karyawan', 'Jabatan', 'Periode', 'Evaluator', 'Rata-rata Skor', 'Hasil Terbaru', 'Status'];
        foreach ($headers as $colIdx => $hdr) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx + 1) . '1', $hdr);
        }
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);
        $sheet->getStyle('A1:I1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF7C3AED');
        $sheet->getStyle('A1:I1')->getFont()->getColor()->setARGB('FFFFFFFF');

        foreach ($grouped as $i => $row) {
            $r = $i + 2;
            if ($row->approved_count > 0) {
                $statusStr = "Approved ({$row->approved_count})";
            } elseif ($row->submitted_count > 0) {
                $statusStr = "Submitted ({$row->submitted_count})";
            } else {
                $statusStr = "Draft ({$row->draft_count})";
            }

            $sheet->setCellValue("A{$r}", $i + 1);
            $sheet->setCellValue("B{$r}", $row->employee_number);
            $sheet->setCellValue("C{$r}", $row->employee_name);
            $sheet->setCellValue("D{$r}", $row->jabatan);
            $sheet->setCellValue("E{$r}", $row->period_name);
            $sheet->setCellValue("F{$r}", $row->evaluator_count);
            $sheet->setCellValue("G{$r}", $row->avg_score !== null ? round((float) $row->avg_score, 1) : '-');
            $sheet->setCellValue("H{$r}", $row->latest_result);
            $sheet->setCellValue("I{$r}", $statusStr);

            if ($i % 2 === 1) {
                $sheet->getStyle("A{$r}:I{$r}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFF8F5FF');
            }
        }

        foreach (range(1, 9) as $colIdx) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($colIdx))->setAutoSize(true);
        }

        $filename = 'Laporan_Appraisal_' . now()->format('Ymd_His') . '.xlsx';
        $writer   = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function exportEmployeePdf(Request $request, int $employeeId): \Symfony\Component\HttpFoundation\Response
    {
        abort_if(
            ! in_array((string) auth()->user()->role, ['admin', 'hrd', 'manager'], true),
            403
        );

        if ((string) auth()->user()->role === 'hrd' && (int) auth()->user()->employee_id === $employeeId) {
            abort(403, 'Anda tidak diizinkan mengekspor data appraisal diri sendiri.');
        }

        $shownIds = array_map('intval', (array) $request->input('evaluator_ids', []));
        $showAll  = empty($shownIds) && $request->isMethod('get');

        $data = $this->buildEmployeeReportData(
            $employeeId,
            $request->input('period_id'),
            $request->input('date_from'),
            $request->input('date_to'),
            $shownIds,
            $showAll
        );

        abort_if($data === null, 404);

        $pdf = DomPdf::loadView('appraisals.pdf.employee_report', $data)->setPaper('a4', 'portrait');

        $safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', $data['employee']->full_name);
        $filename = "Laporan_Appraisal_{$safeName}_" . now()->format('Ymd') . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Bangun seluruh variabel yang dibutuhkan template PDF appraisal untuk satu
     * karyawan. Dipakai bersama oleh export PDF per-karyawan dan export PDF
     * bulk (banyak karyawan dalam 1 file) supaya logikanya tidak dobel.
     * Return null kalau karyawan tidak punya appraisal submitted/approved yang
     * cocok filter (supaya pemanggil bisa skip, bukan 404 keras).
     */
    private function buildEmployeeReportData(
        int $employeeId,
        ?string $periodId,
        ?string $dateFrom,
        ?string $dateTo,
        array $shownIds,
        bool $showAll
    ): ?array {
        $employee = Employee::with('department')->find($employeeId);
        if (! $employee) {
            return null;
        }

        $appraisals = Appraisal::query()
            ->with(['appraiser:id,name', 'details.indicator', 'period:id,name'])
            ->where('employee_id', $employeeId)
            ->when($periodId, fn ($q) => $q->where('appraisal_period_id', $periodId))
            ->when($dateFrom, fn ($q) => $q->whereDate('date_appraised', '>=', $dateFrom))
            ->when($dateTo,   fn ($q) => $q->whereDate('date_appraised', '<=', $dateTo))
            ->whereIn('status', ['submitted', 'approved'])
            ->orderBy('date_appraised')
            ->orderBy('id')
            ->get();

        if ($appraisals->isEmpty()) {
            return null;
        }

        $indicatorIds = $appraisals
            ->flatMap(fn ($a) => $a->details->pluck('appraisal_indicator_id'))
            ->unique()->sort()->values();
        $indicators = AppraisalIndicator::whereIn('id', $indicatorIds)->orderBy('id')->get();

        $matrix = $indicators->map(function ($ind) use ($appraisals) {
            $scores = $appraisals->mapWithKeys(fn ($a) => [
                $a->id => $a->details->firstWhere('appraisal_indicator_id', $ind->id)?->score,
            ]);
            $validScores = $scores->filter(fn ($s) => $s !== null);
            return [
                'label'  => $ind->question,
                'scores' => $scores->toArray(),
                'avg'    => $validScores->isNotEmpty() ? round($validScores->avg(), 2) : null,
            ];
        })->values()->toArray();

        $evalAvgs = $appraisals->mapWithKeys(function ($a) use ($matrix) {
            $scores = collect($matrix)->map(fn ($r) => $r['scores'][$a->id] ?? null)->filter(fn ($s) => $s !== null);
            return [$a->id => $scores->isNotEmpty() ? round($scores->avg(), 2) : null];
        })->toArray();

        $overallAvg     = collect($evalAvgs)->filter()->average();
        $overallAvg     = $overallAvg ? round($overallAvg, 2) : null;
        $evaluatorCount = $appraisals->count();
        $approvedCount  = $appraisals->where('status', 'approved')->count();

        $evaluatorNumber = $appraisals->mapWithKeys(fn ($a, $idx) => [$a->id => $idx + 1])->toArray();

        // Name to display per evaluator: real name if in $shownIds (or GET with no filter), else "Evaluator N"
        $showNames = $appraisals->mapWithKeys(function ($a) use ($shownIds, $showAll, $evaluatorNumber) {
            $num  = $evaluatorNumber[$a->id];
            $name = ($showAll || in_array($a->id, $shownIds))
                ? ($a->appraiser?->name ?? "Evaluator {$num}")
                : "Evaluator {$num}";
            return [$a->id => $name];
        })->toArray();

        $evaluatorNames = implode(', ', array_values($showNames));

        $dateMin   = $appraisals->min(fn ($a) => $a->date_appraised?->format('Y-m-d'));
        $dateMax   = $appraisals->max(fn ($a) => $a->date_appraised?->format('Y-m-d'));
        $dateRange = $dateMin
            ? ($dateMax && $dateMax !== $dateMin ? "{$dateMin} s/d {$dateMax}" : $dateMin)
            : '-';

        $periodName = $periodId
            ? ($appraisals->first()->period?->name ?? 'Report Appraisal Karyawan')
            : 'Report Appraisal Karyawan';

        $narratives = $appraisals->map(fn ($a) => [
            'no'              => $evaluatorNumber[$a->id],
            'name'            => $showNames[$a->id],
            'date'            => $a->date_appraised?->format('Y-m-d') ?? '-',
            'proposed_status' => $this->formatProposedStatus($a->proposed_status),
            'strengths'       => $a->feedback_strengths ?? '-',
            'improvements'    => $a->feedback_improvements ?? '-',
            'notes'           => $a->feedback_notes ?? '-',
        ])->values()->toArray();

        $consensusRaw = $appraisals->whereNotNull('proposed_status')
            ->groupBy('proposed_status')
            ->sortByDesc(fn ($g) => $g->count())
            ->keys()->first();
        $consensusStatus = $this->formatProposedStatus($consensusRaw);
        $latestAppraisal = $appraisals->sortByDesc('id')->first();

        $appName = AppSetting::first()?->app_name ?? config('app.name', 'MEO APPS');

        return compact(
            'employee', 'appraisals', 'periodName', 'dateRange',
            'matrix', 'evalAvgs', 'overallAvg',
            'evaluatorCount', 'approvedCount', 'evaluatorNames', 'evaluatorNumber', 'showNames',
            'narratives', 'consensusStatus', 'latestAppraisal', 'appName'
        );
    }

    /**
     * Export PDF bulk: gabungkan laporan detail SEMUA karyawan yang cocok
     * filter halaman Laporan Appraisal (period_id, status, search) ke dalam
     * satu file PDF, satu bagian per karyawan.
     */
    public function exportBulkPdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        abort_if(
            ! in_array((string) auth()->user()->role, ['admin', 'hrd', 'manager'], true),
            403
        );

        $periodId = $request->input('period_id');
        $status   = $request->input('status');
        $search   = trim((string) $request->input('search', ''));

        $selfExcludeId = null;
        if ((string) auth()->user()->role === 'hrd' && (int) auth()->user()->employee_id > 0) {
            $selfExcludeId = (int) auth()->user()->employee_id;
        }

        $employeeIds = Appraisal::query()
            ->when($periodId, fn ($q) => $q->where('appraisal_period_id', $periodId))
            ->when($status,   fn ($q) => $q->where('status', $status))
            ->when($search,   fn ($q) => $q->whereHas('employee', fn ($eq) =>
                $eq->where('full_name', 'like', "%{$search}%")
                   ->orWhere('jabatan', 'like', "%{$search}%")
                   ->orWhere('employee_number', 'like', "%{$search}%")
            ))
            ->when($selfExcludeId, fn ($q) => $q->where('employee_id', '!=', $selfExcludeId))
            ->whereIn('status', ['submitted', 'approved'])
            ->orderBy('employee_id')
            ->distinct()
            ->pluck('employee_id');

        abort_if($employeeIds->isEmpty(), 404, 'Tidak ada karyawan dengan appraisal submitted/approved sesuai filter.');

        $employeesData = $employeeIds
            ->map(fn ($employeeId) => $this->buildEmployeeReportData(
                (int) $employeeId,
                $periodId,
                null,
                null,
                [],
                true // bulk export: tampilkan nama evaluator asli untuk semua
            ))
            ->filter()
            ->sortBy(fn ($data) => $data['employee']->full_name)
            ->values();

        abort_if($employeesData->isEmpty(), 404);

        $appName = AppSetting::first()?->app_name ?? config('app.name', 'MEO APPS');

        $pdf = DomPdf::loadView('appraisals.pdf.bulk_report', compact('employeesData', 'appName'))
            ->setPaper('a4', 'portrait');

        $filename = 'Laporan_Appraisal_Bulk_' . now()->format('Ymd_His') . '.pdf';

        return $pdf->download($filename);
    }

    public function reportEmployee(Request $request, int $employeeId)
    {
        if (! Schema::hasTable('appraisals')) {
            abort(404);
        }

        abort_if(
            ! in_array((string) auth()->user()->role, ['admin', 'hrd', 'manager'], true),
            403
        );

        if ((string) auth()->user()->role === 'hrd' && (int) auth()->user()->employee_id === $employeeId) {
            abort(403, 'Anda tidak diizinkan melihat data appraisal diri sendiri.');
        }

        $employee = Employee::with('department')->findOrFail($employeeId);
        $periodId = $request->input('period_id');
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        $appraisals = Appraisal::query()
            ->with(['appraiser:id,name', 'details.indicator', 'period:id,name'])
            ->where('employee_id', $employeeId)
            ->when($periodId, fn ($q) => $q->where('appraisal_period_id', $periodId))
            ->when($dateFrom, fn ($q) => $q->whereDate('date_appraised', '>=', $dateFrom))
            ->when($dateTo,   fn ($q) => $q->whereDate('date_appraised', '<=', $dateTo))
            ->whereIn('status', ['submitted', 'approved'])
            ->orderBy('date_appraised')
            ->orderBy('id')
            ->get();

        $indicatorIds = $appraisals
            ->flatMap(fn ($a) => $a->details->pluck('appraisal_indicator_id'))
            ->unique()->sort()->values();
        $indicators = AppraisalIndicator::whereIn('id', $indicatorIds)->orderBy('id')->get();

        $matrix = $indicators->map(function ($ind) use ($appraisals) {
            $scores = $appraisals->mapWithKeys(fn ($a) => [
                $a->id => $a->details->firstWhere('appraisal_indicator_id', $ind->id)?->score,
            ]);
            $validScores = $scores->filter(fn ($s) => $s !== null);
            return [
                'label'  => $ind->question,
                'scores' => $scores->toArray(),
                'avg'    => $validScores->isNotEmpty() ? round($validScores->avg(), 2) : null,
            ];
        })->values()->toArray();

        $evalAvgs = $appraisals->mapWithKeys(function ($a) use ($matrix) {
            $scores = collect($matrix)->map(fn ($r) => $r['scores'][$a->id] ?? null)->filter(fn ($s) => $s !== null);
            return [$a->id => $scores->isNotEmpty() ? round($scores->avg(), 2) : null];
        })->toArray();

        $overallAvg      = collect($evalAvgs)->filter()->average();
        $overallAvg      = $overallAvg ? round((float) $overallAvg, 2) : null;
        $evaluatorNumber = $appraisals->mapWithKeys(fn ($a, $idx) => [$a->id => $idx + 1])->toArray();

        $narratives = $appraisals->map(fn ($a) => [
            'no'              => $evaluatorNumber[$a->id],
            'name'            => $a->appraiser?->name ?? 'Evaluator',
            'date'            => $a->date_appraised?->format('Y-m-d') ?? '-',
            'proposed_status' => $this->formatProposedStatus($a->proposed_status),
            'strengths'       => $a->feedback_strengths ?? '-',
            'improvements'    => $a->feedback_improvements ?? '-',
            'notes'           => $a->feedback_notes ?? '-',
        ])->values()->toArray();

        $consensusRaw = $appraisals->whereNotNull('proposed_status')
            ->groupBy('proposed_status')
            ->sortByDesc(fn ($g) => $g->count())
            ->keys()->first();
        $consensusStatus = $this->formatProposedStatus($consensusRaw);
        $latestAppraisal = $appraisals->sortByDesc('id')->first();

        $dateMin = $appraisals->min(fn ($a) => $a->date_appraised?->format('Y-m-d'));
        $dateMax = $appraisals->max(fn ($a) => $a->date_appraised?->format('Y-m-d'));

        // Batch signature (one per employee+period combo, auto-created on first view)
        $sigBatch = null;
        if (Schema::hasTable('appraisal_batch_signatures')) {
            $sigBatch = AppraisalBatchSignature::where('employee_id', $employeeId)
                ->when($periodId, fn ($q) => $q->where('appraisal_period_id', $periodId))
                ->when(! $periodId, fn ($q) => $q->whereNull('appraisal_period_id'))
                ->latest()
                ->first();

            if (! $sigBatch && $appraisals->isNotEmpty()) {
                $sigBatch = AppraisalBatchSignature::create([
                    'employee_id'        => $employeeId,
                    'appraisal_period_id'=> $periodId ? (int) $periodId : null,
                ]);
            }

            if ($sigBatch) {
                $sigBatch->load(['signerEmployee', 'signerHrd', 'signerSupervisor', 'signerManager', 'signerDirector']);
            }
        }

        $periods  = AppraisalPeriod::orderByDesc('created_at')->get();
        $allUsers = User::orderBy('name')->get(['id', 'name', 'role']);

        $periodName = $periodId
            ? ($appraisals->first()?->period?->name ?? $periods->firstWhere('id', $periodId)?->name ?? 'Semua Periode')
            : 'Semua Periode';

        return view('appraisals.report_employee', compact(
            'employee', 'appraisals', 'matrix', 'evalAvgs', 'overallAvg',
            'evaluatorNumber', 'narratives', 'consensusStatus', 'latestAppraisal',
            'sigBatch', 'periods', 'allUsers',
            'periodId', 'periodName', 'dateFrom', 'dateTo', 'dateMin', 'dateMax'
        ));
    }

    public function saveSigner(Request $request, int $employeeId): RedirectResponse
    {
        abort_if(! Schema::hasTable('appraisal_batch_signatures'), 404);
        abort_if(! in_array((string) auth()->user()->role, ['admin', 'hrd'], true), 403);

        $data = $request->validate([
            'batch_id'       => ['required', 'integer'],
            'role'           => ['required', 'in:employee,hrd,supervisor,manager,director'],
            'signer_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $batch = AppraisalBatchSignature::findOrFail($data['batch_id']);
        abort_unless((int) $batch->employee_id === $employeeId, 403);

        $batch->update(["signer_{$data['role']}_id" => $data['signer_user_id'] ?: null]);

        return back()->with('success', 'Signer berhasil disimpan.');
    }

    public function saveSignature(Request $request, int $employeeId): RedirectResponse
    {
        abort_if(! Schema::hasTable('appraisal_batch_signatures'), 404);

        $data = $request->validate([
            'batch_id'       => ['required', 'integer'],
            'role'           => ['required', 'in:employee,hrd,supervisor,manager,director'],
            'signature_data' => ['required', 'string'],
        ]);

        $batch = AppraisalBatchSignature::findOrFail($data['batch_id']);
        abort_unless((int) $batch->employee_id === $employeeId, 403);

        $signerField = "signer_{$data['role']}_id";
        abort_unless((int) ($batch->$signerField ?? 0) === (int) auth()->id(), 403);

        if (! str_starts_with($data['signature_data'], 'data:image/')) {
            return back()->withErrors(['signature_data' => 'Format tanda tangan tidak valid.']);
        }

        $batch->update([
            "sig_{$data['role']}"      => $data['signature_data'],
            "sig_{$data['role']}_at"   => now(),
        ]);

        return back()->with('success', 'Tanda tangan berhasil disimpan.');
    }

    public function exportEmployeeReportExcel(Request $request, int $employeeId): \Symfony\Component\HttpFoundation\Response
    {
        abort_if(
            ! in_array((string) auth()->user()->role, ['admin', 'hrd', 'manager'], true),
            403
        );

        if ((string) auth()->user()->role === 'hrd' && (int) auth()->user()->employee_id === $employeeId) {
            abort(403);
        }

        $employee = Employee::with('department')->findOrFail($employeeId);
        $periodId = $request->input('period_id');
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');
        $shownIds = array_map('intval', (array) $request->input('evaluator_ids', []));

        $appraisals = Appraisal::query()
            ->with(['appraiser:id,name', 'details.indicator', 'period:id,name'])
            ->where('employee_id', $employeeId)
            ->when($periodId, fn ($q) => $q->where('appraisal_period_id', $periodId))
            ->when($dateFrom, fn ($q) => $q->whereDate('date_appraised', '>=', $dateFrom))
            ->when($dateTo,   fn ($q) => $q->whereDate('date_appraised', '<=', $dateTo))
            ->whereIn('status', ['submitted', 'approved'])
            ->orderBy('date_appraised')
            ->orderBy('id')
            ->get();

        abort_if($appraisals->isEmpty(), 404);

        $evaluatorNumber = $appraisals->mapWithKeys(fn ($a, $idx) => [$a->id => $idx + 1])->toArray();

        $showAll   = empty($shownIds) && $request->isMethod('get');
        $showNames = $appraisals->mapWithKeys(function ($a) use ($shownIds, $showAll, $evaluatorNumber) {
            $num  = $evaluatorNumber[$a->id];
            return [$a->id => ($showAll || in_array($a->id, $shownIds))
                ? ($a->appraiser?->name ?? "Evaluator {$num}")
                : "Evaluator {$num}"];
        })->toArray();

        $indicatorIds = $appraisals->flatMap(fn ($a) => $a->details->pluck('appraisal_indicator_id'))->unique()->sort()->values();
        $indicators   = AppraisalIndicator::whereIn('id', $indicatorIds)->orderBy('id')->get();

        $matrix = $indicators->map(function ($ind) use ($appraisals) {
            $scores      = $appraisals->mapWithKeys(fn ($a) => [$a->id => $a->details->firstWhere('appraisal_indicator_id', $ind->id)?->score]);
            $validScores = $scores->filter(fn ($s) => $s !== null);
            return ['label' => $ind->question, 'scores' => $scores->toArray(), 'avg' => $validScores->isNotEmpty() ? round($validScores->avg(), 2) : null];
        })->values()->toArray();

        $evalAvgs = $appraisals->mapWithKeys(function ($a) use ($matrix) {
            $scores = collect($matrix)->map(fn ($r) => $r['scores'][$a->id] ?? null)->filter(fn ($s) => $s !== null);
            return [$a->id => $scores->isNotEmpty() ? round($scores->avg(), 2) : null];
        })->toArray();

        $overallAvg = collect($evalAvgs)->filter()->average();
        $overallAvg = $overallAvg ? round((float) $overallAvg, 2) : null;

        $evalCount = $appraisals->count();
        $lastCol   = Coordinate::stringFromColumnIndex($evalCount + 2);

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Appraisal');

        $sheet->setCellValue('A1', 'Laporan Appraisal Karyawan');
        $sheet->setCellValue('A2', 'Nama: ' . $employee->full_name);
        $sheet->setCellValue('A3', 'Departemen: ' . ($employee->department?->name ?? '-'));
        $sheet->setCellValue('A4', 'Jabatan: ' . ($employee->jabatan ?? '-'));
        $sheet->setCellValue('A5', 'Rata-rata Akhir: ' . ($overallAvg !== null ? number_format($overallAvg, 2) . ' (' . $this->scoreRatingLabel($overallAvg) . ')' : '-'));
        $sheet->setCellValue('A6', 'Tanggal Export: ' . now()->format('d M Y H:i'));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->mergeCells("A1:{$lastCol}1");

        $hRow = 8;
        $sheet->setCellValue('A' . $hRow, 'Kriteria Penilaian');
        foreach ($appraisals as $idx => $appr) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($idx + 2) . $hRow, $showNames[$appr->id]);
        }
        $sheet->setCellValue($lastCol . $hRow, 'Rata-rata');
        $sheet->getStyle("A{$hRow}:{$lastCol}{$hRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1E3A8A');
        $sheet->getStyle("A{$hRow}:{$lastCol}{$hRow}")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');

        foreach ($matrix as $mIdx => $mRow) {
            $r = $hRow + 1 + $mIdx;
            $sheet->setCellValue('A' . $r, $mRow['label']);
            foreach ($appraisals as $idx => $appr) {
                $s = $mRow['scores'][$appr->id] ?? null;
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($idx + 2) . $r, $s ?? '-');
            }
            $sheet->setCellValue($lastCol . $r, $mRow['avg'] !== null ? number_format($mRow['avg'], 2) : '-');
            if ($mIdx % 2 === 1) {
                $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF0F4FF');
            }
        }

        $avgRow = $hRow + 1 + count($matrix);
        $sheet->setCellValue('A' . $avgRow, 'Rata-rata Per Evaluator');
        foreach ($appraisals as $idx => $appr) {
            $ea = $evalAvgs[$appr->id] ?? null;
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($idx + 2) . $avgRow, $ea !== null ? number_format($ea, 2) : '-');
        }
        $sheet->setCellValue($lastCol . $avgRow, $overallAvg !== null ? number_format($overallAvg, 2) : '-');
        $sheet->getStyle("A{$avgRow}:{$lastCol}{$avgRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$avgRow}:{$lastCol}{$avgRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEFF6FF');

        $narrRow = $avgRow + 3;
        $sheet->setCellValue('A' . $narrRow, 'Ringkasan Narasi & Feedback');
        $sheet->getStyle('A' . $narrRow)->getFont()->setBold(true)->setSize(11);
        foreach (['Evaluator','Tanggal','Proposed Status','Saran','Kritik / Area Perbaikan','Catatan Lain'] as $hi => $hdr) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($hi + 1) . ($narrRow + 1), $hdr);
        }
        $sheet->getStyle('A' . ($narrRow + 1) . ':F' . ($narrRow + 1))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1E3A8A');
        $sheet->getStyle('A' . ($narrRow + 1) . ':F' . ($narrRow + 1))->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');

        foreach ($appraisals as $idx => $appr) {
            $r = $narrRow + 2 + $idx;
            $sheet->setCellValue('A' . $r, $showNames[$appr->id]);
            $sheet->setCellValue('B' . $r, $appr->date_appraised?->format('Y-m-d') ?? '-');
            $sheet->setCellValue('C' . $r, $this->formatProposedStatus($appr->proposed_status));
            $sheet->setCellValue('D' . $r, $appr->feedback_strengths ?? '-');
            $sheet->setCellValue('E' . $r, $appr->feedback_improvements ?? '-');
            $sheet->setCellValue('F' . $r, $appr->feedback_notes ?? '-');
            if ($idx % 2 === 1) {
                $sheet->getStyle("A{$r}:F{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF9FAFB');
            }
        }

        foreach (range(1, max($evalCount + 2, 7)) as $ci) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($ci))->setAutoSize(true);
        }

        $safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', $employee->full_name);
        $filename = "Appraisal_{$safeName}_" . now()->format('Ymd_His') . '.xlsx';
        $writer   = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function formatProposedStatus(?string $status): string
    {
        return match ($status) {
            're_contract'     => 'RE CONTRACT',
            'discontinue'     => 'DISCONTINUE THE CONTRACT',
            'promoted'        => 'PROMOTED',
            'permanent'       => 'CONFIRMATION OF PERMANENT WORKER',
            'position_change' => 'POSITION CHANGE',
            'pending'         => 'PENDING',
            default           => '-',
        };
    }

    private function scoreRatingLabel(?float $avg): string
    {
        if ($avg === null) return '-';
        return match (true) {
            $avg >= 4.0 => 'Sangat Baik',
            $avg >= 3.0 => 'Baik',
            $avg >= 2.0 => 'Cukup',
            default     => 'Kurang',
        };
    }

    public function index()
    {
        if (!Schema::hasTable('appraisals')) {
            return view('appraisals.index', [
                'appraisals' => $this->emptyPaginator(15),
                'moduleWarning' => 'Tabel appraisal belum tersedia di environment ini. Halaman dibuka dengan mode aman.',
            ]);
        }

        $appraisals = Appraisal::query()
            ->with(['employee', 'period', 'appraiser:id,name'])
            ->orderByRaw("CASE status WHEN 'draft' THEN 1 WHEN 'submitted' THEN 2 WHEN 'approved' THEN 3 ELSE 4 END")
            ->orderByRaw(Schema::hasColumn('appraisals', 'due_date') ? 'due_date asc' : 'id desc')
            ->orderByDesc('id')
            ->paginate(15);

        return view('appraisals.index', compact('appraisals'));
    }

    public function my(Request $request)
    {
        if (!Schema::hasTable('appraisals')) {
            return view('appraisals.my', [
                'appraisalGroups' => collect(),
                'moduleWarning' => 'Tabel appraisal belum tersedia di environment ini. Halaman dibuka dengan mode aman.',
            ]);
        }

        $user = $request->user();
        $employeeId = (int) ($user?->employee_id ?? 0);
        abort_unless($employeeId > 0, 403);

        $appraisals = Appraisal::query()
            ->with(['employee', 'period', 'componentScores'])
            ->where('employee_id', $employeeId)
            ->orderByDesc('date_appraised')
            ->orderByDesc('id')
            ->get();

        $appraisalGroups = $appraisals
            ->groupBy(fn (Appraisal $appraisal) => ($appraisal->period?->name ?? 'Tanpa Periode') . '#' . ($appraisal->appraisal_period_id ?? 0))
            ->map(function ($items) {
                $submittedItems = $items->whereIn('status', ['submitted', 'approved'])->values();
                return [
                    'period_name' => $items->first()?->period?->name ?? 'Tanpa Periode',
                    'employee_name' => $items->first()?->employee?->full_name ?? '-',
                    'rows' => $items->values(),
                    'submitted_count' => $submittedItems->count(),
                    'draft_count' => $items->where('status', 'draft')->count(),
                    'average_score' => $submittedItems->whereNotNull('final_score')->avg('final_score'),
                    'final_results' => $submittedItems->pluck('final_result')->filter()->unique()->values(),
                ];
            })
            ->values();

        return view('appraisals.my', compact('appraisalGroups'));
    }

    public function evaluator(Request $request)
    {
        if (!Schema::hasTable('appraisals')) {
            return view('appraisals.evaluator.index', [
                'appraisals' => $this->emptyPaginator(15),
                'moduleWarning' => 'Tabel appraisal belum tersedia di environment ini. Halaman dibuka dengan mode aman.',
            ]);
        }

        $user = $request->user();
        $appraisals = Appraisal::query()
            ->with(['employee', 'period'])
            ->where('appraiser_id', $user->id)
            ->orderByRaw("CASE status WHEN 'draft' THEN 1 WHEN 'submitted' THEN 2 WHEN 'approved' THEN 3 ELSE 4 END")
            ->orderByRaw(Schema::hasColumn('appraisals', 'due_date') ? 'due_date asc' : 'id desc')
            ->orderByDesc('id')
            ->paginate(15);

        return view('appraisals.evaluator.index', compact('appraisals'));
    }

    public function guide()
    {
        return view('appraisals.guide', ['bands' => AppraisalGrading::BANDS]);
    }

    public function show(Appraisal $appraisal, Request $request)
    {
        if (!Schema::hasTable('appraisal_indicators')) {
            return redirect()->route('appraisals.index')->with('error', 'Data indikator appraisal belum siap di environment ini.');
        }

        $user = $request->user();
        $viewerMode = $this->resolveViewerMode($appraisal, $user);
        abort_if($viewerMode === null, 403);

        $appraisal->loadMissing([
            'employee',
            'period',
            'appraiser:id,name',
            'invitedBy:id,name',
            'details.indicator',
            'componentScores',
            'invitationLogs.actor:id,name',
            'invitationLogs.target:id,name',
        ]);

        $existing = Schema::hasTable('appraisal_details') ? $appraisal->details->keyBy('appraisal_indicator_id') : collect();

        // Scope indicators to the template snapshotted when this appraisal was
        // created. Legacy appraisals (no snapshot) keep seeing all indicators.
        // Already-scored indicators are always included even if a later template
        // edit moved them elsewhere, so no historical answer is ever hidden.
        $templateId = Schema::hasColumn('appraisals', 'criteria_template_id') ? $appraisal->criteria_template_id : null;
        $indicatorQuery = AppraisalIndicator::orderBy('category')->orderBy('id');
        if ($templateId) {
            $indicatorQuery->where(function ($q) use ($templateId, $existing) {
                $q->where('template_id', $templateId);
                if ($existing->isNotEmpty()) {
                    $q->orWhereIn('id', $existing->keys());
                }
            });
        }
        $indicators = $indicatorQuery->get();

        $componentRows = $this->componentService->buildForAppraisal($appraisal);
        $categorySummary = $indicators
            ->groupBy(fn ($indicator) => (string) ($indicator->category ?: 'Umum'))
            ->map(function ($items) use ($existing) {
                $scores = collect($items)->map(fn ($indicator) => $existing->get($indicator->id)?->score)->filter(fn ($score) => $score !== null);
                return [
                    'count' => $items->count(),
                    'answered' => $scores->count(),
                    'average' => $scores->count() > 0 ? round((float) $scores->avg(), 2) : null,
                ];
            });

        $canEdit = $viewerMode === 'evaluator' && $appraisal->status !== 'approved';
        $employeeEvaluatorAlias = null;

        if ($viewerMode === 'employee') {
            $employeeEvaluatorAlias = 'Evaluator';

            if (Schema::hasTable('appraisals')) {
                $relatedAppraisalIds = Appraisal::query()
                    ->where('employee_id', $appraisal->employee_id)
                    ->when(
                        $appraisal->appraisal_period_id,
                        fn ($query) => $query->where('appraisal_period_id', $appraisal->appraisal_period_id),
                        fn ($query) => $query->whereNull('appraisal_period_id')
                    )
                    ->orderBy('id')
                    ->pluck('id')
                    ->values();

                $aliasIndex = $relatedAppraisalIds->search(fn ($id) => (int) $id === (int) $appraisal->id);
                $employeeEvaluatorAlias = 'Evaluator ' . (($aliasIndex === false ? 0 : (int) $aliasIndex) + 1);
            }
        }

        return view('appraisals.show', [
            'appraisal' => $appraisal,
            'indicators' => $indicators,
            'existing' => $existing,
            'viewerMode' => $viewerMode,
            'canEdit' => $canEdit,
            'categorySummary' => $categorySummary,
            'componentRows' => $componentRows,
            'employeeEvaluatorAlias' => $employeeEvaluatorAlias,
        ]);
    }

    public function assign(Request $request, Appraisal $appraisal): RedirectResponse
    {
        $data = $request->validate([
            'appraiser_id'    => ['required', 'exists:users,id'],
            'invitation_note' => ['nullable', 'string', 'max:500'],
        ]);

        $oldAppraiser = $appraisal->appraiser_id;

        $appraisal->update([
            'appraiser_id'       => $data['appraiser_id'],
            'invitation_note'    => $data['invitation_note'] ?? null,
            'invited_at'         => now(),
            'invited_by_user_id' => auth()->id(),
        ]);

        if (Schema::hasTable('appraisal_invitation_logs')) {
            AppraisalInvitationLog::create([
                'appraisal_id'   => $appraisal->id,
                'actor_user_id'  => auth()->id(),
                'target_user_id' => (int) $data['appraiser_id'],
                'action'         => 'reassigned',
                'notes'          => 'Re-assign dari user #' . $oldAppraiser . ' ke user #' . $data['appraiser_id'],
                'payload'        => [
                    'old_appraiser_id' => $oldAppraiser,
                    'new_appraiser_id' => (int) $data['appraiser_id'],
                ],
            ]);
        }

        $this->notifyAppraiser($appraisal->fresh(), false, auth()->id());

        return back()->with('success', 'Evaluator berhasil diperbarui.');
    }

    public function submit(Request $request, Appraisal $appraisal)
    {
        if (!Schema::hasTable('appraisal_details') || !Schema::hasTable('appraisal_indicators')) {
            return back()->with('error', 'Modul detail appraisal belum siap di environment ini.');
        }

        $user = $request->user();
        if ($appraisal->appraiser_id !== $user->id) {
            abort(403);
        }

        if ($appraisal->status === 'approved') {
            return back()->with('error', 'Appraisal yang sudah disetujui tidak dapat diubah lagi.');
        }

        $data = $request->validate([
            'scores'                    => 'required|array',
            'scores.*'                  => 'nullable|integer|min:1|max:5',
            'comments'                  => 'nullable|array',
            'comments.*'               => 'nullable|string|max:2000',
            'components'               => 'nullable|array',
            'components.*.score_raw'   => 'nullable|numeric|min:0|max:100',
            'components.*.score_normalized' => 'nullable|numeric|min:0|max:100',
            'components.*.weight'      => 'nullable|numeric|min:0|max:100',
            'components.*.notes'       => 'nullable|string|max:2000',
            'component_kpi_score'      => 'nullable|numeric|min:0|max:100',
            'component_kpi_notes'      => 'nullable|string|max:500',
            'component_training_entries' => 'nullable|string',
            'feedback_strengths'       => 'nullable|string|max:4000',
            'feedback_improvements'    => 'nullable|string|max:4000',
            'feedback_notes'           => 'nullable|string|max:4000',
            'final_result'             => 'nullable|string|max:50',
            'proposed_status'          => 'nullable|in:re_contract,discontinue,promoted,permanent,position_change,pending',
        ]);

        $indicators = AppraisalIndicator::all()->keyBy('id');

        DB::transaction(function () use ($appraisal, $data, $indicators) {
            // ── 1. Star-rating indicators ─────────────────────────────────────
            $appraisal->details()->delete();

            $totalWeight     = 0;
            $weightedStarSum = 0;

            foreach ($data['scores'] as $indicatorId => $score) {
                if ($score === null || $score === '') {
                    continue;
                }

                $indicator = $indicators->get((int) $indicatorId);
                if (! $indicator) {
                    continue;
                }

                $weight = (int) $indicator->weight;
                $totalWeight     += $weight;
                $weightedStarSum += ((int) $score) * $weight;

                AppraisalDetail::create([
                    'appraisal_id'           => $appraisal->id,
                    'appraisal_indicator_id' => (int) $indicatorId,
                    'score'                  => (int) $score,
                    'comment'                => $data['comments'][(string) $indicatorId] ?? null,
                ]);
            }

            // Weighted avg of star ratings (1-5 scale)
            $indicatorAvgScore = $totalWeight > 0 ? round($weightedStarSum / $totalWeight, 4) : null;

            if (! Schema::hasTable('appraisal_component_scores')) {
                // Fallback: use star avg directly as final score (1-5)
                $finalScore = $indicatorAvgScore;
                $appraisal->update([
                    'feedback_strengths'    => $data['feedback_strengths'] ?? null,
                    'feedback_improvements' => $data['feedback_improvements'] ?? null,
                    'feedback_notes'        => $data['feedback_notes'] ?? null,
                    'final_score'           => $finalScore,
                    'final_result'          => $data['final_result'] ?? ($finalScore !== null ? $this->scoreToResult($finalScore) : $appraisal->final_result),
                    'proposed_status'       => $data['proposed_status'] ?? $appraisal->proposed_status,
                    'status'                => 'submitted',
                    'submitted_at'          => now(),
                    'date_appraised'        => now()->toDateString(),
                ]);
                return;
            }

            // ── 2. KPI component (0-100 %) ────────────────────────────────────
            // If KPI is disabled on this appraisal, purge any stale DB row so it
            // won't contaminate the final-score formula below.
            if (! ($appraisal->enable_kpi_component ?? true)) {
                DB::table('appraisal_component_scores')
                    ->where('appraisal_id', $appraisal->id)
                    ->where('component_key', 'kpi')
                    ->delete();
            } elseif (isset($data['component_kpi_score'])) {
                $kpiScore = (float) $data['component_kpi_score'];
                DB::table('appraisal_component_scores')->updateOrInsert(
                    ['appraisal_id' => $appraisal->id, 'component_key' => 'kpi'],
                    [
                        'component_label'  => 'KPI — Pencapaian Target Kerja',
                        'source_type'      => 'manual_kpi',
                        'score_raw'        => $kpiScore,
                        'score_normalized' => $kpiScore,
                        'weight'           => AppraisalWeightConfig::loadFor($appraisal->period?->type)->toWeightsArray()['kpi'],
                        'notes'            => $data['component_kpi_notes'] ?? null,
                        'payload'          => null,
                        'updated_at'       => now(),
                        'created_at'       => now(),
                    ]
                );
            }

            // ── 3. Training multi-entry ───────────────────────────────────────
            if (isset($data['component_training_entries'])) {
                $entries      = json_decode((string) $data['component_training_entries'], true) ?? [];
                $validEntries = collect($entries)
                    ->filter(fn ($e) => ! empty($e['nama_materi']) && is_numeric($e['nilai'] ?? null))
                    ->values()
                    ->toArray();

                $avgScore = count($validEntries) > 0
                    ? round(collect($validEntries)->avg('nilai'), 2)
                    : null;

                DB::table('appraisal_component_scores')->updateOrInsert(
                    ['appraisal_id' => $appraisal->id, 'component_key' => 'training'],
                    [
                        'component_label'  => 'Hasil Training',
                        'source_type'      => 'training_multi',
                        'score_raw'        => $avgScore,
                        'score_normalized' => $avgScore,
                        'weight'           => AppraisalWeightConfig::loadFor($appraisal->period?->type)->toWeightsArray()['training'],
                        'payload'          => json_encode(['entries' => $validEntries]),
                        'notes'            => count($validEntries) . ' materi training',
                        'updated_at'       => now(),
                        'created_at'       => now(),
                    ]
                );
            }

            // ── 4. Skill / Position manual components (optional flags) ────────
            if (! empty($data['components'])) {
                $this->componentService->syncForAppraisal($appraisal, (array) $data['components']);
            }

            // ── 5. Final score — weights loaded from appraisal_weight_configs
            $appraisal->loadMissing('period');
            $wCfg = AppraisalWeightConfig::loadFor($appraisal->period?->type);
            $w    = $wCfg->toWeightsArray();

            $componentRows = DB::table('appraisal_component_scores')
                ->where('appraisal_id', $appraisal->id)
                ->whereIn('component_key', ['kpi', 'training', 'competency_skill', 'competency_position'])
                ->get()
                ->keyBy('component_key');

            // Kriteria Bintang: avg 1-5 → 0-100 (×20); always included when indicators exist
            $criteriaScore = $indicatorAvgScore !== null ? round($indicatorAvgScore * 20, 4) : 0.0;

            $kpiScoreVal  = (($appraisal->enable_kpi_component ?? true) && isset($componentRows['kpi']))
                ? (float) ($componentRows['kpi']->score_normalized ?? 0) : null;
            $trainScore   = isset($componentRows['training'])
                ? (float) ($componentRows['training']->score_normalized ?? 0) : null;
            $skillScore   = ($appraisal->enable_skill_component === true && isset($componentRows['competency_skill']))
                ? (float) ($componentRows['competency_skill']->score_normalized ?? 0) : null;
            $posScore     = ($appraisal->enable_position_component === true && isset($componentRows['competency_position']))
                ? (float) ($componentRows['competency_position']->score_normalized ?? 0) : null;

            // Dynamic weight normalization — skips disabled/missing components
            $weightedSum = $criteriaScore * $w['criteria'];
            $totalWeight = $w['criteria'];

            if ($kpiScoreVal !== null && $w['kpi'] > 0) {
                $weightedSum += $kpiScoreVal * $w['kpi'];
                $totalWeight += $w['kpi'];
            }
            if ($trainScore !== null && $w['training'] > 0) {
                $weightedSum += $trainScore * $w['training'];
                $totalWeight += $w['training'];
            }
            if ($skillScore !== null && $w['skill'] > 0) {
                $weightedSum += $skillScore * $w['skill'];
                $totalWeight += $w['skill'];
            }
            if ($posScore !== null && $w['position'] > 0) {
                $weightedSum += $posScore * $w['position'];
                $totalWeight += $w['position'];
            }

            $finalScore = $totalWeight > 0 ? round($weightedSum / $totalWeight, 2) : 0.0;

            $appraisal->update([
                'feedback_strengths'    => $data['feedback_strengths'] ?? null,
                'feedback_improvements' => $data['feedback_improvements'] ?? null,
                'feedback_notes'        => $data['feedback_notes'] ?? null,
                'final_score'           => $finalScore,
                'final_result'          => $data['final_result'] ?? ($finalScore > 0 ? $this->scoreToResult($finalScore / 20) : $appraisal->final_result),
                'proposed_status'       => $data['proposed_status'] ?? $appraisal->proposed_status,
                'status'                => 'submitted',
                'submitted_at'          => now(),
                'date_appraised'        => now()->toDateString(),
            ]);
        });

        return redirect()->route('appraisals.evaluator')->with('success', 'Appraisal berhasil dikirim. HRD dapat meninjau hasil setelah ini.');
    }

    public function approve(Request $request, Appraisal $appraisal)
    {
        $user = $request->user();
        if (!in_array((string) $user->role, ['admin', 'hrd'], true)) {
            abort(403);
        }

        $appraisal->update(['status' => 'approved']);

        return back()->with('success', 'Appraisal disetujui.');
    }

    public function remind(Request $request, Appraisal $appraisal)
    {
        $user = $request->user();
        if (!in_array((string) $user->role, ['admin', 'hrd'], true)) {
            abort(403);
        }

        if ($appraisal->status === 'approved') {
            return back()->with('error', 'Appraisal yang sudah approved tidak perlu di-remind lagi.');
        }

        $this->notifyAppraiser($appraisal, true, $user->id);

        if (Schema::hasColumn('appraisals', 'last_reminded_at')) {
            $appraisal->forceFill(['last_reminded_at' => now()])->save();
        }

        return back()->with('success', 'Reminder evaluator berhasil dikirim.');
    }

    public function extendDueDate(Request $request, Appraisal $appraisal)
    {
        $user = $request->user();
        if (!in_array((string) $user->role, ['admin', 'hrd'], true)) {
            abort(403);
        }

        $data = $request->validate([
            'new_due_date' => ['required', 'date'],
            'reason'       => ['required', 'string', 'max:500'],
        ]);

        $oldDueDate = $appraisal->due_date?->format('Y-m-d');
        $newDueDate = $data['new_due_date'];

        if ($oldDueDate === $newDueDate) {
            return back()->with('error', 'Due date baru sama dengan due date sebelumnya.');
        }

        $appraisal->update(['due_date' => $newDueDate]);

        if (Schema::hasTable('appraisal_invitation_logs')) {
            AppraisalInvitationLog::create([
                'appraisal_id'   => $appraisal->id,
                'actor_user_id'  => $user->id,
                'target_user_id' => $appraisal->appraiser_id,
                'action'         => 'due_date_extended',
                'notes'          => 'Due date diubah dari ' . ($oldDueDate ?? '-') . ' ke ' . $newDueDate . '. Alasan: ' . $data['reason'],
                'payload'        => [
                    'old_due_date' => $oldDueDate,
                    'new_due_date' => $newDueDate,
                    'reason'       => $data['reason'],
                ],
            ]);
        }

        return back()->with('success', "Due date berhasil diubah dari {$oldDueDate} ke {$newDueDate}.");
    }

    public function print(Appraisal $appraisal, Request $request)
    {
        $user = $request->user();
        $viewerMode = $this->resolveViewerMode($appraisal, $user);
        abort_if($viewerMode === null || $viewerMode === 'employee', 403);

        $appraisal->load(['employee', 'period', 'appraiser:id,name', 'details.indicator', 'componentScores']);
        $html = view('appraisals.print', compact('appraisal'))->render();

        try {
            if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('A4');
                return $pdf->stream('appraisal-' . $appraisal->id . '.pdf');
            }
        } catch (\Throwable $e) {
        }

        return response($html);
    }

    private function resolveViewerMode(Appraisal $appraisal, $user): ?string
    {
        if ((int) ($user->id ?? 0) === (int) $appraisal->appraiser_id) {
            return 'evaluator';
        }

        if (in_array((string) ($user->role ?? ''), ['admin', 'hrd'], true)) {
            return 'reviewer';
        }

        if ((int) ($user->employee_id ?? 0) > 0 && (int) $user->employee_id === (int) $appraisal->employee_id) {
            return 'employee';
        }

        return null;
    }

    private function notifyAppraiser(Appraisal $appraisal, bool $isReminder = false, ?int $actorUserId = null): void
    {
        if (!Schema::hasTable('hr_notifications')) {
            return;
        }

        $appraisal->loadMissing(['employee', 'period']);
        $columns = Schema::getColumnListing('hr_notifications');
        foreach (['type', 'title', 'unique_key'] as $column) {
            if (!in_array($column, $columns, true)) {
                return;
            }
        }

        $payload = [
            'type' => $isReminder ? 'appraisal_reminder' : 'appraisal_invitation',
            'title' => $isReminder ? 'Reminder pengisian appraisal' : 'Invitation evaluator appraisal',
            'unique_key' => ($isReminder ? 'appraisal-reminder-' : 'appraisal-invite-') . $appraisal->id . '-' . now()->format('YmdHi'),
        ];

        if (in_array('user_id', $columns, true)) {
            $payload['user_id'] = $appraisal->appraiser_id;
        }
        if (in_array('body', $columns, true)) {
            $employeeName = $appraisal->employee?->full_name ?? 'karyawan';
            $payload['body'] = $isReminder
                ? 'Mohon segera lengkapi appraisal untuk ' . $employeeName . ' agar review probation tidak tertunda.'
                : 'Anda ditunjuk sebagai evaluator appraisal untuk ' . $employeeName . '.';
        }
        if (in_array('due_date', $columns, true) && Schema::hasColumn('appraisals', 'due_date')) {
            $payload['due_date'] = $appraisal->due_date?->toDateString();
        }
        if (in_array('is_read', $columns, true)) {
            $payload['is_read'] = false;
        }
        if (in_array('meta', $columns, true)) {
            $payload['meta'] = [
                'appraisal_id' => $appraisal->id,
                'route' => route('appraisals.show', $appraisal),
                'period' => $appraisal->period?->name,
            ];
        }

        HrNotification::query()->create($payload);

        if (Schema::hasTable('appraisal_invitation_logs')) {
            AppraisalInvitationLog::query()->create([
                'appraisal_id' => $appraisal->id,
                'actor_user_id' => $actorUserId,
                'target_user_id' => $appraisal->appraiser_id,
                'action' => $isReminder ? 'reminded' : 'invited',
                'notes' => $isReminder ? 'Reminder evaluator dikirim.' : 'Invitation evaluator dibuat.',
                'payload' => [
                    'due_date' => $appraisal->due_date?->toDateString(),
                    'period' => $appraisal->period?->name,
                ],
            ]);
        }
    }

    private function scoreToResult(float $score): string
    {
        return AppraisalGrading::classify($score);
    }

    private function emptyPaginator(int $perPage): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, $perPage, 1, [
            'path' => url()->current(),
            'query' => request()->query(),
        ]);
    }
}



