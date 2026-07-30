<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiDocumentationAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_access_api_documentation_page(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'is_super_admin' => true,
        ]);

        $this->actingAs($user)
            ->get(route('settings.api-docs'))
            ->assertOk()
            ->assertSee('Dokumentasi API OMEO')
            ->assertSee('Superadmin Only');
    }

    public function test_admin_without_superadmin_flag_cannot_access_api_documentation_page(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'is_super_admin' => false,
        ]);

        $this->actingAs($user)
            ->get(route('settings.api-docs'))
            ->assertForbidden();
    }
}