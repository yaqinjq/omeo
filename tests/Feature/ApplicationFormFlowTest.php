<?php

namespace Tests\Feature;

use App\Models\ApplicantProfile;
use App\Models\Candidate;
use App\Models\Department;
use App\Models\Outlet;
use App\Models\Position;
use App\Models\User;
use App\Services\ApplicationFormTemporaryUploadService;
use App\Support\ApplicationFormUploadLimit;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ApplicationFormFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_submit_allows_partial_personal_data_without_final_requirements(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_APPLICANT, 'employee_id' => null]);

        $response = $this->actingAs($user)
            ->from(route('application-form.edit'))
            ->post(route('application-form.update'), [
                'full_name' => 'Draft Parsial',
                'ktp_number' => '3175000000001212',
                'phone_number' => '081212121212',
                'final_submit' => '0',
            ]);

        $response->assertRedirect(route('application-form.edit'))
            ->assertSessionHas('success', 'Draft berhasil disimpan. Anda bisa melanjutkan nanti.')
            ->assertSessionDoesntHaveErrors([
                'educations',
                'medical_histories',
                'signature_data',
                'photo_ktp_file',
                'scan_ktp_file',
                'cv_file',
            ]);

        $profile = ApplicantProfile::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertNull($profile->completed_at);
        $this->assertSame('Draft Parsial', data_get($profile->personal_json, 'full_name'));
        $this->assertSame('3175000000001212', data_get($profile->personal_json, 'ktp_number'));
        $this->assertSame('081212121212', data_get($profile->personal_json, 'phone_number'));

        $reload = $this->actingAs($user)->get(route('application-form.edit'));
        $reload->assertOk()->assertSee('Draft Parsial', false);
    }

    public function test_draft_submit_stores_multipart_file_fallback_uploads(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => User::ROLE_APPLICANT, 'employee_id' => null]);

        $response = $this->actingAs($user)
            ->from(route('application-form.edit'))
            ->post(route('application-form.update'), [
                'full_name' => 'Draft Upload',
                'final_submit' => '0',
                'photo_ktp_file' => UploadedFile::fake()->create('pas-foto.webp', 180, 'image/webp'),
                'scan_ktp_file' => UploadedFile::fake()->create('scan-ktp.webp', 220, 'image/webp'),
                'cv_file' => UploadedFile::fake()->create('cv-draft.pdf', 320, 'application/pdf'),
            ]);

        $response->assertRedirect(route('application-form.edit'))
            ->assertSessionHas('success', 'Draft berhasil disimpan. Anda bisa melanjutkan nanti.');

        $profile = ApplicantProfile::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertStringStartsWith('applicants/photos/', (string) $profile->photo_path);
        $this->assertStringStartsWith('applicants/ktp/', (string) $profile->ktp_path);
        $this->assertStringStartsWith('applicants/cv/', (string) $profile->cv_path);
        Storage::disk('public')->assertExists((string) $profile->photo_path);
        Storage::disk('public')->assertExists((string) $profile->ktp_path);
        Storage::disk('public')->assertExists((string) $profile->cv_path);
    }

    public function test_draft_invalid_file_returns_validation_error_and_keeps_existing_document(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => User::ROLE_APPLICANT, 'employee_id' => null]);

        ApplicantProfile::query()->create([
            'user_id' => $user->id,
            'personal_json' => [
                'full_name' => 'Draft Existing File',
                'email' => $user->email,
                'cv_path' => 'existing/cv.pdf',
            ],
        ]);

        $response = $this->actingAs($user)
            ->from(route('application-form.edit'))
            ->post(route('application-form.update'), [
                'final_submit' => '0',
                'cv_file' => UploadedFile::fake()->create('cv-image.png', 200, 'image/png'),
            ]);

        $response->assertRedirect(route('application-form.edit'))
            ->assertSessionHasErrors(['cv_file'])
            ->assertStatus(302);

        $profile = ApplicantProfile::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('existing/cv.pdf', $profile->cv_path);
    }

    public function test_guest_cannot_submit_application_form(): void
    {
        $response = $this->post(route('application-form.update'), [
            'full_name' => 'Guest Draft',
            'final_submit' => '0',
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_final_submit_accepts_existing_document_paths_without_reupload(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_APPLICANT, 'employee_id' => null]);

        ApplicantProfile::query()->create([
            'user_id' => $user->id,
            'personal_json' => [
                'full_name' => 'Pelamar Lama',
                'email' => $user->email,
                'photo_ktp_path' => 'legacy/photo.jpg',
                'scan_ktp_path' => 'legacy/ktp.pdf',
                'cv_file_path' => 'legacy/cv.pdf',
                'signature_path' => 'legacy/signature.png',
            ],
        ]);

        $response = $this->actingAs($user)->post(route('application-form.update'), $this->validPayload([
            'full_name' => 'Pelamar Lama',
            'ktp_number' => '3175000000000011',
            'signature_data' => '',
            'final_submit' => '1',
        ]));

        $response->assertRedirect()->assertSessionHas('success');
        $profile = ApplicantProfile::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertNotNull($profile->completed_at);
        $this->assertSame('legacy/photo.jpg', $profile->photo_path);
        $this->assertSame('legacy/ktp.pdf', $profile->ktp_path);
        $this->assertSame('legacy/cv.pdf', $profile->cv_path);
        $this->assertSame('legacy/signature.png', data_get($profile->personal_json, 'signature_path'));
    }

    public function test_application_form_saves_applied_snapshots_and_new_personal_fields(): void
    {
        $this->ensureReferenceMasterTables();

        $position = Position::query()->create(['name' => 'Crew Outlet', 'level' => 1]);
        $department = Department::query()->create(['code' => 'OPS', 'name' => 'Operational']);
        $outlet = Outlet::query()->create(['name' => 'Outlet Dago', 'brand_name' => 'Omeo']);
        $user = User::factory()->create(['role' => User::ROLE_APPLICANT, 'employee_id' => null]);

        ApplicantProfile::query()->create([
            'user_id' => $user->id,
            'personal_json' => [
                'full_name' => 'Pelamar Snapshot',
                'email' => $user->email,
                'photo_path' => 'existing/photo.jpg',
                'ktp_path' => 'existing/ktp.pdf',
                'cv_path' => 'existing/cv.pdf',
                'signature_path' => 'existing/signature.png',
            ],
        ]);

        $response = $this->actingAs($user)->post(route('application-form.update'), $this->validPayload([
            'ktp_number' => '3175000000000099',
            'applied_position_id' => $position->id,
            'applied_position_name' => 'Crew Outlet',
            'applied_department_id' => $department->id,
            'applied_outlet_id' => $outlet->id,
            'phone_number' => '081299998888',
            'time_of_birth' => '07:30',
            'signature_data' => '',
            'final_submit' => '1',
        ]));

        $response->assertRedirect()->assertSessionHas('success');
        $profile = ApplicantProfile::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('07:30', data_get($profile->personal_json, 'time_of_birth'));
        $this->assertSame('081299998888', data_get($profile->personal_json, 'phone_number'));
        $this->assertSame($position->id, data_get($profile->personal_json, 'applied_position_id'));
        $this->assertSame('Crew Outlet', data_get($profile->personal_json, 'applied_position_name'));
        $this->assertSame($department->id, data_get($profile->personal_json, 'applied_department_id'));
        $this->assertSame('Operational', data_get($profile->personal_json, 'applied_department_name'));
        $this->assertSame($outlet->id, data_get($profile->personal_json, 'applied_outlet_id'));
        $this->assertSame('Outlet Dago', data_get($profile->personal_json, 'applied_outlet_name'));
    }

    public function test_validation_failure_sets_first_error_step_for_new_required_fields(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_APPLICANT, 'employee_id' => null]);
        ApplicantProfile::query()->create([
            'user_id' => $user->id,
            'personal_json' => [
                'full_name' => 'Pelamar Validasi',
                'email' => $user->email,
                'photo_path' => 'existing/photo.jpg',
                'ktp_path' => 'existing/ktp.pdf',
                'cv_path' => 'existing/cv.pdf',
            ],
        ]);

        $response = $this->actingAs($user)
            ->from(route('application-form.edit'))
            ->post(route('application-form.update'), $this->validPayload([
                'time_of_birth' => '',
                'final_submit' => '1',
            ]));

        $response->assertRedirect()
            ->assertSessionHas('error', 'Periksa kembali field yang ditandai merah sebelum mengirim form.')
            ->assertSessionHasErrors(['time_of_birth'])
            ->assertSessionHas('first_error_step', 1);
    }

    public function test_final_submit_requires_signature_when_no_saved_signature_exists(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_APPLICANT, 'employee_id' => null]);

        ApplicantProfile::query()->create([
            'user_id' => $user->id,
            'personal_json' => [
                'full_name' => 'Pelamar Tanpa TTD',
                'email' => $user->email,
                'photo_path' => 'existing/photo.jpg',
                'ktp_path' => 'existing/ktp.pdf',
                'cv_path' => 'existing/cv.pdf',
            ],
        ]);

        $response = $this->actingAs($user)
            ->from(route('application-form.edit'))
            ->post(route('application-form.update'), $this->validPayload([
                'signature_data' => '',
                'final_submit' => '1',
            ]));

        $response->assertRedirect(route('application-form.edit'))
            ->assertSessionHasErrors(['signature_data'])
            ->assertSessionHas('first_error_step', 6);
    }

    public function test_final_submit_rejects_old_saved_signature_when_signature_was_cleared_in_form(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_APPLICANT, 'employee_id' => null]);

        ApplicantProfile::query()->create([
            'user_id' => $user->id,
            'personal_json' => [
                'full_name' => 'Pelamar Clear Signature',
                'email' => $user->email,
                'photo_path' => 'existing/photo.jpg',
                'ktp_path' => 'existing/ktp.pdf',
                'cv_path' => 'existing/cv.pdf',
                'signature_path' => 'existing/signature.png',
            ],
        ]);

        $response = $this->actingAs($user)
            ->from(route('application-form.edit'))
            ->post(route('application-form.update'), $this->validPayload([
                'signature_data' => '',
                'signature_cleared' => '1',
                'final_submit' => '1',
            ]));

        $response->assertRedirect(route('application-form.edit'))
            ->assertSessionHasErrors(['signature_data'])
            ->assertSessionHas('first_error_step', 6);
    }

    public function test_supporting_documents_become_required_when_candidate_is_shortlisted(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => User::ROLE_APPLICANT, 'employee_id' => null]);

        Candidate::query()->create([
            'user_id' => $user->id,
            'full_name' => 'Pelamar Lolos',
            'email' => $user->email,
            'nik' => '3175000000000022',
            'status' => Candidate::STATUS_SHORTLISTED,
        ]);

        ApplicantProfile::query()->create([
            'user_id' => $user->id,
            'personal_json' => [
                'full_name' => 'Pelamar Lolos',
                'email' => $user->email,
                'photo_path' => 'existing/photo.jpg',
                'ktp_path' => 'existing/ktp.pdf',
                'cv_path' => 'existing/cv.pdf',
                'signature_path' => 'existing/signature.png',
            ],
        ]);

        $response = $this->actingAs($user)
            ->from(route('application-form.edit'))
            ->post(route('application-form.update'), $this->validPayload(['final_submit' => '1']));

        $response->assertRedirect()
            ->assertSessionHasErrors(['graduation_diploma_file', 'graduation_transcript_file', 'graduation_birth_certificate_file', 'skck_file']);
    }

    public function test_application_form_rejects_cv_image_upload_and_keeps_existing_empty_path(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => User::ROLE_APPLICANT, 'employee_id' => null]);

        ApplicantProfile::query()->create([
            'user_id' => $user->id,
            'personal_json' => [
                'full_name' => 'Pelamar CV Gambar',
                'email' => $user->email,
                'photo_path' => 'existing/photo.jpg',
                'ktp_path' => 'existing/ktp.pdf',
                'signature_path' => 'existing/signature.png',
            ],
        ]);

        $response = $this->actingAs($user)->post(route('application-form.update'), $this->validPayload([
            'cv_file' => UploadedFile::fake()->create('cv-print.jpg', 120, 'image/jpeg'),
            'signature_data' => '',
            'final_submit' => '1',
        ]));

        $response->assertRedirect()
            ->assertSessionHasErrors(['cv_file'])
            ->assertSessionHas('first_error_step', 1);

        $profile = ApplicantProfile::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('', (string) $profile->cv_path);
    }

    public function test_application_form_accepts_webp_for_photo_and_ktp_uploads(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => User::ROLE_APPLICANT, 'employee_id' => null]);

        ApplicantProfile::query()->create([
            'user_id' => $user->id,
            'personal_json' => [
                'full_name' => 'Pelamar Mobile',
                'email' => $user->email,
                'signature_path' => 'existing/signature.png',
                'cv_path' => 'existing/cv.pdf',
            ],
        ]);

        $response = $this->actingAs($user)->post(route('application-form.update'), $this->validPayload([
            'photo_ktp_file' => UploadedFile::fake()->create('pas-foto.webp', 180, 'image/webp'),
            'scan_ktp_file' => UploadedFile::fake()->create('scan-ktp.webp', 220, 'image/webp'),
            'signature_data' => '',
            'final_submit' => '1',
        ]));

        $response->assertRedirect()->assertSessionHas('success');

        $profile = ApplicantProfile::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertStringEndsWith('.webp', (string) $profile->photo_path);
        $this->assertStringEndsWith('.webp', (string) $profile->ktp_path);
        Storage::disk('public')->assertExists((string) $profile->photo_path);
        Storage::disk('public')->assertExists((string) $profile->ktp_path);
    }

    public function test_application_form_accepts_pdf_for_cv_upload(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => User::ROLE_APPLICANT, 'employee_id' => null]);

        ApplicantProfile::query()->create([
            'user_id' => $user->id,
            'personal_json' => [
                'full_name' => 'Pelamar CV PDF',
                'email' => $user->email,
                'photo_path' => 'existing/photo.jpg',
                'ktp_path' => 'existing/ktp.pdf',
                'signature_path' => 'existing/signature.png',
            ],
        ]);

        $response = $this->actingAs($user)->post(route('application-form.update'), $this->validPayload([
            'cv_file' => UploadedFile::fake()->create('cv-final.pdf', 320, 'application/pdf'),
            'signature_data' => '',
            'final_submit' => '1',
        ]));

        $response->assertRedirect()->assertSessionHas('success');

        $profile = ApplicantProfile::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertStringEndsWith('.pdf', (string) $profile->cv_path);
        Storage::disk('public')->assertExists((string) $profile->cv_path);
    }

    public function test_application_form_stores_new_signature_data_to_public_disk(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => User::ROLE_APPLICANT, 'employee_id' => null]);

        ApplicantProfile::query()->create([
            'user_id' => $user->id,
            'personal_json' => [
                'full_name' => 'Pelamar TTD Baru',
                'email' => $user->email,
                'photo_path' => 'existing/photo.jpg',
                'ktp_path' => 'existing/ktp.pdf',
                'cv_path' => 'existing/cv.pdf',
            ],
        ]);

        $response = $this->actingAs($user)->post(route('application-form.update'), $this->validPayload([
            'signature_data' => $this->dummyPngDataUri(),
            'final_submit' => '1',
        ]));

        $response->assertRedirect()->assertSessionHas('success');

        $profile = ApplicantProfile::query()->where('user_id', $user->id)->firstOrFail();
        $signaturePath = (string) data_get($profile->personal_json, 'signature_path');

        $this->assertNotSame('', $signaturePath);
        $this->assertStringStartsWith('applicants/signatures/', $signaturePath);
        Storage::disk('public')->assertExists($signaturePath);
    }

    public function test_temporary_upload_endpoint_accepts_mobile_webp_and_drive_pdf(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $user = User::factory()->create(['role' => User::ROLE_APPLICANT, 'employee_id' => null]);

        $photoResponse = $this->actingAs($user)
            ->postJson(route('application-form.upload-temp'), [
                'field' => 'photo_ktp_file',
                'document' => UploadedFile::fake()->create('pas-foto.webp', 180, 'image/webp'),
            ]);

        $photoResponse->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('reason', 'ok')
            ->assertJsonPath('token', $photoResponse->json('upload.token'))
            ->assertJsonPath('upload.field', 'photo_ktp_file');

        $cvResponse = $this->actingAs($user)
            ->postJson(route('application-form.upload-temp'), [
                'field' => 'cv_file',
                'document' => UploadedFile::fake()->create('cv-drive.pdf', 320, 'application/octet-stream'),
            ]);

        $cvResponse->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('reason', 'ok')
            ->assertJsonPath('token', $cvResponse->json('upload.token'))
            ->assertJsonPath('upload.field', 'cv_file');
    }

    public function test_application_form_session_ping_refreshes_csrf_context(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_APPLICANT, 'employee_id' => null]);

        $response = $this->actingAs($user)
            ->getJson(route('application-form.session-ping'));

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('authenticated', true)
            ->assertJsonStructure(['csrf_token', 'server_time', 'upload_limits']);
    }

    public function test_temporary_upload_endpoint_rejects_oversized_photo_with_json_message(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $user = User::factory()->create(['role' => User::ROLE_APPLICANT, 'employee_id' => null]);

        $response = $this->actingAs($user)
            ->postJson(route('application-form.upload-temp'), [
                'field' => 'photo_ktp_file',
                'document' => UploadedFile::fake()->create('pas-foto.jpg', 4097, 'image/jpeg'),
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['ok', 'message', 'request_id', 'reason', 'errors' => ['document']])
            ->assertJsonPath('ok', false)
            ->assertJsonPath('reason', 'upload_failed');
        $this->assertStringContainsString('melebihi batas', (string) $response->json('message'));
    }

    public function test_temporary_upload_endpoint_rejects_invalid_file_type_with_json_message(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $user = User::factory()->create(['role' => User::ROLE_APPLICANT, 'employee_id' => null]);

        $response = $this->actingAs($user)
            ->postJson(route('application-form.upload-temp'), [
                'field' => 'scan_ktp_file',
                'document' => UploadedFile::fake()->create('scan-ktp.txt', 32, 'text/plain'),
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['ok', 'message', 'request_id', 'reason', 'errors' => ['document']])
            ->assertJsonPath('ok', false)
            ->assertJsonPath('reason', 'upload_failed');
        $this->assertStringContainsString('tidak didukung', (string) $response->json('message'));
    }

    public function test_temporary_upload_endpoint_rejects_missing_file_with_json_message(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $user = User::factory()->create(['role' => User::ROLE_APPLICANT, 'employee_id' => null]);

        $response = $this->actingAs($user)
            ->postJson(route('application-form.upload-temp'), [
                'field' => 'cv_file',
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['ok', 'message', 'request_id', 'reason', 'errors' => ['document']])
            ->assertJsonPath('ok', false)
            ->assertJsonPath('reason', 'missing_file');
        $this->assertStringContainsString('File belum terbaca', (string) $response->json('message'));
    }

    public function test_temporary_upload_async_validation_error_returns_json_not_redirect(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $user = User::factory()->create(['role' => User::ROLE_APPLICANT, 'employee_id' => null]);

        $response = $this->actingAs($user)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->post(route('application-form.upload-temp'), [
                'field' => 'scan_ktp_file',
                'document' => UploadedFile::fake()->create('scan-ktp.exe', 24, 'application/octet-stream'),
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('reason', 'upload_failed');
        $this->assertStringContainsString('application/json', (string) $response->headers->get('content-type'));
    }

    public function test_failed_replacement_upload_does_not_delete_previous_temporary_file(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $user = User::factory()->create(['role' => User::ROLE_APPLICANT, 'employee_id' => null]);
        $service = app(ApplicationFormTemporaryUploadService::class);
        $previous = $service->storeTemporaryUpload(
            UploadedFile::fake()->create('pas-foto.webp', 180, 'image/webp'),
            'photo_ktp_file',
            $user->id
        );

        $response = $this->actingAs($user)
            ->postJson(route('application-form.upload-temp'), [
                'field' => 'photo_ktp_file',
                'previous_token' => $previous['token'],
                'document' => UploadedFile::fake()->create('pas-foto.txt', 32, 'text/plain'),
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('reason', 'upload_failed');

        Storage::disk('public')->assertExists($previous['stored_path']);
        Storage::disk('local')->assertExists('application-form-temp/' . $previous['token'] . '.json');
    }

    public function test_application_form_final_submit_accepts_uploaded_tokens_without_reupload(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $user = User::factory()->create(['role' => User::ROLE_APPLICANT, 'employee_id' => null]);

        ApplicantProfile::query()->create([
            'user_id' => $user->id,
            'personal_json' => [
                'full_name' => 'Pelamar Token',
                'email' => $user->email,
                'signature_path' => 'existing/signature.png',
            ],
        ]);

        $service = app(ApplicationFormTemporaryUploadService::class);
        $photo = $service->storeTemporaryUpload(UploadedFile::fake()->create('pas-foto.webp', 180, 'image/webp'), 'photo_ktp_file', $user->id);
        $ktp = $service->storeTemporaryUpload(UploadedFile::fake()->create('scan-ktp.webp', 220, 'image/webp'), 'scan_ktp_file', $user->id);
        $cv = $service->storeTemporaryUpload(UploadedFile::fake()->create('cv-final.pdf', 320, 'application/pdf'), 'cv_file', $user->id);

        $response = $this->actingAs($user)->post(route('application-form.update'), $this->validPayload([
            'photo_ktp_file_token' => $photo['token'],
            'scan_ktp_file_token' => $ktp['token'],
            'cv_file_token' => $cv['token'],
            'signature_data' => '',
            'final_submit' => '1',
        ]));

        $response->assertRedirect()->assertSessionHas('success');

        $profile = ApplicantProfile::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertStringEndsWith('.webp', (string) $profile->photo_path);
        $this->assertStringEndsWith('.webp', (string) $profile->ktp_path);
        $this->assertStringEndsWith('.pdf', (string) $profile->cv_path);
        Storage::disk('public')->assertExists((string) $profile->photo_path);
        Storage::disk('public')->assertExists((string) $profile->ktp_path);
        Storage::disk('public')->assertExists((string) $profile->cv_path);
    }

    public function test_final_submit_fails_clearly_when_required_documents_are_missing(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_APPLICANT, 'employee_id' => null]);

        ApplicantProfile::query()->create([
            'user_id' => $user->id,
            'personal_json' => [
                'full_name' => 'Pelamar Tanpa Dokumen',
                'email' => $user->email,
                'signature_path' => 'existing/signature.png',
            ],
        ]);

        $response = $this->actingAs($user)
            ->from(route('application-form.edit'))
            ->post(route('application-form.update'), $this->validPayload([
                'signature_data' => '',
                'final_submit' => '1',
            ]));

        $response->assertRedirect(route('application-form.edit'))
            ->assertSessionHasErrors(['photo_ktp_file', 'scan_ktp_file', 'cv_file'])
            ->assertSessionHas('first_error_step', 1);
    }

    public function test_final_submit_rejects_expired_temporary_document_token_as_field_error(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => User::ROLE_APPLICANT, 'employee_id' => null]);

        ApplicantProfile::query()->create([
            'user_id' => $user->id,
            'personal_json' => [
                'full_name' => 'Pelamar Token Expired',
                'email' => $user->email,
                'ktp_path' => 'existing/ktp.pdf',
                'cv_path' => 'existing/cv.pdf',
                'signature_path' => 'existing/signature.png',
            ],
        ]);

        $response = $this->actingAs($user)
            ->from(route('application-form.edit'))
            ->post(route('application-form.update'), $this->validPayload([
                'photo_ktp_file_token' => 'expired-token-from-browser-preview',
                'signature_data' => '',
                'final_submit' => '1',
            ]));

        $response->assertRedirect(route('application-form.edit'))
            ->assertSessionHasErrors(['photo_ktp_file'])
            ->assertSessionHas('first_error_step', 1);
    }

    public function test_temporary_upload_endpoint_returns_clear_message_for_heic_when_converter_unavailable(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $user = User::factory()->create(['role' => User::ROLE_APPLICANT, 'employee_id' => null]);

        $response = $this->actingAs($user)
            ->postJson(route('application-form.upload-temp'), [
                'field' => 'photo_ktp_file',
                'document' => UploadedFile::fake()->create('pas-foto.heic', 240, 'image/heic'),
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('reason', 'upload_failed');
        $this->assertStringContainsString('HEIC/HEIF', (string) $response->json('message'));
    }

    public function test_temporary_upload_endpoint_supports_heic_conversion_success_when_service_can_handle_it(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_APPLICANT, 'employee_id' => null]);

        $mock = Mockery::mock(ApplicationFormTemporaryUploadService::class);
        $mock->shouldReceive('discardTemporaryUpload')->once();
        $mock->shouldReceive('storeTemporaryUpload')->once()->andReturn([
            'token' => 'temp-heic-token',
            'field' => 'photo_ktp_file',
            'label' => 'pas foto',
            'preview_url' => 'http://localhost/storage/applicants/tmp/photos/temp-heic-token.jpg',
            'normalized_name' => 'pas-foto.jpg',
            'original_name' => 'pas-foto.heic',
            'mime' => 'image/jpeg',
            'size_bytes' => 123456,
            'source' => 'heic_converted',
        ]);
        $mock->shouldReceive('canConvertHeic')->once()->andReturn(true);
        $this->app->instance(ApplicationFormTemporaryUploadService::class, $mock);

        $response = $this->actingAs($user)
            ->postJson(route('application-form.upload-temp'), [
                'field' => 'photo_ktp_file',
                'document' => UploadedFile::fake()->create('pas-foto.heic', 240, 'image/heic'),
            ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('upload.source', 'heic_converted')
            ->assertJsonPath('diagnostics.heic_conversion_available', true);
    }

    public function test_temporary_upload_endpoint_detects_probable_server_upload_limit_issue(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_APPLICANT, 'employee_id' => null]);

        $response = $this->actingAs($user)
            ->withServerVariables([
                'CONTENT_LENGTH' => (string) (ApplicationFormUploadLimit::effectiveBytes() + 1024),
            ])
            ->postJson(route('application-form.upload-temp'), [
                'field' => 'photo_ktp_file',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('reason', 'server_limit');
    }

    public function test_application_form_rejects_heic_upload_with_clear_message(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_APPLICANT, 'employee_id' => null]);

        ApplicantProfile::query()->create([
            'user_id' => $user->id,
            'personal_json' => [
                'full_name' => 'Pelamar iPhone',
                'email' => $user->email,
                'ktp_path' => 'existing/ktp.pdf',
                'cv_path' => 'existing/cv.pdf',
                'signature_path' => 'existing/signature.png',
            ],
        ]);

        $response = $this->actingAs($user)
            ->from(route('application-form.edit'))
            ->post(route('application-form.update'), $this->validPayload([
                'photo_ktp_file' => UploadedFile::fake()->create('pas-foto.heic', 240, 'image/heic'),
                'signature_data' => '',
                'final_submit' => '1',
            ]));

        $response->assertRedirect(route('application-form.edit'))
            ->assertSessionHasErrors(['photo_ktp_file'])
            ->assertSessionHas('first_error_step', 1);
    }

    public function test_application_form_rejects_invalid_signature_payload_with_clear_message(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_APPLICANT, 'employee_id' => null]);

        ApplicantProfile::query()->create([
            'user_id' => $user->id,
            'personal_json' => [
                'full_name' => 'Pelamar Signature Invalid',
                'email' => $user->email,
                'photo_path' => 'existing/photo.jpg',
                'ktp_path' => 'existing/ktp.pdf',
                'cv_path' => 'existing/cv.pdf',
            ],
        ]);

        $response = $this->actingAs($user)
            ->from(route('application-form.edit'))
            ->post(route('application-form.update'), $this->validPayload([
                'signature_data' => 'data:image/png;base64,not-a-valid-base64-payload###',
                'final_submit' => '1',
            ]));

        $response->assertRedirect(route('application-form.edit'))
            ->assertSessionHasErrors(['signature_data'])
            ->assertSessionHas('first_error_step', 6);
    }

    public function test_validation_failure_preserves_non_file_input_when_cv_is_invalid(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_APPLICANT, 'employee_id' => null]);

        ApplicantProfile::query()->create([
            'user_id' => $user->id,
            'personal_json' => [
                'full_name' => 'Pelamar Preserve',
                'email' => $user->email,
                'photo_path' => 'existing/photo.jpg',
                'ktp_path' => 'existing/ktp.pdf',
                'signature_path' => 'existing/signature.png',
            ],
        ]);

        $response = $this->actingAs($user)
            ->from(route('application-form.edit'))
            ->post(route('application-form.update'), $this->validPayload([
                'full_name' => 'Nama Dari Input Lama',
                'cv_file' => UploadedFile::fake()->create('cv-image.png', 200, 'image/png'),
                'signature_data' => '',
                'final_submit' => '1',
            ]));

        $response->assertRedirect(route('application-form.edit'))
            ->assertSessionHasErrors(['cv_file'])
            ->assertSessionHasInput('full_name', 'Nama Dari Input Lama')
            ->assertSessionHas('first_error_step', 1);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Pelamar Test',
            'ktp_number' => '3175000000000001',
            'place_of_birth' => 'Bandung',
            'date_of_birth' => '1998-01-10',
            'time_of_birth' => '08:15',
            'gender' => 'Laki-laki',
            'religion' => 'Islam',
            'blood_type' => 'O',
            'marital_status' => 'Single',
            'whatsapp' => '081234567890',
            'phone_number' => '081298765432',
            'salary_expectation' => '4500000',
            'applied_position_name' => 'Crew Outlet',
            'willing_out_of_town' => 'Ya',
            'willing_outside_java' => 'Ya',
            'willing_shift' => 'Ya',
            'willing_overtime' => 'Ya',
            'is_smoker' => 'Tidak',
            'has_computer_skill' => 'Ya',
            'wears_glasses' => 'Tidak',
            'join_reason' => 'Saya ingin berkembang bersama perusahaan ini dan berkontribusi jangka panjang.',
            'company_relation_note' => 'Tidak ada saudara atau teman yang bekerja di perusahaan ini.',
            'career_goal' => 'Ingin berkembang menjadi leader operasional yang disiplin dan berintegritas.',
            'additional_information' => 'Siap mengikuti proses rekrutmen sesuai aturan.',
            'available_start_date' => '2026-05-01',
            'honesty_statement' => str_repeat('Saya menyatakan seluruh data yang saya isi adalah benar dan siap mempertanggungjawabkannya. ', 2),
            'ktp_address' => 'Jl. Mawar 1',
            'ktp_rt' => '001',
            'ktp_rw' => '002',
            'ktp_kelurahan' => 'Sukamaju',
            'ktp_kecamatan' => 'Coblong',
            'ktp_city' => 'Bandung',
            'domicile_address' => 'Jl. Melati 2',
            'domicile_rt' => '003',
            'domicile_rw' => '004',
            'domicile_kelurahan' => 'Sukamiskin',
            'domicile_kecamatan' => 'Arcamanik',
            'domicile_city' => 'Bandung',
            'families' => [
                ['relation' => 'Ayah', 'name' => 'Bapak Test', 'gender' => 'Laki-laki', 'dob' => '1970-01-01', 'education' => 'SMA', 'job' => 'Wiraswasta', 'status_note' => ''],
                ['relation' => 'Ibu', 'name' => 'Ibu Test', 'gender' => 'Perempuan', 'dob' => '1972-01-01', 'education' => 'SMA', 'job' => 'Ibu Rumah Tangga', 'status_note' => ''],
            ],
            'emergency_contacts' => [
                ['name' => 'Ayah Test', 'relation' => 'Ayah', 'phone' => '081111111111', 'address' => 'Bandung'],
                ['name' => 'Ibu Test', 'relation' => 'Ibu', 'phone' => '082222222222', 'address' => 'Bandung'],
            ],
            'educations' => [
                ['level' => 'SMA/SMK', 'school' => 'SMK Test', 'major' => 'Akuntansi', 'year_in' => '2013', 'year_out' => '2016', 'gpa' => '85'],
                ['level' => 'D3', 'school' => 'Politeknik Test', 'major' => 'Manajemen', 'year_in' => '2016', 'year_out' => '2019', 'gpa' => '3.20'],
                ['level' => 'S1', 'school' => 'Universitas Test', 'major' => 'Manajemen', 'year_in' => '2019', 'year_out' => '2023', 'gpa' => '3.50'],
            ],
            'languages' => [['language' => 'Indonesia', 'speaking' => 'Baik', 'writing' => 'Baik']],
            'work_experiences' => [['company' => 'PT Test', 'position' => 'Crew', 'date_start' => '2021-01-01', 'date_end' => '2022-01-01', 'salary' => '3500000', 'reason' => 'Kontrak selesai']],
            'reference_contacts' => [
                ['name' => 'Supervisor Test', 'relation' => 'Atasan', 'company' => 'PT Test', 'phone' => '081111111111'],
                ['name' => 'HR Test', 'relation' => 'HRD', 'company' => 'PT Test 2', 'phone' => '082222222222'],
            ],
            'medical_histories' => [['illness' => 'Tidak ada', 'year' => '2024', 'hospitalized' => 'Tidak', 'note' => 'Sehat']],
            'weight_kg' => '60',
            'height_cm' => '170',
            'had_accident' => 'Tidak',
            'police_record' => 'Tidak',
            'psychology_test' => 'Tidak',
            'social_medias' => [['platform' => 'Instagram', 'handle' => '@pelamartest']],
            'courses' => [],
            'organizations' => [],
            'signature_data' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO2Z8L8AAAAASUVORK5CYII=',
            'final_submit' => '0',
        ], $overrides);
    }

    private function ensureReferenceMasterTables(): void
    {
        if (! Schema::hasTable('positions')) {
            Schema::create('positions', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->integer('level')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table): void {
                $table->id();
                $table->string('code')->nullable();
                $table->string('name');
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('outlets')) {
            Schema::create('outlets', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('brand_name')->nullable();
                $table->timestamps();
            });
        }
    }

    private function dummyPngDataUri(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO2Z8L8AAAAASUVORK5CYII=';
    }
}
