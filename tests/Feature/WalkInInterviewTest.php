<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WalkInCheckinToken;
use App\Models\WalkInEvent;
use App\Models\WalkInEventPosition;
use App\Models\WalkInRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WalkInInterviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_only_sees_published_walk_in_events(): void
    {
        $published = $this->event(['title' => 'Walk In Surabaya', 'slug' => 'walk-in-surabaya']);
        $draft = $this->event(['title' => 'Draft Walk In', 'slug' => 'draft-walk-in', 'status' => WalkInEvent::STATUS_DRAFT]);

        $this->get(route('walk-ins.index'))
            ->assertOk()
            ->assertSee('Walk In Surabaya')
            ->assertDontSee('Draft Walk In');

        $this->get(route('walk-ins.show', $published))->assertOk()->assertSee('Daftar Antrian');
        $this->get(route('walk-ins.show', $draft))->assertNotFound();
    }

    public function test_hrd_can_create_walk_in_event_with_positions(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_HRD]);

        $this->actingAs($user)
            ->post(route('dashboard.walk-ins.store'), [
                'title' => 'Walk In Crew Store',
                'status' => WalkInEvent::STATUS_PUBLISHED,
                'event_date' => now()->addDay()->format('Y-m-d'),
                'start_time' => '09:00',
                'end_time' => '15:00',
                'location' => 'Surabaya',
                'positions_text' => "Barista\nKasir",
            ])
            ->assertRedirect(route('dashboard.walk-ins.index'));

        $this->assertDatabaseHas('walk_in_events', ['slug' => 'walk-in-crew-store']);
        $this->assertDatabaseHas('walk_in_event_positions', ['name' => 'Barista']);
        $this->assertDatabaseHas('walk_in_event_positions', ['name' => 'Kasir']);
    }

    public function test_public_can_register_with_required_consents_and_referral(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        $event = $this->event();
        $position = $this->position($event, 'Barista');

        $this->post(route('walk-ins.register', $event), [
            'full_name' => 'Peserta Test',
            'whatsapp_number' => '0812 3456 7890',
            'walk_in_event_position_id' => $position->id,
        ])->assertSessionHasErrors(['whatsapp_consent', 'attendance_commitment']);

        $this->post(route('walk-ins.register', $event), [
            'full_name' => 'Peserta Test',
            'whatsapp_number' => '0812 3456 7890',
            'email' => 'peserta@example.test',
            'walk_in_event_position_id' => $position->id,
            'domicile' => 'Surabaya',
            'referral_code' => 'HRD' . $hrd->id,
            'whatsapp_consent' => '1',
            'attendance_commitment' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('walk_in_registrations', [
            'full_name' => 'Peserta Test',
            'whatsapp_number' => '081234567890',
            'email' => 'peserta@example.test',
            'referral_code' => 'HRD' . $hrd->id,
            'referred_user_id' => $hrd->id,
            'status' => WalkInRegistration::STATUS_REGISTERED,
        ]);
    }

    public function test_duplicate_whatsapp_for_same_event_is_rejected(): void
    {
        $event = $this->event();
        $position = $this->position($event);

        WalkInRegistration::query()->create([
            'walk_in_event_id' => $event->id,
            'walk_in_event_position_id' => $position->id,
            'registration_code' => 'WI-TEST1',
            'full_name' => 'Existing',
            'whatsapp_number' => '081234567890',
            'status' => WalkInRegistration::STATUS_REGISTERED,
            'whatsapp_consent' => true,
            'attendance_commitment' => true,
        ]);

        $this->post(route('walk-ins.register', $event), [
            'full_name' => 'Duplicate',
            'whatsapp_number' => '0812-3456-7890',
            'walk_in_event_position_id' => $position->id,
            'whatsapp_consent' => '1',
            'attendance_commitment' => '1',
        ])->assertSessionHasErrors('whatsapp_number');
    }

    public function test_valid_checkin_token_can_check_in_and_double_checkin_is_clear(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-08 10:00:00'));
        $event = $this->event([
            'event_date' => '2026-05-08',
            'start_time' => '09:00',
            'end_time' => '15:00',
        ]);
        $position = $this->position($event);
        $registration = WalkInRegistration::query()->create([
            'walk_in_event_id' => $event->id,
            'walk_in_event_position_id' => $position->id,
            'registration_code' => 'WI-CHECK',
            'full_name' => 'Check In User',
            'whatsapp_number' => '081234567890',
            'status' => WalkInRegistration::STATUS_REGISTERED,
            'whatsapp_consent' => true,
            'attendance_commitment' => true,
        ]);
        [$plain] = WalkInCheckinToken::issueFor($event);

        $this->get(route('walk-ins.checkin.form', $plain))->assertOk();

        $this->post(route('walk-ins.checkin.submit', $plain), [
            'identifier' => $registration->registration_code,
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('walk_in_registrations', [
            'id' => $registration->id,
            'status' => WalkInRegistration::STATUS_CHECKED_IN,
        ]);

        $this->post(route('walk-ins.checkin.submit', $plain), [
            'identifier' => $registration->registration_code,
        ])->assertSessionHas('warning');

        Carbon::setTestNow();
    }

    public function test_expired_checkin_token_is_rejected(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-08 10:00:00'));
        $event = $this->event(['event_date' => '2026-05-08']);
        $plain = 'expired-token';
        WalkInCheckinToken::query()->create([
            'walk_in_event_id' => $event->id,
            'token_hash' => hash('sha256', $plain),
            'valid_from' => now()->subMinutes(5),
            'expires_at' => now()->subMinute(),
        ]);

        $this->get(route('walk-ins.checkin.form', $plain))->assertNotFound();

        Carbon::setTestNow();
    }

    public function test_hrd_can_mark_participant_passed_and_records_screener(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        $event = $this->event();
        $position = $this->position($event);
        $registration = WalkInRegistration::query()->create([
            'walk_in_event_id' => $event->id,
            'walk_in_event_position_id' => $position->id,
            'registration_code' => 'WI-PASS',
            'full_name' => 'Passed User',
            'whatsapp_number' => '081234500000',
            'status' => WalkInRegistration::STATUS_CHECKED_IN,
            'checked_in_at' => now(),
            'whatsapp_consent' => true,
            'attendance_commitment' => true,
        ]);

        $this->actingAs($hrd)
            ->put(route('dashboard.walk-ins.participants.update', [$event, $registration]), [
                'status' => WalkInRegistration::STATUS_PASSED,
                'screening_note' => 'Komunikasi baik.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('walk_in_registrations', [
            'id' => $registration->id,
            'status' => WalkInRegistration::STATUS_PASSED,
            'screened_by' => $hrd->id,
            'screening_note' => 'Komunikasi baik.',
        ]);
        $this->assertNotNull($registration->fresh()->screened_at);
    }

    private function event(array $overrides = []): WalkInEvent
    {
        return WalkInEvent::query()->create(array_merge([
            'title' => 'Walk In Test',
            'slug' => 'walk-in-test-' . str()->random(6),
            'status' => WalkInEvent::STATUS_PUBLISHED,
            'event_date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '15:00',
            'location' => 'Surabaya',
        ], $overrides));
    }

    private function position(WalkInEvent $event, string $name = 'Crew Store'): WalkInEventPosition
    {
        return WalkInEventPosition::query()->create([
            'walk_in_event_id' => $event->id,
            'name' => $name,
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }
}
