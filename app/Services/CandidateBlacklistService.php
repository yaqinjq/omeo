<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\CandidateBlacklist;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CandidateBlacklistService
{
    public function normalizeNik(?string $nik): ?string
    {
        $value = preg_replace('/\D+/', '', trim((string) $nik));

        return $value !== '' ? $value : null;
    }

    public function normalizeEmail(?string $email): ?string
    {
        $value = mb_strtolower(trim((string) $email));

        return $value !== '' ? $value : null;
    }

    public function normalizePhone(?string $phone): ?string
    {
        $value = preg_replace('/\D+/', '', trim((string) $phone));

        return $value !== '' ? $value : null;
    }

    public function isNikBlacklisted(?string $nik): bool
    {
        $normalizedNik = $this->normalizeNik($nik);
        if (! $normalizedNik || ! $this->hasBlacklistTable()) {
            return false;
        }

        if ($this->supportsFlatColumns()) {
            return CandidateBlacklist::query()->where('nik', $normalizedNik)->exists();
        }

        if (! $this->supportsLegacyColumns()) {
            return false;
        }

        return CandidateBlacklist::query()
            ->where('identifier_type', 'nik')
            ->where('identifier_value', $normalizedNik)
            ->when($this->hasColumn('is_active'), fn ($query) => $query->where('is_active', true))
            ->exists();
    }

    public function findMatches(?string $nik, ?string $email, ?string $phone): Collection
    {
        $normalizedNik = $this->normalizeNik($nik);
        $normalizedEmail = $this->normalizeEmail($email);
        $normalizedPhone = $this->normalizePhone($phone);

        if ((! $normalizedNik && ! $normalizedEmail && ! $normalizedPhone) || ! $this->hasBlacklistTable()) {
            return collect();
        }

        if ($this->supportsFlatColumns()) {
            return CandidateBlacklist::query()
                ->where(function ($query) use ($normalizedNik, $normalizedEmail, $normalizedPhone): void {
                    if ($normalizedNik) {
                        $query->orWhere('nik', $normalizedNik);
                    }
                    if ($normalizedEmail && $this->hasColumn('email')) {
                        $query->orWhere('email', $normalizedEmail);
                    }
                    if ($normalizedPhone && $this->hasColumn('phone')) {
                        $query->orWhere('phone', $normalizedPhone);
                    }
                })
                ->get();
        }

        if (! $this->supportsLegacyColumns()) {
            return collect();
        }

        return CandidateBlacklist::query()
            ->when($this->hasColumn('is_active'), fn ($query) => $query->where('is_active', true))
            ->where(function ($query) use ($normalizedNik, $normalizedEmail, $normalizedPhone): void {
                if ($normalizedNik) {
                    $query->orWhere(function ($nested) use ($normalizedNik): void {
                        $nested->where('identifier_type', 'nik')->where('identifier_value', $normalizedNik);
                    });
                }
                if ($normalizedEmail) {
                    $query->orWhere(function ($nested) use ($normalizedEmail): void {
                        $nested->where('identifier_type', 'email')->where('identifier_value', $normalizedEmail);
                    });
                }
                if ($normalizedPhone) {
                    $query->orWhere(function ($nested) use ($normalizedPhone): void {
                        $nested->where('identifier_type', 'phone')->where('identifier_value', $normalizedPhone);
                    });
                }
            })
            ->get();
    }

    public function upsertBlockedCandidate(
        Candidate $candidate,
        ?string $reason = null,
        ?string $lastAppliedPosition = null,
        ?array $meta = null,
        string $source = 'system'
    ): bool {
        return $this->upsertBlockedIdentity(
            $candidate->nik,
            $candidate->email,
            $candidate->phone,
            $reason,
            $lastAppliedPosition,
            $meta,
            $source,
            candidateId: $candidate->id,
            blockedAt: $candidate->blocked_at ?? now(),
        );
    }

    public function upsertBlockedIdentity(
        ?string $nik,
        ?string $email,
        ?string $phone,
        ?string $reason = null,
        ?string $lastAppliedPosition = null,
        ?array $meta = null,
        string $source = 'system',
        ?int $candidateId = null,
        $blockedAt = null,
    ): bool {
        if (! $this->hasBlacklistTable()) {
            return false;
        }

        $nik = $this->normalizeNik($nik);
        $email = $this->normalizeEmail($email);
        $phone = $this->normalizePhone($phone);
        $blockedAt = $blockedAt ?? now();

        if (! $nik && ! $email && ! $phone) {
            return false;
        }

        if ($this->supportsFlatColumns()) {
            $identity = ['nik' => $nik ?: ('identity-' . ($candidateId ?: md5(($email ?: '') . '|' . ($phone ?: ''))))];
            $payload = $this->onlyExistingColumns([
                'email' => $email,
                'phone' => $phone,
                'reason' => $reason,
                'last_applied_position' => $lastAppliedPosition,
                'blocked_at' => $blockedAt,
                'source' => $source,
                'meta_json' => $meta,
                'updated_at' => now(),
            ]);

            if ($this->hasColumn('created_at')) {
                $payload['created_at'] = now();
            }

            CandidateBlacklist::query()->updateOrCreate($identity, $payload);

            return true;
        }

        if (! $this->supportsLegacyColumns()) {
            return false;
        }

        foreach (['nik' => $nik, 'email' => $email, 'phone' => $phone] as $type => $value) {
            if (! $value) {
                continue;
            }

            $payload = $this->onlyExistingColumns([
                'candidate_id' => $candidateId,
                'is_active' => true,
                'reason' => $reason,
                'created_by' => data_get($meta, 'actor_user_id'),
                'blacklisted_at' => $blockedAt,
                'metadata' => array_filter([
                    'last_applied_position' => $lastAppliedPosition,
                    'source' => $source,
                    'meta' => $meta,
                ]),
                'updated_at' => now(),
            ]);

            if ($this->hasColumn('created_at')) {
                $payload['created_at'] = now();
            }

            CandidateBlacklist::query()->updateOrCreate(
                ['identifier_type' => $type, 'identifier_value' => $value],
                $payload
            );
        }

        return true;
    }

    public function removeBlockedCandidate(Candidate $candidate): void
    {
        if (! $this->hasBlacklistTable()) {
            return;
        }

        $nik = $this->normalizeNik($candidate->nik);
        $email = $this->normalizeEmail($candidate->email);
        $phone = $this->normalizePhone($candidate->phone);

        if ($this->supportsFlatColumns()) {
            CandidateBlacklist::query()
                ->where(function ($query) use ($nik, $email, $phone): void {
                    if ($nik) {
                        $query->orWhere('nik', $nik);
                    }
                    if ($email && $this->hasColumn('email')) {
                        $query->orWhere('email', $email);
                    }
                    if ($phone && $this->hasColumn('phone')) {
                        $query->orWhere('phone', $phone);
                    }
                })
                ->delete();

            return;
        }

        if (! $this->supportsLegacyColumns()) {
            return;
        }

        $query = DB::table('candidate_blacklists')
            ->where(function ($builder) use ($nik, $email, $phone): void {
                if ($nik) {
                    $builder->orWhere(function ($nested) use ($nik): void {
                        $nested->where('identifier_type', 'nik')->where('identifier_value', $nik);
                    });
                }
                if ($email) {
                    $builder->orWhere(function ($nested) use ($email): void {
                        $nested->where('identifier_type', 'email')->where('identifier_value', $email);
                    });
                }
                if ($phone) {
                    $builder->orWhere(function ($nested) use ($phone): void {
                        $nested->where('identifier_type', 'phone')->where('identifier_value', $phone);
                    });
                }
            });

        $payload = $this->onlyExistingColumns([
            'is_active' => false,
            'updated_at' => now(),
        ]);

        if ($payload === []) {
            $query->delete();
            return;
        }

        $query->update($payload);
    }

    private function hasBlacklistTable(): bool
    {
        return Schema::hasTable('candidate_blacklists');
    }

    private function supportsFlatColumns(): bool
    {
        return $this->hasColumn('nik') && ! $this->supportsLegacyColumns();
    }

    private function supportsLegacyColumns(): bool
    {
        return $this->hasColumn('identifier_type') && $this->hasColumn('identifier_value');
    }

    private function hasColumn(string $column): bool
    {
        return $this->hasBlacklistTable() && Schema::hasColumn('candidate_blacklists', $column);
    }

    private function onlyExistingColumns(array $payload): array
    {
        return collect($payload)
            ->filter(fn ($value, $column) => $this->hasColumn((string) $column))
            ->all();
    }
}
