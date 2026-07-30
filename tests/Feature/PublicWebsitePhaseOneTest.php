<?php

namespace Tests\Feature;

use App\Models\CareerDepartment;
use App\Models\CareerPost;
use App\Models\HrTeamMember;
use App\Models\LandingPageSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicWebsitePhaseOneTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_landing_page_with_fallback_content(): void
    {
        $this->get(route('landing'))
            ->assertOk()
            ->assertSee('OMEO HR Suite')
            ->assertSee('Peluang Karir')
            ->assertSee('Tim Human Resource');
    }

    public function test_superadmin_can_update_landing_settings_and_upload_images(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_super_admin' => true]);

        $this->actingAs($user)
            ->get(route('dashboard.landing-page.edit'))
            ->assertOk()
            ->assertSee('Pengaturan Landing Page');

        $this->put(route('dashboard.landing-page.update'), [
            'website_name' => 'OMEO Careers',
            'hero_headline' => 'Karir OMEO',
            'hero_highlight' => 'Premium HR.',
            'hero_badge' => 'Hiring Now',
            'hero_subheadline' => 'Headline dinamis untuk landing page.',
            'primary_button_label' => 'Dashboard',
            'primary_button_url' => '/dashboard',
            'secondary_button_label' => 'Karir',
            'secondary_button_url' => '/karir',
            'cta_title' => 'Bergabung Sekarang',
            'cta_description' => 'CTA dinamis.',
            'cta_button_label' => 'Daftar',
            'cta_button_url' => '/register',
            'logo' => UploadedFile::fake()->image('logo.png'),
            'hero_image' => UploadedFile::fake()->image('hero.jpg'),
        ])->assertRedirect()->assertSessionHas('success');

        $setting = LandingPageSetting::query()->firstOrFail();
        $this->assertSame('Karir OMEO', $setting->hero_headline);
        Storage::disk('public')->assertExists($setting->logo_path);
        Storage::disk('public')->assertExists($setting->hero_image_path);

        Auth::logout();

        $this->get(route('landing'))
            ->assertOk()
            ->assertSee('Karir OMEO')
            ->assertSee('Headline dinamis untuk landing page.');
    }

    public function test_guest_and_non_superadmin_cannot_open_landing_settings(): void
    {
        $this->get(route('dashboard.landing-page.edit'))->assertRedirect(route('login'));

        $user = User::factory()->create(['role' => User::ROLE_HRD, 'is_super_admin' => false]);

        $this->actingAs($user)
            ->get(route('dashboard.landing-page.edit'))
            ->assertForbidden();
    }

    public function test_dynamic_superadmin_role_can_open_landing_settings(): void
    {
        Role::query()->create([
            'slug' => 'owner',
            'name' => 'Owner',
            'description' => 'Dynamic top authority role',
            'is_system' => false,
            'is_super_admin' => true,
        ]);

        $user = User::factory()->create(['role' => 'owner', 'is_super_admin' => false]);

        $this->actingAs($user)
            ->get(route('dashboard.landing-page.edit'))
            ->assertOk()
            ->assertSee('Pengaturan Landing Page');
    }

    public function test_hr_team_active_members_show_on_landing_only(): void
    {
        HrTeamMember::query()->create([
            'name' => 'Ayu HR',
            'position' => 'Recruiter',
            'company_email' => 'ayu@example.test',
            'is_active' => true,
        ]);
        HrTeamMember::query()->create([
            'name' => 'Nonaktif HR',
            'position' => 'Recruiter',
            'company_email' => 'off@example.test',
            'is_active' => false,
        ]);

        $this->get(route('landing'))
            ->assertOk()
            ->assertSee('Ayu HR')
            ->assertDontSee('Nonaktif HR');
    }

    public function test_superadmin_can_create_hr_team_member_with_validation(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_super_admin' => true]);

        $this->actingAs($user)
            ->post(route('dashboard.hr-team.store'), [
                'name' => 'Rina HR',
                'position' => 'Talent Acquisition',
                'company_email' => 'not-an-email',
            ])
            ->assertSessionHasErrors('company_email');

        $this->actingAs($user)
            ->post(route('dashboard.hr-team.store'), [
                'name' => 'Rina HR',
                'position' => 'Talent Acquisition',
                'company_email' => 'rina@example.test',
                'is_active' => '1',
                'photo' => UploadedFile::fake()->image('rina.jpg'),
            ])
            ->assertRedirect(route('dashboard.hr-team.index'));

        $member = HrTeamMember::query()->firstOrFail();
        $this->assertTrue($member->is_active);
        Storage::disk('public')->assertExists($member->photo_path);
    }

    public function test_superadmin_can_edit_and_delete_existing_hr_team_member(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_super_admin' => true]);
        $member = HrTeamMember::query()->create([
            'name' => 'Test HR',
            'position' => 'Recruiter',
            'company_email' => 'test.hr@example.test',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard.hr-team.edit', $member))
            ->assertOk()
            ->assertSee('Edit Anggota Tim HR')
            ->assertSee('Test HR');

        $this->actingAs($user)
            ->put(route('dashboard.hr-team.update', $member), [
                'name' => 'Updated HR',
                'position' => 'HR Business Partner',
                'company_email' => 'updated.hr@example.test',
                'sort_order' => 7,
                'is_active' => '1',
            ])
            ->assertRedirect(route('dashboard.hr-team.index'));

        $this->assertSame(1, HrTeamMember::query()->count());
        $this->assertDatabaseHas('hr_team_members', [
            'id' => $member->id,
            'name' => 'Updated HR',
            'position' => 'HR Business Partner',
            'company_email' => 'updated.hr@example.test',
            'sort_order' => 7,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->delete(route('dashboard.hr-team.destroy', $member))
            ->assertRedirect();

        $this->assertDatabaseMissing('hr_team_members', ['id' => $member->id]);
    }

    public function test_public_career_pages_only_show_published_posts(): void
    {
        $department = CareerDepartment::query()->create(['name' => 'Service', 'slug' => 'service']);
        $published = CareerPost::query()->create([
            'career_department_id' => $department->id,
            'title' => 'Barista',
            'slug' => 'barista',
            'location' => 'Surabaya',
            'employment_type' => 'full-time',
            'description' => 'Melayani customer.',
            'status' => CareerPost::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'seo_description' => 'Lowongan Barista Surabaya',
        ]);
        CareerPost::query()->create([
            'career_department_id' => $department->id,
            'title' => 'Draft Role',
            'slug' => 'draft-role',
            'employment_type' => 'full-time',
            'status' => CareerPost::STATUS_DRAFT,
        ]);

        $this->get(route('careers.index'))
            ->assertOk()
            ->assertSee('Barista')
            ->assertDontSee('Draft Role');

        $this->get(route('careers.show', $published))
            ->assertOk()
            ->assertSee('Lowongan Barista Surabaya')
            ->assertSee('JobPosting');

        $this->get(route('careers.show', ['career' => 'draft-role']))->assertNotFound();
    }

    public function test_hrd_can_create_career_post_and_slug_is_unique(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_HRD]);

        $payload = [
            'department_name' => 'Human Resource',
            'title' => 'Recruitment Specialist',
            'location' => 'Surabaya',
            'employment_type' => 'full-time',
            'description' => 'Mengelola recruitment.',
            'qualifications' => 'Komunikatif.',
            'status' => CareerPost::STATUS_PUBLISHED,
            'published_at' => now()->format('Y-m-d H:i:s'),
            'seo_title' => 'Recruitment Specialist Surabaya',
            'seo_description' => 'Lowongan Recruitment Specialist.',
        ];

        $this->actingAs($user)
            ->post(route('dashboard.careers.store'), $payload)
            ->assertRedirect(route('dashboard.careers.index'));

        $this->actingAs($user)
            ->post(route('dashboard.careers.store'), $payload)
            ->assertRedirect(route('dashboard.careers.index'));

        $this->assertDatabaseHas('career_posts', ['slug' => 'recruitment-specialist']);
        $this->assertDatabaseHas('career_posts', ['slug' => 'recruitment-specialist-2']);
    }
}
