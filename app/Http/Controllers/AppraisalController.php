<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Appraisal;
use App\Models\AppraisalBatchSignature;
use App\Models\AppraisalBatchSignatureSlot;
use App\Models\AppraisalDetail;
use App\Models\AppraisalEditRequest;
use App\Models\AppraisalCriteriaTemplate;
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
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AppraisalController extends Controller
{
    public function __construct(
        private readonly AppraisalComponentService $componentService,
        private readonly \App\Services\Notifications\UnifiedNotificationService $unifiedNotificationService,
    ) {
    }

    /**
     * Monitoring/Laporan/Detail Karyawan dulu tidak punya default filter
     * periode sama sekali — appraisal baru dan appraisal lama (termasuk hasil
     * migrasi "Historis MEO") tercampur jadi satu angka per karyawan begitu
     * saja (mis. 22 evaluator padahal yang diundang cuma 5-6). Kalau HRD
     * tidak pilih periode secara eksplisit, defaultkan ke periode yang
     * sedang aktif supaya angkanya masuk akal; "Semua Periode" tetap bisa
     * dipilih manual via ?period_id=all kalau memang mau lihat riwayat penuh.
     */
    private function resolveDefaultPeriodId(Request $request): ?int
    {
        // Belum ada query string period_id sama sekali (kunjungan pertama,
        // belum pernah submit filter) -> default ke periode aktif.
        // Query string period_id ADA tapi kosong ("" atau "all") -> HRD
        // memang sengaja pilih "Semua Periode", hormati itu, jangan dipaksa.
        if (! $request->has('period_id')) {
            return Schema::hasTable('appraisal_periods')
                ? AppraisalPeriod::where('is_active', true)->value('id')
                : null;
        }

        $requested = $request->input('period_id');

        if ($requested === null || $requested === '' || $requested === 'all') {
            return null;
        }

        return (int) $requested;
    }

    /**
     * Kirim email notifikasi appraisal ke satu user, memakai infra yang sudah
     * ada (UnifiedNotificationService + PlainTextNotificationMail) — dipanggil
     * $user=null supaya hanya kanal email yang jalan, notifikasi internal
     * (hr_notifications) tetap dibuat terpisah lewat kode yang sudah ada
     * supaya tidak dobel/berubah perilakunya.
     *
     * $variables diisi ke template yang HRD atur sendiri di Settings ->
     * Notifikasi (judul/isi pesan per event) - BUKAN teks final yang di-
     * hardcode di sini, supaya perubahan template beneran berpengaruh ke
     * email yang terkirim. Kalau HRD belum pernah mengubah template, fallback
     * ke default bawaan di NotificationSettingsService::defaultTemplates().
     */
    private function sendAppraisalEmail(string $eventKey, ?int $userId, array $variables): void
    {
        if (! $userId) {
            return;
        }

        $user = User::find($userId);
        $email = trim((string) ($user?->email ?: $user?->employee?->email_private ?: ''));
        if ($email === '') {
            return;
        }

        $variables['name'] ??= $user->name;

        $this->unifiedNotificationService->dispatch(
            eventKey: $eventKey,
            user: null,
            email: $email,
            whatsappNumber: '',
            payload: ['variables' => $variables]
        );
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

        $periodId = $this->resolveDefaultPeriodId($request);
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
            $sheet->setCellValue("G{$r}", $row->avg_score !== null ? round((float) $row->avg_score / 20, 2) : '-');
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

        if (! $request->boolean('download', true)) {
            return $pdf->stream($filename);
        }

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

        // Evaluator yang di-exclude HRD (included_in_score=false) sudah
        // dianggap tidak valid di halaman web (dinim + tidak ikut dihitung) —
        // laporan cetak/PDF adalah dokumen final, jadi evaluator itu tidak
        // boleh ikut tercetak sama sekali, bukan cuma tidak dihitung.
        $appraisals = $appraisals->where('included_in_score', true)->values();

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

        // Rata-rata mentah per-kriteria — dipakai hanya di baris "Rata-rata Per
        // Evaluator" tabel Matriks, konsisten dengan baris itu sendiri.
        $matrixOverallAvg = collect($evalAvgs)->filter()->average();
        $matrixOverallAvg = $matrixOverallAvg ? round((float) $matrixOverallAvg, 2) : null;

        // Nilai akhir resmi — SATU sumber kebenaran (final_score, sama persis
        // dengan halaman daftar & report_employee on-screen), bukan dihitung
        // ulang dari skor kriteria mentah saja.
        $finalScores   = $appraisals->pluck('final_score')->filter(fn ($v) => $v !== null);
        $overallAvg100 = $finalScores->isNotEmpty() ? round((float) $finalScores->avg(), 2) : null;
        $overallAvg    = $overallAvg100 !== null ? round($overallAvg100 / 20, 2) : null;
        $overallGrade  = $overallAvg !== null ? \App\Support\AppraisalGrading::classify($overallAvg) : null;

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

        // Batch tanda tangan digital (kalau sudah pernah dibuka lewat halaman
        // web) - export TIDAK membuat batch baru sendiri (read-only), supaya
        // export tidak punya efek samping menulis data.
        $sigBatch = null;
        if (Schema::hasTable('appraisal_batch_signatures')) {
            $sigBatch = AppraisalBatchSignature::where('employee_id', $employeeId)
                ->when($periodId, fn ($q) => $q->where('appraisal_period_id', $periodId))
                ->when(! $periodId, fn ($q) => $q->whereNull('appraisal_period_id'))
                ->latest()
                ->first();
            $sigBatch?->load(['slots.signerUser']);
        }

        return compact(
            'employee', 'appraisals', 'periodName', 'dateRange',
            'matrix', 'evalAvgs', 'matrixOverallAvg', 'overallAvg', 'overallGrade',
            'evaluatorCount', 'approvedCount', 'evaluatorNames', 'evaluatorNumber', 'showNames',
            'narratives', 'consensusStatus', 'latestAppraisal', 'appName', 'sigBatch'
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
        $periodId = $this->resolveDefaultPeriodId($request);
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

        // Evaluator yang sudah ditugaskan tapi belum submit — halaman ini
        // dulunya cuma menampilkan yang SUDAH mengisi, jadi HRD tidak punya
        // cara lihat siapa yang masih perlu di-remind dari Monitoring.
        $pendingAppraisals = Appraisal::query()
            ->with('appraiser:id,name')
            ->where('employee_id', $employeeId)
            ->where('status', 'draft')
            ->when($periodId, fn ($q) => $q->where('appraisal_period_id', $periodId))
            ->orderBy('due_date')
            ->get();

        $indicatorIds = $appraisals
            ->flatMap(fn ($a) => $a->details->pluck('appraisal_indicator_id'))
            ->unique()->sort()->values();
        $indicators = AppraisalIndicator::whereIn('id', $indicatorIds)->orderBy('id')->get();

        $includedIds = $appraisals->where('included_in_score', true)->pluck('id');

        $matrix = $indicators->map(function ($ind) use ($appraisals, $includedIds) {
            $scores = $appraisals->mapWithKeys(fn ($a) => [
                $a->id => $a->details->firstWhere('appraisal_indicator_id', $ind->id)?->score,
            ]);
            $validScores = $scores->filter(fn ($s, $appraisalId) => $s !== null && $includedIds->contains($appraisalId));
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

        // Rata-rata mentah per-kriteria (dipakai HANYA di dalam tabel Matriks
        // Section 1, konsisten dengan baris "RATA-RATA PER EVALUATOR" di tabel
        // yang sama — bukan angka akhir yang ditampilkan di kartu besar).
        $matrixOverallAvg = collect($evalAvgs)
            ->filter(fn ($avg, $appraisalId) => $avg !== null && $includedIds->contains($appraisalId))
            ->average();
        $matrixOverallAvg = $matrixOverallAvg ? round((float) $matrixOverallAvg, 2) : null;

        // Nilai akhir resmi appraisal — SATU sumber kebenaran (final_score,
        // sudah termasuk KPI/Training/Skill/Posisi kalau diisi), sama persis
        // dengan yang dipakai di halaman daftar (Appraisal::final_score).
        // Ditampilkan dalam skala 1-5 supaya konsisten dengan klasifikasi
        // 6-tingkat AppraisalGrading di semua halaman (tidak lagi dihitung
        // ulang secara terpisah dari skor kriteria mentah).
        $includedFinalScores = $appraisals
            ->filter(fn ($a) => $includedIds->contains($a->id) && $a->final_score !== null)
            ->pluck('final_score');
        $overallAvg100 = $includedFinalScores->isNotEmpty() ? round((float) $includedFinalScores->avg(), 2) : null;
        $overallAvg    = $overallAvg100 !== null ? round($overallAvg100 / 20, 2) : null;
        $overallGrade  = $overallAvg !== null ? \App\Support\AppraisalGrading::classify($overallAvg) : null;

        $evaluatorNumber = $appraisals->mapWithKeys(fn ($a, $idx) => [$a->id => $idx + 1])->toArray();

        $narratives = $appraisals->map(fn ($a) => [
            'no'                => $evaluatorNumber[$a->id],
            'name'              => $a->appraiser?->name ?? 'Evaluator',
            'date'              => $a->date_appraised?->format('Y-m-d') ?? '-',
            'proposed_status'   => $this->formatProposedStatus($a->proposed_status),
            'strengths'         => $a->feedback_strengths ?? '-',
            'improvements'      => $a->feedback_improvements ?? '-',
            'notes'             => $a->feedback_notes ?? '-',
            'included_in_score' => $a->included_in_score,
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

                // Default 3 slot: Karyawan (auto, tidak bisa dipilih manual —
                // langsung terikat ke akun login karyawan yang dinilai), lalu
                // 2 slot kategori yang bisa diubah HRD (default PIC & Manager).
                // Bisa ditambah sampai maksimal 4 lewat tombol "+ Tambah TTD".
                $employee->loadMissing('user');
                $slotNow = now();
                AppraisalBatchSignatureSlot::insert([
                    [
                        'batch_signature_id' => $sigBatch->id,
                        'slot_order'          => 1,
                        'slot_type'           => 'employee',
                        'category'            => null,
                        'label'               => 'Karyawan',
                        'signer_user_id'      => $employee->user?->id,
                        'external_name'       => null,
                        'signature_data'      => null,
                        'signed_at'           => null,
                        'created_at'          => $slotNow,
                        'updated_at'          => $slotNow,
                    ],
                    [
                        'batch_signature_id' => $sigBatch->id,
                        'slot_order'          => 2,
                        'slot_type'           => 'category',
                        'category'            => 'pic',
                        'label'               => AppraisalBatchSignatureSlot::CATEGORIES['pic'],
                        'signer_user_id'      => null,
                        'external_name'       => null,
                        'signature_data'      => null,
                        'signed_at'           => null,
                        'created_at'          => $slotNow,
                        'updated_at'          => $slotNow,
                    ],
                    [
                        'batch_signature_id' => $sigBatch->id,
                        'slot_order'          => 3,
                        'slot_type'           => 'category',
                        'category'            => 'manager',
                        'label'               => AppraisalBatchSignatureSlot::CATEGORIES['manager'],
                        'signer_user_id'      => null,
                        'external_name'       => null,
                        'signature_data'      => null,
                        'signed_at'           => null,
                        'created_at'          => $slotNow,
                        'updated_at'          => $slotNow,
                    ],
                ]);
            }

            if ($sigBatch) {
                $sigBatch->load(['slots.signerUser']);
            }
        }

        $periods  = AppraisalPeriod::orderByDesc('created_at')->get();
        $allUsers = User::orderBy('name')->get(['id', 'name', 'role']);
        $categoryCandidates = $this->resolveCategoryCandidates();

        $periodName = $periodId
            ? ($appraisals->first()?->period?->name ?? $periods->firstWhere('id', $periodId)?->name ?? 'Semua Periode')
            : 'Semua Periode';

        $pendingEditRequests = Schema::hasTable('appraisal_edit_requests')
            ? AppraisalEditRequest::whereIn('appraisal_id', $appraisals->pluck('id'))
                ->where('status', 'pending')
                ->with('requestedBy:id,name')
                ->get()
                ->keyBy('appraisal_id')
            : collect();

        $editRequestCounts = Schema::hasTable('appraisal_edit_requests')
            ? AppraisalEditRequest::whereIn('appraisal_id', $appraisals->pluck('id'))
                ->where('status', 'approved')
                ->get()
                ->groupBy('appraisal_id')
                ->map->count()
            : collect();

        return view('appraisals.report_employee', compact(
            'employee', 'appraisals', 'pendingAppraisals', 'matrix', 'evalAvgs', 'matrixOverallAvg',
            'overallAvg', 'overallGrade',
            'evaluatorNumber', 'narratives', 'consensusStatus', 'latestAppraisal',
            'sigBatch', 'periods', 'allUsers', 'categoryCandidates',
            'periodId', 'periodName', 'dateFrom', 'dateTo', 'dateMin', 'dateMax',
            'pendingEditRequests', 'editRequestCounts'
        ));
    }

    /**
     * Untuk tiap kategori penanda tangan (PIC/HRD/Supervisor/Manager/
     * Director), cari karyawan yang Posisi-nya cocok (heuristik nama Posisi,
     * mis. "PIC" untuk kategori pic). Position/department memang idealnya
     * jadi sumber filter (sesuai arahan), tapi datanya belum banyak diisi
     * HRD saat ini — kalau tidak ada yang cocok, kembalikan seluruh user
     * sebagai fallback (filtered=false) supaya dropdown tidak kosong.
     */
    private function resolveCategoryCandidates(): array
    {
        $allUsers = User::orderBy('name')->get(['id', 'name', 'role']);

        $patterns = [
            'pic'        => ['pic'],
            'hrd'        => ['hrd', 'human resource', 'personalia'],
            'supervisor' => ['supervisor', 'spv'],
            'manager'    => ['manager', 'aspv', 'asm', 'head'],
            'director'   => ['director', 'direktur'],
        ];

        $result = [];
        foreach ($patterns as $category => $needles) {
            $matched = User::query()
                ->whereHas('employee.position', function ($q) use ($needles) {
                    $q->where(function ($qq) use ($needles) {
                        foreach ($needles as $needle) {
                            $qq->orWhere('name', 'LIKE', "%{$needle}%");
                        }
                    });
                })
                ->orderBy('name')
                ->get(['id', 'name', 'role']);

            $result[$category] = $matched->isNotEmpty()
                ? ['users' => $matched, 'filtered' => true]
                : ['users' => $allUsers, 'filtered' => false];
        }

        return $result;
    }

    public function saveSigner(Request $request, int $employeeId): RedirectResponse
    {
        abort_if(! Schema::hasTable('appraisal_batch_signature_slots'), 404);
        abort_if(! in_array((string) auth()->user()->role, ['admin', 'hrd'], true), 403);

        $data = $request->validate([
            'slot_id'        => ['required', 'integer', 'exists:appraisal_batch_signature_slots,id'],
            'category'       => ['nullable', Rule::in(array_keys(AppraisalBatchSignatureSlot::CATEGORIES))],
            'signer_user_id' => ['nullable', 'exists:users,id'],
            'external_name'  => ['nullable', 'string', 'max:150'],
        ]);

        $slot = AppraisalBatchSignatureSlot::with('batch')->findOrFail($data['slot_id']);
        abort_unless((int) $slot->batch->employee_id === $employeeId, 403);

        // Slot "Karyawan" (slot_order pertama, auto-bound ke akun karyawan
        // bersangkutan) tidak bisa diubah kategorinya lewat form ini.
        if ($slot->slot_type === 'employee') {
            abort(403, 'Slot Karyawan tidak bisa diubah manual.');
        }

        $category = $data['category'] ?? $slot->category;
        $isManual = $category === 'owner_in_charge';

        $update = [
            'category'  => $category,
            'label'     => AppraisalBatchSignatureSlot::CATEGORIES[$category] ?? $slot->label,
            'slot_type' => $isManual ? 'manual' : 'category',
        ];

        if ($isManual) {
            $update['signer_user_id'] = null;
            $update['external_name'] = $data['external_name']
                ?? $slot->batch->employee?->outlet?->owner_in_charge_name
                ?? null;
        } else {
            $update['signer_user_id'] = $data['signer_user_id'] ?: null;
            $update['external_name'] = null;
        }

        $slot->update($update);

        return back()->with('success', 'Signer berhasil disimpan.');
    }

    public function addSignatureSlot(Request $request, int $employeeId): RedirectResponse
    {
        abort_if(! Schema::hasTable('appraisal_batch_signature_slots'), 404);
        abort_if(! in_array((string) auth()->user()->role, ['admin', 'hrd'], true), 403);

        $data = $request->validate([
            'batch_id' => ['required', 'integer', 'exists:appraisal_batch_signatures,id'],
        ]);

        $batch = AppraisalBatchSignature::findOrFail($data['batch_id']);
        abort_unless((int) $batch->employee_id === $employeeId, 403);

        $currentCount = AppraisalBatchSignatureSlot::where('batch_signature_id', $batch->id)->count();
        if ($currentCount >= AppraisalBatchSignatureSlot::MAX_SLOTS) {
            return back()->with('error', 'Maksimal ' . AppraisalBatchSignatureSlot::MAX_SLOTS . ' penanda tangan per appraisal.');
        }

        $nextOrder = (int) AppraisalBatchSignatureSlot::where('batch_signature_id', $batch->id)->max('slot_order') + 1;

        AppraisalBatchSignatureSlot::create([
            'batch_signature_id' => $batch->id,
            'slot_order'          => $nextOrder,
            'slot_type'           => 'category',
            'category'            => null,
            'label'               => 'Belum dipilih',
        ]);

        return back()->with('success', 'Slot tanda tangan baru ditambahkan. Silakan pilih kategorinya.');
    }

    public function saveSignature(Request $request, int $employeeId): RedirectResponse
    {
        abort_if(! Schema::hasTable('appraisal_batch_signature_slots'), 404);

        $data = $request->validate([
            'slot_id'        => ['required', 'integer', 'exists:appraisal_batch_signature_slots,id'],
            'signature_data' => ['required', 'string'],
        ]);

        $slot = AppraisalBatchSignatureSlot::with('batch')->findOrFail($data['slot_id']);
        abort_unless((int) $slot->batch->employee_id === $employeeId, 403);
        abort_unless((int) ($slot->signer_user_id ?? 0) === (int) auth()->id(), 403);

        if (! str_starts_with($data['signature_data'], 'data:image/')) {
            return back()->withErrors(['signature_data' => 'Format tanda tangan tidak valid.']);
        }

        $slot->update([
            'signature_data' => $data['signature_data'],
            'signed_at'      => now(),
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

        // Evaluator yang di-exclude HRD (included_in_score=false) tidak boleh
        // ikut tercetak di laporan Excel, sama seperti laporan PDF.
        $appraisals = $appraisals->where('included_in_score', true)->values();

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

        $matrixOverallAvg = collect($evalAvgs)->filter()->average();
        $matrixOverallAvg = $matrixOverallAvg ? round((float) $matrixOverallAvg, 2) : null;

        $finalScores   = $appraisals->pluck('final_score')->filter(fn ($v) => $v !== null);
        $overallAvg100 = $finalScores->isNotEmpty() ? round((float) $finalScores->avg(), 2) : null;
        $overallAvg    = $overallAvg100 !== null ? round($overallAvg100 / 20, 2) : null;
        $overallGrade  = $overallAvg !== null ? \App\Support\AppraisalGrading::classify($overallAvg) : null;

        $evalCount = $appraisals->count();
        $lastCol   = Coordinate::stringFromColumnIndex($evalCount + 2);

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Appraisal');

        $sheet->setCellValue('A1', 'Laporan Appraisal Karyawan');
        $sheet->setCellValue('A2', 'Nama: ' . $employee->full_name);
        $sheet->setCellValue('A3', 'Departemen: ' . ($employee->department?->name ?? '-'));
        $sheet->setCellValue('A4', 'Jabatan: ' . ($employee->jabatan ?? '-'));
        $sheet->setCellValue('A5', 'Rata-rata Akhir: ' . ($overallAvg !== null ? number_format($overallAvg, 2) . ' (' . $overallGrade . ')' : '-'));
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
        $sheet->setCellValue($lastCol . $avgRow, $matrixOverallAvg !== null ? number_format($matrixOverallAvg, 2) : '-');
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

    public function index(Request $request)
    {
        if (!Schema::hasTable('appraisals')) {
            return view('appraisals.index', [
                'paginator' => $this->emptyPaginator(15),
                'moduleWarning' => 'Tabel appraisal belum tersedia di environment ini. Halaman dibuka dengan mode aman.',
                'departments' => collect(), 'search' => null, 'departmentId' => null,
                'aggStatus' => null, 'overdueOnly' => false, 'triggerSource' => null,
                'periods' => collect(), 'periodId' => null,
            ]);
        }

        $search        = trim((string) $request->input('search', ''));
        $departmentId  = $request->input('department_id');
        $aggStatus     = $request->input('agg_status');
        $overdueOnly   = $request->boolean('overdue_only');
        $triggerSource = $request->input('trigger_source');
        $periodId      = $this->resolveDefaultPeriodId($request);
        $periods       = Schema::hasTable('appraisal_periods') ? AppraisalPeriod::orderByDesc('created_at')->get() : collect();

        $allAppraisals = Appraisal::query()
            ->with(['employee:id,full_name,employee_number,jabatan,department_id', 'employee.department:id,name', 'period:id,name', 'appraiser:id,name'])
            ->when($periodId, fn ($q) => $q->where('appraisal_period_id', $periodId))
            ->when($search, fn ($q) => $q->whereHas('employee', fn ($eq) =>
                $eq->where('full_name', 'like', "%{$search}%")
                   ->orWhere('jabatan', 'like', "%{$search}%")
                   ->orWhere('employee_number', 'like', "%{$search}%")
            ))
            ->when($departmentId, fn ($q) => $q->whereHas('employee', fn ($eq) => $eq->where('department_id', $departmentId)))
            ->when($triggerSource, fn ($q) => $q->where('trigger_source', $triggerSource))
            ->orderBy('employee_id')
            ->orderBy('id')
            ->get();

        $groupedAll = $allAppraisals->groupBy('employee_id')
            ->map(function ($items, $empId) {
                $emp = $items->first()->employee;

                $total          = $items->count();
                $submittedTotal = $items->whereIn('status', ['submitted', 'approved'])->count();
                $draftCount     = $items->where('status', 'draft')->count();
                $approvedCount  = $items->where('status', 'approved')->count();

                $aggStatus = $submittedTotal === $total
                    ? 'complete'
                    : ($submittedTotal > 0 ? 'partial' : 'none');

                $pendingDueDates = $items->where('status', '!=', 'approved')->pluck('due_date')->filter();
                $nearestDueDate  = $pendingDueDates->sort()->first();
                $isOverdue       = $nearestDueDate && $nearestDueDate->isPast();

                return (object) [
                    'employee_id'      => $empId,
                    'employee_name'    => $emp?->full_name ?? '-',
                    'employee_number'  => $emp?->employee_number ?? '-',
                    'jabatan'          => $emp?->jabatan ?? '-',
                    'department_name'  => $emp?->department?->name ?? '-',
                    'period_name'      => $items->sortByDesc('id')->first()->period?->name ?? '-',
                    'total'            => $total,
                    'submitted_total'  => $submittedTotal,
                    'draft_count'      => $draftCount,
                    'approved_count'   => $approvedCount,
                    'agg_status'       => $aggStatus,
                    'nearest_due_date' => $nearestDueDate,
                    'is_overdue'       => $isOverdue,
                    'trigger_sources'  => $items->pluck('trigger_source')->filter()->unique()->values(),
                    // period_id selalu disertakan eksplisit (bukan array_filter) supaya
                    // halaman Detail konsisten dengan yang sedang dilihat di Monitoring -
                    // termasuk saat "Semua Periode" dipilih (null di sini, bukan dihilangkan
                    // dari URL, karena kalau dihilangkan reportEmployee() akan balik default
                    // ke periode aktif, bukan ikut menampilkan semua periode juga).
                    'report_url'       => route('appraisals.report-employee', ['employeeId' => $empId, 'period_id' => $periodId ?? 'all']),
                ];
            })
            ->when($aggStatus, fn ($c) => $c->where('agg_status', $aggStatus))
            ->when($overdueOnly, fn ($c) => $c->where('is_overdue', true))
            ->sortBy(fn ($row) => $row->nearest_due_date ?? \Illuminate\Support\Carbon::create(9999, 12, 31))
            ->values();

        $perPage     = 15;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $pageItems   = $groupedAll->slice(($currentPage - 1) * $perPage, $perPage)->values();
        $paginator   = new LengthAwarePaginator(
            $pageItems,
            $groupedAll->count(),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->except('page')]
        );

        $departments = Schema::hasTable('departments')
            ? \App\Models\Department::orderBy('name')->get(['id', 'name'])
            : collect();

        return view('appraisals.index', compact(
            'paginator', 'search', 'departmentId', 'aggStatus', 'overdueOnly', 'triggerSource', 'departments',
            'periods', 'periodId'
        ));
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
        // created. Legacy appraisals (no snapshot) resolve one now from the
        // employee's lokasi_kerja — same resolution used when new appraisals
        // are generated — instead of falling through to every template's
        // indicators concatenated together (the cause of HRD seeing what
        // looked like duplicate criteria on the evaluation form).
        // Already-scored indicators are always included even if a later template
        // edit moved them elsewhere, so no historical answer is ever hidden.
        $templateId = Schema::hasColumn('appraisals', 'criteria_template_id') ? $appraisal->criteria_template_id : null;
        if (! $templateId && Schema::hasTable('appraisal_criteria_templates')) {
            $templateId = AppraisalCriteriaTemplate::resolveFor($appraisal->employee?->lokasi_kerja)?->id;
        }
        $indicatorQuery = AppraisalIndicator::orderBy('category')->orderBy('id');
        if ($templateId) {
            $indicatorQuery->where(function ($q) use ($templateId, $existing) {
                $q->where('template_id', $templateId);
                if ($existing->isNotEmpty()) {
                    $q->orWhereIn('id', $existing->keys());
                }
            });
        } else {
            // Template gagal ter-resolve sama sekali (criteria_template_id
            // kosong DAN tidak ada template default untuk lokasi_kerja
            // karyawan ini) — jangan biarkan query tanpa filter, karena itu
            // akan menampilkan SEMUA indikator se-database termasuk kategori
            // "Historis MEO" (khusus data migrasi lama, bukan untuk dinilai
            // evaluator baru). Batasi ke indikator yang memang sudah pernah
            // dijawab di appraisal ini saja (kalau ada).
            $indicatorQuery->when(
                $existing->isNotEmpty(),
                fn ($q) => $q->whereIn('id', $existing->keys()),
                fn ($q) => $q->whereRaw('1 = 0')
            );
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

        $approvedUnusedEditRequest = Schema::hasTable('appraisal_edit_requests')
            ? AppraisalEditRequest::where('appraisal_id', $appraisal->id)
                ->where('status', 'approved')
                ->whereNull('used_at')
                ->latest('id')
                ->first()
            : null;

        $canEdit = $viewerMode === 'evaluator'
            && ($appraisal->status !== 'approved' || $approvedUnusedEditRequest !== null);

        $pendingEditRequest = $viewerMode === 'evaluator' && Schema::hasTable('appraisal_edit_requests')
            ? AppraisalEditRequest::where('appraisal_id', $appraisal->id)->where('status', 'pending')->latest('id')->first()
            : null;

        $editRequestsApprovedCount = Schema::hasTable('appraisal_edit_requests')
            ? AppraisalEditRequest::where('appraisal_id', $appraisal->id)->where('status', 'approved')->count()
            : 0;

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
            'pendingEditRequest' => $pendingEditRequest,
            'editRequestsApprovedCount' => $editRequestsApprovedCount,
            'approvedUnusedEditRequest' => $approvedUnusedEditRequest,
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

        $unusedEditRequest = Schema::hasTable('appraisal_edit_requests')
            ? AppraisalEditRequest::where('appraisal_id', $appraisal->id)
                ->where('status', 'approved')
                ->whereNull('used_at')
                ->latest('id')
                ->first()
            : null;

        if ($appraisal->status === 'approved' && ! $unusedEditRequest) {
            return back()->with('error', 'Appraisal yang sudah disetujui tidak dapat diubah lagi. Ajukan permintaan edit ke HRD dulu.');
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

        // Komentar wajib diisi untuk setiap kriteria yang diberi nilai —
        // dicek manual (bukan lewat rule bawaan) karena daftar kriteria per
        // appraisal dinamis (beda template per kategori karyawan).
        $missingComments = [];
        foreach ($data['scores'] as $indicatorId => $score) {
            if ($score === null || $score === '') {
                continue;
            }
            if (trim((string) ($data['comments'][$indicatorId] ?? '')) === '') {
                $missingComments[] = $indicatorId;
            }
        }

        if (! empty($missingComments)) {
            return back()->withInput()->withErrors([
                'comments' => 'Komentar wajib diisi untuk setiap kriteria yang sudah diberi nilai bintang.',
            ]);
        }

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

        if ($unusedEditRequest) {
            $unusedEditRequest->update(['used_at' => now()]);
        }

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

    public const REMINDER_COOLDOWN_HOURS = 24;

    public function remind(Request $request, Appraisal $appraisal)
    {
        $user = $request->user();
        if (!in_array((string) $user->role, ['admin', 'hrd'], true)) {
            abort(403);
        }

        if ($appraisal->status === 'approved') {
            return back()->with('error', 'Appraisal yang sudah approved tidak perlu di-remind lagi.');
        }

        // Cooldown 24 jam supaya tombol reminder tidak bisa dipencet berkali-kali
        // tanpa sengaja (dulu bisa spam, dan kalau dua klik jatuh di menit yang
        // sama malah bikin error 500 gara-gara unique_key notifikasi bentrok).
        if (
            Schema::hasColumn('appraisals', 'last_reminded_at')
            && $appraisal->last_reminded_at
            && $appraisal->last_reminded_at->diffInHours(now()) < self::REMINDER_COOLDOWN_HOURS
        ) {
            $nextAvailable = $appraisal->last_reminded_at->addHours(self::REMINDER_COOLDOWN_HOURS);
            return back()->with('error', 'Reminder untuk evaluator ini sudah dikirim ' . $appraisal->last_reminded_at->diffForHumans() . '. Bisa dikirim ulang mulai ' . $nextAvailable->format('d M Y H:i') . ' (jeda 24 jam supaya tidak ter-spam).');
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
        $this->notifyDueDateExtended($appraisal, $oldDueDate, $newDueDate);

        return back()->with('success', "Due date berhasil diubah dari {$oldDueDate} ke {$newDueDate}.");
    }

    /**
     * Perpanjang due date semua evaluator (yang belum approved) untuk satu
     * karyawan sekaligus, dipanggil dari halaman Laporan Appraisal per karyawan.
     */
    public function extendDueDateBulk(Request $request, int $employeeId): RedirectResponse
    {
        $user = $request->user();
        if (!in_array((string) $user->role, ['admin', 'hrd'], true)) {
            abort(403);
        }

        $data = $request->validate([
            'new_due_date' => ['required', 'date'],
            'reason'       => ['required', 'string', 'max:500'],
        ]);

        $appraisals = Appraisal::where('employee_id', $employeeId)
            ->where('status', '!=', 'approved')
            ->get();

        if ($appraisals->isEmpty()) {
            return back()->with('error', 'Tidak ada appraisal yang bisa diperpanjang due date-nya (semua sudah approved).');
        }

        foreach ($appraisals as $appraisal) {
            $oldDueDate = $appraisal->due_date?->format('Y-m-d');
            $appraisal->update(['due_date' => $data['new_due_date']]);

            if (Schema::hasTable('appraisal_invitation_logs')) {
                AppraisalInvitationLog::create([
                    'appraisal_id'   => $appraisal->id,
                    'actor_user_id'  => $user->id,
                    'target_user_id' => $appraisal->appraiser_id,
                    'action'         => 'due_date_extended',
                    'notes'          => 'Due date diubah dari ' . ($oldDueDate ?? '-') . ' ke ' . $data['new_due_date'] . ' (perpanjangan massal). Alasan: ' . $data['reason'],
                    'payload'        => [
                        'old_due_date' => $oldDueDate,
                        'new_due_date' => $data['new_due_date'],
                        'reason'       => $data['reason'],
                        'bulk'         => true,
                    ],
                ]);
            }
            $this->notifyDueDateExtended($appraisal, $oldDueDate, $data['new_due_date']);
        }

        return back()->with('success', "Due date {$appraisals->count()} evaluator berhasil diperpanjang ke {$data['new_due_date']}.");
    }

    /**
     * HRD isi keputusan final "Masa Kontrak Diperpanjang" + tanggal efektif
     * untuk satu karyawan (per periode appraisal). Tanggal efektif dihitung
     * otomatis di sisi browser (hari ini + durasi yang dipilih) tapi tetap
     * bisa diubah manual oleh HRD sebelum disimpan - field yang dikirim ke
     * sini adalah tanggal FINAL setelah (kalau perlu) disesuaikan HRD.
     * Disimpan di baris appraisal TERBARU untuk employee+periode ini, sama
     * seperti kolom proposed_contract_duration yang sudah dibaca PDF.
     */
    public function saveContractDecision(Request $request, int $employeeId): RedirectResponse
    {
        $user = $request->user();
        if (!in_array((string) $user->role, ['admin', 'hrd'], true)) {
            abort(403);
        }

        $data = $request->validate([
            'period_id'                          => ['nullable', 'integer'],
            'proposed_contract_duration'          => ['nullable', 'string', 'in:tidak_diperpanjang,3_bulan,6_bulan,1_tahun,2_tahun,custom'],
            'contract_extension_effective_date'   => ['nullable', 'date'],
        ]);

        $latestAppraisal = Appraisal::where('employee_id', $employeeId)
            ->whereIn('status', ['submitted', 'approved'])
            ->when($data['period_id'] ?? null, fn ($q) => $q->where('appraisal_period_id', $data['period_id']))
            ->orderByDesc('id')
            ->first();

        if (! $latestAppraisal) {
            return back()->with('error', 'Tidak ada appraisal untuk disimpan keputusannya.');
        }

        $latestAppraisal->update([
            'proposed_contract_duration'        => $data['proposed_contract_duration'] ?? null,
            'contract_extension_effective_date' => $data['contract_extension_effective_date'] ?? null,
        ]);

        return back()->with('success', 'Keputusan perpanjangan kontrak berhasil disimpan.');
    }

    /**
     * HRD instan include/exclude satu submission evaluator dari perhitungan
     * rata-rata gabungan karyawan, tanpa perlu evaluator terlibat. Dipakai saat
     * evaluator salah memberi nilai dan HRD perlu segera mengoreksi laporan.
     */
    public function toggleIncludeInScore(Request $request, Appraisal $appraisal): RedirectResponse
    {
        $user = $request->user();
        if (!in_array((string) $user->role, ['admin', 'hrd'], true)) {
            abort(403);
        }

        $newValue = ! $appraisal->included_in_score;
        $appraisal->update(['included_in_score' => $newValue]);

        if (Schema::hasTable('appraisal_invitation_logs')) {
            AppraisalInvitationLog::create([
                'appraisal_id'   => $appraisal->id,
                'actor_user_id'  => $user->id,
                'target_user_id' => $appraisal->appraiser_id,
                'action'         => $newValue ? 'score_included' : 'score_excluded',
                'notes'          => $newValue
                    ? 'Penilaian evaluator ini diikutkan kembali ke perhitungan rata-rata.'
                    : 'Penilaian evaluator ini dikecualikan dari perhitungan rata-rata karena dianggap tidak valid.',
            ]);
        }

        return back()->with('success', $newValue
            ? 'Penilaian evaluator diikutkan kembali ke perhitungan.'
            : 'Penilaian evaluator dikecualikan dari perhitungan.');
    }

    /**
     * HRD hapus undangan evaluator yang salah tambah (mis. salah pilih orang
     * karena nama sama dengan orang lain) SEBELUM evaluator itu mengisi
     * penilaian. Hanya diizinkan untuk status draft — begitu sudah submitted/
     * approved, datanya adalah penilaian asli dan harus dijaga (pakai fitur
     * Exclude, bukan hapus permanen).
     */
    public function removeEvaluator(Request $request, Appraisal $appraisal): RedirectResponse
    {
        $user = $request->user();
        if (!in_array((string) $user->role, ['admin', 'hrd'], true)) {
            abort(403);
        }

        if ($appraisal->status !== 'draft') {
            return back()->with('error', 'Evaluator ini sudah mengisi penilaian, tidak bisa dihapus. Gunakan fitur Exclude untuk mengecualikannya dari perhitungan.');
        }

        $evaluatorName = $appraisal->appraiser?->name ?? 'Evaluator';

        DB::transaction(function () use ($appraisal) {
            DB::table('appraisal_details')->where('appraisal_id', $appraisal->id)->delete();
            DB::table('appraisal_component_scores')->where('appraisal_id', $appraisal->id)->delete();
            DB::table('appraisal_invitation_logs')->where('appraisal_id', $appraisal->id)->delete();
            if (Schema::hasTable('appraisal_edit_requests')) {
                DB::table('appraisal_edit_requests')->where('appraisal_id', $appraisal->id)->delete();
            }
            $appraisal->delete();
        });

        return back()->with('success', "Undangan evaluator {$evaluatorName} berhasil dihapus.");
    }

    /**
     * Evaluator mengajukan izin edit ulang penilaian yang sudah di-approve HRD.
     * Maksimal 2 request yang disetujui per appraisal, dibatasi due date.
     */
    public function requestEdit(Request $request, Appraisal $appraisal): RedirectResponse
    {
        $user = $request->user();
        abort_unless((int) $appraisal->appraiser_id === (int) $user->id, 403);
        abort_unless($appraisal->status === 'approved', 400);

        if ($appraisal->due_date && now()->gt($appraisal->due_date)) {
            return back()->with('error', 'Due date appraisal ini sudah lewat, tidak bisa mengajukan edit lagi.');
        }

        $approvedCount = AppraisalEditRequest::where('appraisal_id', $appraisal->id)
            ->where('status', 'approved')
            ->count();
        if ($approvedCount >= AppraisalEditRequest::MAX_APPROVED_PER_APPRAISAL) {
            return back()->with('error', 'Jatah edit untuk appraisal ini sudah habis (maksimal ' . AppraisalEditRequest::MAX_APPROVED_PER_APPRAISAL . 'x). Hubungi HRD.');
        }

        $hasPending = AppraisalEditRequest::where('appraisal_id', $appraisal->id)->where('status', 'pending')->exists();
        if ($hasPending) {
            return back()->with('error', 'Masih ada permintaan edit yang menunggu persetujuan HRD.');
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $editRequest = AppraisalEditRequest::create([
            'appraisal_id'          => $appraisal->id,
            'requested_by_user_id'  => $user->id,
            'reason'                => $data['reason'],
            'status'                => 'pending',
        ]);

        $editRequestBody = ($user->name ?? 'Evaluator') . ' minta izin edit ulang penilaian untuk ' . ($appraisal->employee?->full_name ?? 'karyawan') . '. Alasan: ' . $data['reason'];

        if (Schema::hasTable('hr_notifications') && $appraisal->invited_by_user_id) {
            HrNotification::create([
                'user_id'    => $appraisal->invited_by_user_id,
                'type'       => 'appraisal_edit_request',
                'title'      => 'Permintaan edit penilaian appraisal',
                'body'       => $editRequestBody,
                'is_read'    => false,
                'unique_key' => 'appraisal-edit-request-' . $editRequest->id,
                'meta'       => ['appraisal_id' => $appraisal->id, 'route' => route('appraisals.report-employee', $appraisal->employee_id)],
            ]);
        }
        $this->sendAppraisalEmail('appraisal_edit_request', $appraisal->invited_by_user_id, [
            'evaluator_name' => $user->name ?? 'Evaluator',
            'employee_name'  => $appraisal->employee?->full_name ?? 'karyawan',
            'reason'         => $data['reason'],
        ]);

        return back()->with('success', 'Permintaan edit sudah dikirim ke HRD, tunggu persetujuan.');
    }

    public function approveEditRequest(Request $request, AppraisalEditRequest $editRequest): RedirectResponse
    {
        $user = $request->user();
        if (!in_array((string) $user->role, ['admin', 'hrd'], true)) {
            abort(403);
        }
        abort_unless($editRequest->status === 'pending', 400);

        $editRequest->update([
            'status'               => 'approved',
            'reviewed_by_user_id'  => $user->id,
            'reviewed_at'          => now(),
        ]);

        $appraisal = $editRequest->appraisal;

        if (Schema::hasTable('appraisal_invitation_logs')) {
            AppraisalInvitationLog::create([
                'appraisal_id'   => $appraisal->id,
                'actor_user_id'  => $user->id,
                'target_user_id' => $appraisal->appraiser_id,
                'action'         => 'edit_request_approved',
                'notes'          => 'HRD menyetujui permintaan edit. Alasan evaluator: ' . $editRequest->reason,
            ]);
        }

        $approvedBody = 'HRD menyetujui permintaan edit Anda untuk penilaian ' . ($appraisal->employee?->full_name ?? 'karyawan') . '. Segera edit sebelum due date' . ($appraisal->due_date ? ' (' . $appraisal->due_date->format('d-m-Y') . ')' : '') . '.';

        if (Schema::hasTable('hr_notifications')) {
            HrNotification::create([
                'user_id'    => $appraisal->appraiser_id,
                'type'       => 'appraisal_edit_approved',
                'title'      => 'Permintaan edit penilaian disetujui',
                'body'       => $approvedBody,
                'is_read'    => false,
                'unique_key' => 'appraisal-edit-approved-' . $editRequest->id,
                'meta'       => ['appraisal_id' => $appraisal->id, 'route' => route('appraisals.show', $appraisal->id)],
            ]);
        }
        $this->sendAppraisalEmail('appraisal_edit_approved', $appraisal->appraiser_id, [
            'employee_name' => $appraisal->employee?->full_name ?? 'karyawan',
            'due_date'      => $appraisal->due_date?->format('d-m-Y') ?? '-',
        ]);

        return back()->with('success', 'Permintaan edit disetujui. Evaluator sekarang bisa edit penilaiannya 1x.');
    }

    public function rejectEditRequest(Request $request, AppraisalEditRequest $editRequest): RedirectResponse
    {
        $user = $request->user();
        if (!in_array((string) $user->role, ['admin', 'hrd'], true)) {
            abort(403);
        }
        abort_unless($editRequest->status === 'pending', 400);

        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);

        $editRequest->update([
            'status'               => 'rejected',
            'reviewed_by_user_id'  => $user->id,
            'reviewed_at'          => now(),
            'review_note'          => $data['review_note'] ?? null,
        ]);

        $appraisal = $editRequest->appraisal;
        $rejectedBody = 'HRD menolak permintaan edit Anda untuk penilaian ' . ($appraisal->employee?->full_name ?? 'karyawan') . '.' . ($data['review_note'] ?? '' ? ' Catatan: ' . $data['review_note'] : '');

        if (Schema::hasTable('hr_notifications')) {
            HrNotification::create([
                'user_id'    => $appraisal->appraiser_id,
                'type'       => 'appraisal_edit_rejected',
                'title'      => 'Permintaan edit penilaian ditolak',
                'body'       => $rejectedBody,
                'is_read'    => false,
                'unique_key' => 'appraisal-edit-rejected-' . $editRequest->id,
                'meta'       => ['appraisal_id' => $appraisal->id, 'route' => route('appraisals.show', $appraisal->id)],
            ]);
        }
        $this->sendAppraisalEmail('appraisal_edit_rejected', $appraisal->appraiser_id, [
            'employee_name' => $appraisal->employee?->full_name ?? 'karyawan',
            'review_note'   => $data['review_note'] ?? '-',
        ]);

        return back()->with('success', 'Permintaan edit ditolak.');
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

    /**
     * Perpanjangan due date sebelumnya cuma tercatat di audit log — evaluator
     * tidak pernah diberitahu due date-nya berubah. Ditambahkan agar evaluator
     * tahu tanpa perlu buka aplikasi duluan.
     */
    private function notifyDueDateExtended(Appraisal $appraisal, ?string $oldDueDate, string $newDueDate): void
    {
        $appraisal->loadMissing('employee');
        $employeeName = $appraisal->employee?->full_name ?? 'karyawan';
        $body = 'Due date appraisal untuk ' . $employeeName . ' diperpanjang dari ' . ($oldDueDate ?? '-') . ' ke ' . $newDueDate . '.';

        if (Schema::hasTable('hr_notifications')) {
            HrNotification::create([
                'user_id'    => $appraisal->appraiser_id,
                'type'       => 'appraisal_due_date_extended',
                'title'      => 'Due date appraisal diperpanjang',
                'body'       => $body,
                'is_read'    => false,
                'unique_key' => 'appraisal-due-date-extended-' . $appraisal->id . '-' . now()->format('YmdHis') . '-' . uniqid(),
                'meta'       => ['appraisal_id' => $appraisal->id, 'route' => route('appraisals.show', $appraisal->id)],
            ]);
        }
        $this->sendAppraisalEmail('appraisal_due_date_extended', $appraisal->appraiser_id, [
            'employee_name' => $employeeName,
            'old_due_date'  => $oldDueDate ?? '-',
            'new_due_date'  => $newDueDate,
        ]);
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
            'unique_key' => ($isReminder ? 'appraisal-reminder-' : 'appraisal-invite-') . $appraisal->id . '-' . now()->format('YmdHis') . '-' . uniqid(),
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

        $employeeName = $appraisal->employee?->full_name ?? 'karyawan';
        $this->sendAppraisalEmail(
            $isReminder ? 'appraisal_reminder' : 'appraisal_invitation',
            $appraisal->appraiser_id,
            [
                'employee_name' => $employeeName,
                'due_date'      => $appraisal->due_date?->format('d-m-Y') ?? '-',
            ]
        );

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



