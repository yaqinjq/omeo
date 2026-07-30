<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Outlet;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class AttendanceReportController extends Controller
{
    public function index(Request $request)
    {
        [$fromDate, $toDate, $range] = $this->resolveDateRange($request);
        $dateFrom = $fromDate->toDateString();
        $dateTo = $toDate->toDateString();

        if (! Schema::hasTable('attendance_sessions')) {
            return view('hrd.attendance.index', [
                'sessions' => $this->emptyPaginator($request, 31),
                'employees' => collect(),
                'outlets' => collect(),
                'departments' => collect(),
                'range' => $range,
                'fromDate' => $fromDate,
                'toDate' => $toDate,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'summary' => $this->emptySummary(),
                'moduleWarning' => 'Tabel presensi belum tersedia di environment ini. Halaman dibuka dengan mode aman agar tetap bisa diakses.',
            ]);
        }

        $query = AttendanceSession::query()
            ->with([
                'outlet:id,name,timezone',
                'user:id,name,email,employee_id',
                'user.employee:id,full_name,employee_number,department_id,outlet_id',
                'user.employee.department:id,name',
                'scans:id,attendance_session_id,scan_type,scanned_at_utc,distance_meters,is_within_geofence',
            ])
            ->whereBetween('work_date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->orderByDesc('work_date')
            ->orderBy('user_id');

        if ($request->filled('employee_id') && Schema::hasTable('employees')) {
            $employeeId = (int) $request->integer('employee_id');
            $query->whereHas('user.employee', function ($q) use ($employeeId) {
                $q->where('id', $employeeId);
            });
        }

        if ($request->filled('outlet_id')) {
            $query->where('outlet_id', (int) $request->integer('outlet_id'));
        }

        if ($request->filled('department_id') && Schema::hasTable('employees')) {
            $departmentId = (int) $request->integer('department_id');
            $query->whereHas('user.employee', function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            });
        }

        $sessions = $query->paginate(31)->withQueryString();
        $summary = $this->buildSummary(clone $query);

        return view('hrd.attendance.index', [
            'sessions' => $sessions,
            'employees' => Schema::hasTable('employees') ? Employee::query()->orderBy('full_name')->get(['id', 'full_name', 'employee_number']) : collect(),
            'outlets' => Schema::hasTable('outlets') ? Outlet::query()->operational()->orderBy('name')->get(['id', 'name']) : collect(),
            'departments' => Schema::hasTable('departments') ? Department::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'range' => $range,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'summary' => $summary,
            'moduleWarning' => null,
        ]);
    }

    public function exportPdf(Request $request)
    {
        if (! Schema::hasTable('attendance_sessions')) {
            return back()->with('error', 'Modul rekap presensi belum siap di environment ini. Export PDF belum bisa dijalankan.');
        }

        [$fromDate, $toDate, $range] = $this->resolveDateRange($request);

        $query = AttendanceSession::query()
            ->with([
                'outlet:id,name,timezone',
                'user:id,name,email,employee_id',
                'user.employee:id,full_name,employee_number,department_id',
                'scans:id,attendance_session_id,scan_type,scanned_at_utc,distance_meters,is_within_geofence',
            ])
            ->whereBetween('work_date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->orderBy('work_date')
            ->orderBy('user_id');

        $isFilteredByEmployee = $request->filled('employee_id') && Schema::hasTable('employees');

        if ($isFilteredByEmployee) {
            $employeeId = (int) $request->integer('employee_id');
            $query->whereHas('user.employee', function ($q) use ($employeeId) {
                $q->where('id', $employeeId);
            });
        }

        if ($request->filled('outlet_id')) {
            $query->where('outlet_id', (int) $request->integer('outlet_id'));
        }

        if ($request->filled('department_id') && Schema::hasTable('employees')) {
            $departmentId = (int) $request->integer('department_id');
            $query->whereHas('user.employee', function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            });
        }

        $rows = $query->get();
        $summary = $this->buildSummaryFromRows($rows);

        // Judul dokumen: nama karyawan cuma dipakai kalau memang difilter per-employee,
        // bukan asal ambil baris pertama seperti sebelumnya.
        $employeeName = $isFilteredByEmployee
            ? ($rows->first()?->user?->employee?->full_name ?: 'SEMUA KARYAWAN')
            : 'SEMUA KARYAWAN';
        $employeeNo = $isFilteredByEmployee
            ? ($rows->first()?->user?->employee?->employee_number ?: '-')
            : '-';

        // Kelompokkan per karyawan supaya tiap orang dapat halaman sendiri di PDF,
        // bukan tercampur dalam satu tabel panjang.
        $employeeGroups = $rows->groupBy(fn ($row) => $row->user_id)
            ->map(function ($groupRows) {
                $first = $groupRows->first();

                return [
                    'employeeName' => $first->user?->employee?->full_name ?: ($first->user?->name ?: 'TANPA NAMA'),
                    'employeeNo'   => $first->user?->employee?->employee_number ?: '-',
                    'rows'         => $groupRows,
                    'summary'      => $this->buildSummaryFromRows($groupRows),
                ];
            })
            ->sortBy('employeeName')
            ->values();

        $pdf = Pdf::loadView('hrd.attendance.pdf', [
            'employeeGroups' => $employeeGroups,
            'summary' => $summary,
            'range' => $range,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'employeeName' => $employeeName,
            'employeeNo' => $employeeNo,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('report-absen-' . $fromDate->format('Ymd') . '-' . $toDate->format('Ymd') . '.pdf');
    }

    /**
     * @return array{0:Carbon,1:Carbon,2:string}
     */
    private function resolveDateRange(Request $request): array
    {
        // Custom date_from/date_to selalu menang kalau salah satunya diisi manual,
        // terlepas dari preset range/tanggal acuan yang lama.
        if ($request->filled('date_from') || $request->filled('date_to')) {
            $from = Carbon::parse((string) $request->input('date_from', now()->startOfMonth()->toDateString()))->startOfDay();
            $to = Carbon::parse((string) $request->input('date_to', now()->toDateString()))->endOfDay();

            return [$from, $to, 'custom'];
        }

        // Halaman dibuka tanpa filter sama sekali → default bulan berjalan (awal bulan s.d. hari ini).
        if (! $request->filled('range') && ! $request->filled('date')) {
            $from = now()->startOfMonth()->startOfDay();
            $to = now()->endOfDay();

            return [$from, $to, 'custom'];
        }

        $range = (string) $request->query('range', 'day');
        if (! in_array($range, ['day', 'week', 'month', '3months'], true)) {
            $range = 'day';
        }

        $base = $request->filled('date')
            ? Carbon::parse((string) $request->query('date'))
            : now();

        $from = $base->copy()->startOfDay();
        $to = $base->copy()->endOfDay();

        if ($range === 'week') {
            $from = $base->copy()->startOfWeek();
            $to = $base->copy()->endOfWeek();
        } elseif ($range === 'month') {
            $from = $base->copy()->startOfMonth();
            $to = $base->copy()->endOfMonth();
        } elseif ($range === '3months') {
            $from = $base->copy()->subMonthsNoOverflow(2)->startOfMonth();
            $to = $base->copy()->endOfMonth();
        }

        return [$from, $to, $range];
    }

    private function buildSummary($query): array
    {
        $rows = $query->get([
            'id',
            'status',
            'total_work_minutes',
            'late_minutes',
            'early_leave_minutes',
            'overtime_minutes',
        ]);

        return [
            'kehadiran_hari' => (int) $rows->where('status', 'present')->count(),
            'total_hari' => $rows->count(),
            'total_jam_kerja_menit' => (int) $rows->sum('total_work_minutes'),
            'terlambat_kali' => (int) $rows->filter(fn ($row) => (int) $row->late_minutes > 0)->count(),
            'terlambat_menit' => (int) $rows->sum('late_minutes'),
            'pulang_awal_menit' => (int) $rows->sum('early_leave_minutes'),
            'overtime_menit' => (int) $rows->sum('overtime_minutes'),
            'libur_hari' => (int) $rows->where('status', 'off')->count(),
            'cuti_hari' => (int) $rows->where('status', 'leave')->count(),
            'incomplete_hari' => (int) $rows->where('status', 'incomplete')->count(),
            'absent_hari' => (int) $rows->where('status', 'absent')->count(),
        ];
    }

    private function buildSummaryFromRows($rows): array
    {
        return [
            'kehadiran_hari' => (int) $rows->where('status', 'present')->count(),
            'total_hari' => $rows->count(),
            'total_jam_kerja_menit' => (int) $rows->sum('total_work_minutes'),
            'terlambat_kali' => (int) $rows->filter(fn ($row) => (int) $row->late_minutes > 0)->count(),
            'terlambat_menit' => (int) $rows->sum('late_minutes'),
            'pulang_awal_menit' => (int) $rows->sum('early_leave_minutes'),
            'overtime_menit' => (int) $rows->sum('overtime_minutes'),
            'libur_hari' => (int) $rows->where('status', 'off')->count(),
            'cuti_hari' => (int) $rows->where('status', 'leave')->count(),
            'incomplete_hari' => (int) $rows->where('status', 'incomplete')->count(),
            'absent_hari' => (int) $rows->where('status', 'absent')->count(),
        ];
    }

    private function emptyPaginator(Request $request, int $perPage): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, $perPage, $request->integer('page', 1), [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
    }

    private function emptySummary(): array
    {
        return [
            'kehadiran_hari' => 0,
            'total_hari' => 0,
            'total_jam_kerja_menit' => 0,
            'terlambat_kali' => 0,
            'terlambat_menit' => 0,
            'pulang_awal_menit' => 0,
            'overtime_menit' => 0,
            'libur_hari' => 0,
            'cuti_hari' => 0,
            'incomplete_hari' => 0,
            'absent_hari' => 0,
        ];
    }
}
