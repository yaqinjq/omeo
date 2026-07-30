<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OutletPermitManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureSupportTables();
    }

    public function test_hrd_can_create_update_and_view_outlet_permit_documents(): void
    {
        Storage::fake('public');
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        $outlet = Outlet::query()->create([
            'name' => 'Outlet Tunjungan',
            'brand_name' => 'OMEO',
            'timezone' => 'Asia/Jakarta',
        ]);

        $pdf = UploadedFile::fake()->create('nib.pdf', 120, 'application/pdf');
        $image = UploadedFile::fake()->image('foto-izin.png');

        $this->actingAs($hrd)
            ->post(route('outlets.permits.store', $outlet), [
                'permit_type' => 'NIB',
                'document_number' => 'NIB-001',
                'issuer_name' => 'OSS',
                'issued_at' => '2026-01-01',
                'expires_at' => '2027-01-01',
                'status' => 'active',
                'notes' => 'Dokumen awal outlet.',
                'attachments' => [$pdf, $image],
            ])
            ->assertRedirect(route('outlets.edit', $outlet))
            ->assertSessionHas('success');

        $permit = $outlet->permits()->with('attachments')->firstOrFail();
        $this->assertSame('NIB', $permit->permit_type);
        $this->assertCount(2, $permit->attachments);
        foreach ($permit->attachments as $attachment) {
            Storage::disk('public')->assertExists($attachment->file_path);
        }

        $newAttachment = UploadedFile::fake()->create('surat-perpanjangan.pdf', 100, 'application/pdf');

        $this->actingAs($hrd)
            ->put(route('outlets.permits.update', [$outlet, $permit]), [
                'permit_type' => 'NIB Revisi',
                'document_number' => 'NIB-001-REV',
                'issuer_name' => 'OSS Nasional',
                'issued_at' => '2026-02-01',
                'expires_at' => '2027-02-01',
                'status' => 'active',
                'notes' => 'Sudah diperbarui.',
                'attachments' => [$newAttachment],
            ])
            ->assertRedirect(route('outlets.edit', $outlet))
            ->assertSessionHas('success');

        $permit->refresh();
        $permit->load('attachments');
        $this->assertSame('NIB Revisi', $permit->permit_type);
        $this->assertSame('NIB-001-REV', $permit->document_number);
        $this->assertCount(3, $permit->attachments);

        $response = $this->actingAs($hrd)->get(route('outlets.edit', $outlet));
        $response->assertOk();
        $response->assertSeeText('Dokumen Perizinan Outlet');

        $response->assertSeeText('surat-perpanjangan.pdf');
    }

    public function test_hrd_can_delete_outlet_permit_attachment_and_permit(): void
    {
        Storage::fake('public');
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        $outlet = Outlet::query()->create([
            'name' => 'Outlet Darmo',
            'brand_name' => 'OMEO',
            'timezone' => 'Asia/Jakarta',
        ]);

        $attachment = UploadedFile::fake()->create('izin.pdf', 80, 'application/pdf');

        $this->actingAs($hrd)->post(route('outlets.permits.store', $outlet), [
            'permit_type' => 'Sertifikat Laik Higiene',
            'document_number' => 'SLH-001',
            'issuer_name' => 'Dinkes',
            'issued_at' => '2026-03-01',
            'expires_at' => '2027-03-01',
            'status' => 'active',
            'notes' => 'Awal simpan.',
            'attachments' => [$attachment],
        ])->assertRedirect(route('outlets.edit', $outlet));

        $permit = $outlet->permits()->with('attachments')->firstOrFail();
        $storedAttachment = $permit->attachments->firstOrFail();
        Storage::disk('public')->assertExists($storedAttachment->file_path);

        $this->actingAs($hrd)
            ->delete(route('outlets.permits.attachments.destroy', [$outlet, $permit, $storedAttachment]))
            ->assertRedirect(route('outlets.edit', $outlet))
            ->assertSessionHas('success');

        Storage::disk('public')->assertMissing($storedAttachment->file_path);
        $this->assertDatabaseMissing('outlet_permit_attachments', ['id' => $storedAttachment->id]);

        $this->actingAs($hrd)
            ->delete(route('outlets.permits.destroy', [$outlet, $permit]))
            ->assertRedirect(route('outlets.edit', $outlet))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('outlet_permits', ['id' => $permit->id]);
    }

    private function ensureSupportTables(): void
    {
        if (! Schema::hasTable('outlets')) {
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

        if (! Schema::hasTable('outlet_permits')) {
            Schema::create('outlet_permits', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('outlet_id');
                $table->string('permit_type');
                $table->string('document_number')->nullable();
                $table->string('issuer_name')->nullable();
                $table->date('issued_at')->nullable();
                $table->date('expires_at')->nullable();
                $table->string('status', 20)->default('active');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('outlet_permit_attachments')) {
            Schema::create('outlet_permit_attachments', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('outlet_permit_id');
                $table->string('file_path');
                $table->string('original_name')->nullable();
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->timestamps();
            });
        }
    }
}


