<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OutletApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('outlet_api.direct_token', 'test-direct-outlet-token');
        Config::set('outlet_api.login.allowed_emails', ['cia_usr@mko.com']);
        Config::set('outlet_api.login.allowed_roles', ['admin', 'hrd']);
        Config::set('outlet_api.login.token_name', 'ocia-compat-test');
        Config::set('outlet_api.login.token_expires_in_minutes', 10080);

        $this->ensureOutletTable();
    }

    public function test_outlet_api_requires_valid_bearer_token(): void
    {
        $this->getJson('/api/outlets')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthorized.')
            ->assertJsonPath('error', 'missing_bearer_token');
    }

    public function test_outlet_api_reports_when_direct_token_is_not_configured(): void
    {
        Config::set('outlet_api.direct_token', '');

        $this->withToken('anything')
            ->getJson('/api/outlets')
            ->assertStatus(503)
            ->assertJsonPath('error', 'direct_token_not_configured');
    }

    public function test_compatibility_login_returns_bearer_token(): void
    {
        User::factory()->create([
            'email' => 'cia_usr@mko.com',
            'password' => 'cia_usrMKO2026',
            'role' => 'admin',
        ]);

        $this->postJson('/api/login', [
            'email' => 'cia_usr@mko.com',
            'password' => 'cia_usrMKO2026',
        ])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('endpoints.branches', url('/api/branches'))
            ->assertJsonStructure([
                'message',
                'access_token',
                'token',
                'token_type',
                'endpoints' => ['branches', 'outlets'],
            ]);
    }

    public function test_compatibility_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'cia_usr@mko.com',
            'password' => 'cia_usrMKO2026',
            'role' => 'admin',
        ]);

        $this->postJson('/api/login', [
            'email' => 'cia_usr@mko.com',
            'password' => 'wrong-password',
        ])
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Invalid API credentials.']);
    }

    public function test_compatibility_login_requires_email_and_password(): void
    {
        $this->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_compatibility_login_rejects_user_outside_allowlist(): void
    {
        User::factory()->create([
            'email' => 'other@example.com',
            'password' => 'secret-password',
            'role' => 'employee',
        ]);

        $this->postJson('/api/login', [
            'email' => 'other@example.com',
            'password' => 'secret-password',
        ])
            ->assertForbidden()
            ->assertExactJson(['message' => 'This user is not allowed to access the Outlet API.']);
    }

    public function test_outlet_api_returns_paginated_outlet_list_with_filters(): void
    {
        Outlet::query()->create([
            'name' => 'Outlet A',
            'brand_name' => 'MEO',
            'external_id' => 'OUT-001',
            'location' => 'Surabaya',
            'latitude' => -7.286971,
            'longitude' => 112.739687,
            'radius_meters' => 60,
            'geofence_radius_m' => 60,
            'timezone' => 'Asia/Jakarta',
            'work_start_time' => '08:00:00',
            'work_end_time' => '17:00:00',
            'updated_at' => Carbon::parse('2026-04-10 10:00:00'),
        ]);

        Outlet::query()->create([
            'name' => 'Outlet B',
            'brand_name' => 'OMEO',
            'external_id' => 'OUT-002',
            'timezone' => 'Asia/Jakarta',
            'updated_at' => Carbon::parse('2026-04-01 10:00:00'),
        ]);

        $response = $this->withToken('test-direct-outlet-token')
            ->getJson('/api/outlets?brand_name=MEO&updated_since=2026-04-05&per_page=10');

        $response->assertOk()
            ->assertJsonPath('message', 'OK')
            ->assertJsonPath('schema_version', '2026-06-03')
            ->assertJsonPath('sync_key_strategy.preferred', 'external_id')
            ->assertJsonPath('sync_key_strategy.fallback', 'id')
            ->assertJsonPath('filters.brand_name', 'MEO')
            ->assertJsonPath('data.0.name', 'Outlet A')
            ->assertJsonPath('data.0.external_id', 'OUT-001')
            ->assertJsonPath('data.0.radius_meters', 60)
            ->assertJsonPath('data.0.work_start_time', '08:00')
            ->assertJsonPath('data.0.work_end_time', '17:00')
            ->assertJsonCount(1, 'data');
    }

    public function test_branches_api_requires_token(): void
    {
        $this->getJson('/api/branches')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthorized.')
            ->assertJsonPath('error', 'missing_bearer_token');
    }

    public function test_branches_api_returns_compatibility_alias_fields(): void
    {
        Outlet::query()->create([
            'name' => 'OFFICE KK',
            'brand_name' => 'MKO GROUP',
            'external_id' => 'BR-001',
            'location' => 'Jakarta',
            'timezone' => 'Asia/Jakarta',
        ]);

        $user = User::factory()->create([
            'email' => 'cia_usr@mko.com',
            'password' => 'cia_usrMKO2026',
            'role' => 'admin',
        ]);

        $token = $user->createToken('ocia-compat-test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/branches?per_page=10')
            ->assertOk()
            ->assertJsonPath('data.0.branch_name', 'OFFICE KK')
            ->assertJsonPath('data.0.branch_code', 'BR-001')
            ->assertJsonPath('data.0.code', 'BR-001')
            ->assertJsonPath('branches.0.title', 'OFFICE KK');
    }

    public function test_outlet_api_returns_outlet_detail_or_404(): void
    {
        $outlet = Outlet::query()->create([
            'name' => 'Outlet Detail',
            'brand_name' => 'MEO',
            'external_id' => 'OUT-DETAIL',
            'timezone' => 'Asia/Jakarta',
        ]);

        $this->withToken('test-direct-outlet-token')
            ->getJson('/api/outlets/' . $outlet->id)
            ->assertOk()
            ->assertJsonPath('data.id', $outlet->id)
            ->assertJsonPath('data.external_id', 'OUT-DETAIL');

        $this->withToken('test-direct-outlet-token')
            ->getJson('/api/outlets/999999')
            ->assertNotFound();
    }

    public function test_compatibility_login_token_cannot_access_direct_outlets_endpoint(): void
    {
        $user = User::factory()->create([
            'email' => 'cia_usr@mko.com',
            'password' => 'cia_usrMKO2026',
            'role' => 'admin',
        ]);

        $token = $user->createToken('ocia-compat-test', ['outlet-api:branches'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/outlets')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'invalid_outlet_api_token');
    }

    private function ensureOutletTable(): void
    {
        if (Schema::hasTable('outlets')) {
            return;
        }

        Schema::create('outlets', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('location')->nullable();
            $table->string('brand_name')->nullable();
            $table->string('external_id')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->integer('radius_meters')->nullable();
            $table->integer('geofence_radius_m')->nullable();
            $table->string('timezone')->nullable();
            $table->time('work_start_time')->nullable();
            $table->time('work_end_time')->nullable();
            $table->timestamps();
        });
    }
}
