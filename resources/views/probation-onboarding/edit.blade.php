@extends('layouts.app')

@section('content')
@php
    $initialBankAccounts = old('bank_accounts', $currentBankAccounts);
@endphp
<div class="space-y-6">
    <div class="card p-6">
        <h1 class="text-xl font-bold">Kelengkapan Payroll Probation</h1>
        <p class="text-sm text-muted mt-1">Data yang Anda kirim akan masuk antrian verifikasi HRD. Data utama baru berubah setelah disetujui.</p>
    </div>

    @if(!empty($moduleWarning))
        <div class="rounded-2xl border border-amber-300 bg-amber-50 text-amber-900 p-4">{{ $moduleWarning }}</div>
    @endif

    @if($pendingRequest)
        <div class="rounded-2xl border border-amber-300 bg-amber-50 text-amber-900 p-4">
            Status saat ini: <strong>Menunggu verifikasi HRD</strong> (submit: {{ optional($pendingRequest->submitted_at)->format('d-m-Y H:i') ?? '-' }}).
        </div>
    @endif

    @if($latestRejected)
        <div class="rounded-2xl border border-red-300 bg-red-50 text-red-900 p-4">
            Pengajuan terakhir ditolak HRD. Alasan: <strong>{{ $latestRejected->review_note ?: '-' }}</strong>
        </div>
    @endif

    @if($latestApproved)
        <div class="rounded-2xl border border-emerald-300 bg-emerald-50 text-emerald-900 p-4">
            Pengajuan terakhir disetujui HRD pada {{ optional($latestApproved->reviewed_at)->format('d-m-Y H:i') ?? '-' }}.
        </div>
    @endif

    <form action="{{ route('probation-onboarding.update') }}" method="POST" enctype="multipart/form-data" class="card p-6 space-y-8">
        @csrf

        <div>
            <h2 class="text-lg font-semibold">Dokumen Payroll</h2>
            <p class="text-sm text-muted mt-1">Wajib: SIM, NPWP, BPJS Kesehatan, KK. Opsional: BPJS TK, Passport.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium">Nomor SIM <span class="text-red-500">*</span></label>
                <input type="text" name="sim_number" value="{{ old('sim_number', $current['sim_number'] ?? '') }}" class="mt-1 w-full rounded-lg border px-3 py-2" required>
                <label class="block text-xs text-muted mt-2">Upload SIM <span class="text-red-500">*</span></label>
                <input type="file" name="sim_file" class="mt-1 w-full text-sm" accept=".pdf,.jpg,.jpeg,.png,.webp">
            </div>
            <div>
                <label class="block text-sm font-medium">Nomor NPWP <span class="text-red-500">*</span></label>
                <input type="text" name="npwp_number" value="{{ old('npwp_number', $current['npwp_number'] ?? '') }}" class="mt-1 w-full rounded-lg border px-3 py-2" required>
                <label class="block text-xs text-muted mt-2">Upload NPWP <span class="text-red-500">*</span></label>
                <input type="file" name="npwp_file" class="mt-1 w-full text-sm" accept=".pdf,.jpg,.jpeg,.png,.webp">
            </div>
            <div>
                <label class="block text-sm font-medium">Nomor BPJS Kesehatan <span class="text-red-500">*</span></label>
                <input type="text" name="bpjs_kes_number" value="{{ old('bpjs_kes_number', $current['bpjs_kes_number'] ?? '') }}" class="mt-1 w-full rounded-lg border px-3 py-2" required>
                <label class="block text-xs text-muted mt-2">Upload BPJS Kesehatan <span class="text-red-500">*</span></label>
                <input type="file" name="bpjs_kes_file" class="mt-1 w-full text-sm" accept=".pdf,.jpg,.jpeg,.png,.webp">
            </div>
            <div>
                <label class="block text-sm font-medium">Nomor BPJS TK <span class="text-slate-500">(Opsional)</span></label>
                <input type="text" name="bpjs_tk_number" value="{{ old('bpjs_tk_number', $current['bpjs_tk_number'] ?? '') }}" class="mt-1 w-full rounded-lg border px-3 py-2">
                <label class="block text-xs text-muted mt-2">Upload BPJS TK <span class="text-slate-500">(Opsional)</span></label>
                <input type="file" name="bpjs_tk_file" class="mt-1 w-full text-sm" accept=".pdf,.jpg,.jpeg,.png,.webp">
            </div>
            <div>
                <label class="block text-sm font-medium">Nomor Passport <span class="text-slate-500">(Opsional)</span></label>
                <input type="text" name="passport_number" value="{{ old('passport_number', $current['passport_number'] ?? '') }}" class="mt-1 w-full rounded-lg border px-3 py-2">
                <label class="block text-xs text-muted mt-2">Upload Passport <span class="text-slate-500">(Opsional)</span></label>
                <input type="file" name="passport_file" class="mt-1 w-full text-sm" accept=".pdf,.jpg,.jpeg,.png,.webp">
            </div>
            <div>
                <label class="block text-sm font-medium">Nomor KK <span class="text-red-500">*</span></label>
                <input type="text" name="kk_number" value="{{ old('kk_number', $current['kk_number'] ?? '') }}" class="mt-1 w-full rounded-lg border px-3 py-2" required>
                <label class="block text-xs text-muted mt-2">Upload KK <span class="text-red-500">*</span></label>
                <input type="file" name="kk_file" class="mt-1 w-full text-sm" accept=".pdf,.jpg,.jpeg,.png,.webp">
            </div>
        </div>

        <div class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold">Rekening Bank</h2>
                    <p class="text-sm text-muted mt-1">Anda dapat menyimpan lebih dari satu rekening. Tandai satu rekening utama dan unggah lebih dari satu foto bukti bila diperlukan.</p>
                </div>
                <button type="button" id="addBankAccountBtn" class="btn">Tambah Rekening</button>
            </div>

            <div id="bankAccountsWrapper" class="space-y-4">
                @forelse($initialBankAccounts as $index => $account)
                    <div class="rounded-2xl border border-slate-200 p-4 bank-account-card" data-index="{{ $index }}">
                        <div class="flex items-center justify-between gap-3 mb-4">
                            <h3 class="font-semibold">Rekening #{{ $loop->iteration }}</h3>
                            <button type="button" class="text-sm text-red-600 remove-bank-account">Hapus</button>
                        </div>
                        <input type="hidden" name="bank_accounts[{{ $index }}][id]" value="{{ $account['id'] ?? '' }}">
                        <input type="hidden" name="bank_accounts[{{ $index }}][bank_name]" class="bank-name-field" value="{{ old("bank_accounts.$index.bank_name", $account['bank_name'] ?? '') }}">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium">Bank</label>
                                <select name="bank_accounts[{{ $index }}][bank_code]" class="mt-1 w-full rounded-lg border px-3 py-2 bank-code-select">
                                    <option value="">-- Pilih bank --</option>
                                    @foreach($bankOptions as $option)
                                        <option value="{{ $option['code'] }}" data-label="{{ $option['name'] }}" @selected(old("bank_accounts.$index.bank_code", $account['bank_code'] ?? '') === $option['code'])>{{ $option['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium">Nomor Rekening</label>
                                <input type="text" name="bank_accounts[{{ $index }}][account_number]" value="{{ old("bank_accounts.$index.account_number", $account['account_number'] ?? '') }}" class="mt-1 w-full rounded-lg border px-3 py-2">
                            </div>
                            <div>
                                <label class="block text-sm font-medium">Nama Pemilik Rekening</label>
                                <input type="text" name="bank_accounts[{{ $index }}][account_holder_name]" value="{{ old("bank_accounts.$index.account_holder_name", $account['account_holder_name'] ?? '') }}" class="mt-1 w-full rounded-lg border px-3 py-2">
                            </div>
                            <div class="flex items-end gap-2">
                                <label class="inline-flex items-center gap-2 text-sm font-medium">
                                    <input type="checkbox" name="bank_accounts[{{ $index }}][is_primary]" value="1" @checked(old("bank_accounts.$index.is_primary", $account['is_primary'] ?? false))>
                                    Jadikan rekening utama
                                </label>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="block text-sm font-medium">Upload Foto Rekening</label>
                            <input type="file" name="bank_accounts[{{ $index }}][files][]" multiple class="mt-1 w-full text-sm" accept=".pdf,.jpg,.jpeg,.png,.webp">
                            <div class="mt-2 flex flex-wrap gap-2 text-sm">
                                @foreach((array) ($account['files'] ?? []) as $file)
                                    <input type="hidden" name="bank_accounts[{{ $index }}][existing_files][]" value="{{ $file['file_path'] ?? '' }}">
                                    <a href="{{ asset('storage/' . ltrim($file['file_path'] ?? '', '/')) }}" target="_blank" class="rounded-full bg-slate-100 px-3 py-1 text-slate-700">{{ $file['original_name'] ?? basename((string) ($file['file_path'] ?? '')) }}</a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 p-4 text-sm text-slate-500" id="bankAccountsEmptyState">
                        Belum ada rekening. Klik tombol "Tambah Rekening" untuk mulai mengisi.
                    </div>
                @endforelse
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="btn">Kirim untuk Verifikasi HRD</button>
        </div>
    </form>
</div>

<template id="bankAccountTemplate">
    <div class="rounded-2xl border border-slate-200 p-4 bank-account-card" data-index="__INDEX__">
        <div class="flex items-center justify-between gap-3 mb-4">
            <h3 class="font-semibold">Rekening Baru</h3>
            <button type="button" class="text-sm text-red-600 remove-bank-account">Hapus</button>
        </div>
        <input type="hidden" name="bank_accounts[__INDEX__][bank_name]" class="bank-name-field" value="">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium">Bank</label>
                <select name="bank_accounts[__INDEX__][bank_code]" class="mt-1 w-full rounded-lg border px-3 py-2 bank-code-select">
                    <option value="">-- Pilih bank --</option>
                    @foreach($bankOptions as $option)
                        <option value="{{ $option['code'] }}" data-label="{{ $option['name'] }}">{{ $option['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium">Nomor Rekening</label>
                <input type="text" name="bank_accounts[__INDEX__][account_number]" class="mt-1 w-full rounded-lg border px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium">Nama Pemilik Rekening</label>
                <input type="text" name="bank_accounts[__INDEX__][account_holder_name]" class="mt-1 w-full rounded-lg border px-3 py-2">
            </div>
            <div class="flex items-end gap-2">
                <label class="inline-flex items-center gap-2 text-sm font-medium">
                    <input type="checkbox" name="bank_accounts[__INDEX__][is_primary]" value="1">
                    Jadikan rekening utama
                </label>
            </div>
        </div>
        <div class="mt-4">
            <label class="block text-sm font-medium">Upload Foto Rekening</label>
            <input type="file" name="bank_accounts[__INDEX__][files][]" multiple class="mt-1 w-full text-sm" accept=".pdf,.jpg,.jpeg,.png,.webp">
        </div>
    </div>
</template>

<script>
(function () {
    const wrapper = document.getElementById('bankAccountsWrapper');
    const template = document.getElementById('bankAccountTemplate');
    const addBtn = document.getElementById('addBankAccountBtn');
    const emptyState = document.getElementById('bankAccountsEmptyState');
    let nextIndex = {{ is_countable($initialBankAccounts) ? count($initialBankAccounts) : 0 }};

    function syncBankName(card) {
        const select = card.querySelector('.bank-code-select');
        const hidden = card.querySelector('.bank-name-field');
        if (!select || !hidden) return;
        const label = select.options[select.selectedIndex]?.dataset?.label || '';
        hidden.value = label;
    }

    function bindCard(card) {
        const removeBtn = card.querySelector('.remove-bank-account');
        const select = card.querySelector('.bank-code-select');

        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                card.remove();
                if (wrapper.querySelectorAll('.bank-account-card').length === 0 && emptyState) {
                    emptyState.classList.remove('hidden');
                }
            });
        }

        if (select) {
            select.addEventListener('change', function () {
                syncBankName(card);
            });
            syncBankName(card);
        }
    }

    wrapper.querySelectorAll('.bank-account-card').forEach(bindCard);

    addBtn?.addEventListener('click', function () {
        if (!template || !wrapper) return;
        if (emptyState) emptyState.classList.add('hidden');
        const html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex++));
        const container = document.createElement('div');
        container.innerHTML = html.trim();
        const card = container.firstElementChild;
        if (!card) return;
        wrapper.appendChild(card);
        bindCard(card);
    });
})();
</script>
@endsection
