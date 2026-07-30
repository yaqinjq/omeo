<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Outlet;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SlipGajiController extends Controller
{
    public function index(Request $request)
    {
        $periode  = $request->input('periode', now()->format('Y-m'));
        $outletId = $request->input('outlet_id') ? (int) $request->input('outlet_id') : null;
        $search   = trim((string) $request->input('search', ''));

        $availablePeriods = DB::table('finance_bpjs_records')
            ->whereNull('deleted_at')
            ->select('periode')
            ->distinct()
            ->orderByDesc('periode')
            ->pluck('periode');

        $outlets = Outlet::orderBy('name')->get(['id', 'name']);

        $employees = collect();
        $paginator = new LengthAwarePaginator([], 0, 25, 1, ['path' => $request->url()]);

        if ($availablePeriods->contains($periode)) {
            $noKomps = DB::table('finance_bpjs_records')
                ->where('periode', $periode)
                ->whereNull('deleted_at')
                ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
                ->select('no_komp')
                ->distinct()
                ->pluck('no_komp');

            $rows = collect();

            if ($noKomps->isNotEmpty()) {
                $placeholders = implode(',', array_fill(0, $noKomps->count(), '?'));
                $rows = DB::table('employees as e')
                    ->whereRaw("CAST(e.nokom AS UNSIGNED) IN ({$placeholders})", $noKomps->map(fn ($n) => (int) $n)->all())
                    ->when($search, fn ($q) => $q->where(function ($sub) use ($search) {
                        $sub->where('e.full_name', 'like', "%{$search}%")
                            ->orWhere('e.nokom', 'like', "%{$search}%")
                            ->orWhere('e.employee_number', 'like', "%{$search}%");
                    }))
                    ->orderBy('e.full_name')
                    ->select('e.id', 'e.full_name', 'e.nokom', 'e.employee_number', 'e.jabatan')
                    ->get();
            }

            $perPage     = 25;
            $currentPage = LengthAwarePaginator::resolveCurrentPage();
            $pageItems   = $rows->slice(($currentPage - 1) * $perPage, $perPage)->values();
            $paginator   = new LengthAwarePaginator(
                $pageItems,
                $rows->count(),
                $perPage,
                $currentPage,
                ['path' => $request->url(), 'query' => $request->except('page')]
            );
            $employees = $pageItems;
        }

        return view('finance.slip_gaji.index', [
            'periode'          => $periode,
            'availablePeriods' => $availablePeriods,
            'outlets'          => $outlets,
            'outletId'         => $outletId,
            'search'           => $search,
            'employees'        => $employees,
            'paginator'        => $paginator,
        ]);
    }

    public function pdf(Request $request, string $periode, int $employeeId)
    {
        $outletId = $request->input('outlet_id') ? (int) $request->input('outlet_id') : null;

        $employee = Employee::with(['department', 'bankAccounts' => fn ($q) => $q->where('is_primary', true)])
            ->findOrFail($employeeId);

        $records = DB::table('finance_bpjs_records')
            ->whereRaw('CAST(no_komp AS UNSIGNED) = CAST(? AS UNSIGNED)', [$employee->nokom])
            ->where('periode', $periode)
            ->whereNull('deleted_at')
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->get();

        abort_if($records->isEmpty(), 404, 'Tidak ada data payroll untuk karyawan ini pada periode tersebut.');

        $sum = fn (string $col) => (float) $records->sum(fn ($r) => (float) ($r->{$col} ?? 0));

        $gajiPokok  = $sum('gaji_pokok');
        $tunjangan  = $sum('tunjangan_total');
        $attd       = $sum('attd');
        $hr         = $sum('hr');
        $ot         = $sum('ot1_amount') + $sum('ot2_amount');
        $sExpense   = $sum('s_expense');
        $totalPendapatan = $gajiPokok + $tunjangan + $attd + $hr + $ot + $sExpense;

        $bpjsTk     = $sum('bpjs_tk_employee');
        $bpjsKes    = $sum('bpjs_jkes_employee');
        $potonganLain = $sum('potongan_total');
        $totalPotongan = $bpjsTk + $bpjsKes + $potonganLain;

        $takeHomePay = $totalPendapatan - $totalPotongan;

        $outletNames = $records->pluck('outlet_name')->filter()->unique()->values();
        $bankAccount = $employee->bankAccounts->first();

        [$year, $month] = explode('-', $periode);
        $bulanID = [
            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
            '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
            '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
        ];
        $periodeLabel = ($bulanID[$month] ?? $month) . ' ' . $year;

        $pdf = DomPdf::loadView('finance.slip_gaji.pdf', [
            'employee'         => $employee,
            'periodeLabel'     => $periodeLabel,
            'outletNames'      => $outletNames,
            'bankAccount'      => $bankAccount,
            'gajiPokok'        => $gajiPokok,
            'tunjangan'        => $tunjangan,
            'attd'             => $attd,
            'hr'               => $hr,
            'ot'               => $ot,
            'sExpense'         => $sExpense,
            'totalPendapatan'  => $totalPendapatan,
            'bpjsTk'           => $bpjsTk,
            'bpjsKes'          => $bpjsKes,
            'potonganLain'     => $potonganLain,
            'totalPotongan'    => $totalPotongan,
            'takeHomePay'      => $takeHomePay,
        ])->setPaper('a4', 'portrait');

        $safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', $employee->full_name);
        $filename = "Slip_Gaji_{$safeName}_{$periode}.pdf";

        if ($request->boolean('download')) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }
}
