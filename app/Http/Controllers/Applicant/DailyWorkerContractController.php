<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Services\DailyWorkerContractService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DailyWorkerContractController extends Controller
{
    public function index(Request $request)
    {
        $candidate = $request->user()?->resolveCandidate();

        if (!$candidate) {
            return view('applicant.contracts.index', ['contracts' => collect()]);
        }

        $contracts = Contract::query()
            ->with('template:id,name')
            ->where('candidate_id', $candidate->id)
            ->orderByDesc('id')
            ->paginate(10);

        return view('applicant.contracts.index', [
            'contracts' => $contracts,
        ]);
    }

    public function show(Request $request, Contract $contract, DailyWorkerContractService $service)
    {
        $this->ensureOwnedByCurrentCandidate($request, $contract);

        $service->markViewed($contract->load('candidate'), $request->user());

        return view('applicant.contracts.show', [
            'contract' => $contract->fresh(['template:id,name', 'latestStamp:id,contract_id,stamp_type,stamp_number,stamp_proof_path,confirmed_at']),
        ]);
    }

    public function storeStamp(Request $request, Contract $contract, DailyWorkerContractService $service)
    {
        $this->ensureOwnedByCurrentCandidate($request, $contract);

        if (!in_array($contract->status, [
            Contract::STATUS_SENT,
            Contract::STATUS_VIEWED,
            Contract::STATUS_AWAITING_STAMP,
            Contract::STATUS_REJECTED,
        ], true)) {
            return back()->with('error', 'Materai tidak bisa diubah pada status kontrak saat ini.');
        }

        $data = $request->validate([
            'stamp_number' => ['nullable', 'string', 'max:120'],
            'stamp_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:3072'],
            'stamp_confirmed' => ['required', 'accepted'],
        ], [
            'stamp_file.mimes' => 'Bukti materai harus JPG, PNG, atau PDF.',
            'stamp_file.max' => 'Ukuran bukti materai maksimal 3MB.',
            'stamp_confirmed.required' => 'Konfirmasi materai wajib dicentang.',
            'stamp_confirmed.accepted' => 'Konfirmasi materai wajib dicentang.',
        ]);

        if (empty($data['stamp_number']) && !$request->hasFile('stamp_file')) {
            return back()->withErrors(['stamp_number' => 'Isi nomor materai atau upload bukti materai.']);
        }

        $stampProofPath = null;
        if ($request->hasFile('stamp_file')) {
            $stampProofPath = $request->file('stamp_file')->store('contracts/stamps', 'public');
        }

        DB::transaction(function () use ($service, $contract, $request, $data, $stampProofPath): void {
            $service->confirmStamp(
                $contract,
                $request->user(),
                $data['stamp_number'] ?? null,
                $stampProofPath
            );
        });

        return back()->with('success', 'Materai berhasil disimpan. Lanjutkan tanda tangan biru untuk submit kontrak.');
    }

    public function submit(Request $request, Contract $contract, DailyWorkerContractService $service)
    {
        $this->ensureOwnedByCurrentCandidate($request, $contract);

        if (!in_array($contract->status, [Contract::STATUS_AWAITING_SIGNATURE], true)) {
            return back()->with('error', 'Kontrak belum siap ditandatangani. Simpan materai terlebih dahulu.');
        }

        $data = $request->validate([
            'signature_data' => ['required', 'string'],
        ], [
            'signature_data.required' => 'Tanda tangan digital wajib diisi.',
        ]);

        if (!str_starts_with((string) $data['signature_data'], 'data:image/png;base64,')) {
            return back()->withErrors(['signature_data' => 'Format tanda tangan tidak valid.']);
        }

        DB::transaction(function () use ($request, $contract, $service, $data): void {
            $signaturePath = $this->storeSignature((string) $data['signature_data'], $contract->id);
            $candidateName = (string) ($contract->candidate?->full_name ?: $request->user()->name ?: 'Kandidat');
            $service->submitSignedContract($contract, $request->user(), $signaturePath, $candidateName);
        });

        return back()->with('success', 'Kontrak berhasil ditandatangani dan dikirim ke HRD.');
    }

    public function downloadPdf(Request $request, Contract $contract)
    {
        $this->ensureOwnedByCurrentCandidate($request, $contract);

        $path = $contract->pdf_path_original;
        if (!$path || !Storage::disk('public')->exists($path)) {
            return back()->with('error', 'PDF kontrak original belum tersedia.');
        }

        return Storage::disk('public')->download($path, 'Kontrak_DW_' . $contract->contract_number . '_original.pdf');
    }

    private function ensureOwnedByCurrentCandidate(Request $request, Contract $contract): void
    {
        $candidateId = (int) ($request->user()?->resolveCandidate()?->id ?? 0);
        if ($candidateId <= 0 || $candidateId !== (int) $contract->candidate_id) {
            abort(403);
        }
    }

    private function storeSignature(string $dataUri, int $contractId): string
    {
        $base64 = substr($dataUri, strlen('data:image/png;base64,'));
        $binary = base64_decode($base64, true);

        if ($binary === false) {
            abort(422, 'Format tanda tangan tidak valid.');
        }

        $path = 'contracts/signatures/contract-' . $contractId . '-' . now()->format('YmdHis') . '.png';
        Storage::disk('public')->put($path, $binary);

        return $path;
    }
}

