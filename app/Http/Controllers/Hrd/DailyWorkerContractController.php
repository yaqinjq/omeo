<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Services\DailyWorkerContractService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DailyWorkerContractController extends Controller
{
    public function index(Request $request)
    {
        $status = trim((string) $request->query('status', ''));
        $search = trim((string) $request->query('search', ''));

        $contracts = Contract::query()
            ->with(['candidate:id,full_name,email,nik', 'template:id,name', 'latestCandidateSignature'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('contract_number', 'like', "%{$search}%")
                        ->orWhereHas('candidate', function ($candidateQuery) use ($search) {
                            $candidateQuery->where('full_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('nik', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByRaw("CASE WHEN status IN ('submitted','hr_review') THEN 0 ELSE 1 END")
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('hrd.contracts.index', [
            'contracts' => $contracts,
            'status' => $status,
            'search' => $search,
        ]);
    }

    public function send(Request $request, DailyWorkerContractService $service)
    {
        $data = $request->validate([
            'candidate_ids' => ['required', 'array', 'min:1'],
            'candidate_ids.*' => ['integer', 'exists:candidates,id'],
            'template_id' => ['nullable', 'integer', 'exists:contract_templates,id'],
            'use_active_template' => ['nullable', 'boolean'],
        ], [
            'candidate_ids.required' => 'Pilih minimal satu kandidat.',
            'candidate_ids.min' => 'Pilih minimal satu kandidat.',
            'template_id.exists' => 'Template kontrak tidak ditemukan.',
        ]);

        $useActive = $request->boolean('use_active_template', true);

        $template = ContractTemplate::query()
            ->where('type', ContractTemplate::TYPE_DAILY_WORKER)
            ->when(!$useActive && !empty($data['template_id']), fn ($query) => $query->where('id', (int) $data['template_id']))
            ->when($useActive || empty($data['template_id']), fn ($query) => $query->where('is_active', true))
            ->first();

        if (!$template) {
            return back()->with('error', 'Template kontrak aktif belum tersedia atau pilihan template tidak valid.');
        }

        $candidates = Candidate::query()->whereIn('id', $data['candidate_ids'])->get();

        $sent = 0;
        $skippedNoUser = 0;
        $skippedExisting = 0;

        DB::transaction(function () use ($candidates, $template, $service, $request, &$sent, &$skippedNoUser, &$skippedExisting): void {
            foreach ($candidates as $candidate) {
                if ((int) ($candidate->user_id ?? 0) <= 0) {
                    $skippedNoUser++;
                    continue;
                }

                $existing = Contract::query()
                    ->where('candidate_id', $candidate->id)
                    ->whereIn('status', [
                        Contract::STATUS_SENT,
                        Contract::STATUS_VIEWED,
                        Contract::STATUS_AWAITING_STAMP,
                        Contract::STATUS_AWAITING_SIGNATURE,
                        Contract::STATUS_SUBMITTED,
                        Contract::STATUS_HR_REVIEW,
                    ])
                    ->exists();

                if ($existing) {
                    $skippedExisting++;
                    continue;
                }

                $snapshot = $service->buildCandidateSnapshot($candidate, $request->user());
                $service->createAndSendContract($candidate, $template, $request->user(), $snapshot);
                $sent++;
            }
        });

        if ($sent === 0) {
            return back()->with('error', 'Tidak ada kontrak terkirim. Kontrak aktif mungkin sudah ada, atau kandidat belum punya akun login.');
        }

        return back()->with('success', "Kontrak terkirim: {$sent}. Dilewati (akun login belum ada): {$skippedNoUser}. Dilewati (kontrak aktif sudah ada): {$skippedExisting}.");
    }

    public function show(Contract $contract, DailyWorkerContractService $service, Request $request)
    {
        $contract->load([
            'candidate:id,full_name,email,nik',
            'template:id,name',
            'latestCandidateSignature',
            'latestStamp',
        ]);

        if ($contract->status === Contract::STATUS_SUBMITTED) {
            $contract = $service->markHrReview($contract, $request->user());
        }

        return view('hrd.contracts.show', [
            'contract' => $contract,
        ]);
    }

    public function review(Request $request, Contract $contract, DailyWorkerContractService $service)
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'review_reason' => ['nullable', 'string', 'max:2000', 'required_if:decision,reject'],
        ], [
            'decision.required' => 'Keputusan review wajib dipilih.',
            'decision.in' => 'Keputusan review tidak valid.',
            'review_reason.required_if' => 'Alasan penolakan wajib diisi jika kontrak ditolak.',
            'review_reason.max' => 'Alasan review maksimal 2000 karakter.',
        ]);

        if (!in_array($contract->status, [Contract::STATUS_SUBMITTED, Contract::STATUS_HR_REVIEW], true)) {
            return back()->with('error', 'Kontrak belum siap direview.');
        }

        $isApprove = $data['decision'] === 'approve';
        $reason = trim((string) ($data['review_reason'] ?? ''));
        if ($isApprove && $reason === '') {
            $reason = 'Disetujui HRD.';
        }

        $service->reviewByHrd($contract, $request->user(), $isApprove, $reason);

        return back()->with('success', $isApprove
            ? 'Kontrak disetujui. Kandidat siap diproses ke probation.'
            : 'Kontrak ditolak. Notifikasi dan alasan sudah dikirim ke kandidat.');
    }

    public function downloadPdf(Request $request, Contract $contract)
    {
        $variant = trim((string) $request->query('variant', 'signed'));

        $path = $variant === 'original'
            ? $contract->pdf_path_original
            : ($contract->pdf_path_signed ?: $contract->pdf_path_original);

        if (!$path || !Storage::disk('public')->exists($path)) {
            return back()->with('error', 'File PDF kontrak belum tersedia.');
        }

        $suffix = $variant === 'original' ? 'original' : 'signed';

        return Storage::disk('public')->download($path, 'Kontrak_DW_' . $contract->contract_number . '_' . $suffix . '.pdf');
    }
}

