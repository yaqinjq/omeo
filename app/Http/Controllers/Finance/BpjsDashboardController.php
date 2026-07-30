<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\FinanceBpjsOutletSummary;
use App\Models\FinanceBpjsRecord;
use App\Models\Outlet;
use Illuminate\Http\Request;

class BpjsDashboardController extends Controller
{
    public function index(Request $request)
    {
        $currentMonth = now()->format('Y-m');
        $periodeFrom  = $request->get('periode_from', now()->subMonths(3)->format('Y-m'));
        $periodeTo    = $request->get('periode_to', $currentMonth);
        $outletId     = $request->get('outlet_id');

        // Ensure from <= to
        if ($periodeFrom > $periodeTo) {
            [$periodeFrom, $periodeTo] = [$periodeTo, $periodeFrom];
        }

        $recordsQuery = FinanceBpjsRecord::with(['employee', 'outlet'])
            ->whereBetween('periode', [$periodeFrom, $periodeTo]);

        if ($outletId) {
            $recordsQuery->where('outlet_id', $outletId);
        }

        $records = $recordsQuery->orderBy('outlet_name')->orderBy('nama')->paginate(50);

        $summaries = FinanceBpjsOutletSummary::with('outlet')
            ->whereBetween('periode', [$periodeFrom, $periodeTo])
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->get();

        $outlets = Outlet::orderBy('name')->get();

        $totalKaryawan  = $summaries->sum('total_karyawan');
        $totalTerdaftar = $summaries->sum('karyawan_terdaftar_bpjs');
        $totalTk        = $summaries->sum('total_bpjs_tk');
        $totalJkes      = $summaries->sum('total_bpjs_jkes');
        $totalBpjs      = $summaries->sum('total_bpjs_keseluruhan');

        return view('finance.bpjs.index', compact(
            'records', 'summaries', 'outlets', 'periodeFrom', 'periodeTo', 'outletId',
            'totalKaryawan', 'totalTerdaftar', 'totalTk', 'totalJkes', 'totalBpjs'
        ));
    }

    public function updatePaymentStatus(Request $request, FinanceBpjsOutletSummary $summary)
    {
        $request->validate([
            'status_bayar'    => ['required', 'in:belum_bayar,sudah_bayar,verified'],
            'tanggal_bayar'   => ['nullable', 'date'],
            'nomor_referensi' => ['nullable', 'string', 'max:100'],
            'catatan'         => ['nullable', 'string', 'max:500'],
        ]);

        $data = $request->only(['status_bayar', 'tanggal_bayar', 'nomor_referensi', 'catatan']);

        if ($request->status_bayar === 'verified') {
            $data['verified_by'] = auth()->id();
            $data['verified_at'] = now();
        }

        $summary->update($data);

        return back()->with('success', "Status pembayaran BPJS outlet {$summary->outlet?->name} berhasil diperbarui.");
    }
}
