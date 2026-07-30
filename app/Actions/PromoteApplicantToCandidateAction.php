<?php

namespace App\Actions;

use App\Models\ApplicantProfile;
use App\Models\Candidate;
use App\Models\User;
use App\Services\ApplicantGovernanceAuditService;
use App\Services\ApplicantTalentPoolQuery;
use App\Services\CandidateBlacklistService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PromoteApplicantToCandidateAction
{
    public function __construct(
        private readonly CandidateBlacklistService $blacklistService,
        private readonly ApplicantGovernanceAuditService $auditService,
        private readonly ApplicantTalentPoolQuery $talentPoolQuery,
    ) {
    }

    public function execute(ApplicantProfile $profile, ?User $actor = null, array $meta = []): Candidate
    {
        $profile->loadMissing('user');

        if (! $profile->isGovernanceActive()) {
            throw ValidationException::withMessages([
                'profile' => 'Profil pelamar tidak aktif di Talent Pool.',
            ]);
        }

        if (! $profile->is_complete) {
            throw ValidationException::withMessages([
                'profile' => 'Pelamar belum melengkapi profil. Tombol ini hanya bisa digunakan untuk profil yang sudah lengkap.',
            ]);
        }

        $personal = $profile->normalizedPersonalJson();
        $fullName = trim((string) data_get($personal, 'full_name', $profile->full_name));
        if ($fullName === '') {
            throw ValidationException::withMessages([
                'profile' => 'Nama lengkap pelamar belum tersedia. Mohon lengkapi biodata pelamar terlebih dahulu.',
            ]);
        }

        $email = $profile->user?->email ?? data_get($personal, 'email');
        $phone = data_get($personal, 'whatsapp');
        $nik = data_get($personal, 'ktp_number');

        $matches = $this->blacklistService->findMatches((string) $nik, (string) $email, (string) $phone);
        if ($matches->isNotEmpty()) {
            $types = $matches->pluck('identifier_type')->filter()->unique()->implode(', ');
            if ($types === '') {
                $types = collect(['nik', 'email', 'phone'])
                    ->filter(fn ($type) => $matches->pluck($type)->filter()->isNotEmpty())
                    ->implode(', ');
            }

            throw ValidationException::withMessages([
                'profile' => 'Pelamar tidak dapat diloloskan karena ter-blacklist (' . ($types !== '' ? $types : 'identitas') . ').',
            ]);
        }

        return DB::transaction(function () use ($profile, $actor, $fullName, $email, $phone, $nik, $meta): Candidate {
            $existingCandidate = $this->talentPoolQuery->resolveCandidateForProfile($profile);
            $wasExisting = $existingCandidate !== null;

            if (! $existingCandidate && ($nik || $email)) {
                $existingCandidate = Candidate::query()
                    ->where(function ($query) use ($nik, $email): void {
                        if ($nik) {
                            $query->orWhere('nik', $nik);
                        }

                        if ($email) {
                            $query->orWhere('email', $email);
                        }
                    })
                    ->latest('id')
                    ->first();
                $wasExisting = $existingCandidate !== null;
            }

            $payload = [
                'user_id' => $profile->user?->id,
                'full_name' => $fullName,
                'email' => $email,
                'phone' => $phone,
                'nik' => $nik,
                'status' => Candidate::STATUS_SHORTLISTED,
                'notes' => 'Sumber: Lolos Administrasi HRD - Applicant Profile #' . $profile->id,
            ];

            if (Candidate::supportsAppliedProfileColumns()) {
                $payload = array_merge($payload, $this->appliedSnapshotPayload($profile));
            }

            if ($existingCandidate) {
                $existingCandidate->fill([
                    'user_id' => $payload['user_id'] ?: $existingCandidate->user_id,
                    'full_name' => $payload['full_name'],
                    'email' => $payload['email'] ?: $existingCandidate->email,
                    'phone' => $payload['phone'] ?: $existingCandidate->phone,
                    'nik' => $payload['nik'] ?: $existingCandidate->nik,
                    'notes' => $payload['notes'],
                ]);

                if (Candidate::supportsAppliedProfileColumns()) {
                    $existingCandidate->fill($this->appliedSnapshotPayload($profile));
                }

                if (! in_array($existingCandidate->status, [
                    Candidate::STATUS_ACCEPTED,
                    Candidate::STATUS_REJECTED,
                    Candidate::STATUS_BLOCKED,
                ], true)) {
                    $existingCandidate->status = Candidate::STATUS_SHORTLISTED;
                    $existingCandidate->accepted_at = null;
                    $existingCandidate->rejected_at = null;
                    $existingCandidate->blocked_at = null;
                }

                if (! $existingCandidate->applied_at) {
                    $existingCandidate->applied_at = now();
                }

                $existingCandidate->save();
                $candidate = $existingCandidate->fresh();
            } else {
                $payload['applied_at'] = now();
                $candidate = Candidate::query()->create($payload);
            }

            $this->auditService->log(
                $profile,
                'applicant_passed_administration',
                (string) ($profile->governance_status ?: ApplicantProfile::GOVERNANCE_STATUS_ACTIVE),
                (string) ($profile->governance_status ?: ApplicantProfile::GOVERNANCE_STATUS_ACTIVE),
                array_merge($meta, [
                    'candidate_id' => $candidate->id,
                    'candidate_status' => $candidate->status,
                    'was_existing_candidate' => $wasExisting,
                ]),
                $actor?->id
            );

            return $candidate;
        });
    }

    /**
     * @return array<string,mixed>
     */
    private function appliedSnapshotPayload(ApplicantProfile $profile): array
    {
        return [
            'applied_position_id' => $profile->applied_position_id ?: null,
            'applied_position_name' => $profile->applied_position_name !== '' ? $profile->applied_position_name : null,
            'applied_department_id' => $profile->applied_department_id ?: null,
            'applied_department_name' => $profile->applied_department_name !== '' ? $profile->applied_department_name : null,
            'applied_outlet_id' => $profile->applied_outlet_id ?: null,
            'applied_outlet_name' => $profile->applied_outlet_name !== '' ? $profile->applied_outlet_name : null,
        ];
    }
}
