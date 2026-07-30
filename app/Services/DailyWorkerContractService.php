<?php

namespace App\Services;

use App\Models\ApplicantProfile;
use App\Models\Candidate;
use App\Models\Contract;
use App\Models\ContractSignature;
use App\Models\ContractStamp;
use App\Models\ContractTemplate;
use App\Models\HrNotification;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DailyWorkerContractService
{
    /**
     * @return array<string,mixed>
     */
    public function buildCandidateSnapshot(Candidate $candidate, User $actor): array
    {
        $profile = ApplicantProfile::query()
            ->when($candidate->user_id, fn ($query) => $query->where('user_id', $candidate->user_id))
            ->first();

        $personal = $profile?->personal_json ?? [];
        $address = $profile?->address_json ?? [];
        $work = collect($profile?->work_experiences ?? [])->first();

        $position = (string) (
            data_get($personal, 'applied_position')
            ?? data_get($personal, 'position_applied')
            ?? data_get($personal, 'position')
            ?? data_get($work, 'position')
            ?? '-'
        );

        $outlet = (string) (
            data_get($personal, 'outlet')
            ?? data_get($personal, 'applied_outlet')
            ?? data_get($personal, 'preferred_outlet')
            ?? '-'
        );

        $candidateAddress = trim((string) (
            data_get($address, 'domicile_address')
            ?? data_get($address, 'ktp_address')
            ?? data_get($personal, 'address')
            ?? '-'
        ));

        return [
            'candidate_name' => (string) ($candidate->full_name ?: '-'),
            'candidate_nik' => (string) ($candidate->nik ?: '-'),
            'candidate_email' => (string) ($candidate->email ?: '-'),
            'candidate_phone' => (string) ($candidate->phone ?: '-'),
            'candidate_address' => $candidateAddress !== '' ? $candidateAddress : '-',
            'position_name' => $position,
            'outlet_name' => $outlet,
            'hrd_name' => (string) ($actor->name ?: 'HRD'),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param array<string,mixed> $snapshot
     */
    public function createAndSendContract(Candidate $candidate, ContractTemplate $template, User $actor, array $snapshot = []): Contract
    {
        return DB::transaction(function () use ($candidate, $template, $actor, $snapshot): Contract {
            $lockedTemplate = ContractTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();
            $contractNumber = $this->generateContractNumber($lockedTemplate);

            $payload = !empty($snapshot) ? $snapshot : $this->buildCandidateSnapshot($candidate, $actor);
            $payload['contract_number'] = $contractNumber;
            $payload['today_date'] = now()->translatedFormat('d F Y');

            $html = $this->renderTemplateHtml((string) $lockedTemplate->body_html, $payload);

            $lockedTemplate->update([
                'next_sequence' => ((int) $lockedTemplate->next_sequence) + 1,
                'updated_by' => $actor->id,
            ]);

            $contract = Contract::create([
                'candidate_id' => $candidate->id,
                'contract_template_id' => $lockedTemplate->id,
                'contract_number' => $contractNumber,
                'status' => Contract::STATUS_SENT,
                'sent_at' => now(),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
                'meta_json' => array_merge($payload, [
                    'body_html' => $html,
                    'status_history' => [
                        ['status' => Contract::STATUS_SENT, 'at' => now()->toIso8601String(), 'actor_user_id' => $actor->id],
                    ],
                ]),
            ]);

            $originalPdfPath = $this->storePdfFromHtml($html, 'contracts/originals', $contract->contract_number);
            $contract->update([
                'pdf_path_original' => $originalPdfPath,
            ]);

            $this->notifyCandidate($contract->fresh('candidate'));

            return $contract->fresh();
        });
    }

    public function markViewed(Contract $contract, User $actor): Contract
    {
        if ((int) ($contract->candidate?->user_id ?? 0) !== (int) $actor->id) {
            abort(403);
        }

        if ($contract->viewed_at !== null) {
            return $contract;
        }

        $status = $contract->status === Contract::STATUS_SENT ? Contract::STATUS_VIEWED : $contract->status;

        $contract->update([
            'viewed_at' => now(),
            'status' => $status,
            'updated_by' => $actor->id,
            'meta_json' => $this->appendStatusHistory($contract->meta_json, $status, $actor->id, null),
        ]);

        return $contract->fresh();
    }

    public function confirmStamp(Contract $contract, User $actor, ?string $stampNumber, ?string $stampProofPath): Contract
    {
        $stampType = $stampProofPath ? ContractStamp::TYPE_UPLOAD_PROOF : ContractStamp::TYPE_NUMBER_INPUT;

        $contract->stamps()->create([
            'stamp_type' => $stampType,
            'stamp_number' => $stampNumber,
            'stamp_proof_path' => $stampProofPath,
            'confirmed_at' => now(),
        ]);

        $contract->update([
            'status' => Contract::STATUS_AWAITING_SIGNATURE,
            'updated_by' => $actor->id,
            'meta_json' => $this->appendStatusHistory($contract->meta_json, Contract::STATUS_AWAITING_SIGNATURE, $actor->id, 'Materai dikonfirmasi kandidat.'),
        ]);

        return $contract->fresh();
    }

    public function submitSignedContract(Contract $contract, User $actor, string $signaturePath, string $signerName): Contract
    {
        $contract->signatures()->create([
            'signer_role' => 'candidate',
            'signer_name' => $signerName,
            'signature_image_path' => $signaturePath,
            'signed_at' => now(),
            'meta_json' => [
                'ink_color' => 'blue',
                'device_user_agent' => request()->userAgent(),
            ],
        ]);

        $signedPdfPath = $this->buildSignedPdf($contract->fresh(['candidate', 'latestStamp', 'latestCandidateSignature']));

        $contract->update([
            'status' => Contract::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'updated_by' => $actor->id,
            'pdf_path_signed' => $signedPdfPath,
            'meta_json' => $this->appendStatusHistory($contract->meta_json, Contract::STATUS_SUBMITTED, $actor->id, 'Kontrak ditandatangani kandidat dan disubmit.'),
        ]);

        $this->notifyHrdSubmitted($contract->fresh('candidate'));

        return $contract->fresh();
    }

    public function markHrReview(Contract $contract, User $actor): Contract
    {
        if ($contract->status !== Contract::STATUS_SUBMITTED) {
            return $contract;
        }

        $contract->update([
            'status' => Contract::STATUS_HR_REVIEW,
            'updated_by' => $actor->id,
            'meta_json' => $this->appendStatusHistory($contract->meta_json, Contract::STATUS_HR_REVIEW, $actor->id, 'Kontrak masuk tahap review HRD.'),
        ]);

        return $contract->fresh();
    }

    public function reviewByHrd(Contract $contract, User $actor, bool $isApprove, string $reason): Contract
    {
        $target = $isApprove ? Contract::STATUS_APPROVED : Contract::STATUS_REJECTED;
        $now = now();

        $meta = $contract->meta_json ?? [];
        if (!$isApprove) {
            $meta['rejection_reason'] = $reason;
        }

        $meta = $this->appendStatusHistory($meta, $target, $actor->id, $reason);

        $contract->update([
            'status' => $target,
            'reviewed_at' => $now,
            'approved_at' => $isApprove ? $now : null,
            'updated_by' => $actor->id,
            'meta_json' => $meta,
        ]);

        $this->notifyCandidateReviewed($contract->fresh('candidate'));

        return $contract->fresh();
    }

    public function notifyCandidate(Contract $contract): void
    {
        $userId = (int) ($contract->candidate?->user_id ?? 0);
        if ($userId <= 0) {
            return;
        }

        HrNotification::create([
            'user_id' => $userId,
            'type' => 'daily_worker_contract',
            'title' => 'Kontrak Daily Worker Sudah Tersedia',
            'body' => 'Silakan buka inbox kontrak, isi materai, tanda tangan biru, lalu kirim ke HRD.',
            'due_date' => now()->toDateString(),
            'is_read' => false,
            'unique_key' => 'contract-sent-' . $contract->id . '-' . now()->timestamp,
            'meta' => [
                'contract_id' => $contract->id,
                'route' => route('applicant.contracts.show', $contract),
            ],
        ]);
    }

    public function notifyHrdSubmitted(Contract $contract): void
    {
        $hrUsers = User::query()
            ->whereIn('role', ['admin', 'hrd'])
            ->pluck('id');

        foreach ($hrUsers as $userId) {
            HrNotification::create([
                'user_id' => (int) $userId,
                'type' => 'daily_worker_contract',
                'title' => 'Kontrak Ditandatangani & Dikirim',
                'body' => 'Ada kontrak kandidat yang sudah ditandatangani dan menunggu review HRD.',
                'due_date' => now()->toDateString(),
                'is_read' => false,
                'unique_key' => 'contract-submitted-' . $contract->id . '-' . $userId . '-' . now()->timestamp,
                'meta' => [
                    'contract_id' => $contract->id,
                    'route' => route('hrd.contracts.show', $contract),
                ],
            ]);
        }
    }

    public function notifyCandidateReviewed(Contract $contract): void
    {
        $userId = (int) ($contract->candidate?->user_id ?? 0);
        if ($userId <= 0) {
            return;
        }

        $isApproved = $contract->status === Contract::STATUS_APPROVED;
        $meta = $contract->meta_json ?? [];

        HrNotification::create([
            'user_id' => $userId,
            'type' => 'daily_worker_contract',
            'title' => $isApproved ? 'Kontrak Disetujui HRD' : 'Kontrak Ditolak HRD',
            'body' => $isApproved
                ? 'Kontrak Anda disetujui. Anda siap diproses ke tahap probation.'
                : 'Kontrak ditolak HRD. Alasan: ' . ((string) ($meta['rejection_reason'] ?? '-')),
            'due_date' => now()->toDateString(),
            'is_read' => false,
            'unique_key' => 'contract-reviewed-' . $contract->id . '-' . now()->timestamp,
            'meta' => [
                'contract_id' => $contract->id,
                'status' => $contract->status,
                'route' => route('applicant.contracts.show', $contract),
            ],
        ]);
    }

    /**
     * @param array<string,mixed>|null $meta
     * @return array<string,mixed>
     */
    private function appendStatusHistory(?array $meta, string $status, ?int $actorUserId, ?string $note): array
    {
        $current = $meta ?? [];
        $history = is_array($current['status_history'] ?? null) ? $current['status_history'] : [];
        $history[] = [
            'status' => $status,
            'at' => now()->toIso8601String(),
            'actor_user_id' => $actorUserId,
            'note' => $note,
        ];
        $current['status_history'] = $history;

        return $current;
    }

    private function renderTemplateHtml(string $bodyHtml, array $payload): string
    {
        $map = [
            '{{candidate_name}}' => (string) ($payload['candidate_name'] ?? '-'),
            '{{candidate_nik}}' => (string) ($payload['candidate_nik'] ?? '-'),
            '{{candidate_email}}' => (string) ($payload['candidate_email'] ?? '-'),
            '{{candidate_phone}}' => (string) ($payload['candidate_phone'] ?? '-'),
            '{{candidate_address}}' => (string) ($payload['candidate_address'] ?? '-'),
            '{{position_name}}' => (string) ($payload['position_name'] ?? '-'),
            '{{outlet_name}}' => (string) ($payload['outlet_name'] ?? '-'),
            '{{contract_number}}' => (string) ($payload['contract_number'] ?? '-'),
            '{{today_date}}' => (string) ($payload['today_date'] ?? now()->translatedFormat('d F Y')),
            '{{hrd_name}}' => (string) ($payload['hrd_name'] ?? 'HRD'),
        ];

        return str_replace(array_keys($map), array_values($map), $bodyHtml);
    }

    private function generateContractNumber(ContractTemplate $template): string
    {
        $prefix = (string) ($template->numbering_prefix ?? 'DW/');
        $format = (string) ($template->numbering_format ?? '{prefix}{YYYY}{MM}{SEQ4}');
        $seq = max(1, (int) $template->next_sequence);

        $map = [
            '{prefix}' => $prefix,
            '{YYYY}' => now()->format('Y'),
            '{YY}' => now()->format('y'),
            '{MM}' => now()->format('m'),
            '{DD}' => now()->format('d'),
            '{SEQ4}' => str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
            '{SEQ}' => (string) $seq,
        ];

        return strtr($format, $map);
    }

    private function storePdfFromHtml(string $html, string $folder, string $contractNumber): string
    {
        $path = trim($folder, '/') . '/' . str_replace(['/', '\\', ' '], '_', $contractNumber) . '_' . now()->format('YmdHis') . '.pdf';
        $pdf = Pdf::loadHTML('<html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>')->setPaper('a4');
        Storage::disk('public')->put($path, $pdf->output());

        return $path;
    }

    private function buildSignedPdf(Contract $contract): string
    {
        $meta = $contract->meta_json ?? [];
        $bodyHtml = (string) ($meta['body_html'] ?? '');
        $signaturePath = (string) ($contract->latestCandidateSignature?->signature_image_path ?? '');
        $stamp = $contract->latestStamp;

        $signatureImageUrl = $signaturePath !== '' ? asset('storage/' . ltrim($signaturePath, '/')) : '';

        $stampText = '-';
        if ($stamp) {
            $stampText = $stamp->stamp_number ?: 'Bukti upload tersedia';
        }

        $signedBlock = '<div style="margin-top:24px; border-top:1px solid #ddd; padding-top:12px;">'
            . '<div style="font-weight:600; color:#1d4ed8;">Tanda Tangan Kandidat (Blue Ink)</div>'
            . '<div style="margin-top:6px;">Materai: ' . e($stampText) . '</div>'
            . ($signatureImageUrl !== ''
                ? '<div style="margin-top:8px;"><img src="' . e($signatureImageUrl) . '" alt="Signature" style="width:220px; height:auto;"></div>'
                : '<div style="margin-top:8px;">(Tidak ada image signature)</div>')
            . '<div style="margin-top:6px;">Signed at: ' . now()->format('d/m/Y H:i:s') . '</div>'
            . '</div>';

        $finalHtml = $bodyHtml . $signedBlock;

        return $this->storePdfFromHtml($finalHtml, 'contracts/signed', $contract->contract_number . '_signed');
    }
}
