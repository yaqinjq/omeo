<?php

namespace App\Http\Controllers\Sport;

use App\Http\Controllers\Controller;
use App\Models\Sport\CancellationRequest;
use App\Models\Sport\Event;
use App\Models\Sport\Registration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Sisi Koordinator — buat/kelola event yang menjadi tanggung jawabnya,
 * lihat roster, dan memutuskan permintaan pembatalan peserta.
 *
 * Keputusan owner yang BELUM ada (lihat README modul Sport):
 * - Apakah event perlu approval Admin Finance sebelum publish/share?
 *   => BELUM diimplementasikan. Koordinator publish langsung begitu syarat
 *      kapasitas terpenuhi, sesuai instruksi "jangan mengasumsikan Admin
 *      wajib menyetujui" sampai pemilik proses memastikan sebaliknya.
 */
class EventController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $events = Event::query()
            ->when(! $this->isSportAdmin($user), fn ($q) => $q->where('coordinator_user_id', $user->id))
            ->withCount(['registrations', 'attendances'])
            ->orderByDesc('event_date')
            ->get();

        return view('sport.coordinator.index', ['events' => $events]);
    }

    public function create(): View
    {
        return view('sport.coordinator.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['coordinator_user_id'] = $request->user()->id;
        $data['created_by'] = $request->user()->id;
        $data['status'] = Event::STATUS_DRAFT;
        $data['share_token'] = Str::random(32);

        Event::create($data);

        return redirect()->route('sport.coordinator.index')->with('success', 'Event tersimpan sebagai draft.');
    }

    public function edit(Request $request, Event $event): View
    {
        $this->authorizeCoordinator($request, $event);

        return view('sport.coordinator.edit', ['event' => $event]);
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeCoordinator($request, $event);

        if (in_array($event->status, [Event::STATUS_COMPLETED, Event::STATUS_CANCELLED], true)) {
            return back()->with('error', 'Event yang sudah selesai/dibatalkan tidak bisa diedit.');
        }

        $data = $this->validated($request);
        $event->update($data);

        return redirect()->route('sport.coordinator.index')->with('success', 'Event berhasil diupdate.');
    }

    /**
     * Publish: draft -> published. Wajib kapasitas valid dulu kalau has_coach.
     */
    public function publish(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeCoordinator($request, $event);

        if ($event->status !== Event::STATUS_DRAFT) {
            return back()->with('error', 'Hanya event berstatus draft yang bisa dipublish.');
        }

        if (! $event->canPublish()) {
            return back()->with('error', 'Event dengan coach wajib mengisi kapasitas peserta (lebih dari 0) sebelum dipublish.');
        }

        $event->update(['status' => Event::STATUS_PUBLISHED]);

        return back()->with('success', 'Event berhasil dipublish dan siap menerima pendaftaran.');
    }

    public function cancel(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeCoordinator($request, $event);

        $data = $request->validate(['cancel_reason' => ['nullable', 'string', 'max:255']]);

        if ($event->status === Event::STATUS_CANCELLED) {
            return back()->with('error', 'Event ini sudah dibatalkan.');
        }

        $event->update([
            'status' => Event::STATUS_CANCELLED,
            'cancelled_by' => $request->user()->id,
            'cancelled_at' => now(),
            'cancel_reason' => $data['cancel_reason'] ?? null,
        ]);

        return back()->with('success', 'Event dibatalkan. Pendaftaran & riwayat peserta tetap tersimpan (tidak dihapus).');
    }

    /**
     * Roster: daftar peserta + status kehadiran + permintaan pembatalan
     * yang menunggu keputusan.
     */
    public function show(Request $request, Event $event): View
    {
        $this->authorizeCoordinator($request, $event);

        $event->load([
            'registrations.employee:id,full_name',
            'registrations.attendance',
            'registrations.cancellationRequests' => fn ($q) => $q->latest(),
        ]);

        $pendingCancellations = CancellationRequest::query()
            ->whereHas('registration', fn ($q) => $q->where('sport_event_id', $event->id))
            ->where('status', CancellationRequest::STATUS_PENDING)
            ->with(['registration.employee:id,full_name'])
            ->get();

        return view('sport.coordinator.show', [
            'event' => $event,
            'pendingCancellations' => $pendingCancellations,
        ]);
    }

    public function approveCancellation(Request $request, CancellationRequest $cancellationRequest): RedirectResponse
    {
        $registration = $cancellationRequest->registration()->with('event')->first();
        $this->authorizeCoordinator($request, $registration->event);

        if ($cancellationRequest->status !== CancellationRequest::STATUS_PENDING) {
            return back()->with('error', 'Permintaan ini sudah diputuskan sebelumnya.');
        }

        $cancellationRequest->update([
            'status' => CancellationRequest::STATUS_APPROVED,
            'decided_by_user_id' => $request->user()->id,
            'decided_at' => now(),
        ]);
        $registration->update(['status' => Registration::STATUS_CANCELLED]);

        return back()->with('success', 'Pembatalan disetujui. Slot peserta ini otomatis lepas.');
    }

    public function rejectCancellation(Request $request, CancellationRequest $cancellationRequest): RedirectResponse
    {
        $registration = $cancellationRequest->registration()->with('event')->first();
        $this->authorizeCoordinator($request, $registration->event);

        if ($cancellationRequest->status !== CancellationRequest::STATUS_PENDING) {
            return back()->with('error', 'Permintaan ini sudah diputuskan sebelumnya.');
        }

        $cancellationRequest->update([
            'status' => CancellationRequest::STATUS_REJECTED,
            'decided_by_user_id' => $request->user()->id,
            'decided_at' => now(),
        ]);
        // Registrasi dikembalikan ke status "registered" — pembatalan ditolak berarti peserta tetap ikut.
        $registration->update(['status' => Registration::STATUS_REGISTERED]);

        return back()->with('success', 'Pembatalan ditolak. Peserta tetap tercatat mengikuti event ini.');
    }

    private function authorizeCoordinator(Request $request, Event $event): void
    {
        $user = $request->user();
        abort_unless($this->isSportAdmin($user) || (int) $event->coordinator_user_id === (int) $user->id, 403);
    }

    private function isSportAdmin($user): bool
    {
        return in_array((string) $user->role, ['admin', 'finance'], true);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'sport' => ['required', 'string', 'max:50'],
            'title' => ['nullable', 'string', 'max:150'],
            'location' => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date'],
            // Dibandingkan sebagai string datetime-local mentah (sama-sama
            // belum dikonversi di titik ini) — urutan start < end tetap sah
            // diperiksa di sini sebelum konversi timezone di bawah.
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'has_coach' => ['nullable', 'boolean'],
            'coach_name' => ['nullable', 'string', 'max:150'],
            'capacity' => ['nullable', 'integer', 'min:1'],
        ]);

        $data['has_coach'] = $request->boolean('has_coach');
        if (! $data['has_coach']) {
            $data['capacity'] = null;
            $data['coach_name'] = null;
        }

        // Input datetime-local dari form Koordinator TIDAK membawa info
        // timezone — nilainya murni "jam dinding" yang diketik Koordinator,
        // yang secara bisnis dimaksudkan sebagai waktu Asia/Jakarta (WIB).
        // Dikonversi eksplisit ke timezone app (config('app.timezone') = UTC)
        // SEBELUM disimpan, supaya nanti dibandingkan apples-to-apples
        // dengan Carbon::now()/Event::isRegistrationOpen()/
        // isAttendanceWindowOpen() yang semuanya beroperasi di timezone app.
        // Lihat README_SPORT_MODULE.md bagian "Timezone" untuk audit lengkap
        // kenapa ini perlu dan kenapa scope-nya dibatasi ke Sport saja.
        $data['start_at'] = $this->jakartaInputToAppTimezone($data['start_at']);
        $data['end_at'] = $this->jakartaInputToAppTimezone($data['end_at']);

        return $data;
    }

    private function jakartaInputToAppTimezone(string $value): Carbon
    {
        return Carbon::parse($value, Event::BUSINESS_TIMEZONE)->setTimezone(config('app.timezone'));
    }
}
