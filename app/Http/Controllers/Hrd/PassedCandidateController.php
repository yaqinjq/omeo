<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CandidateAssessment;
use App\Models\Contract;
use App\Models\ContractTemplate;
use Illuminate\Http\Request;

class PassedCandidateController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $query = Candidate::query()
            ->with([
                'assessment:id,candidate_id,status,updated_at',
                'user:id,email',
                'user.applicantProfile:id,user_id,personal_json,address_json,work_json',
                'latestContract:id,candidate_id,status',
            ])
            ->where(function ($q) {
                $q->where('status', Candidate::STATUS_ACCEPTED)
                    ->orWhereHas('assessment', function ($a) {
                        $a->where('status', CandidateAssessment::STATUS_PASSED);
                    });
            });

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%");
            });
        }

        $candidates = $query
            ->orderByDesc('accepted_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $candidates->getCollection()->transform(function (Candidate $candidate) {
            $profile = $candidate->user?->applicantProfile;
            $personal = $profile?->personal_json ?? [];
            $work = collect($profile?->work_experiences ?? [])->first();

            $candidate->position_name = (string) (
                data_get($personal, 'applied_position')
                ?? data_get($personal, 'position_applied')
                ?? data_get($personal, 'position')
                ?? data_get($work, 'position')
                ?? '-'
            );

            $candidate->outlet_name = (string) (
                data_get($personal, 'outlet')
                ?? data_get($personal, 'applied_outlet')
                ?? data_get($personal, 'preferred_outlet')
                ?? '-'
            );

            $candidate->accepted_label = $candidate->accepted_at?->format('d/m/Y H:i')
                ?? $candidate->assessment?->updated_at?->format('d/m/Y H:i')
                ?? '-';

            /** @var Contract|null $latestContract */
            $latestContract = $candidate->latestContract;
            $candidate->latest_contract_status = $latestContract?->status ?: 'belum_dikirim';
            $candidate->latest_contract_id = $latestContract?->id;

            return $candidate;
        });

        $templates = ContractTemplate::query()
            ->where('type', ContractTemplate::TYPE_DAILY_WORKER)
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);

        return view('hrd.passed_candidates.index', [
            'candidates' => $candidates,
            'search' => $search,
            'templates' => $templates,
        ]);
    }
}
