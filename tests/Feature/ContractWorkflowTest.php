<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\HrNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContractWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_hrd_can_send_contract_and_store_original_pdf(): void
    {
        Storage::fake('public');

        $hrd = $this->createUser('hrd');
        $candidate = $this->createCandidateWithUser();
        $template = $this->createTemplate();

        $response = $this->actingAs($hrd)->post(route('hrd.contracts.send'), [
            'candidate_ids' => [$candidate->id],
            'use_active_template' => 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $contract = Contract::query()->where('candidate_id', $candidate->id)->first();

        $this->assertNotNull($contract);
        $this->assertSame(Contract::STATUS_SENT, $contract->status);
        $this->assertNotEmpty($contract->contract_number);
        $this->assertNotEmpty($contract->pdf_path_original);
        Storage::disk('public')->assertExists($contract->pdf_path_original);

        $this->assertDatabaseHas('hr_notifications', [
            'user_id' => $candidate->user_id,
            'type' => 'daily_worker_contract',
            'title' => 'Kontrak Daily Worker Sudah Tersedia',
        ]);

        $template->refresh();
        $this->assertSame(2, (int) $template->next_sequence);
    }

    public function test_candidate_can_stamp_then_submit_signature(): void
    {
        Storage::fake('public');

        [$hrd, $candidateUser, $contract] = $this->seedSentContract();

        $this->actingAs($candidateUser)->get(route('applicant.contracts.show', $contract))->assertOk();

        $this->actingAs($candidateUser)
            ->post(route('applicant.contracts.stamp', $contract), [
                'stamp_number' => 'MTR-123456789',
                'stamp_confirmed' => '1',
            ])
            ->assertRedirect();

        $contract->refresh();
        $this->assertSame(Contract::STATUS_AWAITING_SIGNATURE, $contract->status);
        $this->assertDatabaseHas('contract_stamps', [
            'contract_id' => $contract->id,
            'stamp_number' => 'MTR-123456789',
        ]);

        $this->actingAs($candidateUser)
            ->post(route('applicant.contracts.submit', $contract), [
                'signature_data' => $this->dummyPngDataUri(),
            ])
            ->assertRedirect();

        $contract->refresh();
        $this->assertSame(Contract::STATUS_SUBMITTED, $contract->status);
        $this->assertNotEmpty($contract->pdf_path_signed);
        Storage::disk('public')->assertExists($contract->pdf_path_signed);

        $this->assertDatabaseHas('contract_signatures', [
            'contract_id' => $contract->id,
            'signer_role' => 'candidate',
            'signer_name' => 'Kandidat Test',
        ]);

        $this->assertDatabaseHas('hr_notifications', [
            'user_id' => $hrd->id,
            'type' => 'daily_worker_contract',
            'title' => 'Kontrak Ditandatangani & Dikirim',
        ]);
    }

    public function test_hrd_can_review_and_approve_contract_after_submission(): void
    {
        Storage::fake('public');

        [$hrd, $candidateUser, $contract] = $this->seedSubmittedContract();

        $this->actingAs($hrd)->get(route('hrd.contracts.show', $contract))->assertOk();

        $contract->refresh();
        $this->assertSame(Contract::STATUS_HR_REVIEW, $contract->status);

        $this->actingAs($hrd)
            ->post(route('hrd.contracts.review', $contract), [
                'decision' => 'approve',
                'review_reason' => 'Dokumen lengkap.',
            ])
            ->assertRedirect();

        $contract->refresh();
        $this->assertSame(Contract::STATUS_APPROVED, $contract->status);
        $this->assertNotNull($contract->approved_at);

        $this->assertDatabaseHas('hr_notifications', [
            'user_id' => $candidateUser->id,
            'type' => 'daily_worker_contract',
            'title' => 'Kontrak Disetujui HRD',
        ]);
    }

    /**
     * @return array{0: User, 1: User, 2: Contract}
     */
    private function seedSentContract(): array
    {
        $hrd = $this->createUser('hrd');
        $candidate = $this->createCandidateWithUser();
        $candidateUser = $candidate->user;
        $template = $this->createTemplate();

        $this->actingAs($hrd)->post(route('hrd.contracts.send'), [
            'candidate_ids' => [$candidate->id],
            'use_active_template' => 1,
        ]);

        $contract = Contract::query()->where('candidate_id', $candidate->id)->firstOrFail();

        return [$hrd, $candidateUser, $contract];
    }

    /**
     * @return array{0: User, 1: User, 2: Contract}
     */
    private function seedSubmittedContract(): array
    {
        [$hrd, $candidateUser, $contract] = $this->seedSentContract();

        $this->actingAs($candidateUser)->post(route('applicant.contracts.stamp', $contract), [
            'stamp_number' => 'MTR-987654321',
            'stamp_confirmed' => '1',
        ]);

        $contract->refresh();

        $this->actingAs($candidateUser)->post(route('applicant.contracts.submit', $contract), [
            'signature_data' => $this->dummyPngDataUri(),
        ]);

        return [$hrd, $candidateUser, $contract->fresh()];
    }

    private function createUser(string $role): User
    {
        return User::query()->create([
            'name' => strtoupper($role) . ' User',
            'email' => $role . '-' . uniqid() . '@example.test',
            'password' => Hash::make('password'),
            'role' => $role,
            'employee_id' => $role === 'hrd' ? 1 : null,
            'email_verified_at' => now(),
        ]);
    }

    private function createCandidateWithUser(): Candidate
    {
        $user = $this->createUser('employee');

        return Candidate::query()->create([
            'user_id' => $user->id,
            'full_name' => 'Kandidat Test',
            'email' => $user->email,
            'phone' => '08123456789',
            'nik' => '3175000000000001',
            'status' => Candidate::STATUS_ACCEPTED,
        ]);
    }

    private function createTemplate(): ContractTemplate
    {
        return ContractTemplate::query()->create([
            'name' => 'Template DW Test',
            'type' => ContractTemplate::TYPE_DAILY_WORKER,
            'is_active' => true,
            'numbering_prefix' => 'DW/TEST/',
            'numbering_format' => '{prefix}{YYYY}{MM}{SEQ4}',
            'next_sequence' => 1,
            'body_html' => '<h1>Kontrak DW</h1><p>{{candidate_name}}</p><p>{{contract_number}}</p>',
            'document_title' => 'Kontrak DW Test',
            'main_content' => 'Isi kontrak test',
            'is_builder_mode' => true,
        ]);
    }

    private function dummyPngDataUri(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO2R8K8AAAAASUVORK5CYII=';
    }
}




