<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\BpjsLegalAccount;
use App\Models\BpjsOfficialBill;
use App\Models\BpjsOfficialBillRow;
use App\Models\BpjsReconciliationResult;
use App\Models\LegalEntity;
use App\Services\Finance\BpjsBillMatcherService;
use App\Services\Finance\BpjsLegalEntityResolverService;
use App\Services\Finance\BpjsOfficialBillParserService;
use App\Services\Finance\BpjsReconciliationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BpjsReconciliationController extends Controller
{
    public function __construct(
        private BpjsOfficialBillParserService $parser,
        private BpjsBillMatcherService        $matcher,
        private BpjsReconciliationService     $reconciler,
        private BpjsLegalEntityResolverService $legalEntityResolver,
    ) {}

    // ── Bill list ─────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $bills = BpjsOfficialBill::with('legalEntity', 'uploader')
            ->when($request->filled('periode'), fn($q) => $q->where('periode', $request->periode))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->paginate(20)->withQueryString();

        $legalEntities = LegalEntity::where('is_active', true)->orderBy('name')->get(['id', 'name', 'short_name']);

        return view('finance.bpjs_reconciliation.index', compact('bills', 'legalEntities'));
    }

    // ── Upload form ───────────────────────────────────────────────────────

    public function create()
    {
        $legalEntities  = LegalEntity::where('is_active', true)->orderBy('name')->get(['id', 'name', 'short_name']);
        $bpjsAccounts   = BpjsLegalAccount::where('bpjs_type', 'ketenagakerjaan')
            ->where('is_active', true)
            ->with('legalEntity')
            ->get();

        return view('finance.bpjs_reconciliation.create', compact('legalEntities', 'bpjsAccounts'));
    }

    // ── Store (parse + save rows) — supports multi-file PDF upload ───────────

    public function store(Request $request)
    {
        $request->validate([
            'pdf_files'              => ['required', 'array', 'min:1', 'max:10'],
            'pdf_files.*'            => ['file', 'mimes:pdf,xlsx,xls,csv', 'max:20480'],
            'periode'                => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'legal_entity_id'        => ['nullable', 'integer', 'exists:legal_entities,id'],
            'bpjs_legal_account_id'  => ['nullable', 'integer', 'exists:bpjs_legal_accounts,id'],
        ]);

        $files   = $request->file('pdf_files');
        $results = [];
        $errors  = [];

        foreach ($files as $file) {
            try {
                $bill      = $this->processSingleFile($file, $request);
                $results[] = [
                    'file'    => $file->getClientOriginalName(),
                    'bill_id' => $bill->id,
                    'rows'    => $bill->total_rows_parsed,
                ];
            } catch (\Throwable $e) {
                $errors[] = $file->getClientOriginalName() . ': ' . $e->getMessage();
            }
        }

        if (empty($results)) {
            return back()
                ->withErrors(['pdf_files' => 'Semua file gagal diproses. ' . implode(' | ', $errors)])
                ->withInput();
        }

        if (count($results) === 1 && empty($errors)) {
            return redirect()
                ->route('finance.bpjs-reconciliation.review', $results[0]['bill_id'])
                ->with('success', "PDF berhasil diparse: {$results[0]['rows']} karyawan dari {$results[0]['file']}. Silakan review nama karyawan.");
        }

        $msg = count($results) . ' file berhasil diproses (' . array_sum(array_column($results, 'rows')) . ' karyawan total).';
        if (! empty($errors)) {
            $msg .= ' ' . count($errors) . ' file gagal: ' . implode(', ', $errors);
        }

        return redirect()
            ->route('finance.bpjs-reconciliation.index')
            ->with('success', $msg);
    }

    // ── Parse & persist a single uploaded file ────────────────────────────

    private function processSingleFile(
        \Illuminate\Http\UploadedFile $file,
        Request $request
    ): BpjsOfficialBill {
        $origName = $file->getClientOriginalName();
        $ext      = strtolower($file->getClientOriginalExtension());

        $formatMap = ['pdf' => 'pdf_digital', 'xlsx' => 'excel', 'xls' => 'excel', 'csv' => 'csv'];

        // Simpan file ke storage agar bisa di-reparse nanti
        $storedPath = null;
        if ($ext === 'pdf') {
            $storedPath = $file->store('bpjs/bills', 'local');
            $filePath   = \Storage::disk('local')->path($storedPath);
        } else {
            $filePath = $file->getPathname();
        }

        $parsed = $this->parser->parse($filePath, $origName);
        $meta   = $parsed['meta'] ?? [];
        $rows   = $parsed['rows'] ?? [];

        if (empty($rows)) {
            throw new \RuntimeException('Tidak ada data karyawan yang berhasil diparsing dari file.');
        }

        // Kalau uploader tidak pilih PT manual, coba resolve/auto-create dari
        // NPP + nama perusahaan yang berhasil diparsing dari file tagihan.
        $legalEntityId = $request->legal_entity_id;
        if (! $legalEntityId && ! empty($meta['npp'])) {
            $legalEntity = $this->legalEntityResolver->resolveOrCreate($meta['npp'], $meta['namaPerusahaan'] ?? null);
            $legalEntityId = $legalEntity?->id;
        }

        $bill = BpjsOfficialBill::create([
            'npp'                   => $meta['npp'] ?? null,
            'nama_perusahaan'       => $meta['namaPerusahaan'] ?? null,
            'legal_entity_id'       => $legalEntityId,
            'bpjs_legal_account_id' => $request->bpjs_legal_account_id,
            'periode'               => $request->periode,
            'source_format'         => $formatMap[$ext] ?? 'excel',
            'source_file_name'      => $origName,
            'pdf_path'              => $storedPath,
            'total_rows_parsed'     => count($rows),
            'total_iuran_official'  => array_sum(array_column($rows, 'total_iuran')),
            'status'                => 'draft',
            'uploaded_by'           => auth()->user()?->id,
        ]);

        foreach (array_chunk($rows, 100) as $chunk) {
            BpjsOfficialBillRow::insert(array_map(fn ($r) => array_merge($r, [
                'bill_id'      => $bill->id,
                'match_status' => 'pending',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]), $chunk));
        }

        $this->matcher->matchBill($bill);

        return $bill;
    }

    // ── Show bill detail + matching results ───────────────────────────────

    public function show(BpjsOfficialBill $bpjsReconciliation)
    {
        $bill = $bpjsReconciliation->load('legalEntity', 'bpjsLegalAccount', 'uploader');

        $rows = BpjsOfficialBillRow::where('bill_id', $bill->id)
            ->with('employee')
            ->orderBy('match_status')
            ->orderBy('nama')
            ->paginate(50);

        $statusCounts = BpjsOfficialBillRow::where('bill_id', $bill->id)
            ->selectRaw('match_status, count(*) as cnt')
            ->groupBy('match_status')
            ->pluck('cnt', 'match_status');

        return view('finance.bpjs_reconciliation.show', compact('bill', 'rows', 'statusCounts'));
    }

    // ── Manual match confirmation ─────────────────────────────────────────

    public function confirmMatch(Request $request, BpjsOfficialBillRow $row)
    {
        $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
        ]);

        $row->update([
            'employee_id'     => $request->employee_id,
            'match_status'    => 'manual_confirmed',
            'match_confidence'=> 100,
            'match_note'      => 'Dikonfirmasi manual oleh ' . auth()->user()?->name,
        ]);

        // Update bill counters
        $bill = $row->bill;
        $bill->update([
            'matched_count'   => BpjsOfficialBillRow::where('bill_id', $bill->id)->whereIn('match_status', ['auto', 'manual_confirmed'])->count(),
            'unmatched_count' => BpjsOfficialBillRow::where('bill_id', $bill->id)->whereIn('match_status', ['not_found', 'flagged'])->count(),
        ]);

        return back()->with('success', "Matching dikonfirmasi untuk {$row->nama}.");
    }

    // ── Run reconciliation ────────────────────────────────────────────────

    public function reconcile(BpjsOfficialBill $bpjsReconciliation)
    {
        $bill = $bpjsReconciliation;

        if ((int) $bill->matched_count === 0) {
            return back()->withErrors(['msg' => 'Tidak ada karyawan yang ter-match. Jalankan matching dulu.']);
        }

        try {
            $stats = $this->reconciler->reconcile($bill);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['msg' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['msg' => 'Rekonsiliasi gagal: ' . $e->getMessage()]);
        }

        $total = array_sum($stats);
        $ok    = $stats['ok'] ?? 0;

        return redirect()->route('finance.bpjs-reconciliation.dashboard', $bill)
            ->with('success', "Rekonsiliasi selesai: {$ok}/{$total} cocok, " . ($stats['selisih_signifikan'] ?? 0) . " selisih signifikan.");
    }

    // ── Reconciliation dashboard ──────────────────────────────────────────

    public function dashboard(BpjsOfficialBill $bpjsReconciliation)
    {
        $bill = $bpjsReconciliation->load('legalEntity');

        $results = BpjsReconciliationResult::where('bill_id', $bill->id)
            ->with('employee', 'billRow')
            ->orderByRaw("FIELD(status, 'selisih_signifikan', 'perlu_review', 'selisih_minor', 'ok')")
            ->orderByRaw('ABS(selisih_total) DESC')
            ->paginate(50);

        $summary = BpjsReconciliationResult::where('bill_id', $bill->id)
            ->selectRaw("
                count(*) as total,
                sum(case when status='ok' then 1 else 0 end) as ok_count,
                sum(case when status='selisih_minor' then 1 else 0 end) as minor_count,
                sum(case when status='selisih_signifikan' then 1 else 0 end) as signifikan_count,
                sum(case when status='perlu_review' then 1 else 0 end) as review_count,
                sum(official_total) as total_official,
                sum(omeo_total) as total_omeo,
                sum(selisih_total) as total_selisih
            ")->first();

        return view('finance.bpjs_reconciliation.dashboard', compact('bill', 'results', 'summary'));
    }

    // ── Delete bill ───────────────────────────────────────────────────────

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            DB::table('bpjs_official_bill_rows')->where('bill_id', $id)->delete();
            DB::table('bpjs_reconciliation_results')->where('bill_id', $id)->delete();
            DB::table('bpjs_official_bills')->where('id', $id)->delete();
            DB::commit();
            return redirect()
                ->route('finance.bpjs-reconciliation.index')
                ->with('success', 'Tagihan BPJS berhasil dihapus.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal hapus: ' . $e->getMessage());
        }
    }

    // ── Re-run matching ───────────────────────────────────────────────────

    public function rematch(BpjsOfficialBill $bpjsReconciliation)
    {
        $bill = $bpjsReconciliation;
        BpjsOfficialBillRow::where('bill_id', $bill->id)->update(['match_status' => 'pending', 'employee_id' => null]);
        $stats = $this->matcher->matchBill($bill);
        $auto  = $stats['auto'] ?? 0;
        return back()->with('success', "Matching selesai: {$auto} auto-matched.");
    }

    // ── Review nama karyawan (Step 2 setelah upload) ─────────────────────────

    public function review(int $billId)
    {
        $bill = BpjsOfficialBill::findOrFail($billId);

        $rows = BpjsOfficialBillRow::where('bill_id', $billId)
            ->orderBy('id')
            ->get(['id', 'nik', 'nama', 'total_iuran']);

        $totals    = $rows->pluck('total_iuran')->sort()->values();
        $medianIdx = (int) ($totals->count() / 2);
        $median    = $totals[$medianIdx] ?? 0;

        $rows = $rows->map(function ($row) use ($median) {
            $wordCount    = count(explode(' ', trim($row->nama)));
            $row->warning = null;

            if ($wordCount === 1) {
                $row->warning = 'fragment';
            } elseif ($median > 0 && $row->total_iuran > $median * 5) {
                $row->warning = 'total';
            }

            return $row;
        });

        $warningCount = $rows->filter(fn ($r) => $r->warning !== null)->count();

        return view('finance.bpjs_reconciliation.review', compact(
            'bill', 'rows', 'warningCount', 'median'
        ));
    }

    // ── Inline edit nama (AJAX PATCH) ────────────────────────────────────────

    public function updateRowName(Request $request, BpjsOfficialBillRow $row)
    {
        $request->validate([
            'nama' => ['required', 'string', 'min:2', 'max:150'],
        ]);

        $old = $row->nama;
        $row->update(['nama' => strtoupper(trim($request->nama))]);

        return response()->json([
            'success' => true,
            'old'     => $old,
            'new'     => $row->nama,
        ]);
    }

    // ── Inline edit NIK (AJAX PATCH) ─────────────────────────────────────────
    // Dipakai saat parsing PDF menggeser pasangan nama/NIK (mis. nama yang
    // patah ke 2 baris di PDF) — user bisa ketik ulang NIK yang benar sambil
    // lihat dokumen aslinya.

    public function updateRowNik(Request $request, BpjsOfficialBillRow $row)
    {
        $request->validate([
            'nik' => ['required', 'string', 'regex:/^\d{16}$/'],
        ]);

        $old = $row->nik;
        $row->update(['nik' => $request->nik]);

        return response()->json([
            'success' => true,
            'old'     => $old,
            'new'     => $row->nik,
        ]);
    }

    // ── Tukar NIK dengan baris sebelum/sesudahnya (AJAX POST) ───────────────
    // Cara cepat memperbaiki pergeseran nama/NIK akibat baris nama yang patah
    // di PDF — daripada mengetik ulang 16 digit NIK secara manual, user cukup
    // klik untuk menukar pasangan NIK antara dua baris yang bersebelahan.

    public function swapRowNik(Request $request, BpjsOfficialBillRow $row)
    {
        $data = $request->validate([
            'direction' => ['required', 'in:up,down'],
        ]);

        $neighbor = BpjsOfficialBillRow::where('bill_id', $row->bill_id)
            ->where('id', $data['direction'] === 'up' ? '<' : '>', $row->id)
            ->orderBy('id', $data['direction'] === 'up' ? 'desc' : 'asc')
            ->first();

        if (! $neighbor) {
            return response()->json(['success' => false, 'message' => 'Tidak ada baris di sisi itu.'], 422);
        }

        [$rowNik, $neighborNik] = [$row->nik, $neighbor->nik];
        $row->update(['nik' => $neighborNik]);
        $neighbor->update(['nik' => $rowNik]);

        return response()->json([
            'success'      => true,
            'row_nik'      => $row->nik,
            'neighbor_id'  => $neighbor->id,
            'neighbor_nik' => $neighbor->nik,
        ]);
    }

    // ── Konfirmasi review → jalankan NIK matching ─────────────────────────────

    public function confirmReview(int $billId)
    {
        $bill = BpjsOfficialBill::findOrFail($billId);

        BpjsOfficialBillRow::where('bill_id', $bill->id)
            ->update(['match_status' => 'pending', 'employee_id' => null]);

        $stats = $this->matcher->matchBill($bill);
        $auto  = $stats['auto'] ?? 0;

        return redirect()
            ->route('finance.bpjs-reconciliation.show', $billId)
            ->with('success', "Review selesai. {$auto} karyawan ter-match otomatis. Silakan tinjau hasil matching.");
    }

    // ── Re-parse PDF dari storage ─────────────────────────────────────────────

    public function reparse(int $billId)
    {
        $bill = BpjsOfficialBill::findOrFail($billId);

        if (! $bill->pdf_path || ! \Storage::disk('local')->exists($bill->pdf_path)) {
            return back()->with('error',
                'File PDF tidak ditemukan di storage. File lama yang diupload sebelum fitur ini tidak bisa di-reparse. Silakan hapus dan upload ulang.');
        }

        $pdfFullPath = \Storage::disk('local')->path($bill->pdf_path);

        try {
            $parsed = $this->parser->parse($pdfFullPath, $bill->source_file_name);
            $rows   = $parsed['rows'] ?? [];
            $meta   = $parsed['meta'] ?? [];

            if (empty($rows)) {
                return back()->with('error', 'Re-parse tidak menghasilkan data karyawan.');
            }

            DB::beginTransaction();

            BpjsOfficialBillRow::where('bill_id', $billId)->delete();

            foreach (array_chunk($rows, 100) as $chunk) {
                BpjsOfficialBillRow::insert(array_map(fn ($r) => array_merge($r, [
                    'bill_id'      => $billId,
                    'match_status' => 'pending',
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]), $chunk));
            }

            $bill->update([
                'total_rows_parsed'    => count($rows),
                'total_iuran_official' => array_sum(array_column($rows, 'total_iuran')),
                'matched_count'        => 0,
                'unmatched_count'      => 0,
                'status'               => 'draft',
            ]);

            DB::commit();

        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Re-parse gagal: ' . $e->getMessage());
        }

        return redirect()
            ->route('finance.bpjs-reconciliation.review', $billId)
            ->with('success', 'Re-parse selesai: ' . count($rows) . ' baris. Silakan review nama karyawan.');
    }

    // ── Reset salah match (flagged dengan NIK berbeda) ────────────────────────

    public function cleanupMismatches(int $id)
    {
        $bill  = BpjsOfficialBill::findOrFail($id);
        $reset = $this->matcher->cleanupFlaggedMismatches($bill->id);

        $bill->update([
            'matched_count'   => BpjsOfficialBillRow::where('bill_id', $bill->id)->whereIn('match_status', ['auto', 'manual_confirmed'])->count(),
            'unmatched_count' => BpjsOfficialBillRow::where('bill_id', $bill->id)->whereIn('match_status', ['not_found', 'flagged'])->count(),
        ]);

        return back()->with('success', "{$reset} baris yang salah match sudah direset ke Not Found.");
    }

    // ── Cross-billing: rekap iuran per outlet ────────────────────────────────

    public function crossBilling(int $bill): View
    {
        $bill = BpjsOfficialBill::findOrFail($bill);

        $sessions = DB::table('payroll_import_sessions')
            ->orderByDesc('periode')
            ->get(['id', 'source_file_name', 'periode', 'total_rows']);

        $sessionId = (int) request('session_id', $sessions->first()?->id ?? 0);

        $rows = $this->crossBillingQuery($bill->id, $sessionId)->get();

        // Multi-session lookup untuk baris yang belum dapat outlet dari session utama
        $rows = $rows->map(function ($row) use ($sessionId) {
            if (! is_null($row->outlet_name_raw)) return $row;

            $namaParts = explode(' ', trim($row->nama_bpjs));
            $firstWord = $namaParts[0] ?? '';
            $lastWord  = end($namaParts);

            // Hindari false positive untuk nama 1 kata (mis. "DAVID")
            if (count($namaParts) < 2) {
                $row->match_status = 'not_found';
                $row->match_note   = 'Tidak ditemukan di semua data payroll';
                return $row;
            }

            $found = DB::table('payroll_import_rows as p')
                ->join('payroll_import_sessions as s', 's.id', '=', 'p.session_id')
                ->where('p.session_id', '!=', $sessionId)
                ->where(function ($q) use ($firstWord, $lastWord) {
                    // Harus match KEDUA kata (depan DAN belakang) untuk hindari false positive
                    $q->where('p.nama', 'LIKE', '%' . $firstWord . '%')
                      ->where('p.nama', 'LIKE', '%' . $lastWord . '%');
                })
                ->select(
                    'p.nama as payroll_nama', 'p.outlet_name_raw', 'p.posisi',
                    'p.no_komp', 'p.session_id', 's.source_file_name', 's.periode'
                )
                ->orderBy('p.session_id', 'desc')
                ->first();

            if ($found) {
                $namaBpjsClean = strtoupper(trim($row->nama_bpjs));
                $namaPayClean  = strtoupper(trim($found->payroll_nama));
                $isExactMatch  = $namaBpjsClean === $namaPayClean;

                $row->outlet_name_raw = $found->outlet_name_raw;
                $row->posisi          = $found->posisi;
                $row->match_status    = $isExactMatch ? 'other_session' : 'needs_review';
                $row->match_note      = $isExactMatch
                    ? 'Data dari ' . $found->periode
                    : 'Nama BPJS: "' . $row->nama_bpjs . '" vs Payroll: "' . $found->payroll_nama
                      . '" (' . $found->periode . ') — HARAP VERIFIKASI HRD';
            } else {
                $row->match_status = 'not_found';
                $row->match_note   = 'Tidak ditemukan di semua data payroll';
            }

            return $row;
        });

        $byOutlet = $rows->groupBy(fn($r) => $r->outlet_name_raw ?? 'BELUM TERDATA');

        $summary = $byOutlet->map(fn($items, $outlet) => (object)[
            'outlet'          => $outlet,
            'jumlah_karyawan' => $items->count(),
            'total_iuran'     => $items->sum('total_iuran'),
            'karyawan'        => $items,
        ])->sortByDesc('total_iuran')->values();

        $stats = [
            'total_karyawan'   => $rows->count(),
            'terdata_juni'     => $rows->where('match_status', 'confirmed')->count(),
            'terdata_lain'     => $rows->where('match_status', 'other_session')->count(),
            'perlu_verifikasi' => $rows->where('match_status', 'needs_review')->count(),
            'belum_terdata'    => $rows->where('match_status', 'not_found')->count(),
            'total_iuran'      => $rows->sum('total_iuran'),
            'total_outlets'    => $byOutlet->count(),
        ];

        return view('finance.bpjs_reconciliation.cross_billing',
            compact('bill', 'summary', 'stats', 'sessions', 'sessionId'));
    }

    public function exportCrossBilling(int $bill): BinaryFileResponse
    {
        $bill      = BpjsOfficialBill::findOrFail($bill);
        $sessionId = (int) request('session_id', 22);

        $rows = $this->crossBillingQuery($bill->id, $sessionId)->get();
        $byOutlet = $rows->groupBy(fn($r) => $r->outlet_name_raw ?? 'BELUM TERDATA');

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $ws          = $spreadsheet->getActiveSheet();
        $ws->setTitle('Rekap per Outlet');

        $ws->setCellValue('A1', 'REKAP CROSS-BILLING BPJS');
        $ws->setCellValue('A2', ($bill->nama_perusahaan ?? $bill->source_file_name) . ' · Periode ' . $bill->periode);
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $ws->mergeCells('A1:D1');
        $ws->mergeCells('A2:D2');

        foreach (['No', 'Outlet', 'Jumlah Karyawan', 'Total Iuran (Rp)'] as $i => $h) {
            $ws->setCellValue(
                \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1) . '4', $h
            );
        }
        $ws->getStyle('A4:D4')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $ws->getStyle('A4:D4')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('7C3AED');

        $row = 5; $no = 1; $grandTotal = 0;
        foreach ($byOutlet->sortByDesc(fn($i) => $i->sum('total_iuran')) as $outlet => $items) {
            $subtotal    = $items->sum('total_iuran');
            $grandTotal += $subtotal;
            $ws->setCellValue("A{$row}", $no++);
            $ws->setCellValue("B{$row}", $outlet);
            $ws->setCellValue("C{$row}", $items->count());
            $ws->setCellValue("D{$row}", $subtotal);
            $row++;
        }
        $ws->setCellValue("B{$row}", 'GRAND TOTAL');
        $ws->setCellValue("C{$row}", $rows->count());
        $ws->setCellValue("D{$row}", $grandTotal);
        $ws->getStyle("A{$row}:D{$row}")->getFont()->setBold(true);
        $ws->getColumnDimension('B')->setWidth(35);
        $ws->getColumnDimension('C')->setWidth(18);
        $ws->getColumnDimension('D')->setWidth(22);

        // Sheet detail per outlet
        foreach ($byOutlet as $outlet => $items) {
            $ws2 = $spreadsheet->createSheet();
            $ws2->setTitle(mb_substr($outlet, 0, 31));
            foreach (['No', 'NIK', 'No.Komp', 'Nama', 'Posisi', 'Total Iuran'] as $i => $h) {
                $ws2->setCellValue(
                    \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1) . '1', $h
                );
            }
            $ws2->getStyle('A1:F1')->getFont()->setBold(true);
            $ws2->getStyle('A1:F1')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('EDE9FE');

            $r2 = 2; $n2 = 1;
            foreach ($items as $k) {
                $ws2->setCellValue("A{$r2}", $n2++);
                $ws2->setCellValue("B{$r2}", "'" . $k->nik);
                $ws2->setCellValue("C{$r2}", $k->employee_number ?? '-');
                $ws2->setCellValue("D{$r2}", $k->nama_bpjs);
                $ws2->setCellValue("E{$r2}", $k->posisi ?? '-');
                $ws2->setCellValue("F{$r2}", (float) $k->total_iuran);
                $r2++;
            }
            $ws2->setCellValue("D{$r2}", 'SUBTOTAL');
            $ws2->setCellValue("F{$r2}", $items->sum('total_iuran'));
            $ws2->getStyle("A{$r2}:F{$r2}")->getFont()->setBold(true);
            foreach (['B' => 18, 'D' => 30, 'E' => 25, 'F' => 18] as $col => $w) {
                $ws2->getColumnDimension($col)->setWidth($w);
            }
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $tmp    = tempnam(sys_get_temp_dir(), 'cross_billing_') . '.xlsx';
        $writer->save($tmp);

        $filename = 'CrossBilling_' . str_replace(' ', '_', $bill->nama_perusahaan ?? 'BPJS')
                  . '_' . $bill->periode . '.xlsx';

        return response()->download($tmp, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function crossBillingQuery(int $billId, int $sessionId): \Illuminate\Database\Query\Builder
    {
        return DB::table('bpjs_official_bill_rows as b')
            ->leftJoin('employees as e', 'e.nik', '=', 'b.nik')
            ->leftJoin('payroll_import_rows as p', function ($join) use ($sessionId) {
                $join->whereRaw('CAST(p.no_komp AS UNSIGNED) = CAST(e.employee_number AS UNSIGNED)')
                     ->where('p.session_id', $sessionId);
            })
            ->where('b.bill_id', $billId)
            ->select(
                'b.id as row_id', 'b.nama as nama_bpjs', 'b.nik', 'b.total_iuran',
                'e.id as employee_id', 'e.employee_number',
                'p.no_komp', 'p.outlet_name_raw', 'p.posisi',
                DB::raw("'confirmed' as match_status"),
                DB::raw('null as match_note')
            )
            ->orderBy('p.outlet_name_raw')
            ->orderBy('b.nama');
    }
}
