<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Walkin\Candidate;
use App\Models\Walkin\Event;
use App\Models\Walkin\ReferralLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WalkinController extends Controller
{
    // ── HRD: daftar event ────────────────────────────────────────────────────

    public function index(): View
    {
        $events = Event::query()
            ->withCount('candidates')
            ->orderByDesc('event_start_datetime')
            ->orderByDesc('id')
            ->paginate(20);

        return view('recruitment.walkin.index', compact('events'));
    }

    // ── HRD: form buat event ─────────────────────────────────────────────────

    public function create(): View
    {
        return view('recruitment.walkin.create');
    }

    // ── HRD: simpan event ────────────────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title'                   => ['required', 'string', 'max:160'],
            'event_start_datetime'    => ['required', 'date'],
            'event_end_datetime'      => ['required', 'date', 'after:event_start_datetime'],
            'location'                => ['nullable', 'string', 'max:160'],
            'description'             => ['nullable', 'string'],
            'target_positions'        => ['nullable', 'array'],
            'target_positions.*'      => ['string', 'max:80'],
            'status'                  => ['required', 'in:draft,published,ongoing,completed'],
            'registration_open_until' => ['nullable', 'date'],
        ]);

        $data['created_by'] = auth()->id();

        $event = Event::create($data);

        return redirect()->route('walkin.dashboard', $event->id)
            ->with('success', 'Event Walk-In berhasil dibuat.');
    }

    // ── HRD: detail event + kandidat ─────────────────────────────────────────

    public function show(int $id): View
    {
        $event = Event::with('creator')->findOrFail($id);
        $candidates = Candidate::where('event_id', $id)
            ->with('referrer')
            ->orderBy('registration_number')
            ->paginate(30);

        return view('recruitment.walkin.show', compact('event', 'candidates'));
    }

    // ── HRD: generate/get link referral (FIX 6 — expanded roles) ─────────────

    public function myLink(int $id): RedirectResponse
    {
        $event = Event::findOrFail($id);
        $user  = auth()->user();
        $userId = (int) auth()->id();

        // Roles that may generate a referral link
        $allowedRoles = [
            'admin', 'hrd', 'manager', 'supervisor',
            'outlet_manager', 'asst_manager',
            'demi_chef', 'trainer', 'coordinator',
        ];

        $userRole = $user->role ?? (
            method_exists($user, 'getRoleNames')
                ? ($user->getRoleNames()->first() ?? '')
                : ''
        );

        $canGenerate = in_array($userRole, $allowedRoles)
            || (method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo('recruitment.walkin.generate_link'));

        if (! $canGenerate) {
            return back()->with('error', 'Anda tidak memiliki akses untuk generate link.');
        }

        $link = ReferralLink::where('event_id', $id)->where('user_id', $userId)->first();

        if (! $link) {
            $code = $this->generateReferralCode($id, $userId);
            $link = ReferralLink::create([
                'event_id'      => $id,
                'user_id'       => $userId,
                'referral_code' => $code,
            ]);
        }

        return redirect()->route('walkin.dashboard', $id)
            ->with('myLinkUrl', url('/daftar/' . $link->referral_code))
            ->with('myCode', $link->referral_code);
    }

    // ── PUBLIK: form registrasi kandidat ─────────────────────────────────────

    public function register(string $code): View
    {
        $link = ReferralLink::with('event')->where('referral_code', $code)->firstOrFail();
        $event = $link->event;

        abort_unless($event->isRegistrationOpen(), 410, 'Pendaftaran sudah ditutup.');

        return view('recruitment.walkin.register', compact('event', 'link'));
    }

    // ── PUBLIK: simpan registrasi (FIX 1 — cegah duplikasi nomor HP) ─────────

    public function registerStore(Request $request, string $code): View|RedirectResponse
    {
        $link = ReferralLink::with('event')->where('referral_code', $code)->firstOrFail();
        $event = $link->event;

        abort_unless($event->isRegistrationOpen(), 410);

        $data = $request->validate([
            'candidate_name'   => ['required', 'string', 'max:160'],
            'candidate_phone'  => ['required', 'string', 'max:30'],
            'candidate_email'  => ['nullable', 'email', 'max:160'],
            'ig_account'       => ['nullable', 'string', 'max:80'],
            'applied_position' => ['nullable', 'string', 'max:80'],
        ]);

        // FIX 1: Cek duplikasi nomor HP dalam event yang sama
        $existing = DB::table('walkin_candidates')
            ->where('event_id', $event->id)
            ->where('candidate_phone', $data['candidate_phone'])
            ->first();

        if ($existing) {
            return back()->withErrors([
                'candidate_phone' => 'Nomor HP ini sudah terdaftar di event ini. '
                    . 'Nomor antrian Anda: ' . $existing->registration_number,
            ])->withInput();
        }

        $regNumber = DB::transaction(function () use ($event, $link, $data): string {
            $count = Candidate::where('event_id', $event->id)->lockForUpdate()->count();
            $regNumber = 'W' . str_pad($event->id, 3, '0', STR_PAD_LEFT)
                        . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);

            Candidate::create(array_merge($data, [
                'event_id'            => $event->id,
                'referral_code'       => $link->referral_code,
                'referred_by_user_id' => $link->user_id,
                'registration_number' => $regNumber,
            ]));

            $link->increment('total_registrations');

            return $regNumber;
        });

        return view('recruitment.walkin.register_success', [
            'event'     => $event,
            'name'      => $data['candidate_name'],
            'regNumber' => $regNumber,
        ]);
    }

    // ── HRD: dashboard event ─────────────────────────────────────────────────

    public function dashboard(int $id): View
    {
        $event  = Event::findOrFail($id);
        $userId = (int) auth()->id();
        $user   = auth()->user();

        $isManager = method_exists($user, 'hasRole')
            && $user->hasRole(['admin', 'manager']);

        $myLink = ReferralLink::where('event_id', $id)->where('user_id', $userId)->first();
        $myLinkUrl = $myLink ? url('/daftar/' . $myLink->referral_code) : null;

        if (session('myLinkUrl')) {
            $myLinkUrl = session('myLinkUrl');
        }

        $allCandidates = Candidate::where('event_id', $id)
            ->with('referrer')
            ->orderBy('registration_number')
            ->get();

        $myCandidates = Candidate::where('event_id', $id)
            ->where('referred_by_user_id', $userId)
            ->with('referrer')
            ->orderBy('registration_number')
            ->get();

        $stats = [
            'total'            => $allCandidates->count(),
            'checked_in'       => $allCandidates->where('registration_status', 'checked_in')->count(),
            'no_show'          => $allCandidates->where('registration_status', 'no_show')->count(),
            'interview_passed' => $allCandidates->where('interview_status', 'passed')->count(),
            'test_passed'      => $allCandidates->where('test_status', 'passed')->count(),
            'accepted'         => $allCandidates->where('final_status', 'accepted')->count(),
            'reserved'         => $allCandidates->where('final_status', 'reserved')->count(),
        ];

        // === AUTO-SORT BY POSISI ===
        $positionQueues = $allCandidates
            ->sortBy([
                fn ($c) => strtoupper(trim($c->applied_position ?? 'TIDAK DIISI')),
                fn ($c) => $c->check_in_time?->timestamp ?? PHP_INT_MAX,
                fn ($c) => $c->created_at?->timestamp ?? 0,
            ])
            ->groupBy(fn ($c) => strtoupper(trim($c->applied_position ?? 'TIDAK DIISI')))
            ->sortKeys()
            ->map(function ($candidates) {
                $queueNo = 1;
                return $candidates->map(function ($candidate) use (&$queueNo) {
                    if ($candidate->registration_status === 'checked_in') {
                        $candidate->queue_number = $queueNo++;
                    } else {
                        $candidate->queue_number = null;
                    }
                    return $candidate;
                });
            });

        $positionSummary = $positionQueues->map(fn ($candidates, $pos) => [
            'position'   => $pos,
            'total'      => $candidates->count(),
            'checked_in' => $candidates->where('registration_status', 'checked_in')->count(),
            'pending'    => $candidates->where('registration_status', 'registered')->count(),
            'no_show'    => $candidates->where('registration_status', 'no_show')->count(),
        ]);

        $totalCheckedIn  = $stats['checked_in'];
        $totalRegistered = $stats['total'];

        return view('recruitment.walkin.dashboard', compact(
            'event', 'stats', 'allCandidates', 'myCandidates',
            'myLink', 'myLinkUrl', 'isManager',
            'positionQueues', 'positionSummary', 'totalCheckedIn', 'totalRegistered',
        ));
    }

    // ── PUBLIK: halaman info event (tanpa perlu referral code) ───────────────

    public function publicShow(int $id): View
    {
        $event = Event::whereIn('status', ['published', 'ongoing', 'completed'])
            ->findOrFail($id);

        return view('recruitment.walkin.public_show', compact('event'));
    }

    // ── HRD: check-in kandidat ────────────────────────────────────────────────

    public function checkIn(int $candidateId): RedirectResponse
    {
        $candidate = Candidate::findOrFail($candidateId);
        $candidate->update([
            'registration_status' => 'checked_in',
            'check_in_time'       => now(),
        ]);

        return back()->with('success', "Check-in {$candidate->candidate_name} berhasil.");
    }

    // ── HRD: detail kandidat ─────────────────────────────────────────────────

    public function showCandidate(int $candidateId): View
    {
        $candidate = DB::table('walkin_candidates as wc')
            ->leftJoin('walkin_events as we', 'we.id', '=', 'wc.event_id')
            ->leftJoin('walkin_referral_links as wrl', 'wrl.referral_code', '=', 'wc.referral_code')
            ->leftJoin('users as u', 'u.id', '=', 'wrl.user_id')
            ->leftJoin('outlets as o', 'o.id', '=', 'wc.plotting_outlet_id')
            ->leftJoin('users as interviewer', 'interviewer.id', '=', 'wc.interviewed_by')
            ->where('wc.id', $candidateId)
            ->select(
                'wc.*',
                'we.title as event_title',
                'we.event_start_datetime',
                'u.name as referred_by_name',
                'o.name as outlet_name',
                'interviewer.name as interviewer_name'
            )
            ->first();

        abort_if(! $candidate, 404);

        $outlets = DB::table('outlets')
            ->whereIn('outlet_type', ['operational', 'payroll'])
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('recruitment.walkin.candidate_detail', compact('candidate', 'outlets'));
    }

    // ── HRD: update status interview ─────────────────────────────────────────

    public function updateInterview(Request $request, int $candidateId): RedirectResponse
    {
        $data = $request->validate([
            'interview_status' => ['required', 'in:passed,failed,skipped'],
            'interview_notes'  => ['nullable', 'string', 'max:500'],
        ]);

        DB::table('walkin_candidates')
            ->where('id', $candidateId)
            ->update([
                'interview_status' => $data['interview_status'],
                'interview_notes'  => $data['interview_notes'] ?? null,
                'interviewed_by'   => auth()->id(),
                'updated_at'       => now(),
            ]);

        return back()->with('success', 'Status interview diperbarui.');
    }

    // ── HRD: update hasil tes ─────────────────────────────────────────────────

    public function updateTest(Request $request, int $candidateId): RedirectResponse
    {
        $data = $request->validate([
            'test_type'   => ['required', 'in:differensial_disc,iq,none'],
            'test_score'  => ['nullable', 'numeric', 'min:0', 'max:100'],
            'test_status' => ['required', 'in:passed,failed,pending'],
        ]);

        DB::table('walkin_candidates')
            ->where('id', $candidateId)
            ->update([
                'test_type'   => $data['test_type'],
                'test_score'  => $data['test_score'],
                'test_status' => $data['test_status'],
                'updated_at'  => now(),
            ]);

        return back()->with('success', 'Hasil tes diperbarui.');
    }

    // ── HRD: plotting & final status ─────────────────────────────────────────

    public function updatePlotting(Request $request, int $candidateId): RedirectResponse
    {
        $data = $request->validate([
            'plotting_outlet_id' => ['nullable', 'exists:outlets,id'],
            'plotting_position'  => ['nullable', 'string', 'max:100'],
            'final_status'       => ['required', 'in:pending,accepted,reserved,rejected'],
            'notes'              => ['nullable', 'string', 'max:500'],
        ]);

        DB::table('walkin_candidates')
            ->where('id', $candidateId)
            ->update([
                'plotting_outlet_id' => $data['plotting_outlet_id'],
                'plotting_position'  => $data['plotting_position'],
                'final_status'       => $data['final_status'],
                'notes'              => $data['notes'] ?? null,
                'updated_at'         => now(),
            ]);

        return back()->with('success', 'Plotting kandidat diperbarui.');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function generateReferralCode(int $eventId, int $userId): string
    {
        $code = 'W' . str_pad($eventId, 3, '0', STR_PAD_LEFT)
               . '-H' . str_pad($userId, 2, '0', STR_PAD_LEFT)
               . '-' . strtoupper(substr(md5(uniqid()), 0, 3));

        while (DB::table('walkin_referral_links')->where('referral_code', $code)->exists()) {
            $code = 'W' . str_pad($eventId, 3, '0', STR_PAD_LEFT)
                   . '-H' . str_pad($userId, 2, '0', STR_PAD_LEFT)
                   . '-' . strtoupper(substr(md5(uniqid()), 0, 3));
        }

        return $code;
    }
}
