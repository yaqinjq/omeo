<?php

namespace App\Services;

use App\Models\ApplicantProfile;
use App\Models\Candidate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ApplicantTalentPoolQuery
{
    public const TAB_INCOMPLETE = 'incomplete';
    public const TAB_UNVERIFIED = 'unverified';
    public const TAB_VERIFIED = 'verified';

    /**
     * @return array{tab:string,search:string,sort:string,order:string,page:int}
     */
    public function filtersFromRequest(Request $request): array
    {
        $tab = (string) $request->input('tab', $request->query('tab', self::TAB_INCOMPLETE));
        $sort = (string) $request->input('sort', $request->query('sort', 'updated_at'));
        $order = strtolower((string) $request->input('order', $request->query('order', 'desc'))) === 'asc' ? 'asc' : 'desc';

        return [
            'tab' => in_array($tab, [self::TAB_INCOMPLETE, self::TAB_UNVERIFIED, self::TAB_VERIFIED], true) ? $tab : self::TAB_INCOMPLETE,
            'search' => trim((string) $request->input('search', $request->query('search', ''))),
            'sort' => $sort === 'name' ? 'name' : 'updated_at',
            'order' => $order,
            'page' => max(1, (int) $request->input('page', $request->query('page', 1))),
        ];
    }

    /**
     * @param array{tab:string,search:string,sort:string,order:string,page?:int} $filters
     */
    public function build(array $filters, bool $withUser = true): Builder
    {
        $query = ApplicantProfile::query()
            ->when($withUser, fn (Builder $builder) => $builder->with('user'));

        if (ApplicantProfile::supportsGovernanceStatusColumn()) {
            $query->where('governance_status', ApplicantProfile::GOVERNANCE_STATUS_ACTIVE);
        }

        $this->applySearchFilter($query, (string) ($filters['search'] ?? ''));
        $this->applyTabFilter($query, (string) ($filters['tab'] ?? self::TAB_INCOMPLETE));
        $this->applySort($query, (string) ($filters['sort'] ?? 'updated_at'), (string) ($filters['order'] ?? 'desc'));

        return $query;
    }

    /**
     * @param array{tab:string,search:string,sort:string,order:string,page?:int} $filters
     * @param array<int,int|string> $profileIds
     * @return array<int,int>
     */
    public function resolveSelectedProfileIds(array $filters, string $selectionScope, array $profileIds = []): array
    {
        $query = $this->build($filters, withUser: false)->select('applicant_profiles.id');

        if ($selectionScope === 'filtered') {
            return $query->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $ids = collect($profileIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        return $query
            ->whereIn('applicant_profiles.id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return array{user_ids:array<int,int>,emails:array<int,string>,niks:array<int,string>}
     */
    public function buildCandidateSignals(): array
    {
        $candidates = Candidate::query()
            ->select(['user_id', 'email', 'nik'])
            ->get();

        return [
            'user_ids' => $candidates
                ->pluck('user_id')
                ->filter(fn ($value) => $value !== null)
                ->map(fn ($value) => (int) $value)
                ->unique()
                ->values()
                ->all(),
            'emails' => $candidates
                ->pluck('email')
                ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                ->map(fn ($value) => mb_strtolower(trim((string) $value)))
                ->unique()
                ->values()
                ->all(),
            'niks' => $candidates
                ->pluck('nik')
                ->filter(fn ($value) => is_string($value) && trim((string) $value) !== '')
                ->map(fn ($value) => trim((string) $value))
                ->unique()
                ->values()
                ->all(),
        ];
    }

    public function resolveCandidateForProfile(ApplicantProfile $profile): ?Candidate
    {
        $email = mb_strtolower(trim((string) ($profile->user?->email ?? data_get($profile->personal_json, 'email', ''))));
        $nik = trim((string) data_get($profile->personal_json, 'ktp_number', ''));
        $userId = $profile->user_id ? (int) $profile->user_id : null;

        if ($userId === null && $email === '' && $nik === '') {
            return null;
        }

        return Candidate::query()
            ->where(function (Builder $query) use ($userId, $email, $nik): void {
                if ($userId !== null) {
                    $query->orWhere('user_id', $userId);
                }

                if ($email !== '') {
                    $query->orWhere('email', $email);
                }

                if ($nik !== '') {
                    $query->orWhere('nik', $nik);
                }
            })
            ->latest('id')
            ->first();
    }

    public function isVerified(ApplicantProfile $profile, array $signals): bool
    {
        if (! $profile->is_complete || ! $profile->isGovernanceActive()) {
            return false;
        }

        $email = mb_strtolower(trim((string) ($profile->user?->email ?? data_get($profile->personal_json, 'email', ''))));
        $nik = trim((string) data_get($profile->personal_json, 'ktp_number', ''));

        return in_array((int) $profile->user_id, $signals['user_ids'], true)
            || ($email !== '' && in_array($email, $signals['emails'], true))
            || ($nik !== '' && in_array($nik, $signals['niks'], true));
    }

    private function applySearchFilter(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $query->where(function (Builder $builder) use ($search): void {
            $builder
                ->where('personal_json->full_name', 'like', "%{$search}%")
                ->orWhere('personal_json->email', 'like', "%{$search}%")
                ->orWhere('personal_json->ktp_number', 'like', "%{$search}%")
                ->orWhereHas('user', function (Builder $userQuery) use ($search): void {
                    $userQuery->where('email', 'like', "%{$search}%");
                });
        });
    }

    private function applyTabFilter(Builder $query, string $tab): void
    {
        $signals = $this->buildCandidateSignals();

        if ($tab === self::TAB_INCOMPLETE) {
            $query->whereNull('completed_at');

            return;
        }

        $query->whereNotNull('completed_at');

        if ($tab === self::TAB_VERIFIED) {
            $query->where(function (Builder $builder) use ($signals): void {
                $this->applyVerifiedMatchClause($builder, $signals);
            });

            return;
        }

        $query->where(function (Builder $builder) use ($signals): void {
            $this->applyUnverifiedClause($builder, $signals);
        });
    }

    /**
     * @param array{user_ids:array<int,int>,emails:array<int,string>,niks:array<int,string>} $signals
     */
    private function applyVerifiedMatchClause(Builder $query, array $signals): void
    {
        $userIds = $signals['user_ids'];
        $emails = $signals['emails'];
        $niks = $signals['niks'];

        $query->where(function (Builder $builder) use ($userIds, $emails, $niks): void {
            if ($userIds !== []) {
                $builder->orWhereIn('user_id', $userIds);
            }

            if ($emails !== []) {
                $builder->orWhereIn('personal_json->email', $emails)
                    ->orWhereHas('user', function (Builder $userQuery) use ($emails): void {
                        $userQuery->whereIn('email', $emails);
                    });
            }

            if ($niks !== []) {
                $builder->orWhereIn('personal_json->ktp_number', $niks);
            }
        });
    }

    /**
     * @param array{user_ids:array<int,int>,emails:array<int,string>,niks:array<int,string>} $signals
     */
    private function applyUnverifiedClause(Builder $query, array $signals): void
    {
        $userIds = $signals['user_ids'];
        $emails = $signals['emails'];
        $niks = $signals['niks'];

        if ($userIds !== []) {
            $query->whereNotIn('user_id', $userIds);
        }

        if ($emails !== []) {
            $query->where(function (Builder $builder) use ($emails): void {
                $builder->whereNull('personal_json->email')
                    ->orWhereNotIn('personal_json->email', $emails);
            })->whereDoesntHave('user', function (Builder $userQuery) use ($emails): void {
                $userQuery->whereIn('email', $emails);
            });
        }

        if ($niks !== []) {
            $query->where(function (Builder $builder) use ($niks): void {
                $builder->whereNull('personal_json->ktp_number')
                    ->orWhereNotIn('personal_json->ktp_number', $niks);
            });
        }
    }

    private function applySort(Builder $query, string $sort, string $order): void
    {
        if ($sort === 'name') {
            $query->orderBy('personal_json->full_name', $order)->orderBy('id');

            return;
        }

        $query->orderBy('updated_at', $order)->orderBy('id', 'desc');
    }
}
