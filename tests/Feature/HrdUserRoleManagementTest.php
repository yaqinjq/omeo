<?php

namespace Tests\Feature;

use App\Models\ApplicantProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrdUserRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_applicant_cannot_access_hrd_dashboard_and_users_page(): void
    {
        $applicant = User::factory()->create(['role' => User::ROLE_APPLICANT]);

        $this->actingAs($applicant)
            ->get('/hrd/dashboard')
            ->assertForbidden();

        $this->actingAs($applicant)
            ->get(route('hrd.users.index'))
            ->assertForbidden();
    }

    public function test_hrd_can_access_users_and_roles_page(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);

        $this->actingAs($hrd)
            ->get(route('hrd.users.index'))
            ->assertOk()
            ->assertSeeText('Users & Roles');
    }

    public function test_search_supports_name_email_and_nik(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $target = User::factory()->create([
            'role' => User::ROLE_APPLICANT,
            'name' => 'Budi NIK',
            'email' => 'budi@example.com',
        ]);

        ApplicantProfile::query()->create([
            'user_id' => $target->id,
            'personal_json' => ['ktp_number' => '1234567890'],
        ]);

        $this->actingAs($admin)
            ->get(route('hrd.users.index', ['q' => '1234567890']))
            ->assertOk()
            ->assertSee('Budi NIK');
    }

    public function test_admin_cannot_demote_self(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->from(route('hrd.users.index'))
            ->patch(route('hrd.users.role.update', $admin), [
                'role' => User::ROLE_APPLICANT,
            ])
            ->assertRedirect(route('hrd.users.index'))
            ->assertSessionHasErrors('role');

        $this->assertSame(User::ROLE_ADMIN, $admin->fresh()->role);
    }

    public function test_hrd_cannot_set_admin_role(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        $target = User::factory()->create(['role' => User::ROLE_EMPLOYEE]);

        $this->actingAs($hrd)
            ->from(route('hrd.users.index'))
            ->patch(route('hrd.users.role.update', $target), [
                'role' => User::ROLE_ADMIN,
            ])
            ->assertRedirect(route('hrd.users.index'))
            ->assertSessionHasErrors('role');

        $this->assertSame(User::ROLE_EMPLOYEE, $target->fresh()->role);
    }

    public function test_admin_can_update_user_role_to_hrd(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $target = User::factory()->create(['role' => User::ROLE_APPLICANT]);

        $this->actingAs($admin)
            ->from(route('hrd.users.index'))
            ->patch(route('hrd.users.role.update', $target), [
                'role' => User::ROLE_HRD,
            ])
            ->assertRedirect(route('hrd.users.index'))
            ->assertSessionHas('success');

        $this->assertSame(User::ROLE_HRD, $target->fresh()->role);
    }
}


