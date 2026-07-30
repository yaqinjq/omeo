<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\WalkInCheckinToken;
use App\Models\WalkInEvent;
use App\Models\WalkInRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WalkInEventController extends Controller
{
    public function index(Request $request): View
    {
        $events = WalkInEvent::query()
            ->withCount('registrations')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest('event_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('hrd.walk_ins.index', [
            'events' => $events,
            'statuses' => WalkInEvent::STATUSES,
        ]);
    }

    public function create(): View
    {
        return view('hrd.walk_ins.form', [
            'event' => new WalkInEvent([
                'status' => WalkInEvent::STATUS_DRAFT,
                'event_date' => now()->addWeek(),
                'start_time' => '09:00',
                'end_time' => '15:00',
            ]),
            'positionsText' => '',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $positions = $this->positionsFromText((string) ($data['positions_text'] ?? ''));
        unset($data['positions_text'], $data['banner']);

        $data['slug'] = $this->uniqueSlug($data['slug'] ?? $data['title']);

        if ($request->hasFile('banner')) {
            $data['banner_path'] = $request->file('banner')->store('walk-ins', 'public');
        }

        DB::transaction(function () use ($data, $positions): void {
            $event = WalkInEvent::query()->create($data);
            $this->syncPositions($event, $positions);
        });

        return redirect()->route('dashboard.walk-ins.index')->with('success', 'Event Walk In berhasil dibuat.');
    }

    public function edit(WalkInEvent $walkIn): View
    {
        $walkIn->load('positions');

        return view('hrd.walk_ins.form', [
            'event' => $walkIn,
            'positionsText' => $walkIn->positions->sortBy('sort_order')->pluck('name')->implode("\n"),
        ]);
    }

    public function update(Request $request, WalkInEvent $walkIn): RedirectResponse
    {
        $data = $this->validated($request, $walkIn);
        $positions = $this->positionsFromText((string) ($data['positions_text'] ?? ''));
        unset($data['positions_text'], $data['banner']);

        $data['slug'] = $this->uniqueSlug($data['slug'] ?? $data['title'], $walkIn->id);

        if ($request->hasFile('banner')) {
            $data['banner_path'] = $request->file('banner')->store('walk-ins', 'public');
        }

        DB::transaction(function () use ($walkIn, $data, $positions): void {
            $walkIn->update($data);
            $this->syncPositions($walkIn, $positions);
        });

        return redirect()->route('dashboard.walk-ins.index')->with('success', 'Event Walk In berhasil diperbarui.');
    }

    public function destroy(WalkInEvent $walkIn): RedirectResponse
    {
        $walkIn->delete();

        return back()->with('success', 'Event Walk In berhasil dihapus.');
    }

    public function participants(Request $request, WalkInEvent $walkIn): View
    {
        $walkIn->load('positions');

        $registrations = WalkInRegistration::query()
            ->forEvent($walkIn)
            ->with(['position', 'referredUser', 'screener'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('position'), fn ($query) => $query->where('walk_in_event_position_id', $request->integer('position')))
            ->when($request->filled('referral'), fn ($query) => $query->where('referral_code', $request->string('referral')))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        $referrals = WalkInRegistration::query()
            ->forEvent($walkIn)
            ->whereNotNull('referral_code')
            ->select('referral_code', DB::raw('count(*) as total'))
            ->groupBy('referral_code')
            ->orderBy('referral_code')
            ->get();

        return view('hrd.walk_ins.participants', [
            'event' => $walkIn,
            'registrations' => $registrations,
            'statuses' => WalkInRegistration::STATUSES,
            'referrals' => $referrals,
        ]);
    }

    public function updateParticipant(Request $request, WalkInEvent $walkIn, WalkInRegistration $registration): RedirectResponse
    {
        abort_unless((int) $registration->walk_in_event_id === (int) $walkIn->id, 404);

        $data = $request->validate([
            'status' => ['required', Rule::in([WalkInRegistration::STATUS_PASSED, WalkInRegistration::STATUS_REJECTED, WalkInRegistration::STATUS_SCREENED, WalkInRegistration::STATUS_NO_SHOW])],
            'screening_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $registration->update([
            'status' => $data['status'],
            'screening_note' => $data['screening_note'] ?? null,
            'screened_by' => $request->user()->id,
            'screened_at' => now(),
        ]);

        return back()->with('success', 'Status screening peserta berhasil diperbarui.');
    }

    public function checkinQr(WalkInEvent $walkIn): View
    {
        [$plain] = WalkInCheckinToken::issueFor($walkIn, auth()->user());
        $checkinUrl = route('walk-ins.checkin.form', $plain);

        return view('hrd.walk_ins.checkin_qr', [
            'event' => $walkIn,
            'checkinUrl' => $checkinUrl,
            'expiresAt' => now()->addSeconds(90),
        ]);
    }

    private function validated(Request $request, ?WalkInEvent $event = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180', Rule::unique('walk_in_events', 'slug')->ignore($event?->id)],
            'status' => ['required', Rule::in(WalkInEvent::STATUSES)],
            'event_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:160'],
            'address' => ['nullable', 'string', 'max:2000'],
            'maps_url' => ['nullable', 'url', 'max:500'],
            'description' => ['nullable', 'string'],
            'quota' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'participant_instruction' => ['nullable', 'string', 'max:2000'],
            'positions_text' => ['required', 'string', 'max:3000'],
            'banner' => ['nullable', 'image', 'max:4096'],
        ]);
    }

    private function positionsFromText(string $text): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $text) ?: [])
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function syncPositions(WalkInEvent $event, array $positions): void
    {
        $event->positions()->update(['is_active' => false]);

        foreach ($positions as $index => $name) {
            $event->positions()->updateOrCreate(
                ['name' => $name],
                ['sort_order' => $index + 1, 'is_active' => true]
            );
        }
    }

    private function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'walk-in';
        $slug = $base;
        $counter = 2;

        while (WalkInEvent::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
