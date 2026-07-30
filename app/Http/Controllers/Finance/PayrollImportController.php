<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\PayrollImportRow;
use App\Models\PayrollImportSession;
use App\Services\Finance\PayrollImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayrollImportController extends Controller
{
    public function __construct(
        private PayrollImportService $importService
    ) {}

    public function index()
    {
        $sessions = PayrollImportSession::with('importer')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('finance.import.index', compact('sessions'));
    }

    public function create()
    {
        return view('finance.import.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'payroll_file' => [
                'required',
                'file',
                'max:10240',
                function ($_attribute, $value, $fail) {
                    $ext = strtolower($value->getClientOriginalExtension());
                    if (! in_array($ext, ['csv', 'xlsx', 'xls'])) {
                        $fail('Format file tidak valid. Hanya CSV, XLSX, dan XLS yang diperbolehkan.');
                    }
                },
            ],
        ]);

        try {
            $session = $this->importService->processUpload(
                $request->file('payroll_file'),
                auth()->id()
            );
        } catch (\Exception $e) {
            return back()->withErrors(['payroll_file' => 'Gagal memproses file payroll: ' . $e->getMessage()]);
        }

        // Sync ke annual summary — terpisah dari import utama agar
        // kegagalan sync tidak membatalkan hasil import yang sudah tersimpan.
        try {
            [$tahun, $bulan] = array_map('intval', explode('-', $session->periode));
            $this->importService->syncToAnnualSummary($session->id, $tahun, $bulan);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('syncToAnnualSummary gagal', [
                'session_id' => $session->id,
                'error'      => $e->getMessage(),
            ]);
        }

        if ($session->status === 'needs_review') {
            return redirect()
                ->route('finance.import.show', $session)
                ->with('info', "Import selesai. Ditemukan {$session->updated_count} data perlu konfirmasi dan {$session->unmatched_count} data tidak cocok.");
        }

        return redirect()
            ->route('finance.import.index')
            ->with('success', "Import berhasil! " . ($session->new_count + $session->unmatched_count) . " data tersimpan.");
    }

    public function show(PayrollImportSession $session)
    {
        $search  = request('search', '');
        $sort    = request('sort', 'no_komp');
        $dir     = request('dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $perPage = request('per_page', '15');

        $allowedSorts = ['no_komp', 'nama', 'outlet_name_raw', 'gaji_pokok'];
        if (! in_array($sort, $allowedSorts)) {
            $sort = 'no_komp';
        }

        if ($perPage === 'all') {
            $perPageInt = 99999;
        } else {
            $perPageInt = in_array((int) $perPage, [15, 50, 100, 200]) ? (int) $perPage : 15;
        }

        $applyFilters = fn($query) => $query
            ->when($search, fn($q) => $q->where(function ($q2) use ($search) {
                $q2->where('no_komp', 'like', "%{$search}%")
                   ->orWhere('nama', 'like', "%{$search}%")
                   ->orWhere('outlet_name_raw', 'like', "%{$search}%");
            }))
            ->orderBy($sort, $dir);

        $pendingRows = $applyFilters(
            $session->rows()->where('row_status', 'update_pending')->with(['employee', 'outlet'])
        )->paginate($perPageInt, ['*'], 'pending_page');

        $unmatchedRows = $applyFilters(
            $session->rows()->where('row_status', 'unmatched')
        )->paginate($perPageInt, ['*'], 'unmatched_page');

        $newRows = $applyFilters(
            $session->rows()->where('row_status', 'new')->with('outlet')
        )->paginate($perPageInt, ['*'], 'new_page');

        return view('finance.import.review', compact(
            'session', 'pendingRows', 'unmatchedRows', 'newRows',
            'search', 'sort', 'dir', 'perPage'
        ));
    }

    public function destroy(PayrollImportSession $session)
    {
        DB::beginTransaction();
        try {
            DB::table('finance_bpjs_records')
                ->where('import_session_id', $session->id)
                ->update(['import_session_id' => null]);

            DB::table('payroll_import_rows')
                ->where('session_id', $session->id)
                ->delete();

            $periode = $session->periode;
            $brand   = $session->brand_label;
            $session->delete();

            DB::commit();
            return redirect()
                ->route('finance.import.index')
                ->with('success', "Sesi import {$periode} ({$brand}) berhasil dihapus.");
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menghapus sesi import: ' . $e->getMessage());
        }
    }

    public function confirmRow(Request $request, PayrollImportSession $session, PayrollImportRow $row)
    {
        abort_if($row->session_id !== $session->id, 404);

        if ($request->input('action') === 'skip') {
            $this->importService->skipRow($row, auth()->id());

            return back()->with('success', "Data '{$row->nama}' berhasil di-skip.");
        }

        $this->importService->confirmRow($row, auth()->id());

        return back()->with('success', "Data '{$row->nama}' berhasil diperbarui.");
    }

    public function confirmAll(PayrollImportSession $session)
    {
        $this->importService->confirmAll($session, auth()->id());

        return redirect()
            ->route('finance.import.index')
            ->with('success', "Semua perubahan periode {$session->periode} telah dikonfirmasi.");
    }
}
