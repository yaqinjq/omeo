@extends('layouts.app')

@section('content')
<div class="space-y-4">
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="text-lg font-semibold text-slate-900">Master Karyawan</div>
                <p class="text-sm text-slate-500">
                    Pusat data operasional HRD untuk profil, payroll, status kerja, dan riwayat utama karyawan.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50"
                    href="{{ route('employees.index', array_merge(request()->query(), ['view' => $viewMode === 'all' ? 'compact' : 'all'])) }}"
                >
                    {{ $viewMode === 'all' ? 'Kembali ke View Biasa' : 'View All' }}
                </a>

                {{-- TOMBOL TEMPLATE IMPORT DISEMBUNYIKAN (Sprint G sudah menggantikan) --}}

                <div class="relative inline-block" x-data="{ open: false }">
                    <button @click="open = !open"
                            @click.away="open = false"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm
                                   font-medium text-emerald-700 bg-emerald-50
                                   border border-emerald-300 rounded-lg
                                   hover:bg-emerald-100 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                             viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  stroke-width="2"
                                  d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Export
                        <svg class="w-3 h-3" fill="none" stroke="currentColor"
                             viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="open"
                         x-transition
                         class="absolute right-0 mt-1 w-52 bg-white rounded-xl
                                border border-gray-200 shadow-lg z-50 overflow-hidden">
                        {{-- Export All --}}
                        <a href="{{ route('employees.export', request()->query()) }}"
                           class="flex items-center gap-3 px-4 py-3 text-sm
                                  text-gray-700 hover:bg-gray-50 transition-colors">
                            <svg class="w-4 h-4 text-emerald-600" fill="none"
                                 stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      stroke-width="2"
                                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <div>
                                <p class="font-medium">Export All</p>
                                <p class="text-xs text-gray-400">Semua data karyawan</p>
                            </div>
                        </a>
                        <div class="border-t border-gray-100"></div>
                        {{-- Export For HRIS --}}
                        <a href="{{ route('employees.export.hris') }}"
                           class="flex items-center gap-3 px-4 py-3 text-sm
                                  text-gray-700 hover:bg-gray-50 transition-colors">
                            <svg class="w-4 h-4 text-blue-600" fill="none"
                                 stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      stroke-width="2"
                                      d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582 4-8 4"/>
                            </svg>
                            <div>
                                <p class="font-medium">Export For HRIS</p>
                                <p class="text-xs text-gray-400">Format import sistem HRIS</p>
                            </div>
                        </a>
                    </div>
                </div>

                <a
                    class="rounded-lg bg-slate-900 px-3 py-2 text-sm text-white hover:bg-slate-800"
                    href="{{ route('employees.create') }}"
                >
                    + Tambah Karyawan
                </a>
            </div>
        </div>
    </div>

    {{-- IMPORT KARYAWAN EXISTING DISEMBUNYIKAN (Sprint G sudah menggantikan dengan HR XLS Import) --}}
    {{--
    <div class="grid gap-4 xl:grid-cols-[1.4fr_1fr]">
        <form
            method="POST"
            action="{{ route('employees.import.store') }}"
            enctype="multipart/form-data"
            class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:grid-cols-[1fr_auto] lg:items-end"
        >
            @csrf

            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Import Karyawan Existing
                </label>

                <input
                    type="file"
                    name="file"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    accept=".csv,.txt,.xlsx"
                    required
                >

                <p class="mt-1 text-xs text-slate-500">
                    Mode aman: hanya membuat data baru. Konflik NIK/NOKOM/email akan dilewati dan dicatat pada ringkasan import.
                </p>

                @error('file')
                    <div class="mt-2 text-sm text-red-600">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700">
                Upload Import
            </button>
        </form>

        <div class="space-y-2 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 shadow-sm">
            <div class="font-semibold">Panduan Import Existing Employee</div>
            <div>Wajib diisi: <span class="font-medium">NIK, Nama Lengkap, Status Employment, Jabatan/Position</span>.</div>
            <div>Disarankan diisi: NOKOM, Email Private, No HP, Join Date, Departemen, Outlet, dan Gaji Saat Ini.</div>
            <div>Mapping aman yang dipakai:</div>
            <div>- Departemen: <code>department_code</code> lalu fallback <code>department_name</code></div>
            <div>- Posisi: <code>position_name</code></div>
            <div>- Outlet: <code>outlet_external_id</code> lalu fallback <code>outlet_name</code></div>
            <div>- Akun login: hanya akun existing yang cocok via email atau NIK/applicant profile yang akan dihubungkan otomatis.</div>
            <div>- Duplicate: baris dengan NIK, NOKOM, atau email private yang sudah ada akan di-skip, bukan overwrite.</div>
        </div>
    </div>
    --}}

    @if(session('employee_import_summary'))
        <div class="space-y-2 rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900 shadow-sm">
            <div>Total baris: {{ data_get(session('employee_import_summary'), 'total_rows', 0) }}</div>
            <div>Dibuat: {{ data_get(session('employee_import_summary'), 'created', 0) }}</div>
            <div>Akun existing terhubung: {{ data_get(session('employee_import_summary'), 'linked_users', 0) }}</div>
            <div>Dilewati conflict: {{ data_get(session('employee_import_summary'), 'duplicates', 0) }}</div>
            <div>Gagal validasi: {{ data_get(session('employee_import_summary'), 'failed', 0) }}</div>

            @php
                $employeeImportWarnings = collect(
                    data_get(session('employee_import_summary'), 'warnings', [])
                )->take(3);
            @endphp

            @if($employeeImportWarnings->isNotEmpty())
                <div class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900">
                    @foreach($employeeImportWarnings as $warning)
                        <div>{{ $warning }}</div>
                    @endforeach
                </div>
            @endif

            @php
                $employeeImportErrors = collect(
                    data_get(session('employee_import_summary'), 'row_errors', [])
                )->take(5);
            @endphp

            @if($employeeImportErrors->isNotEmpty())
                <div class="rounded-lg border border-blue-300 bg-white/70 p-3 text-xs text-slate-700">
                    @foreach($employeeImportErrors as $rowError)
                        <div>
                            Baris {{ data_get($rowError, 'row', '?') }}:
                            {{ data_get($rowError, 'message', '-') }}
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    <form method="GET" action="{{ route('employees.index') }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <input type="hidden" name="view" value="{{ $viewMode }}">

        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-5">
            <div class="xl:col-span-2">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Cari</label>
                <input
                    type="text"
                    name="q"
                    value="{{ $filterOptions['q'] ?? '' }}"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    placeholder="Nama, NIK, NOKOM, email, telepon"
                >
            </div>

            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Departemen</label>
                <select name="department_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Semua departemen</option>
                    @foreach($departments as $department)
                        <option
                            value="{{ $department->id }}"
                            {{ (string) ($filterOptions['department_id'] ?? '') === (string) $department->id ? 'selected' : '' }}
                        >
                            {{ $department->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Jabatan</label>
                <select name="position_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Semua jabatan</option>
                    @foreach($positions as $position)
                        <option
                            value="{{ $position->id }}"
                            {{ (string) ($filterOptions['position_id'] ?? '') === (string) $position->id ? 'selected' : '' }}
                        >
                            {{ $position->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
                <select name="status" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Semua status</option>
                    @foreach(['probation' => 'Probation', 'contract' => 'Kontrak', 'permanent' => 'Tetap', 'resigned' => 'Resigned'] as $statusValue => $statusLabel)
                        <option
                            value="{{ $statusValue }}"
                            {{ ($filterOptions['status'] ?? '') === $statusValue ? 'selected' : '' }}
                        >
                            {{ $statusLabel }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Brand</label>
                <select name="brand_name" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Semua brand</option>
                    @foreach($brandOptions as $brandOption)
                        <option
                            value="{{ $brandOption }}"
                            {{ ($filterOptions['brand_name'] ?? '') === $brandOption ? 'selected' : '' }}
                        >
                            {{ $brandOption }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Outlet</label>
                <select name="outlet_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Semua outlet</option>
                    @foreach($outlets as $outlet)
                        <option
                            value="{{ $outlet->id }}"
                            {{ (string) ($filterOptions['outlet_id'] ?? '') === (string) $outlet->id ? 'selected' : '' }}
                        >
                            {{ $outlet->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Join Date Dari</label>
                <input
                    type="date"
                    name="join_date_from"
                    value="{{ $filterOptions['join_date_from'] ?? '' }}"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
            </div>

            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Join Date Sampai</label>
                <input
                    type="date"
                    name="join_date_to"
                    value="{{ $filterOptions['join_date_to'] ?? '' }}"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
            </div>

            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Asal Sekolah Terakhir</label>
                <input
                    type="text"
                    name="latest_school"
                    value="{{ $filterOptions['latest_school'] ?? '' }}"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    placeholder="Contoh: SMK 1 / Universitas"
                >
            </div>

            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Masa Kerja</label>
                <select name="tenure" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Semua masa kerja</option>
                    @foreach($tenureOptions as $tenureValue => $tenureLabel)
                        <option
                            value="{{ $tenureValue }}"
                            {{ ($filterOptions['tenure'] ?? '') === $tenureValue ? 'selected' : '' }}
                        >
                            {{ $tenureLabel }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm text-white hover:bg-slate-800">
                Terapkan Filter
            </button>

            <a
                href="{{ route('employees.index', ['view' => $viewMode]) }}"
                class="rounded-lg border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50"
            >
                Reset
            </a>
        </div>
    </form>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
            <div>
                <div class="text-sm font-semibold text-slate-900">
                    {{ $viewMode === 'all' ? 'View All Master Karyawan' : 'List Master Karyawan' }}
                </div>
                <div class="text-xs text-slate-500">{{ $employees->total() }} data ditemukan</div>
            </div>
            <div class="text-xs text-slate-500">
                Mode: {{ $viewMode === 'all' ? 'View All' : 'Compact' }}
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-3 py-3 text-left font-semibold">Nama</th>
                        <th class="px-3 py-3 text-left font-semibold">NIK / NOKOM</th>
                        <th class="px-3 py-3 text-left font-semibold">Kontak</th>
                        <th class="px-3 py-3 text-left font-semibold">Departemen / Jabatan</th>
                        <th class="px-3 py-3 text-left font-semibold">Status</th>
                        <th class="px-3 py-3 text-left font-semibold">Join Date</th>

                        @if($viewMode === 'all')
                            <th class="px-3 py-3 text-left font-semibold">Brand / Outlet</th>
                            <th class="px-3 py-3 text-left font-semibold">Payroll</th>
                            <th class="px-3 py-3 text-left font-semibold">Gaji Saat Ini</th>
                            <th class="px-3 py-3 text-left font-semibold">Pendidikan Terakhir</th>
                        @endif

                        <th class="px-3 py-3 text-left font-semibold">Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($employees as $employee)
                        @php
                            $primaryBank = null;
                            if ($hasBankAccountsTable) {
                                $primaryBank = $employee->bankAccounts->firstWhere('is_primary', true) ?: $employee->bankAccounts->first();
                            }

                            $educationSource = [];
                            if ($hasApplicantProfilesTable && $employee->user && $employee->user->applicantProfile) {
                                $educationSource = $employee->user->applicantProfile->educations ?? [];
                            }

                            $educationRows = collect($educationSource)
                                ->filter(function ($row) {
                                    return is_array($row) && trim((string) ($row['school'] ?? '')) !== '';
                                })
                                ->sortByDesc(function ($row) {
                                    return (int) ($row['year_out'] ?? ($row['year_in'] ?? 0));
                                })
                                ->values();

                            $latestEducation = $educationRows->first();

                            if ($latestEducation) {
                                $latestSchool = trim((string) ($latestEducation['school'] ?? '-'));
                                $latestMajor = trim((string) ($latestEducation['major'] ?? ''));
                                if ($latestMajor !== '') {
                                    $latestSchool .= ' - ' . $latestMajor;
                                }
                            } else {
                                $latestSchool = '-';
                            }

                            $statusEmployment = $employee->status_employment ?: '-';
                            $joinDateText = $employee->join_date ? optional($employee->join_date)->format('d M Y') : '-';
                            $joinDateHuman = $employee->join_date ? $employee->join_date->diffForHumans(null, true) : '-';
                            $salaryText = $employee->current_salary ? 'Rp ' . number_format((float) $employee->current_salary, 0, ',', '.') : '-';
                        @endphp

                        <tr class="border-t border-slate-100 align-top">
                            <td class="px-3 py-3">
                                <div class="font-semibold text-slate-900">{{ $employee->full_name }}</div>
                                <div class="text-xs text-slate-500">
                                    {{ $employee->user?->email ?: ($employee->email_private ?: '-') }}
                                </div>
                            </td>

                            <td class="px-3 py-3 text-slate-600">
                                <div>{{ $employee->nik_display ?: ($employee->employee_number ?: '-') }}</div>
                                <div class="text-xs">{{ ($employee->employee_number ?: $employee->nokom) ?: '-' }}</div>
                            </td>

                            <td class="px-3 py-3 text-slate-600">
                                <div>{{ $employee->phone_number ?: '-' }}</div>
                                <div class="text-xs">{{ $employee->email_private ?: '-' }}</div>
                            </td>

                            <td class="px-3 py-3 text-slate-600">
                                <div>{{ $employee->department?->name ?: '-' }}</div>
                                <div class="text-xs">{{ $employee->position?->name ?: ($employee->jabatan ?: '-') }}</div>
                            </td>

                            <td class="px-3 py-3">
                                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
                                    {{ ucfirst($statusEmployment) }}
                                </span>
                            </td>

                            <td class="px-3 py-3 text-slate-600">
                                <div>{{ $joinDateText }}</div>
                                <div class="text-xs">{{ $joinDateHuman }}</div>
                            </td>

                            @if($viewMode === 'all')
                                <td class="px-3 py-3 text-slate-600">
                                    <div>{{ $employee->outlet?->brand_name ?: '-' }}</div>
                                    <div class="text-xs">{{ $employee->outlet?->name ?: '-' }}</div>
                                </td>

                                <td class="px-3 py-3 text-slate-600">
                                    <div>{{ $primaryBank?->bank_name ?: 'Belum ada rekening' }}</div>
                                    <div class="text-xs">{{ $primaryBank?->account_number ?: 'Payroll belum lengkap' }}</div>
                                </td>

                                <td class="px-3 py-3 text-slate-600">{{ $salaryText }}</td>

                                <td class="px-3 py-3 text-slate-600">{{ $latestSchool }}</td>
                            @endif

                            <td class="px-3 py-3">
                                <div class="flex flex-wrap gap-2 text-xs">
                                    <a
                                        class="rounded-lg border border-slate-300 px-2.5 py-1.5 hover:bg-slate-50"
                                        href="{{ route('employees.show', $employee) }}"
                                    >
                                        Profile
                                    </a>

                                    <a
                                        class="rounded-lg border border-slate-300 px-2.5 py-1.5 hover:bg-slate-50"
                                        href="{{ route('employees.edit', $employee) }}"
                                    >
                                        Edit
                                    </a>

                                    <form
                                        method="POST"
                                        action="{{ route('employees.destroy', $employee) }}"
                                        onsubmit="return confirm('Hapus karyawan ini?')"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-lg border border-red-300 px-2.5 py-1.5 text-red-600 hover:bg-red-50">
                                            Hapus
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $viewMode === 'all' ? 11 : 7 }}" class="px-3 py-10 text-center text-sm text-slate-500">
                                Belum ada data karyawan yang sesuai filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 px-4 py-3">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-4">
                    <span class="text-sm text-gray-500">
                        Menampilkan {{ $employees->firstItem() ?? 0 }}–{{ $employees->lastItem() ?? 0 }}
                        dari {{ $employees->total() }} karyawan
                    </span>
                    <x-per-page-selector :per-page="(int) request('per_page', 15)" />
                </div>
                <div>{{ $employees->appends(request()->query())->links() }}</div>
            </div>
        </div>
    </div>
</div>
@endsection