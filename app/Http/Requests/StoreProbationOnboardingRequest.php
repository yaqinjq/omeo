<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\EmployeeProfileService;
use App\Services\PayrollProfileTargetService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProbationOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $user = $this->user();
        $service = app(PayrollProfileTargetService::class);
        $profileService = app(EmployeeProfileService::class);
        $current = $service->getCurrent((int) $user?->id, $user?->employee_id ? (int) $user->employee_id : null);
        $existingBankAccounts = $user?->employee_id ? $profileService->getBankAccounts((int) $user->employee_id) : [];

        $has = fn (string $key): bool => !empty(trim((string) ($current[$key] ?? '')));
        $hasExistingBankAccounts = !empty($existingBankAccounts);

        return [
            'sim_number' => ['required', 'string', 'max:100'],
            'npwp_number' => ['required', 'string', 'max:100'],
            'bpjs_kes_number' => ['required', 'string', 'max:100'],
            'bpjs_tk_number' => ['nullable', 'string', 'max:100'],
            'passport_number' => ['nullable', 'string', 'max:100'],
            'kk_number' => ['required', 'string', 'max:100'],

            'sim_file' => [Rule::requiredIf(! $has('sim_file')), 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'npwp_file' => [Rule::requiredIf(! $has('npwp_file')), 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'bpjs_kes_file' => [Rule::requiredIf(! $has('bpjs_kes_file')), 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'bpjs_tk_file' => [Rule::requiredIf(!empty(trim((string) $this->input('bpjs_tk_number'))) && ! $has('bpjs_tk_file')), 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'passport_file' => [Rule::requiredIf(!empty(trim((string) $this->input('passport_number'))) && ! $has('passport_file')), 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'kk_file' => [Rule::requiredIf(! $has('kk_file')), 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],

            'bank_accounts' => [Rule::requiredIf(! $hasExistingBankAccounts), 'array', 'min:1'],
            'bank_accounts.*.id' => ['nullable', 'integer'],
            'bank_accounts.*.bank_code' => ['required_with:bank_accounts.*.account_number,bank_accounts.*.account_holder_name', 'nullable', 'string', 'max:50'],
            'bank_accounts.*.bank_name' => ['nullable', 'string', 'max:150'],
            'bank_accounts.*.account_number' => ['required_with:bank_accounts.*.bank_code,bank_accounts.*.account_holder_name', 'nullable', 'string', 'max:100'],
            'bank_accounts.*.account_holder_name' => ['required_with:bank_accounts.*.bank_code,bank_accounts.*.account_number', 'nullable', 'string', 'max:150'],
            'bank_accounts.*.is_primary' => ['nullable', 'boolean'],
            'bank_accounts.*.existing_files' => ['nullable', 'array'],
            'bank_accounts.*.existing_files.*' => ['nullable', 'string', 'max:255'],
            'bank_accounts.*.files' => ['nullable', 'array'],
            'bank_accounts.*.files.*' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'sim_number.required' => 'Nomor SIM wajib diisi.',
            'npwp_number.required' => 'Nomor NPWP wajib diisi.',
            'bpjs_kes_number.required' => 'Nomor BPJS Kesehatan wajib diisi.',
            'kk_number.required' => 'Nomor KK wajib diisi.',
            'bank_accounts.required' => 'Minimal satu rekening bank wajib diisi.',
            'bank_accounts.min' => 'Minimal satu rekening bank wajib diisi.',
            'bank_accounts.*.bank_code.required_with' => 'Pilih bank untuk setiap rekening yang diisi.',
            'bank_accounts.*.account_number.required_with' => 'Nomor rekening wajib diisi.',
            'bank_accounts.*.account_holder_name.required_with' => 'Nama pemilik rekening wajib diisi.',

            'sim_file.required' => 'File SIM wajib diunggah.',
            'npwp_file.required' => 'File NPWP wajib diunggah.',
            'bpjs_kes_file.required' => 'File BPJS Kesehatan wajib diunggah.',
            'kk_file.required' => 'File KK wajib diunggah.',
            'bpjs_tk_file.required' => 'File BPJS TK wajib jika nomor BPJS TK diisi.',
            'passport_file.required' => 'File passport wajib jika nomor passport diisi.',

            'sim_file.mimes' => 'Format file SIM harus PDF/JPG/JPEG/PNG/WEBP.',
            'npwp_file.mimes' => 'Format file NPWP harus PDF/JPG/JPEG/PNG/WEBP.',
            'bpjs_kes_file.mimes' => 'Format file BPJS Kesehatan harus PDF/JPG/JPEG/PNG/WEBP.',
            'bpjs_tk_file.mimes' => 'Format file BPJS TK harus PDF/JPG/JPEG/PNG/WEBP.',
            'passport_file.mimes' => 'Format file passport harus PDF/JPG/JPEG/PNG/WEBP.',
            'kk_file.mimes' => 'Format file KK harus PDF/JPG/JPEG/PNG/WEBP.',
            'bank_accounts.*.files.*.mimes' => 'Format foto rekening harus PDF/JPG/JPEG/PNG/WEBP.',

            'sim_file.max' => 'Ukuran file SIM maksimal 5MB.',
            'npwp_file.max' => 'Ukuran file NPWP maksimal 5MB.',
            'bpjs_kes_file.max' => 'Ukuran file BPJS Kesehatan maksimal 5MB.',
            'bpjs_tk_file.max' => 'Ukuran file BPJS TK maksimal 5MB.',
            'passport_file.max' => 'Ukuran file passport maksimal 5MB.',
            'kk_file.max' => 'Ukuran file KK maksimal 5MB.',
            'bank_accounts.*.files.*.max' => 'Ukuran foto rekening maksimal 5MB per file.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $accounts = $this->input('bank_accounts', []);
        if (!is_array($accounts)) {
            $accounts = [];
        }

        $normalized = [];
        foreach ($accounts as $index => $account) {
            if (!is_array($account)) {
                continue;
            }

            $normalized[$index] = [
                'id' => $account['id'] ?? null,
                'bank_code' => trim((string) ($account['bank_code'] ?? '')),
                'bank_name' => trim((string) ($account['bank_name'] ?? '')),
                'account_number' => trim((string) ($account['account_number'] ?? '')),
                'account_holder_name' => trim((string) ($account['account_holder_name'] ?? '')),
                'is_primary' => $account['is_primary'] ?? false,
                'existing_files' => array_values(array_filter(array_map(
                    static fn ($path) => trim((string) $path),
                    (array) ($account['existing_files'] ?? [])
                ))),
            ];
        }

        $this->merge(['bank_accounts' => array_values($normalized)]);
    }
}
