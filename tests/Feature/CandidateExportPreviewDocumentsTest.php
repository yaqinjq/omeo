<?php

namespace Tests\Feature;

use App\Models\ApplicantProfile;
use App\Models\Candidate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CandidateExportPreviewDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_page_displays_photo_and_ktp_visual_panels_when_files_exist(): void
    {
        Storage::fake('public');

        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        $candidateUser = User::factory()->create([
            'role' => User::ROLE_CANDIDATE,
            'email' => 'export.preview@example.com',
        ]);

        $photoPath = UploadedFile::fake()->image('candidate-photo.jpg')->store('applicants/photos', 'public');
        $ktpPath = UploadedFile::fake()->image('candidate-ktp.png')->store('applicants/ktp', 'public');
        $cvPath = UploadedFile::fake()->image('candidate-cv.jpg')->store('applicants/cv', 'public');

        ApplicantProfile::query()->create([
            'user_id' => $candidateUser->id,
            'personal_json' => [
                'full_name' => 'Preview Kandidat',
                'email' => $candidateUser->email,
                'photo_path' => $photoPath,
                'ktp_path' => $ktpPath,
                'cv_path' => $cvPath,
            ],
        ]);

        $candidate = Candidate::query()->create([
            'user_id' => $candidateUser->id,
            'full_name' => 'Preview Kandidat',
            'email' => $candidateUser->email,
            'nik' => '3175000000000044',
            'status' => Candidate::STATUS_APPLIED,
        ]);

        $this->actingAs($hrd)
            ->get(route('candidates.export-preview', $candidate))
            ->assertOk()
            ->assertSeeText('Dokumen Visual Kandidat')
            ->assertSeeText('Pas Foto')
            ->assertSeeText('Scan KTP')
            ->assertSeeText('Download Profil PDF')
            ->assertSeeText('Download CV Asli')
            ->assertSeeText('Download Paket Kandidat')
            ->assertSee('alt="Pas Foto Kandidat"', false)
            ->assertSee('alt="Scan KTP Kandidat"', false)
            ->assertSee('alt="CV Kandidat"', false);
    }

    public function test_profile_pdf_download_response_uses_pdf_filename_and_content_headers(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        $candidateUser = User::factory()->create([
            'role' => User::ROLE_CANDIDATE,
            'email' => 'export.profile@example.com',
        ]);

        ApplicantProfile::query()->create([
            'user_id' => $candidateUser->id,
            'personal_json' => [
                'full_name' => 'Header Kandidat',
                'email' => $candidateUser->email,
            ],
        ]);

        $candidate = Candidate::query()->create([
            'user_id' => $candidateUser->id,
            'full_name' => 'Header Kandidat',
            'email' => $candidateUser->email,
            'nik' => '3175000000000045',
            'status' => Candidate::STATUS_APPLIED,
        ]);

        $response = $this->actingAs($hrd)->get(route('candidates.export-pdf', $candidate));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('.pdf', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
    }

    public function test_preview_pdf_response_uses_inline_pdf_filename(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        $candidateUser = User::factory()->create([
            'role' => User::ROLE_CANDIDATE,
            'email' => 'preview.header@example.com',
        ]);

        ApplicantProfile::query()->create([
            'user_id' => $candidateUser->id,
            'personal_json' => [
                'full_name' => 'Preview Header Kandidat',
                'email' => $candidateUser->email,
            ],
        ]);

        $candidate = Candidate::query()->create([
            'user_id' => $candidateUser->id,
            'full_name' => 'Preview Header Kandidat',
            'email' => $candidateUser->email,
            'nik' => '3175000000000046',
            'status' => Candidate::STATUS_APPLIED,
        ]);

        $response = $this->actingAs($hrd)->get(route('candidates.preview-pdf', $candidate));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('.pdf', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('inline', (string) $response->headers->get('content-disposition'));
    }

    public function test_cv_download_and_package_download_use_clear_filenames(): void
    {
        Storage::fake('public');

        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        $candidateUser = User::factory()->create([
            'role' => User::ROLE_CANDIDATE,
            'email' => 'package.preview@example.com',
        ]);

        $cvPath = UploadedFile::fake()->create('candidate-cv.pdf', 160, 'application/pdf')->store('applicants/cv', 'public');

        ApplicantProfile::query()->create([
            'user_id' => $candidateUser->id,
            'personal_json' => [
                'full_name' => 'Paket Kandidat',
                'email' => $candidateUser->email,
                'cv_path' => $cvPath,
            ],
        ]);

        $candidate = Candidate::query()->create([
            'user_id' => $candidateUser->id,
            'full_name' => 'Paket Kandidat',
            'email' => $candidateUser->email,
            'nik' => '3175000000000047',
            'status' => Candidate::STATUS_APPLIED,
        ]);

        $cvResponse = $this->actingAs($hrd)->get(route('candidates.export-cv', $candidate));
        $cvResponse->assertOk();
        $this->assertStringContainsString('.pdf', (string) $cvResponse->headers->get('content-disposition'));

        $packageResponse = $this->actingAs($hrd)->get(route('candidates.export-package', $candidate));
        $packageResponse->assertOk();
        $packageResponse->assertHeader('content-type', 'application/zip');
        $this->assertStringContainsString('.zip', (string) $packageResponse->headers->get('content-disposition'));
    }
}
