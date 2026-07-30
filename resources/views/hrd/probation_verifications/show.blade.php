@extends('layouts.app')

@section('content')
@php
    $oldPersonal = $oldApplicantProfile['personal'] ?? [];
    $oldAddress = $oldApplicantProfile['address'] ?? [];
    $requestApplicantProfile = $requestApplicantProfile ?? [];
    $requestPersonal = $requestApplicantProfile['personal'] ?? [];
    $requestAddress = $requestApplicantProfile['address'] ?? [];
    $sectionLabels = [
        'family' => 'Data Keluarga',
        'education' => 'Riwayat Pendidikan',
        'language' => 'Kemampuan Bahasa',
        'course' => 'Kursus & Pelatihan',
        'work' => 'Riwayat Pekerjaan',
        'organization' => 'Organisasi',
        'reference_contacts' => 'Kontak Referensi',
        'medical' => 'Riwayat Medis',
        'social' => 'Social Media',
    ];
@endphp
<div class="space-y-6">
    <div class="card p-6">
        <h1 class="text-xl font-bold">Review Verifikasi Data Karyawan #{{ $changeRequest->id }}</h1>
        <p class="mt-1 text-sm text-muted">{{ $changeRequest->user->name ?? '-' }} ({{ $changeRequest->user->email ?? '-' }})</p>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="card p-6">
            <h2 class="mb-3 font-semibold">Ringkasan Profil Saat Ini</h2>
            <div class="space-y-2 text-sm">
                <div>Nama: <strong>{{ $oldProfile['full_name'] ?? '-' }}</strong></div>
                <div>Email kontak: <strong>{{ $oldProfile['email_private'] ?? '-' }}</strong></div>
                <div>No. HP: <strong>{{ $oldProfile['phone_number'] ?? '-' }}</strong></div>
                <div>Status kerja: <strong>{{ $oldProfile['status_employment'] ?? '-' }}</strong></div>
                <div>Departemen: <strong>{{ $oldProfile['department'] ?? '-' }}</strong></div>
                <div>Jabatan: <strong>{{ $oldProfile['position'] ?? '-' }}</strong></div>
            </div>
        </div>
        <div class="card p-6">
            <h2 class="mb-3 font-semibold">Ringkasan Perubahan Request</h2>
            <div class="space-y-2 text-sm">
                <div>Nama: <strong>{{ $requestProfile['full_name'] ?? ($requestPersonal['full_name'] ?? '-') }}</strong></div>
                <div>Email kontak: <strong>{{ $requestProfile['email_private'] ?? '-' }}</strong></div>
                <div>No. HP: <strong>{{ $requestProfile['phone_number'] ?? '-' }}</strong></div>
                <div>NIK: <strong>{{ $requestPersonal['ktp_number'] ?? '-' }}</strong></div>
                <div>Tempat/Tgl Lahir: <strong>{{ ($requestPersonal['place_of_birth'] ?? '-') }} / {{ ($requestPersonal['date_of_birth'] ?? '-') }}</strong></div>
                <div>Status Nikah: <strong>{{ $requestPersonal['marital_status'] ?? '-' }}</strong></div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="card p-6">
            <h2 class="mb-4 font-semibold">Applicant Profile Saat Ini</h2>
            <div class="grid grid-cols-1 gap-3 text-sm md:grid-cols-2">
                <div><span class="text-muted">NIK</span><div class="font-semibold">{{ $oldPersonal['ktp_number'] ?? '-' }}</div></div>
                <div><span class="text-muted">Tempat lahir</span><div class="font-semibold">{{ $oldPersonal['place_of_birth'] ?? '-' }}</div></div>
                <div><span class="text-muted">Tanggal lahir</span><div class="font-semibold">{{ $oldPersonal['date_of_birth'] ?? '-' }}</div></div>
                <div><span class="text-muted">Jenis kelamin</span><div class="font-semibold">{{ $oldPersonal['gender'] ?? '-' }}</div></div>
                <div><span class="text-muted">Agama</span><div class="font-semibold">{{ $oldPersonal['religion'] ?? '-' }}</div></div>
                <div><span class="text-muted">WhatsApp</span><div class="font-semibold">{{ $oldPersonal['whatsapp'] ?? '-' }}</div></div>
                <div class="md:col-span-2"><span class="text-muted">Alamat KTP</span><div class="font-semibold">{{ $oldAddress['ktp_address'] ?? '-' }}</div></div>
                <div class="md:col-span-2"><span class="text-muted">Alamat Domisili</span><div class="font-semibold">{{ $oldAddress['domicile_address'] ?? '-' }}</div></div>
            </div>
        </div>
        <div class="card p-6">
            <h2 class="mb-4 font-semibold">Applicant Profile Request</h2>
            <div class="grid grid-cols-1 gap-3 text-sm md:grid-cols-2">
                <div><span class="text-muted">NIK</span><div class="font-semibold">{{ $requestPersonal['ktp_number'] ?? '-' }}</div></div>
                <div><span class="text-muted">Tempat lahir</span><div class="font-semibold">{{ $requestPersonal['place_of_birth'] ?? '-' }}</div></div>
                <div><span class="text-muted">Tanggal lahir</span><div class="font-semibold">{{ $requestPersonal['date_of_birth'] ?? '-' }}</div></div>
                <div><span class="text-muted">Jenis kelamin</span><div class="font-semibold">{{ $requestPersonal['gender'] ?? '-' }}</div></div>
                <div><span class="text-muted">Agama</span><div class="font-semibold">{{ $requestPersonal['religion'] ?? '-' }}</div></div>
                <div><span class="text-muted">WhatsApp</span><div class="font-semibold">{{ $requestPersonal['whatsapp'] ?? '-' }}</div></div>
                <div class="md:col-span-2"><span class="text-muted">Alamat KTP</span><div class="font-semibold">{{ $requestAddress['ktp_address'] ?? '-' }}</div></div>
                <div class="md:col-span-2"><span class="text-muted">Alamat Domisili</span><div class="font-semibold">{{ $requestAddress['domicile_address'] ?? '-' }}</div></div>
            </div>
            <div class="mt-4 space-y-2 text-sm">
                <div>Pas Foto:
                    @if(!empty($requestPersonal['photo_path']))
                        <a href="{{ asset('storage/' . ltrim($requestPersonal['photo_path'], '/')) }}" class="text-brand underline" target="_blank">Lihat</a>
                    @else
                        <span>-</span>
                    @endif
                </div>
                <div>Scan KTP:
                    @if(!empty($requestPersonal['ktp_path']))
                        <a href="{{ asset('storage/' . ltrim($requestPersonal['ktp_path'], '/')) }}" class="text-brand underline" target="_blank">Lihat</a>
                    @else
                        <span>-</span>
                    @endif
                </div>
                <div>CV:
                    @if(!empty($requestPersonal['cv_path']))
                        <a href="{{ asset('storage/' . ltrim($requestPersonal['cv_path'], '/')) }}" class="text-brand underline" target="_blank">Lihat</a>
                    @else
                        <span>-</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="card p-6">
            <h2 class="mb-3 font-semibold">Payroll Saat Ini</h2>
            <div class="space-y-2 text-sm">
                <div>SIM: <strong>{{ $oldPayroll['sim_number'] ?? '-' }}</strong></div>
                <div>NPWP: <strong>{{ $oldPayroll['npwp_number'] ?? '-' }}</strong></div>
                <div>BPJS Kesehatan: <strong>{{ $oldPayroll['bpjs_kes_number'] ?? '-' }}</strong></div>
                <div>BPJS TK: <strong>{{ $oldPayroll['bpjs_tk_number'] ?? '-' }}</strong></div>
                <div>Passport: <strong>{{ $oldPayroll['passport_number'] ?? '-' }}</strong></div>
                <div>KK: <strong>{{ $oldPayroll['kk_number'] ?? '-' }}</strong></div>
                <div>Verified At: <strong>{{ !empty($oldPayroll['payroll_verified_at']) ? \Carbon\Carbon::parse($oldPayroll['payroll_verified_at'])->format('d-m-Y H:i') : '-' }}</strong></div>
            </div>
        </div>
        <div class="card p-6">
            <h2 class="mb-3 font-semibold">Payroll Request</h2>
            <div class="space-y-2 text-sm">
                <div>SIM: <strong>{{ $requestPayroll['sim_number'] ?? '-' }}</strong></div>
                <div>NPWP: <strong>{{ $requestPayroll['npwp_number'] ?? '-' }}</strong></div>
                <div>BPJS Kesehatan: <strong>{{ $requestPayroll['bpjs_kes_number'] ?? '-' }}</strong></div>
                <div>BPJS TK: <strong>{{ $requestPayroll['bpjs_tk_number'] ?? '-' }}</strong></div>
                <div>Passport: <strong>{{ $requestPayroll['passport_number'] ?? '-' }}</strong></div>
                <div>KK: <strong>{{ $requestPayroll['kk_number'] ?? '-' }}</strong></div>
            </div>
            <div class="mt-4 space-y-2 text-sm">
                @foreach(['SIM' => 'sim_file', 'NPWP' => 'npwp_file', 'BPJS Kesehatan' => 'bpjs_kes_file', 'BPJS TK' => 'bpjs_tk_file', 'Passport' => 'passport_file', 'KK' => 'kk_file'] as $label => $key)
                    <div>
                        File {{ $label }}:
                        @if(!empty($requestPayrollAttachments[$key]))
                            <a href="{{ asset('storage/' . ltrim($requestPayrollAttachments[$key], '/')) }}" class="text-brand underline" target="_blank">Lihat</a>
                        @else
                            <span>-</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card p-6">
        <h2 class="mb-4 font-semibold">Perbandingan Rekening Bank</h2>
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <div>
                <h3 class="mb-3 font-medium">Rekening Saat Ini</h3>
                <div class="space-y-3">
                    @forelse($oldBankAccounts as $account)
                        <div class="rounded-xl border p-3 text-sm">
                            <div><strong>{{ $account['bank_name'] ?: '-' }}</strong> @if($account['is_primary'])<span class="text-emerald-600">(Utama)</span>@endif</div>
                            <div>No. Rekening: {{ $account['account_number'] ?: '-' }}</div>
                            <div>Atas Nama: {{ $account['account_holder_name'] ?: '-' }}</div>
                        </div>
                    @empty
                        <div class="text-sm text-slate-500">Belum ada rekening aktif.</div>
                    @endforelse
                </div>
            </div>
            <div>
                <h3 class="mb-3 font-medium">Rekening Request</h3>
                <div class="space-y-3">
                    @forelse($requestBankAccounts as $account)
                        <div class="rounded-xl border p-3 text-sm">
                            <div><strong>{{ $account['bank_name'] ?: '-' }}</strong> @if(!empty($account['is_primary']))<span class="text-emerald-600">(Utama)</span>@endif</div>
                            <div>No. Rekening: {{ $account['account_number'] ?: '-' }}</div>
                            <div>Atas Nama: {{ $account['account_holder_name'] ?: '-' }}</div>
                            <div class="mt-2 space-y-1">
                                @forelse((array) ($account['file_paths'] ?? []) as $path)
                                    <a href="{{ asset('storage/' . ltrim($path, '/')) }}" target="_blank" class="text-brand underline block">{{ basename($path) }}</a>
                                @empty
                                    <span class="text-slate-500">Belum ada file.</span>
                                @endforelse
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-slate-500">Request ini tidak mengubah rekening bank.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="card p-6">
        <h2 class="mb-4 font-semibold">Detail Section Application Form</h2>
        <div class="space-y-5">
            @foreach($sectionLabels as $sectionKey => $sectionLabel)
                <div class="rounded-2xl border border-slate-200 p-4">
                    <div class="mb-3 font-semibold">{{ $sectionLabel }}</div>
                    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                        <div>
                            <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Data Saat Ini</div>
                            <pre class="overflow-x-auto rounded-xl bg-slate-50 p-3 text-xs text-slate-700">{{ json_encode(data_get($oldApplicantProfile, $sectionKey, []), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                        <div>
                            <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Request Baru</div>
                            <pre class="overflow-x-auto rounded-xl bg-amber-50 p-3 text-xs text-slate-700">{{ json_encode(data_get($requestApplicantProfile, $sectionKey, []), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="card p-6">
        <div class="mb-4 text-sm">Status: <span class="badge">{{ strtoupper($changeRequest->status) }}</span></div>

        @if($changeRequest->status === 'pending')
            <div class="flex flex-col items-start gap-3 md:flex-row">
                <form method="POST" action="{{ route('hrd.probation-verifications.approve', $changeRequest) }}">
                    @csrf
                    <button type="submit" class="btn">Approve</button>
                </form>

                <form method="POST" action="{{ route('hrd.probation-verifications.reject', $changeRequest) }}" class="w-full md:w-auto">
                    @csrf
                    <textarea name="review_note" rows="2" class="w-full rounded-lg border px-3 py-2 md:w-96" placeholder="Alasan reject (wajib)"></textarea>
                    <button type="submit" class="btn-danger mt-2">Reject</button>
                </form>
            </div>
        @else
            <div class="text-sm text-muted">Sudah direview oleh {{ $changeRequest->reviewer->name ?? '-' }} pada {{ optional($changeRequest->reviewed_at)->format('d-m-Y H:i') ?? '-' }}.</div>
            @if($changeRequest->status === 'rejected')
                <div class="mt-2 text-sm text-red-600">Alasan reject: {{ $changeRequest->review_note ?: '-' }}</div>
            @endif
        @endif
    </div>
</div>
@endsection
