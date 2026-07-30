<?php

namespace App\Http\Controllers;

use App\Models\LandingPageSetting;
use App\Models\User;
use App\Models\WalkInCheckinToken;
use App\Models\WalkInEvent;
use App\Models\WalkInRegistration;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WalkInController extends Controller
{
    public function index(): View
    {
        return view('walk_ins.index', [
            'landingSetting' => LandingPageSetting::current(),
            'events' => WalkInEvent::query()
                ->published()
                ->with('activePositions')
                ->orderBy('event_date')
                ->paginate(12),
        ]);
    }

    public function show(Request $request, WalkInEvent $event): View
    {
        abort_unless($event->status === WalkInEvent::STATUS_PUBLISHED, 404);

        $event->load('activePositions');

        return view('walk_ins.show', [
            'landingSetting' => LandingPageSetting::current(),
            'event' => $event,
            'referralCode' => trim((string) $request->query('ref')),
        ]);
    }

    public function register(Request $request, WalkInEvent $event): RedirectResponse
    {
        abort_unless($event->status === WalkInEvent::STATUS_PUBLISHED, 404);
        abort_unless($event->isRegistrationOpen(), 422, 'Pendaftaran walk-in untuk event ini sudah ditutup.');

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:160'],
            'whatsapp_number' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'walk_in_event_position_id' => ['required', 'exists:walk_in_event_positions,id'],
            'domicile' => ['nullable', 'string', 'max:160'],
            'referral_code' => ['nullable', 'string', 'max:80'],
            'whatsapp_consent' => ['accepted'],
            'attendance_commitment' => ['accepted'],
        ]);

        abort_unless(
            $event->activePositions()->whereKey($data['walk_in_event_position_id'])->exists(),
            422,
            'Posisi tidak tersedia untuk event ini.'
        );

        if ($event->quota && $event->registrations()->count() >= $event->quota) {
            return back()->withInput()->withErrors(['quota' => 'Kuota peserta untuk event ini sudah penuh.']);
        }

        if (! empty($data['email']) && $event->registrations()->whereRaw('LOWER(email) = ?', [mb_strtolower($data['email'])])->exists()) {
            return back()->withInput()->withErrors(['email' => 'Email ini sudah terdaftar untuk event yang sama.']);
        }

        $referralCode = strtoupper(trim((string) ($data['referral_code'] ?? '')));
        $normalizedWhatsapp = $this->normalizeWhatsapp($data['whatsapp_number']);

        try {
            $registration = WalkInRegistration::query()->create([
                'walk_in_event_id' => $event->id,
                'walk_in_event_position_id' => $data['walk_in_event_position_id'],
                'registration_code' => $this->registrationCode(),
                'full_name' => $data['full_name'],
                'whatsapp_number' => $normalizedWhatsapp,
                'email' => $data['email'] ?? null,
                'domicile' => $data['domicile'] ?? null,
                'referral_code' => $referralCode ?: null,
                'referred_user_id' => $this->resolveReferralUserId($referralCode),
                'status' => WalkInRegistration::STATUS_REGISTERED,
                'whatsapp_consent' => true,
                'attendance_commitment' => true,
            ]);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                return back()
                    ->withInput()
                    ->withErrors(['whatsapp_number' => 'Nomor WhatsApp ini sudah terdaftar untuk event yang sama.']);
            }

            throw $exception;
        }

        $registration->load('event', 'position');

        return redirect()
            ->route('walk-ins.success', $registration)
            ->with('success', 'Pendaftaran walk-in berhasil.');
    }

    public function success(WalkInRegistration $registration): View
    {
        $registration->load('event', 'position');

        return view('walk_ins.success', [
            'landingSetting' => LandingPageSetting::current(),
            'registration' => $registration,
        ]);
    }

    public function checkinForm(string $token): View
    {
        $checkinToken = WalkInCheckinToken::findValidPlainToken($token);
        abort_unless($checkinToken && $checkinToken->event?->isCheckinOpen(), 404);

        return view('walk_ins.checkin', [
            'landingSetting' => LandingPageSetting::current(),
            'token' => $token,
            'event' => $checkinToken->event,
        ]);
    }

    public function checkin(Request $request, string $token): RedirectResponse
    {
        $checkinToken = WalkInCheckinToken::findValidPlainToken($token);
        abort_unless($checkinToken && $checkinToken->event?->isCheckinOpen(), 404);

        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:80'],
        ]);

        $identifier = trim((string) $data['identifier']);
        $normalizedWhatsapp = $this->normalizeWhatsapp($identifier);

        $registration = WalkInRegistration::query()
            ->forEvent($checkinToken->event)
            ->where(function ($query) use ($identifier, $normalizedWhatsapp): void {
                $query->where('registration_code', $identifier)
                    ->orWhere('whatsapp_number', $normalizedWhatsapp);
            })
            ->first();

        if (! $registration) {
            return back()->withErrors(['identifier' => 'Data registrasi tidak ditemukan. Pastikan kode registrasi atau WhatsApp benar.']);
        }

        if ($registration->checked_in_at) {
            return back()->with('warning', 'Peserta ini sudah check-in pada ' . $registration->checked_in_at->format('d/m/Y H:i') . '.');
        }

        $registration->update([
            'status' => WalkInRegistration::STATUS_CHECKED_IN,
            'checked_in_at' => now(),
        ]);

        return back()->with('success', 'Check-in berhasil untuk ' . $registration->full_name . '.');
    }

    private function registrationCode(): string
    {
        do {
            $code = 'WI-' . now()->format('ymd') . '-' . strtoupper(Str::random(5));
        } while (WalkInRegistration::query()->where('registration_code', $code)->exists());

        return $code;
    }

    private function normalizeWhatsapp(string $number): string
    {
        return preg_replace('/[^0-9+]/', '', trim($number)) ?: trim($number);
    }

    private function resolveReferralUserId(string $code): ?int
    {
        if (! preg_match('/^HRD(\d+)$/i', $code, $matches)) {
            return null;
        }

        return User::query()
            ->whereKey((int) $matches[1])
            ->whereIn('role', [User::ROLE_HRD, User::ROLE_ADMIN, User::ROLE_MANAGER])
            ->value('id');
    }
}
