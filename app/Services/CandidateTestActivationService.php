<?php

namespace App\Services;

use App\Models\AssessmentForm;
use App\Models\Candidate;
use App\Models\FormAssignment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CandidateTestActivationService
{
    /**
     * @param array<string,mixed> $payload
     * @return Collection<int,AssessmentForm>
     */
    public function resolveFormsFromPayload(array $payload): Collection
    {
        $legacyIds = $this->extractLegacyFormIds($payload);
        $requestedIds = collect($payload['form_ids'] ?? []);

        if (! empty($payload['form_id'])) {
            $requestedIds->push($payload['form_id']);
        }

        $requestedIds = $requestedIds
            ->merge($legacyIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($requestedIds->isEmpty()) {
            throw ValidationException::withMessages([
                'form_ids' => 'Pilih minimal satu test dari master Form Dinamis.',
            ]);
        }

        $forms = AssessmentForm::query()
            ->whereIn('id', $requestedIds->all())
            ->where('is_active', true)
            ->get(['id', 'code', 'name', 'type', 'duration_minutes']);

        if ($forms->count() !== $requestedIds->count()) {
            throw ValidationException::withMessages([
                'form_ids' => 'Sebagian test yang dipilih tidak ditemukan atau sedang nonaktif.',
            ]);
        }

        if ($legacyIds !== []) {
            $this->assertLegacySelectionsStillMatch($forms, $payload);
        }

        if ($forms->contains(fn (AssessmentForm $form) => empty($form->duration_minutes))) {
            throw ValidationException::withMessages([
                'form_ids' => 'Durasi template belum diatur. Lengkapi duration_minutes di Form Builder sebelum aktivasi test.',
            ]);
        }

        return $forms
            ->sortBy(fn (AssessmentForm $form) => sprintf(
                '%02d:%s:%08d',
                $this->typeOrder($form->type),
                mb_strtolower($form->name),
                (int) $form->id,
            ))
            ->values();
    }

    /**
     * @param array<int,int> $candidateIds
     * @param Collection<int,AssessmentForm> $forms
     * @param array<string,mixed> $meta
     * @return array{processed_pairs:int,opened_or_updated:int,skipped_completed:int,failed:int}
     */
    public function activateForCandidates(array $candidateIds, Collection $forms, ?User $actor = null, array $meta = []): array
    {
        $ids = collect($candidateIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $stats = [
            'processed_pairs' => 0,
            'opened_or_updated' => 0,
            'skipped_completed' => 0,
            'failed' => 0,
        ];

        if ($ids->isEmpty() || $forms->isEmpty()) {
            return $stats;
        }

        $candidates = Candidate::query()
            ->whereIn('id', $ids->all())
            ->get()
            ->keyBy('id');

        foreach ($ids as $candidateId) {
            $candidate = $candidates->get($candidateId);
            if (! $candidate) {
                $stats['failed'] += $forms->count();
                continue;
            }

            foreach ($forms as $form) {
                $stats['processed_pairs']++;

                try {
                    $result = $this->activateSingleAssignment($candidate, $form, $actor, $meta);

                    if ($result === 'skipped_completed') {
                        $stats['skipped_completed']++;
                        continue;
                    }

                    $stats['opened_or_updated']++;
                } catch (Throwable) {
                    $stats['failed']++;
                }
            }
        }

        return $stats;
    }

    /**
     * @param Collection<int,AssessmentForm> $forms
     * @param array<string,mixed> $meta
     * @return array{processed_pairs:int,opened_or_updated:int,skipped_completed:int,failed:int}
     */
    public function activateForCandidate(Candidate $candidate, Collection $forms, ?User $actor = null, array $meta = []): array
    {
        return $this->activateForCandidates([(int) $candidate->id], $forms, $actor, $meta);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<int,int>
     */
    private function extractLegacyFormIds(array $payload): array
    {
        $action = (string) ($payload['action'] ?? '');
        $ids = [];

        if (in_array($action, ['iq', 'both'], true) && ! empty($payload['iq_form_id'])) {
            $ids[] = (int) $payload['iq_form_id'];
        }

        if (in_array($action, ['disc', 'both'], true) && ! empty($payload['disc_form_id'])) {
            $ids[] = (int) $payload['disc_form_id'];
        }

        return $ids;
    }

    /**
     * @param Collection<int,AssessmentForm> $forms
     * @param array<string,mixed> $payload
     */
    private function assertLegacySelectionsStillMatch(Collection $forms, array $payload): void
    {
        $action = (string) ($payload['action'] ?? '');

        if (in_array($action, ['iq', 'both'], true)) {
            $iqFormId = (int) ($payload['iq_form_id'] ?? 0);
            $matched = $forms->first(fn (AssessmentForm $form) => (int) $form->id === $iqFormId && $form->type === AssessmentForm::TYPE_IQ);
            if (! $matched) {
                throw ValidationException::withMessages([
                    'iq_form_id' => 'Template IQ tidak valid, tidak aktif, atau tipe tidak sesuai.',
                ]);
            }
        }

        if (in_array($action, ['disc', 'both'], true)) {
            $discFormId = (int) ($payload['disc_form_id'] ?? 0);
            $matched = $forms->first(fn (AssessmentForm $form) => (int) $form->id === $discFormId && $form->type === AssessmentForm::TYPE_DISC);
            if (! $matched) {
                throw ValidationException::withMessages([
                    'disc_form_id' => 'Template DISC tidak valid, tidak aktif, atau tipe tidak sesuai.',
                ]);
            }
        }
    }

    /**
     * @param array<string,mixed> $meta
     */
    private function activateSingleAssignment(Candidate $candidate, AssessmentForm $form, ?User $actor, array $meta): string
    {
        return DB::transaction(function () use ($candidate, $form, $actor): string {
            $assignment = FormAssignment::query()
                ->where('form_id', $form->id)
                ->where('candidate_id', $candidate->id)
                ->lockForUpdate()
                ->first();

            if ($assignment && in_array($assignment->status, [FormAssignment::STATUS_SUBMITTED, FormAssignment::STATUS_EXPIRED], true)) {
                return 'skipped_completed';
            }

            $assignment ??= new FormAssignment([
                'form_id' => $form->id,
                'candidate_id' => $candidate->id,
            ]);

            $openedAt = now();
            $assignment->fill([
                'status' => FormAssignment::STATUS_OPENED,
                'opened_at' => $openedAt,
                'expires_at' => $openedAt->copy()->addMinutes((int) $form->duration_minutes),
                'closed_at' => null,
                'created_by' => $actor?->id,
            ])->save();

            return 'opened';
        });
    }

    private function typeOrder(string $type): int
    {
        return match ($type) {
            AssessmentForm::TYPE_IQ => 1,
            AssessmentForm::TYPE_DISC => 2,
            AssessmentForm::TYPE_TIU => 3,
            AssessmentForm::TYPE_DIFERENSIAL => 4,
            AssessmentForm::TYPE_FAT => 5,
            AssessmentForm::TYPE_CUSTOM => 6,
            default => 99,
        };
    }
}
