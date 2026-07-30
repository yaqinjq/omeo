@extends('layouts.app')

@section('content')
@php
    use Illuminate\Support\Str;

    $profile = $snapshot['profile'] ?? [];
    $employee = $snapshot['employee'] ?? null;

    $activeBpjs = $bpjsAssignments->first(fn ($a) => is_null($a->end_date));
    $linkedAccount = $snapshot['linked_user'] ?? null;
    $payroll = $snapshot['payroll'] ?? [];
    $bankAccounts = $snapshot['bank_accounts'] ?? [];
    $appraisals = $snapshot['appraisals'] ?? collect();
    $trainingSummary = $snapshot['training_summary'] ?? ['total' => 0, 'completed' => 0, 'in_progress' => 0, 'latest' => collect(), 'programs' => collect(), 'events' => collect()];
    $salaryHistories = $snapshot['salary_histories'] ?? collect();
    $profileChangeRequests = $snapshot['profile_change_requests'] ?? collect();
    $editable = $snapshot['editable_form'] ?? [];
    $sections = $snapshot['candidate_sections'] ?? [];
    $documents = $snapshot['documents'] ?? [];
    $additionalDocuments = $snapshot['additional_documents'] ?? [];
    $meta = $snapshot['meta'] ?? [];

    $personal = $editable['personal'] ?? [];
    $address = $editable['address'] ?? [];
    $families = $sections['families'] ?? [];
    $educations = $sections['educations'] ?? [];
    $languages = $sections['languages'] ?? [];
    $courses = $sections['courses'] ?? [];
    $workExperiences = $sections['work_experiences'] ?? [];
    $referenceContacts = $sections['reference_contacts'] ?? [];
    $organizations = $sections['organizations'] ?? [];
    $medicalHistories = $sections['medical_histories'] ?? [];
    $socialMedias = $sections['social_medias'] ?? [];

    $formatDate = static fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('d M Y') : '-';
    $formatDateTime = static fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('d M Y H:i') : '-';
    $formatCurrency = static fn ($value) => $value !== null && $value !== '' ? 'Rp ' . number_format((float) $value, 0, ',', '.') : '-';
    $documentUrl = static fn (array $document) => !empty($document['path']) ? asset('storage/' . ltrim((string) $document['path'], '/')) : null;

    $resolvedName = trim((string) ($profile['full_name'] ?? ($personal['full_name'] ?? 'Profil Karyawan')));
    $resolvedNik = trim((string) ($profile['nik'] ?? ($personal['ktp_number'] ?? '-')));
    $resolvedLoginEmail = trim((string) ($profile['email_login'] ?? ($linkedAccount?->email ?? '-')));
    $resolvedContactEmail = trim((string) ($profile['email_private'] ?? ($personal['email'] ?? '-')));
    $resolvedPhone = trim((string) ($profile['phone_number'] ?? ($personal['whatsapp'] ?? '-')));
    $age = !empty($personal['date_of_birth']) ? \Illuminate\Support\Carbon::parse($personal['date_of_birth'])->age : null;

    $photoDocument = $documents['photo'] ?? ['exists' => false, 'is_image' => false, 'path' => null, 'extension' => '-'];
    $ktpDocument = $documents['ktp'] ?? ['exists' => false, 'is_image' => false, 'path' => null, 'extension' => '-'];
    $cvDocument = $documents['cv'] ?? ['exists' => false, 'is_image' => false, 'path' => null, 'extension' => '-'];

    $summaryCards = [
        ['label' => 'Status Kerja', 'value' => strtoupper((string) ($profile['status_employment'] ?? '-'))],
        ['label' => 'Departemen', 'value' => $profile['department'] ?? '-'],
        ['label' => 'Jabatan', 'value' => $profile['position'] ?? '-'],
        ['label' => 'Outlet', 'value' => $profile['outlet'] ?? '-'],
    ];

    $basicFacts = [
        'Nama Lengkap' => $resolvedName,
        'NIK' => $resolvedNik,
        'Email Login' => $resolvedLoginEmail,
        'Email Kontak' => $resolvedContactEmail,
        'No. HP' => $resolvedPhone,
        'WhatsApp' => $personal['whatsapp'] ?? '-',
        'Tempat Lahir' => $personal['place_of_birth'] ?? '-',
        'Tanggal Lahir' => $formatDate($personal['date_of_birth'] ?? null),
        'Jam Lahir' => $personal['time_of_birth'] ?? '-',
        'Jenis Kelamin' => $personal['gender'] ?? '-',
        'Agama' => $personal['religion'] ?? '-',
        'Golongan Darah' => $personal['blood_type'] ?? '-',
        'Status Pernikahan' => $personal['marital_status'] ?? '-',
        'Tanggal Menikah' => $formatDate($personal['marriage_date'] ?? null),
        'Alamat KTP' => $address['ktp_address'] ?? '-',
        'Provinsi KTP' => $address['ktp_province'] ?? '-',
        'Kota/Kabupaten KTP' => $address['ktp_city'] ?? '-',
        'Alamat Domisili' => $address['domicile_address'] ?? '-',
    ];
@endphp

<div class="min-h-[calc(100vh-140px)] rounded-3xl bg-gray-100 p-4 md:p-6 dark:bg-gray-900" x-data="{ tab: 'biodata' }">
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
        <aside class="space-y-6 xl:col-span-4">
            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800 text-white shadow-sm">
                <div class="p-6">
                    <div class="relative mx-auto h-36 w-36 overflow-hidden rounded-full border-4 border-white/10 bg-white/10 shadow-inner">
                        @if($photoDocument['exists'] && $photoDocument['is_image'])
                            <img src="{{ $documentUrl($photoDocument) }}" alt="Foto {{ $resolvedName }}" class="h-full w-full object-cover">
                        @else
                            <div class="flex h-full items-center justify-center text-4xl font-bold text-white/80">{{ Str::upper(Str::substr($resolvedName !== '' ? $resolvedName : 'K', 0, 1)) }}</div>
                        @endif
                        <div class="absolute bottom-2 right-2 h-8 w-8 rounded-full border-4 border-slate-900 bg-emerald-500"></div>
                    </div>

                    <div class="mt-6 text-center">
                        <div class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-300">Profile 360</div>
                        <h1 class="mt-3 text-3xl font-bold tracking-tight text-white">{{ $resolvedName !== '' ? $resolvedName : '-' }}</h1>
                        <p class="mt-2 text-sm text-slate-300">{{ $age !== null ? $age . ' tahun' : 'Employee detail untuk HRD' }}</p>
                    </div>

                    <div class="mt-5 flex flex-wrap justify-center gap-2 text-xs font-semibold">
                        <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1.5">{{ strtoupper((string) ($profile['status_employment'] ?? '-')) }}</span>
                        <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1.5">{{ $profile['department'] ?? 'Departemen belum diatur' }}</span>
                        <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1.5">{{ $profile['position'] ?? 'Jabatan belum diatur' }}</span>
                    </div>

                    <div class="mt-6 grid grid-cols-1 gap-3 text-sm text-slate-200">
                        <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-300">NIK</div>
                            <div class="mt-2 font-semibold text-white">{{ $resolvedNik !== '' ? $resolvedNik : '-' }}</div>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-300">Email Kontak</div>
                            <div class="mt-2 break-all font-semibold text-white">{{ $resolvedContactEmail !== '' ? $resolvedContactEmail : '-' }}</div>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-300">No. HP</div>
                            <div class="mt-2 font-semibold text-white">{{ $resolvedPhone !== '' ? $resolvedPhone : '-' }}</div>
                        </div>
                    </div>

                    <div class="mt-5 rounded-2xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-4 text-center">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-emerald-200">Gaji Saat Ini</div>
                        <div class="mt-2 text-2xl font-extrabold text-white">{{ $formatCurrency($profile['current_salary'] ?? null) }}</div>
                    </div>

                    <div class="mt-4 rounded-2xl border border-white/10 bg-white/5 px-4 py-4 text-sm text-slate-200">
                        <div class="font-semibold text-white">Status Akun & Sumber Data</div>
                        <div class="mt-2">Akun login: {{ $linkedAccount?->email ? 'Terhubung' : 'Belum terhubung' }}</div>
                        <div class="mt-1 text-xs text-slate-300">
                            {{ !empty($meta['uses_applicant_profile']) ? 'Applicant profile kandidat dipakai sebagai fallback utama agar detail karyawan setara dengan candidate profile.' : 'Belum ada applicant profile yang bisa dipakai sebagai fallback, jadi halaman hanya menampilkan data master employee yang tersedia.' }}
                        </div>
                    </div>

                    <div class="mt-5 space-y-3">
                        <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-center text-sm font-semibold text-white">NOKOM: {{ $profile['employee_number'] ?? '-' }}</div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-center text-sm font-semibold text-white">Join Date: {{ $formatDate($profile['join_date'] ?? null) }}</div>
                        @if($activeBpjs)
                        <div class="rounded-2xl border border-blue-400/30 bg-blue-500/15 px-4 py-3 text-center">
                            <div class="text-[10px] font-semibold uppercase tracking-[0.18em] text-blue-200">PT BPJS Aktif</div>
                            <div class="mt-1 text-xs font-bold text-white">{{ $activeBpjs->legalEntity?->short_name ?? $activeBpjs->legalEntity?->name ?? '-' }}</div>
                            <div class="mt-0.5 text-[10px] text-blue-200">Sejak {{ \Carbon\Carbon::parse($activeBpjs->effective_date)->format('d M Y') }}</div>
                        </div>
                        @else
                        <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-center">
                            <div class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">PT BPJS</div>
                            <div class="mt-1 text-xs text-slate-400">Belum diset</div>
                        </div>
                        @endif
                        @php $activeAssignSidebar = ($assignments ?? collect())->first(fn($a) => is_null($a->end_date)); @endphp
                        @if($activeAssignSidebar)
                        <div class="rounded-2xl border border-indigo-400/30 bg-indigo-500/15 px-4 py-3 text-center">
                            <div class="text-[10px] font-semibold uppercase tracking-[0.18em] text-indigo-200">Outlet Tugas</div>
                            <div class="mt-1 text-xs font-bold text-white">{{ $activeAssignSidebar->outlet?->name ?? 'Belum diset' }}</div>
                        </div>
                        @if($activeAssignSidebar->payroll_outlet_id && $activeAssignSidebar->payroll_outlet_id !== $activeAssignSidebar->outlet_id)
                        <div class="rounded-2xl border border-violet-400/30 bg-violet-500/15 px-4 py-3 text-center">
                            <div class="text-[10px] font-semibold uppercase tracking-[0.18em] text-violet-200">Cost Center</div>
                            <div class="mt-1 text-xs font-bold text-white">{{ $activeAssignSidebar->payrollOutlet?->name ?? '-' }}</div>
                        </div>
                        @endif
                        @endif
                        @if(($profile['status_employment'] ?? '') === 'probation' && auth()->user()?->hasRole(['admin','hrd']))
                        <form method="POST" action="{{ route('employees.promote', $employee) }}"
                              onsubmit="return confirm('Promosikan {{ $resolvedName }} menjadi Karyawan Tetap?')">
                            @csrf @method('PATCH')
                            <button type="submit"
                                    class="w-full block rounded-2xl bg-emerald-500 px-4 py-3
                                           text-center text-sm font-semibold text-white
                                           transition hover:bg-emerald-600">
                                Promosikan → Karyawan Tetap
                            </button>
                        </form>
                        @endif
                        <a href="{{ route('employees.edit', $employee) }}" class="block rounded-2xl bg-white px-4 py-3 text-center text-sm font-semibold text-slate-900 transition hover:bg-slate-100">Edit Data Master</a>
                        <a href="{{ route('employees.index') }}" class="block rounded-2xl border border-white/15 bg-white/5 px-4 py-3 text-center text-sm font-semibold text-white transition hover:bg-white/10">Kembali ke Master Karyawan</a>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="mb-5 flex items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Dokumen Utama</h2>
                    <span class="text-sm text-slate-500">Candidate parity</span>
                </div>
                <div class="space-y-4">
                    @foreach([
                        ['label' => 'Pas Foto', 'meta' => $photoDocument],
                        ['label' => 'Scan KTP', 'meta' => $ktpDocument],
                        ['label' => 'CV', 'meta' => $cvDocument],
                    ] as $document)
                        @php $url = $documentUrl($document['meta']); @endphp
                        <div class="overflow-hidden rounded-3xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800/60">
                            <div class="flex h-48 items-center justify-center border-b border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900/60">
                                @if($document['meta']['exists'] && $document['meta']['is_image'])
                                    <img src="{{ $url }}" alt="{{ $document['label'] }}" class="h-full w-full rounded-2xl object-contain">
                                @elseif($document['meta']['exists'])
                                    <div class="text-center text-slate-600 dark:text-slate-300">
                                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-lg font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-100">{{ $document['meta']['extension'] }}</div>
                                        <div class="mt-3 text-sm font-semibold">Dokumen tersedia</div>
                                        <div class="text-xs text-slate-500">Preview file non-gambar dibuka lewat link.</div>
                                    </div>
                                @else
                                    <div class="text-center text-slate-400">
                                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl border border-dashed border-slate-300 text-2xl">+</div>
                                        <div class="mt-3 text-sm font-medium">Belum ada dokumen</div>
                                    </div>
                                @endif
                            </div>
                            <div class="flex items-center justify-between gap-3 px-4 py-4">
                                <div>
                                    <div class="text-sm font-semibold text-slate-900 dark:text-white">{{ $document['label'] }}</div>
                                    <div class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-500">{{ $document['meta']['exists'] ? $document['meta']['extension'] : 'EMPTY' }}</div>
                                </div>
                                @if($document['meta']['exists'])
                                    <a href="{{ $url }}" target="_blank" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">Buka File</a>
                                @else
                                    <span class="text-sm text-slate-400">Belum diunggah</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="mb-5 flex items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Dokumen Tambahan</h2>
                    <span class="text-sm text-slate-500">Kelulusan & pendukung</span>
                </div>
                <div class="space-y-3">
                    @foreach([
                        ['label' => 'Ijazah', 'meta' => $additionalDocuments['diploma'] ?? ['exists' => false, 'path' => null, 'extension' => '-']],
                        ['label' => 'Transkrip Nilai', 'meta' => $additionalDocuments['transcript'] ?? ['exists' => false, 'path' => null, 'extension' => '-']],
                        ['label' => 'Akta Lahir', 'meta' => $additionalDocuments['birth_certificate'] ?? ['exists' => false, 'path' => null, 'extension' => '-']],
                    ] as $document)
                        @php $url = $documentUrl($document['meta']); @endphp
                        <div class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700">
                            <div>
                                <div class="text-sm font-semibold text-slate-900 dark:text-white">{{ $document['label'] }}</div>
                                <div class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-500">{{ $document['meta']['exists'] ? $document['meta']['extension'] : 'EMPTY' }}</div>
                            </div>
                            @if($document['meta']['exists'])
                                <a href="{{ $url }}" target="_blank" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">Buka File</a>
                            @else
                                <span class="text-sm text-slate-400">Belum diunggah</span>
                            @endif
                        </div>
                    @endforeach

                    <div class="rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700">
                        <div class="text-sm font-semibold text-slate-900 dark:text-white">Berkas Pendukung</div>
                        @php $supportingFiles = $additionalDocuments['supporting_files'] ?? []; @endphp
                        @if(empty($supportingFiles))
                            <div class="mt-1 text-sm text-slate-400">Belum ada berkas pendukung.</div>
                        @else
                            <div class="mt-2 space-y-2">
                                @foreach($supportingFiles as $index => $supportingDocument)
                                    <div class="flex items-center justify-between gap-3 text-sm">
                                        <span class="text-slate-600 dark:text-slate-300">Berkas {{ $index + 1 }} ({{ $supportingDocument['extension'] }})</span>
                                        <a href="{{ $documentUrl($supportingDocument) }}" target="_blank" class="text-blue-600 hover:underline dark:text-blue-400">Buka File</a>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </section>
        </aside>

        <section class="space-y-6 xl:col-span-8">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach($summaryCards as $card)
                    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                        <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">{{ $card['label'] }}</div>
                        <div class="mt-3 text-2xl font-bold text-slate-900 dark:text-white">{{ $card['value'] ?: '-' }}</div>
                    </div>
                @endforeach
            </div>

            @if(!empty($meta['uses_applicant_profile']))
                <div class="rounded-3xl border border-blue-200 bg-blue-50 px-5 py-4 text-sm text-blue-900 shadow-sm dark:border-blue-900/60 dark:bg-blue-950/40 dark:text-blue-100">
                    Halaman HRD ini memakai employee data sebagai sumber utama, lalu fallback ke candidate/applicant profile yang sama agar section profil tetap lengkap setelah kandidat diterima menjadi karyawan.
                </div>
            @endif

            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="flex overflow-x-auto border-b border-slate-200 dark:border-slate-700">
                    @foreach([
                        'biodata'     => 'Informasi Dasar',
                        'pendidikan'  => 'Pendidikan',
                        'karir'       => 'Karir',
                        'medis'       => 'Medis & Sosial',
                        'payroll'     => 'Payroll',
                        'training'    => 'Training',
                        'appraisal'   => 'Appraisal',
                        'approval'    => 'Approval',
                        'penugasan'   => '📍 Penugasan',
                        'bpjs'        => 'BPJS',
                    ] as $key => $label)
                        <button @click="tab = '{{ $key }}'" :class="{ 'border-blue-500 text-blue-600': tab === '{{ $key }}' }" class="whitespace-nowrap border-b-2 border-transparent px-6 py-4 text-sm font-bold text-slate-600 transition dark:text-slate-300">{{ $label }}</button>
                    @endforeach
                </div>

                <div class="space-y-8 p-6 md:p-8">
                    <div x-show="tab === 'biodata'" class="space-y-8">
                        <div>
                            <h3 class="text-3xl font-extrabold text-slate-900 dark:text-white">Informasi Dasar</h3>
                            <div class="mt-6 grid grid-cols-1 gap-5 md:grid-cols-2">
                                @foreach($basicFacts as $label => $value)
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-700 dark:bg-slate-800/50">
                                        <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ $label }}</div>
                                        <div class="mt-2 text-sm font-semibold text-slate-900 dark:text-white">{{ $value ?: '-' }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <h3 class="text-3xl font-extrabold text-slate-900 dark:text-white">Keluarga</h3>
                            <div class="mt-5 overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                                        <tr>
                                            <th class="px-4 py-3 text-left">Hubungan</th>
                                            <th class="px-4 py-3 text-left">Nama</th>
                                            <th class="px-4 py-3 text-left">Gender</th>
                                            <th class="px-4 py-3 text-left">Tanggal Lahir</th>
                                            <th class="px-4 py-3 text-left">Pendidikan</th>
                                            <th class="px-4 py-3 text-left">Pekerjaan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($families as $family)
                                            <tr class="border-b border-slate-100 dark:border-slate-800">
                                                <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ $family['relation'] ?: '-' }}</td>
                                                <td class="px-4 py-3 text-slate-700 dark:text-slate-200">{{ $family['name'] ?: '-' }}</td>
                                                <td class="px-4 py-3 text-slate-700 dark:text-slate-200">{{ $family['gender'] ?: '-' }}</td>
                                                <td class="px-4 py-3 text-slate-700 dark:text-slate-200">{{ $family['dob'] ?: '-' }}</td>
                                                <td class="px-4 py-3 text-slate-700 dark:text-slate-200">{{ $family['education'] ?: '-' }}</td>
                                                <td class="px-4 py-3 text-slate-700 dark:text-slate-200">{{ $family['job'] ?: '-' }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="6" class="px-4 py-4 text-slate-400">Belum ada data keluarga.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div x-show="tab === 'pendidikan'" style="display:none;" class="space-y-8">
                        <div>
                            <h3 class="text-3xl font-extrabold text-slate-900 dark:text-white">Riwayat Pendidikan</h3>
                            <div class="mt-5 overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                                        <tr>
                                            <th class="px-4 py-3 text-left">Jenjang</th>
                                            <th class="px-4 py-3 text-left">Institusi</th>
                                            <th class="px-4 py-3 text-left">Jurusan</th>
                                            <th class="px-4 py-3 text-left">Masuk</th>
                                            <th class="px-4 py-3 text-left">Lulus</th>
                                            <th class="px-4 py-3 text-left">Nilai / IPK</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($educations as $education)
                                            <tr class="border-b border-slate-100 dark:border-slate-800">
                                                <td class="px-4 py-3">{{ $education['level'] ?: '-' }}</td>
                                                <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ $education['school'] ?: '-' }}</td>
                                                <td class="px-4 py-3">{{ $education['major'] ?: '-' }}</td>
                                                <td class="px-4 py-3">{{ $education['year_in'] ?: '-' }}</td>
                                                <td class="px-4 py-3">{{ $education['year_out'] ?: '-' }}</td>
                                                <td class="px-4 py-3">{{ $education['gpa'] ?: '-' }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="6" class="px-4 py-4 text-slate-400">Belum ada riwayat pendidikan.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                            <div class="rounded-3xl border border-slate-200 p-5 dark:border-slate-700">
                                <h4 class="text-xl font-bold text-slate-900 dark:text-white">Bahasa</h4>
                                <div class="mt-4 overflow-x-auto">
                                    <table class="min-w-full text-sm">
                                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                                            <tr>
                                                <th class="px-4 py-3 text-left">Bahasa</th>
                                                <th class="px-4 py-3 text-left">Lisan</th>
                                                <th class="px-4 py-3 text-left">Tulisan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($languages as $language)
                                                <tr class="border-b border-slate-100 dark:border-slate-800">
                                                    <td class="px-4 py-3">{{ $language['language'] ?: '-' }}</td>
                                                    <td class="px-4 py-3">{{ $language['speaking'] ?: '-' }}</td>
                                                    <td class="px-4 py-3">{{ $language['writing'] ?: '-' }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="3" class="px-4 py-4 text-slate-400">Belum ada data bahasa.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="rounded-3xl border border-slate-200 p-5 dark:border-slate-700">
                                <h4 class="text-xl font-bold text-slate-900 dark:text-white">Bahasa & Kursus</h4>
                                <div class="mt-4 overflow-x-auto">
                                    <table class="min-w-full text-sm">
                                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                                            <tr>
                                                <th class="px-4 py-3 text-left">Kursus</th>
                                                <th class="px-4 py-3 text-left">Penyelenggara</th>
                                                <th class="px-4 py-3 text-left">Tahun</th>
                                                <th class="px-4 py-3 text-left">Sertifikat</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($courses as $course)
                                                <tr class="border-b border-slate-100 dark:border-slate-800">
                                                    <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ $course['name'] ?: '-' }}</td>
                                                    <td class="px-4 py-3">{{ $course['organizer'] ?: '-' }}</td>
                                                    <td class="px-4 py-3">{{ $course['year'] ?: '-' }}</td>
                                                    <td class="px-4 py-3">{{ $course['certificate'] ?: '-' }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="4" class="px-4 py-4 text-slate-400">Belum ada data kursus dan pelatihan.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div x-show="tab === 'karir'" style="display:none;" class="space-y-8">
                        <div>
                            <h3 class="text-3xl font-extrabold text-slate-900 dark:text-white">Riwayat Pekerjaan</h3>
                            <div class="mt-5 overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                                        <tr>
                                            <th class="px-4 py-3 text-left">Perusahaan</th>
                                            <th class="px-4 py-3 text-left">Jabatan</th>
                                            <th class="px-4 py-3 text-left">Periode</th>
                                            <th class="px-4 py-3 text-left">Gaji</th>
                                            <th class="px-4 py-3 text-left">Alasan Keluar</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($workExperiences as $experience)
                                            <tr class="border-b border-slate-100 dark:border-slate-800">
                                                <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ $experience['company'] ?: '-' }}</td>
                                                <td class="px-4 py-3">{{ $experience['position'] ?: '-' }}</td>
                                                <td class="px-4 py-3">{{ $experience['date_start'] ?: '-' }} - {{ $experience['date_end'] ?: '-' }}</td>
                                                <td class="px-4 py-3">{{ $experience['salary'] ?: '-' }}</td>
                                                <td class="px-4 py-3">{{ $experience['reason'] ?: '-' }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="5" class="px-4 py-4 text-slate-400">Belum ada riwayat pekerjaan.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                            <div class="rounded-3xl border border-slate-200 p-5 dark:border-slate-700">
                                <h4 class="text-xl font-bold text-slate-900 dark:text-white">Kontak Referensi</h4>
                                <div class="mt-4 overflow-x-auto">
                                    <table class="min-w-full text-sm">
                                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                                            <tr>
                                                <th class="px-4 py-3 text-left">Nama</th>
                                                <th class="px-4 py-3 text-left">Hubungan</th>
                                                <th class="px-4 py-3 text-left">Perusahaan</th>
                                                <th class="px-4 py-3 text-left">No. HP</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($referenceContacts as $reference)
                                                <tr class="border-b border-slate-100 dark:border-slate-800">
                                                    <td class="px-4 py-3">{{ $reference['name'] ?: '-' }}</td>
                                                    <td class="px-4 py-3">{{ $reference['relation'] ?: '-' }}</td>
                                                    <td class="px-4 py-3">{{ $reference['company'] ?: '-' }}</td>
                                                    <td class="px-4 py-3">{{ $reference['phone'] ?: '-' }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="4" class="px-4 py-4 text-slate-400">Belum ada data kontak referensi.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="rounded-3xl border border-slate-200 p-5 dark:border-slate-700">
                                <h4 class="text-xl font-bold text-slate-900 dark:text-white">Organisasi</h4>
                                <div class="mt-4 overflow-x-auto">
                                    <table class="min-w-full text-sm">
                                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                                            <tr>
                                                <th class="px-4 py-3 text-left">Organisasi</th>
                                                <th class="px-4 py-3 text-left">Peran</th>
                                                <th class="px-4 py-3 text-left">Tahun</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($organizations as $organization)
                                                <tr class="border-b border-slate-100 dark:border-slate-800">
                                                    <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ $organization['name'] ?: '-' }}</td>
                                                    <td class="px-4 py-3">{{ $organization['role'] ?: '-' }}</td>
                                                    <td class="px-4 py-3">{{ $organization['year'] ?: '-' }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="3" class="px-4 py-4 text-slate-400">Belum ada pengalaman organisasi.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div x-show="tab === 'medis'" style="display:none;" class="space-y-8">
                        <div>
                            <h3 class="text-3xl font-extrabold text-slate-900 dark:text-white">Riwayat Medis</h3>
                            <div class="mt-5 overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                                        <tr>
                                            <th class="px-4 py-3 text-left">Penyakit</th>
                                            <th class="px-4 py-3 text-left">Tahun</th>
                                            <th class="px-4 py-3 text-left">Rawat Inap</th>
                                            <th class="px-4 py-3 text-left">Catatan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($medicalHistories as $medical)
                                            <tr class="border-b border-slate-100 dark:border-slate-800">
                                                <td class="px-4 py-3">{{ $medical['illness'] ?: '-' }}</td>
                                                <td class="px-4 py-3">{{ $medical['year'] ?: '-' }}</td>
                                                <td class="px-4 py-3">{{ $medical['hospitalized'] ?: '-' }}</td>
                                                <td class="px-4 py-3">{{ $medical['note'] ?: '-' }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4" class="px-4 py-4 text-slate-400">Belum ada riwayat medis.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-3xl font-extrabold text-slate-900 dark:text-white">Sosial Media</h3>
                            <div class="mt-5 overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                                        <tr>
                                            <th class="px-4 py-3 text-left">Platform</th>
                                            <th class="px-4 py-3 text-left">Username / Link</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($socialMedias as $social)
                                            <tr class="border-b border-slate-100 dark:border-slate-800">
                                                <td class="px-4 py-3">{{ $social['platform'] ?: '-' }}</td>
                                                <td class="px-4 py-3 break-all">{{ $social['handle'] ?: '-' }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="2" class="px-4 py-4 text-slate-400">Belum ada data sosial media.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div x-show="tab === 'payroll'" style="display:none;" class="space-y-8">
                        <div>
                            <h3 class="text-3xl font-extrabold text-slate-900 dark:text-white">Payroll, Rekening, dan Riwayat Gaji</h3>
                            <div class="mt-5 grid grid-cols-1 gap-6 xl:grid-cols-2">
                                <div class="rounded-3xl border border-slate-200 p-5 text-sm dark:border-slate-700">
                                    <div class="space-y-3 text-slate-700 dark:text-slate-200">
                                        <div>SIM: <strong>{{ $payroll['sim_number'] ?? '-' }}</strong></div>
                                        <div>NPWP: <strong>{{ $payroll['npwp_number'] ?? '-' }}</strong></div>
                                        <div>BPJS Kesehatan: <strong>{{ $payroll['bpjs_kes_number'] ?? '-' }}</strong></div>
                                        <div>BPJS TK: <strong>{{ $payroll['bpjs_tk_number'] ?? '-' }}</strong></div>
                                        <div>Passport: <strong>{{ $payroll['passport_number'] ?? '-' }}</strong></div>
                                        <div>KK: <strong>{{ $payroll['kk_number'] ?? '-' }}</strong></div>
                                        <div>Verified At: <strong>{{ !empty($payroll['payroll_verified_at']) ? $formatDateTime($payroll['payroll_verified_at']) : '-' }}</strong></div>
                                    </div>
                                </div>

                                <div class="xl:col-span-2 rounded-3xl border border-slate-200 p-5 dark:border-slate-700"
                                     x-data="{ showAddRekeningModal: false }">
                                    <div class="mb-4 flex items-center justify-between">
                                        <div class="text-lg font-semibold text-slate-900 dark:text-white">Rekening Bank</div>
                                        @if(empty($meta['has_bank_accounts_table']))
                                        @else
                                        <button @click="showAddRekeningModal = true"
                                                class="flex items-center gap-1.5 rounded-xl bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                                            + Tambah Rekening
                                        </button>
                                        @endif
                                    </div>

                                    @if(empty($meta['has_bank_accounts_table']))
                                        <div class="text-sm text-amber-700 dark:text-amber-300">Tabel rekening karyawan belum tersedia di environment ini.</div>
                                    @else
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full text-sm">
                                                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                                                    <tr>
                                                        <th class="px-4 py-3 text-left">Bank</th>
                                                        <th class="px-4 py-3 text-left">No Rekening</th>
                                                        <th class="px-4 py-3 text-left">Nama Pemilik</th>
                                                        <th class="px-4 py-3 text-left">Primary</th>
                                                        <th class="px-4 py-3 text-left">Aksi</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse($bankAccounts as $account)
                                                        <tr class="border-b border-slate-100 dark:border-slate-800">
                                                            <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ $account['bank_name'] ?: '-' }}</td>
                                                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $account['account_number'] ?: '-' }}</td>
                                                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $account['account_holder_name'] ?: '-' }}</td>
                                                            <td class="px-4 py-3">
                                                                @if(!empty($account['is_primary']))
                                                                    <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200">PRIMARY</span>
                                                                @else
                                                                    <span class="text-slate-400">—</span>
                                                                @endif
                                                            </td>
                                                            <td class="px-4 py-3">
                                                                <form method="POST"
                                                                      action="{{ route('employee-bank-accounts.destroy', [$employee->id, $account['id']]) }}"
                                                                      onsubmit="return confirm('Hapus rekening {{ addslashes($account['bank_name']) }}?')">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit"
                                                                            class="rounded-lg bg-red-50 px-3 py-1 text-xs font-medium text-red-700 hover:bg-red-100 dark:bg-red-900/30 dark:text-red-400">
                                                                        Hapus
                                                                    </button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="5" class="px-4 py-5 text-center text-slate-400">Belum ada rekening terdaftar.</td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>

                                        {{-- Modal Tambah Rekening --}}
                                        <div x-show="showAddRekeningModal"
                                             x-cloak
                                             style="display:none;"
                                             class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
                                             @click.self="showAddRekeningModal = false">
                                            <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl dark:bg-slate-800">
                                                <div class="mb-4 flex items-center justify-between">
                                                    <h4 class="text-lg font-semibold text-slate-900 dark:text-white">Tambah Rekening Bank</h4>
                                                    <button @click="showAddRekeningModal = false"
                                                            class="rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700 dark:hover:text-slate-200">
                                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                                <form method="POST" action="{{ route('employee-bank-accounts.store', $employee->id) }}" class="space-y-4">
                                                    @csrf
                                                    <div>
                                                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Nama Bank <span class="text-red-500">*</span></label>
                                                        <input type="text" name="bank_name" required maxlength="150"
                                                               class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                                               placeholder="Contoh: Bank Mandiri">
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">No Rekening <span class="text-red-500">*</span></label>
                                                        <input type="text" name="account_number" required maxlength="100"
                                                               class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                                               placeholder="Contoh: 1234567890">
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Nama Pemilik Rekening <span class="text-red-500">*</span></label>
                                                        <input type="text" name="account_holder_name" required maxlength="150"
                                                               class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                                               placeholder="Sesuai nama di buku rekening">
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        <input type="checkbox" name="is_primary" id="modal_is_primary" value="1"
                                                               class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                                        <label for="modal_is_primary" class="text-sm text-slate-700 dark:text-slate-300">
                                                            Jadikan rekening utama <span class="text-slate-400">(akan mengganti primary yang ada)</span>
                                                        </label>
                                                    </div>
                                                    <div class="flex justify-end gap-3 pt-2">
                                                        <button type="button" @click="showAddRekeningModal = false"
                                                                class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700">
                                                            Batal
                                                        </button>
                                                        <button type="submit"
                                                                class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                                                            Simpan Rekening
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div>
                            <h4 class="text-2xl font-extrabold text-slate-900 dark:text-white">Riwayat Gaji</h4>
                            <div class="mt-5 overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                                        <tr>
                                            <th class="px-4 py-3 text-left">Tanggal Efektif</th>
                                            <th class="px-4 py-3 text-left">Nominal</th>
                                            <th class="px-4 py-3 text-left">Dicatat Oleh</th>
                                            <th class="px-4 py-3 text-left">Catatan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($salaryHistories as $salaryHistory)
                                            <tr class="border-b border-slate-100 dark:border-slate-800">
                                                <td class="px-4 py-3">{{ $formatDate($salaryHistory->effective_date ?? null) }}</td>
                                                <td class="px-4 py-3">{{ $formatCurrency($salaryHistory->amount ?? null) }}</td>
                                                <td class="px-4 py-3">{{ $salaryHistory->actor?->name ?: '-' }}</td>
                                                <td class="px-4 py-3">{{ $salaryHistory->notes ?: '-' }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4" class="px-4 py-4 text-slate-400">Belum ada riwayat gaji.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div x-show="tab === 'training'" style="display:none;" class="space-y-6">
                        <div>
                            <h3 class="text-3xl font-extrabold text-slate-900 dark:text-white">History Training</h3>
                            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-700 dark:bg-slate-800/50"><div class="text-xs uppercase tracking-wide text-slate-500">Total</div><div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">{{ $trainingSummary['total'] }}</div></div>
                                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-700 dark:bg-slate-800/50"><div class="text-xs uppercase tracking-wide text-slate-500">Completed</div><div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">{{ $trainingSummary['completed'] }}</div></div>
                                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-700 dark:bg-slate-800/50"><div class="text-xs uppercase tracking-wide text-slate-500">In Progress</div><div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">{{ $trainingSummary['in_progress'] }}</div></div>
                            </div>
                        </div>

                        <div class="space-y-3">
                            @forelse(($trainingSummary['latest'] ?? collect()) as $training)
                                <div class="rounded-3xl border border-slate-200 p-5 dark:border-slate-700">
                                    <div class="font-semibold text-slate-900 dark:text-white">{{ $training->summary_title ?? ($training->material?->title ?: $training->program?->name ?: 'Training') }}</div>
                                    <div class="mt-2 text-sm text-slate-500">{{ ucfirst((string) ($training->status ?: '-')) }} @if($training->completion_date) | {{ $formatDateTime($training->completion_date) }} @elseif($training->completed_at) | {{ $formatDateTime($training->completed_at) }} @endif</div>
                                    <div class="mt-2 text-sm text-slate-600 dark:text-slate-300">Quiz / Progress: {{ $training->quiz_score !== null ? $training->quiz_score : (isset($training->progress_percent) ? number_format((float) $training->progress_percent, 0) . '%' : '-') }}</div>
                                </div>
                            @empty
                                <div class="text-sm text-slate-400">Belum ada history training.</div>
                            @endforelse
                        </div>
                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                            <div class="rounded-3xl border border-slate-200 p-5 dark:border-slate-700">
                                <div class="mb-3 text-lg font-semibold text-slate-900 dark:text-white">Program LMS</div>
                                <div class="space-y-2 text-sm">
                                    @forelse(($trainingSummary['programs'] ?? collect()) as $programTraining)
                                        <div>{{ $programTraining->program?->name ?: '-' }} | {{ ucfirst((string) ($programTraining->status ?: '-')) }} | {{ number_format((float) $programTraining->progress_percent, 0) }}%</div>
                                    @empty
                                        <div class="text-slate-400">Belum ada program LMS.</div>
                                    @endforelse
                                </div>
                            </div>
                            <div class="rounded-3xl border border-slate-200 p-5 dark:border-slate-700">
                                <div class="mb-3 text-lg font-semibold text-slate-900 dark:text-white">Event Training</div>
                                <div class="space-y-2 text-sm">
                                    @forelse(($trainingSummary['events'] ?? collect()) as $eventTraining)
                                        <div>{{ $eventTraining->event?->title ?: '-' }} | {{ ucfirst((string) ($eventTraining->status ?: '-')) }} | {{ $formatDateTime($eventTraining->checked_in_at ?? null) }}</div>
                                    @empty
                                        <div class="text-slate-400">Belum ada event training.</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>

                    <div x-show="tab === 'appraisal'" style="display:none;" class="space-y-4">
                        <h3 class="text-3xl font-extrabold text-slate-900 dark:text-white">History Appraisal & Feedback Pimpinan</h3>
                        <div class="space-y-4">
                            @forelse($appraisals as $appraisal)
                                <div class="rounded-3xl border border-slate-200 p-5 dark:border-slate-700">
                                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                        <div>
                                            <div class="font-semibold text-slate-900 dark:text-white">{{ $appraisal->period?->name ?: 'Appraisal' }}</div>
                                            <div class="mt-1 text-sm text-slate-500">{{ $formatDate($appraisal->date_appraised ?? null) }} | {{ ucfirst((string) ($appraisal->status ?: '-')) }}</div>
                                        </div>
                                        <div class="text-sm text-slate-700 dark:text-slate-200">
                                            <div>Skor Akhir: <strong>{{ $appraisal->final_score !== null ? number_format((float) $appraisal->final_score, 2) : '-' }}</strong></div>
                                            <div>Hasil: <strong>{{ $appraisal->final_result ?: '-' }}</strong></div>
                                        </div>
                                    </div>
                                    <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
                                        <div class="rounded-2xl bg-slate-50 p-4 text-sm dark:bg-slate-800/50">
                                            <div class="mb-2 font-semibold text-slate-900 dark:text-white">Catatan HRD</div>
                                            <div class="text-slate-700 dark:text-slate-200">{{ $appraisal->notes_hrd ?: '-' }}</div>
                                        </div>
                                        <div class="rounded-2xl bg-slate-50 p-4 text-sm dark:bg-slate-800/50">
                                            <div class="mb-2 font-semibold text-slate-900 dark:text-white">Komentar Pimpinan</div>
                                            @php $comments = $appraisal->details->filter(fn($detail) => trim((string) $detail->comment) !== ''); @endphp
                                            @forelse($comments as $detail)
                                                <div class="mb-3 last:mb-0">
                                                    <div class="text-xs uppercase tracking-wide text-slate-500">{{ $detail->indicator?->name ?: 'Indikator' }}</div>
                                                    <div class="text-slate-700 dark:text-slate-200">{{ $detail->comment }}</div>
                                                </div>
                                            @empty
                                                <div class="text-slate-500">-</div>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-sm text-slate-400">Belum ada history appraisal.</div>
                            @endforelse
                        </div>
                    </div>

                    <div x-show="tab === 'approval'" style="display:none;" class="space-y-4">
                        <h3 class="text-3xl font-extrabold text-slate-900 dark:text-white">History Pengajuan Perubahan Data</h3>
                        <div class="space-y-3">
                            @forelse($profileChangeRequests as $request)
                                <div class="rounded-3xl border border-slate-200 p-5 dark:border-slate-700">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="font-semibold text-slate-900 dark:text-white">{{ ucfirst((string) $request->status) }}</div>
                                        <div class="text-sm text-slate-500">{{ $formatDateTime($request->submitted_at ?? null) }}</div>
                                    </div>
                                    <div class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $request->review_note ?: 'Belum ada catatan review.' }}</div>
                                </div>
                            @empty
                                <div class="text-sm text-slate-400">Belum ada history approval profile.</div>
                            @endforelse
                        </div>
                    </div>

                    {{-- ── TAB PENUGASAN ───────────────────────────────────────────────── --}}
                    <div x-show="tab === 'penugasan'" style="display:none;" class="space-y-6"
                         x-data="{ showAssignModal: false }">

                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Riwayat Penugasan Outlet</h3>
                                <p class="text-xs text-slate-400 mt-0.5">Outlet operasional, cost center gaji, dan entitas legal terkait</p>
                            </div>
                            @can('manage-employees')
                            <button @click="showAssignModal = true"
                                    type="button"
                                    style="background-color:#2563EB;color:#FFFFFF;"
                                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-xl hover:opacity-90 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Set Penugasan Baru
                            </button>
                            @endcan
                        </div>

                        {{-- Active assignment badges --}}
                        @php $activeAssign = ($assignments ?? collect())->first(fn($a) => is_null($a->end_date)); @endphp
                        @if($activeAssign)
                        <div class="flex flex-wrap gap-3">
                            <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-2.5 text-sm">
                                <div class="text-[10px] font-bold uppercase tracking-wide text-blue-400">Outlet Tugas</div>
                                <div class="font-semibold text-blue-800 mt-0.5">{{ $activeAssign->outlet?->name ?? 'Belum diset' }}</div>
                            </div>
                            <div class="rounded-xl border border-violet-200 bg-violet-50 px-4 py-2.5 text-sm">
                                <div class="text-[10px] font-bold uppercase tracking-wide text-violet-400">Cost Center</div>
                                <div class="font-semibold text-violet-800 mt-0.5">{{ $activeAssign->payrollOutlet?->name ?? 'Belum diset' }}</div>
                            </div>
                            @if($activeAssign->legalEntity)
                            <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-2.5 text-sm">
                                <div class="text-[10px] font-bold uppercase tracking-wide text-green-400">Entitas Legal</div>
                                <div class="font-semibold text-green-800 mt-0.5">{{ $activeAssign->legalEntity->name }}</div>
                            </div>
                            @endif
                        </div>
                        @endif

                        {{-- Tabel riwayat --}}
                        <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-slate-700">
                            <table class="min-w-full text-sm divide-y divide-slate-200 dark:divide-slate-700">
                                <thead class="bg-slate-50 dark:bg-slate-800 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                    <tr>
                                        <th class="px-4 py-3 text-left">Outlet Tugas</th>
                                        <th class="px-4 py-3 text-left">Cost Center</th>
                                        <th class="px-4 py-3 text-left">Entitas Legal</th>
                                        <th class="px-4 py-3 text-left">Departemen</th>
                                        <th class="px-4 py-3 text-left">Mulai</th>
                                        <th class="px-4 py-3 text-left">Selesai</th>
                                        <th class="px-4 py-3 text-left">Tipe</th>
                                        <th class="px-4 py-3 text-left">Alasan</th>
                                        @can('manage-employees')<th class="px-4 py-3 text-center">Aksi</th>@endcan
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700 bg-white dark:bg-slate-900">
                                    @forelse($assignments ?? [] as $asgn)
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition">
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-slate-800 dark:text-white">{{ $asgn->outlet?->name ?? '—' }}</div>
                                            @if(is_null($asgn->end_date))
                                                <span class="inline-block mt-1 rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-bold text-blue-700">AKTIF</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300 text-xs">{{ $asgn->payrollOutlet?->name ?? '—' }}</td>
                                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300 text-xs">{{ $asgn->legalEntity?->name ?? '—' }}</td>
                                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300 text-xs">{{ $asgn->department ?? '—' }}</td>
                                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300 whitespace-nowrap">{{ $asgn->effective_date_display }}</td>
                                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300 whitespace-nowrap">{{ $asgn->end_date ? $asgn->end_date->format('d M Y') : '—' }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-block rounded-full px-2 py-0.5 text-[10px] font-bold
                                                {{ $asgn->assignment_type === 'primary' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600' }}">
                                                {{ $asgn->assignment_type_label }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400 text-xs max-w-[140px] truncate" title="{{ $asgn->reason }}">{{ $asgn->reason ?: '—' }}</td>
                                        @can('manage-employees')
                                        <td class="px-4 py-3 text-center">
                                            <form method="POST"
                                                  action="{{ route('employees.assignments.destroy', [$employee, $asgn]) }}"
                                                  onsubmit="return confirm('Hapus riwayat penugasan ini?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-xs text-red-600 hover:text-red-800 font-medium">Hapus</button>
                                            </form>
                                        </td>
                                        @endcan
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="9" class="px-4 py-8 text-center text-slate-400 text-sm">Belum ada riwayat penugasan outlet.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {{-- Modal Set Penugasan Baru --}}
                        <div x-show="showAssignModal" x-cloak
                             class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                             @click.self="showAssignModal = false">
                            <div class="w-full max-w-lg rounded-2xl bg-white dark:bg-slate-900 shadow-xl p-6 space-y-4 overflow-y-auto max-h-[90vh]">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-lg font-bold text-slate-900 dark:text-white">Set Penugasan Baru</h4>
                                    <button @click="showAssignModal = false" type="button"
                                            class="text-slate-400 hover:text-slate-600">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>

                                <form method="POST"
                                      action="{{ route('employees.assignments.store', $employee) }}"
                                      class="space-y-4">
                                    @csrf

                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Outlet Tugas</label>
                                            <select name="outlet_id"
                                                    class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                                <option value="">— Pilih Outlet —</option>
                                                @foreach($outletOptions ?? [] as $ol)
                                                    <option value="{{ $ol->id }}">{{ $ol->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Cost Center (Payroll)</label>
                                            <select name="payroll_outlet_id"
                                                    class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                                <option value="">— Sama dengan Outlet Tugas —</option>
                                                @foreach($outletOptions ?? [] as $ol)
                                                    <option value="{{ $ol->id }}">{{ $ol->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Entitas Legal (PT BPJS)</label>
                                            <select name="legal_entity_id"
                                                    class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                                <option value="">— Pilih Entitas —</option>
                                                @foreach($legalEntities as $le)
                                                    <option value="{{ $le->id }}">{{ $le->short_name ?? $le->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Departemen</label>
                                            <input type="text" name="department" placeholder="Mis. Operations..."
                                                   class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Tanggal Efektif <span class="text-red-500">*</span></label>
                                            <input type="date" name="effective_date" required
                                                   class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Tipe Penugasan</label>
                                            <select name="assignment_type"
                                                    class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                                <option value="primary">Utama</option>
                                                <option value="secondary">Tambahan</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Alasan</label>
                                        <input type="text" name="reason" placeholder="Mis. Mutasi, Promosi..."
                                               class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Catatan</label>
                                        <textarea name="notes" rows="2" placeholder="Opsional..."
                                                  class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none resize-none"></textarea>
                                    </div>

                                    <div class="flex gap-3 pt-1">
                                        <button type="submit"
                                                class="flex-1 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 transition">
                                            Simpan Penugasan
                                        </button>
                                        <button type="button" @click="showAssignModal = false"
                                                class="flex-1 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                                            Batal
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    {{-- ── TAB BPJS ─────────────────────────────────────────────────── --}}
                    <div x-show="tab === 'bpjs'" style="display:none;" class="space-y-6" x-data="{ showModal: false, editId: null, editData: {} }">

                        <div class="flex items-center justify-between">
                            <h3 class="text-3xl font-extrabold text-slate-900 dark:text-white">Penugasan PT BPJS</h3>
                            @can('manage-employees')
                            <button @click="showModal = true; editId = null; editData = {}"
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Set PT BPJS Aktif
                            </button>
                            @endcan
                        </div>

                        @if(session('success'))
                            <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
                        @endif

                        {{-- Tabel riwayat --}}
                        <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-slate-700">
                            <table class="min-w-full text-sm divide-y divide-slate-200 dark:divide-slate-700">
                                <thead class="bg-slate-50 dark:bg-slate-800 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                    <tr>
                                        <th class="px-4 py-3 text-left">PT BPJS</th>
                                        <th class="px-4 py-3 text-left">Mulai</th>
                                        <th class="px-4 py-3 text-left">Selesai</th>
                                        <th class="px-4 py-3 text-left">Alasan</th>
                                        <th class="px-4 py-3 text-left">Catatan</th>
                                        <th class="px-4 py-3 text-left">Dicatat Oleh</th>
                                        @can('manage-employees')<th class="px-4 py-3 text-center">Aksi</th>@endcan
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700 bg-white dark:bg-slate-900">
                                    @forelse($bpjsAssignments as $asgn)
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition">
                                        <td class="px-4 py-3">
                                            <div class="font-semibold text-slate-900 dark:text-white">{{ $asgn->legalEntity?->name ?? '-' }}</div>
                                            @if(is_null($asgn->end_date))
                                                <span class="inline-block mt-1 rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-bold text-green-700">AKTIF</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ \Carbon\Carbon::parse($asgn->effective_date)->format('d M Y') }}</td>
                                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $asgn->end_date ? \Carbon\Carbon::parse($asgn->end_date)->format('d M Y') : '—' }}</td>
                                        <td class="px-4 py-3">
                                            @php
                                                $reasonColor = match($asgn->reason) {
                                                    'join'     => 'bg-blue-100 text-blue-700',
                                                    'transfer' => 'bg-yellow-100 text-yellow-700',
                                                    'resign'   => 'bg-red-100 text-red-600',
                                                    default    => 'bg-slate-100 text-slate-600',
                                                };
                                            @endphp
                                            <span class="inline-block rounded-full px-2 py-0.5 text-xs font-semibold {{ $reasonColor }}">{{ $asgn->reason_label }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400 text-xs max-w-[160px] truncate" title="{{ $asgn->notes }}">{{ $asgn->notes ?: '—' }}</td>
                                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400 text-xs">{{ $asgn->creator?->name ?? '-' }}</td>
                                        @can('manage-employees')
                                        <td class="px-4 py-3 text-center">
                                            <div class="flex justify-center gap-2">
                                                <button type="button"
                                                        @click="showModal = true; editId = {{ $asgn->id }}; editData = {
                                                            legal_entity_id: '{{ $asgn->legal_entity_id }}',
                                                            effective_date: '{{ $asgn->effective_date->format('Y-m-d') }}',
                                                            end_date: '{{ $asgn->end_date?->format('Y-m-d') ?? '' }}',
                                                            reason: '{{ $asgn->reason }}',
                                                            notes: '{{ addslashes($asgn->notes ?? '') }}'
                                                        }"
                                                        class="text-xs text-blue-600 hover:text-blue-800 font-medium">Edit</button>
                                                <form method="POST"
                                                      action="{{ route('bpjs-assignments.destroy', [$employee, $asgn]) }}"
                                                      onsubmit="return confirm('Hapus riwayat penugasan ini?')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="text-xs text-red-600 hover:text-red-800 font-medium">Hapus</button>
                                                </form>
                                            </div>
                                        </td>
                                        @endcan
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="7" class="px-4 py-8 text-center text-slate-400 text-sm">Belum ada riwayat penugasan PT BPJS.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {{-- Modal Set / Edit PT BPJS --}}
                        <div x-show="showModal" x-cloak
                             class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                             @click.self="showModal = false">
                            <div class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 shadow-xl p-6 space-y-5">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-lg font-bold text-slate-900 dark:text-white"
                                        x-text="editId ? 'Edit Penugasan BPJS' : 'Set PT BPJS Aktif'"></h4>
                                    <button @click="showModal = false" type="button"
                                            class="text-slate-400 hover:text-slate-600">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>

                                {{-- Store form (new) --}}
                                <form x-show="!editId"
                                      method="POST"
                                      action="{{ route('bpjs-assignments.store', $employee) }}"
                                      class="space-y-4">
                                    @csrf
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">PT BPJS</label>
                                        <select name="legal_entity_id" required
                                                class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                            <option value="">— Pilih PT BPJS —</option>
                                            @foreach($legalEntities as $le)
                                                <option value="{{ $le->id }}">{{ $le->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Tanggal Efektif</label>
                                            <input type="date" name="effective_date" required
                                                   class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Tanggal Selesai</label>
                                            <input type="date" name="end_date"
                                                   class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Alasan</label>
                                        <select name="reason" required
                                                class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                            <option value="join">Bergabung</option>
                                            <option value="transfer">Pindah PT</option>
                                            <option value="resign">Resign</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Catatan</label>
                                        <input type="text" name="notes" placeholder="Opsional..."
                                               class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                    </div>
                                    <div class="flex gap-3 pt-2">
                                        <button type="submit"
                                                class="flex-1 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 transition">Simpan</button>
                                        <button type="button" @click="showModal = false"
                                                class="flex-1 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">Batal</button>
                                    </div>
                                </form>

                                {{-- Update forms (per row, hidden until editId matches) --}}
                                @foreach($bpjsAssignments as $asgn)
                                <form x-show="editId === {{ $asgn->id }}"
                                      method="POST"
                                      action="{{ route('bpjs-assignments.update', [$employee, $asgn]) }}"
                                      class="space-y-4">
                                    @csrf @method('PUT')
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">PT BPJS</label>
                                        <select name="legal_entity_id" required
                                                class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                            @foreach($legalEntities as $le)
                                                <option value="{{ $le->id }}" {{ $asgn->legal_entity_id == $le->id ? 'selected' : '' }}>{{ $le->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Tanggal Efektif</label>
                                            <input type="date" name="effective_date" required value="{{ $asgn->effective_date->format('Y-m-d') }}"
                                                   class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Tanggal Selesai</label>
                                            <input type="date" name="end_date" value="{{ $asgn->end_date?->format('Y-m-d') }}"
                                                   class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Alasan</label>
                                        <select name="reason" required
                                                class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                            <option value="join"     {{ $asgn->reason === 'join'     ? 'selected' : '' }}>Bergabung</option>
                                            <option value="transfer" {{ $asgn->reason === 'transfer' ? 'selected' : '' }}>Pindah PT</option>
                                            <option value="resign"   {{ $asgn->reason === 'resign'   ? 'selected' : '' }}>Resign</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Catatan</label>
                                        <input type="text" name="notes" value="{{ $asgn->notes }}"
                                               class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                    </div>
                                    <div class="flex gap-3 pt-2">
                                        <button type="submit"
                                                class="flex-1 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 transition">Perbarui</button>
                                        <button type="button" @click="showModal = false"
                                                class="flex-1 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">Batal</button>
                                    </div>
                                </form>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<script src="//unpkg.com/alpinejs" defer></script>
@endsection
