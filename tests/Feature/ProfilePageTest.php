<?php

namespace Tests\Feature;

use App\Models\ApplicantProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfilePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_profile_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Profil Akun');
    }

    public function test_profile_page_prompts_user_to_complete_application_form_when_data_is_incomplete(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_PROBATION]);

        ApplicantProfile::query()->create([
            'user_id' => $user->id,
            'personal_json' => [
                'full_name' => 'Legacy Employee',
                'email' => $user->email,
            ],
        ]);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSeeText('Masih ada data yang perlu dilengkapi')
            ->assertSee(route('application-form.edit'), false)
            ->assertSeeText('Lengkapi Application Form');
    }
}
