@extends('layouts.app')

@section('content')
@php
    $profileSource = $linkedEmployee ? 'employee' : ($linkedApplicantProfile ? 'applicant' : 'account_only');
    $personal = $linkedApplicantProfile?->personal_json ?? [];
    $roleLabels = \App\Models\User::roleLabels();
    $currentRole = $user->normalizedRole();
    $roleLabel = $roleLabels[$currentRole] ?? ucfirst($currentRole);
    $completion = $linkedApplicantProfile?->getCompletionProgress() ?? ['completed' => 0, 'total' => 6, 'percentage' => 0, 'sections' => []];
    $missingSections = collect($linkedApplicantProfile?->getMissingFields() ?? []);
    $canUseApplicationForm = Route::has('application-form.edit') && ($linkedEmployee || $linkedApplicantProfile || in_array($currentRole, [\App\Models\User::ROLE_APPLICANT, \App\Models\User::ROLE_CANDIDATE, \App\Models\User::ROLE_EMPLOYEE, \App\Models\User::ROLE_PROBATION], true));
@endphp

<div class="space-y-6">
    <div class="card p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="text-sm font-semibold uppercase tracking-[0.24em] text-muted">Profil Akun</div>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ $user->name }}</h1>
                <p class="mt-1 text-sm text-muted">Kelola akun login, password, dan ringkasan data pribadi Anda dari satu halaman.</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-900/60">
                    <div class="text-xs uppercase tracking-wide text-slate-500">Role</div>
                    <div class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ $roleLabel }}</div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-900/60">
                    <div class="text-xs uppercase tracking-wide text-slate-500">Email Login</div>
                    <div class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ $user->email }}</div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-900/60">
                    <div class="text-xs uppercase tracking-wide text-slate-500">Sumber Profil</div>
                    <div class="mt-1 font-semibold text-slate-800 dark:text-slate-100">
                        @if($profileSource === 'employee')
                            Employee + Applicant Profile
                        @elseif($profileSource === 'applicant')
                            Applicant Profile
                        @else
                            Akun Login Saja
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($canUseApplicationForm)
        <div class="rounded-3xl border {{ $missingSections->isNotEmpty() || ! $linkedApplicantProfile ? 'border-amber-200 bg-amber-50' : 'border-emerald-200 bg-emerald-50' }} p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.22em] {{ $missingSections->isNotEmpty() || ! $linkedApplicantProfile ? 'text-amber-700' : 'text-emerald-700' }}">Kelengkapan Data Rekrutmen</div>
                    <h2 class="mt-2 text-lg font-semibold {{ $missingSections->isNotEmpty() || ! $linkedApplicantProfile ? 'text-amber-950' : 'text-emerald-950' }}">
                        @if(! $linkedApplicantProfile)
                            Application Form lengkap belum pernah dibuat
                        @elseif($missingSections->isNotEmpty())
                            Masih ada data yang perlu dilengkapi
                        @else
                            Application Form sudah lengkap
                        @endif
                    </h2>
                    <p class="mt-2 max-w-3xl text-sm leading-6 {{ $missingSections->isNotEmpty() || ! $linkedApplicantProfile ? 'text-amber-900' : 'text-emerald-900' }}">
                        Gunakan Application Form terbaru untuk melengkapi data kandidat lama, kandidat yang sudah lolos administrasi, maupun karyawan/probation yang biodatanya belum mengikuti format terbaru.
                    </p>
                    @if($linkedApplicantProfile)
                        <div class="mt-4 inline-flex items-center gap-2 rounded-full border border-white/60 bg-white/80 px-3 py-2 text-xs font-semibold text-slate-700">
                            <span>{{ $completion['completed'] }}/{{ $completion['total'] }} bagian lengkap</span>
                            <span>&bull;</span>
                            <span>{{ $completion['percentage'] }}%</span>
                        </div>
                    @endif
                </div>
                <div class="flex w-full flex-col gap-3 lg:w-auto lg:min-w-[220px]">
                    <a href="{{ route('application-form.edit') }}" class="inline-flex items-center justify-center rounded-2xl {{ $missingSections->isNotEmpty() || ! $linkedApplicantProfile ? 'bg-amber-900 text-white hover:bg-amber-950' : 'bg-emerald-700 text-white hover:bg-emerald-800' }} px-4 py-3 text-sm font-semibold transition">{{ $linkedApplicantProfile ? 'Buka Application Form' : 'Mulai Lengkapi Data' }}</a>
                    @if($linkedEmployee && Route::has('employee-profile.show'))
                        <a href="{{ route('employee-profile.show') }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Lihat My Profile</a>
                    @endif
                </div>
            </div>

            @if($missingSections->isNotEmpty())
                <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($missingSections as $section)
                        <div class="rounded-2xl border border-amber-200 bg-white/80 p-4 text-sm text-amber-950">
                            <div class="font-semibold">{{ $section['label'] }}</div>
                            <div class="mt-2 text-amber-800">{{ collect($section['fields'] ?? [])->take(3)->implode(', ') }}{{ collect($section['fields'] ?? [])->count() > 3 ? ' dan lainnya' : '' }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="xl:col-span-1 space-y-6">
            <div class="card p-6">
                <div class="text-lg font-semibold text-slate-900 dark:text-slate-100">Ringkasan Profil</div>
                <div class="mt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300">
                    <div>
                        <div class="text-xs uppercase tracking-wide text-slate-500">Nama Lengkap</div>
                        <div class="mt-1 font-semibold text-slate-900 dark:text-slate-100">{{ $linkedEmployee?->full_name ?: data_get($personal, 'full_name', $user->name) }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wide text-slate-500">No. HP</div>
                        <div class="mt-1 font-semibold text-slate-900 dark:text-slate-100">{{ $linkedEmployee?->phone_number ?: data_get($personal, 'phone_number', data_get($personal, 'whatsapp', '-')) }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wide text-slate-500">NIK</div>
                        <div class="mt-1 font-semibold text-slate-900 dark:text-slate-100">{{ $linkedEmployee?->nik ?: data_get($personal, 'ktp_number', '-') }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wide text-slate-500">Status Relasi</div>
                        <div class="mt-1 font-semibold text-slate-900 dark:text-slate-100">
                            @if($linkedEmployee)
                                Terhubung ke employee #{{ $linkedEmployee->id }}
                            @elseif($linkedApplicantProfile)
                                Terhubung ke applicant profile #{{ $linkedApplicantProfile->id }}
                            @else
                                Belum punya relasi biodata lengkap
                            @endif
                        </div>
                    </div>
                </div>

                <div class="mt-5 space-y-3">
                    @if($canUseApplicationForm)
                        <a href="{{ route('application-form.edit') }}" class="btn w-full justify-center">Lengkapi Application Form</a>
                    @endif
                    @if($linkedEmployee && Route::has('employee-profile.show'))
                        <a href="{{ route('employee-profile.show') }}" class="btn-outline w-full justify-center">Buka Profil Pribadi</a>
                    @endif
                    @if($linkedEmployee && Route::has('employee-profile.edit'))
                        <a href="{{ route('employee-profile.edit') }}" class="btn-outline w-full justify-center">Perbarui Biodata Karyawan</a>
                    @endif
                </div>
            </div>
        </div>

        <div class="xl:col-span-2 space-y-6">
            <div class="card p-6">
                @include('profile.partials.update-profile-information-form')
            </div>

            <div class="card p-6">
                @include('profile.partials.update-password-form')
            </div>
        </div>
    </div>
</div>
@endsection
