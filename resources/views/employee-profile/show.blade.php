@extends('layouts.app')

@section('content')
@php
    use Illuminate\Support\Str;

    $profile = $snapshot['profile'];
    $payroll = $snapshot['payroll'];
    $bankAccounts = $snapshot['bank_accounts'];
    $appraisals = $snapshot['appraisals'] ?? collect();
    $editable = $snapshot['editable_form'] ?? [];
    $sections = $snapshot['candidate_sections'] ?? [];

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
    $applicantProfile = $snapshot['applicant_profile'];
    $employee = $snapshot['employee'];
    $completion = $applicantProfile?->getCompletionProgress() ?? ['completed' => 0, 'total' => 6, 'percentage' => 0, 'sections' => []];
    $missingSections = collect($applicantProfile?->getMissingFields() ?? []);

    $formatDate = static fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('d M Y') : '-';
    $formatDateTime = static fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('d M Y H:i') : '-';
    $formatCurrency = static fn ($value) => $value !== null && $value !== '' ? 'Rp ' . number_format((float) $value, 0, ',', '.') : '-';
    $storageUrl = static fn (?string $path) => $path ? asset('storage/' . ltrim($path, '/')) : null;
    $documentMeta = static function (?string $path) use ($storageUrl): array {
        $extension = strtolower((string) pathinfo((string) $path, PATHINFO_EXTENSION));
        $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);

        return [
            'path' => $path,
            'url' => $storageUrl($path),
            'extension' => $extension !== '' ? strtoupper($extension) : '-',
            'is_image' => $isImage,
            'exists' => filled($path),
        ];
    };

    $resolvedName = trim((string) ($profile['full_name'] ?: ($personal['full_name'] ?? 'Profil Karyawan')));
    $resolvedNik = trim((string) ($profile['nik'] ?: ($personal['ktp_number'] ?? '-')));
    $resolvedEmail = trim((string) ($profile['email_private'] ?: ($personal['email'] ?? ($profile['email_login'] ?? '-'))));
    $resolvedPhone = trim((string) ($profile['phone_number'] ?: ($personal['whatsapp'] ?? '-')));
    $age = !empty($personal['date_of_birth']) ? \Illuminate\Support\Carbon::parse($personal['date_of_birth'])->age : null;

    $photoDocument = $documentMeta($personal['photo_path'] ?? null);
    $ktpDocument = $documentMeta($personal['ktp_path'] ?? null);
    $cvDocument = $documentMeta($personal['cv_path'] ?? null);

    $summaryCards = [
        ['label' => 'Status kerja', 'value' => strtoupper((string) ($profile['status_employment'] ?: '-')), 'tone' => 'slate'],
        ['label' => 'Progress profil', 'value' => ($completion['percentage'] ?? 0) . '%', 'tone' => ($completion['percentage'] ?? 0) >= 100 ? 'emerald' : 'amber'],
        ['label' => 'Payroll', 'value' => !empty($payroll['payroll_verified_at']) ? 'Terverifikasi' : 'Perlu dilengkapi', 'tone' => !empty($payroll['payroll_verified_at']) ? 'emerald' : 'amber'],
        ['label' => 'Rekening aktif', 'value' => (string) count($bankAccounts), 'tone' => 'blue'],
    ];

    $basicFacts = [
        'Nama Lengkap' => $resolvedName,
        'NIK' => $resolvedNik,
        'Email Login' => $profile['email_login'] ?: '-',
        'Email Kontak' => $resolvedEmail,
        'No. HP' => $resolvedPhone,
        'WhatsApp' => $personal['whatsapp'] ?? '-',
        'Tempat Lahir' => $personal['place_of_birth'] ?? '-',
        'Tanggal Lahir' => $formatDate($personal['date_of_birth'] ?? null),
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

    $employmentFacts = [
        'No. Karyawan' => $profile['employee_number'] ?: '-',
        'Departemen' => $profile['department'] ?: '-',
        'Jabatan' => $profile['position'] ?: '-',
        'Outlet' => $profile['outlet'] ?: '-',
        'Join Date' => $formatDate($profile['join_date'] ?? null),
        'Akhir Probation' => $formatDate($profile['probation_end_date'] ?? null),
        'Gaji Saat Ini' => $formatCurrency($profile['current_salary'] ?? null),
        'Payroll Verified' => !empty($payroll['payroll_verified_at']) ? $formatDateTime($payroll['payroll_verified_at']) : '-',
        'SIM' => $payroll['sim_number'] ?? '-',
        'NPWP' => $payroll['npwp_number'] ?? '-',
        'BPJS Kesehatan' => $payroll['bpjs_kes_number'] ?? '-',
        'BPJS Ketenagakerjaan' => $payroll['bpjs_tk_number'] ?? '-',
        'Passport' => $payroll['passport_number'] ?? '-',
        'Kartu Keluarga' => $payroll['kk_number'] ?? '-',
    ];
@endphp

<div class="space-y-6">
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
        <aside class="xl:col-span-4 space-y-6">
            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800 text-white shadow-sm">
                <div class="p-6">
                    <div class="mx-auto h-36 w-36 overflow-hidden rounded-full border-4 border-white/10 bg-white/10 shadow-inner">
                        @if($photoDocument['exists'] && $photoDocument['is_image'])
                            <img src="{{ $photoDocument['url'] }}" alt="Pas foto {{ $resolvedName }}" class="h-full w-full object-cover">
                        @else
                            <div class="flex h-full items-center justify-center text-4xl font-bold text-white/80">{{ Str::upper(Str::substr((string) $resolvedName, 0, 1)) }}</div>
                        @endif
                    </div>
                    <div class="mt-6 text-center">
                        <div class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-300">Employee Profile</div>
                        <h1 class="mt-3 text-3xl font-bold tracking-tight">{{ $resolvedName }}</h1>
                        <p class="mt-2 text-sm text-slate-300">{{ $age !== null ? $age . ' tahun' : 'Usia belum tersedia' }}</p>
                    </div>
                    <div class="mt-5 flex flex-wrap justify-center gap-2 text-xs font-semibold">
                        <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1.5">{{ strtoupper((string) ($profile['status_employment'] ?: '-')) }}</span>
                        <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1.5">{{ $profile['department'] ?: 'Departemen belum diatur' }}</span>
                        <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1.5">{{ $profile['position'] ?: 'Jabatan belum diatur' }}</span>
                    </div>
                    <div class="mt-6 grid grid-cols-1 gap-3 text-sm text-slate-200">
                        <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-300">NIK</div>
                            <div class="mt-2 font-semibold text-white">{{ $resolvedNik !== '' ? $resolvedNik : '-' }}</div>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-300">Email Kontak</div>
                            <div class="mt-2 break-all font-semibold text-white">{{ $resolvedEmail !== '' ? $resolvedEmail : '-' }}</div>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-300">No. HP</div>
                            <div class="mt-2 font-semibold text-white">{{ $resolvedPhone !== '' ? $resolvedPhone : '-' }}</div>
                        </div>
                    </div>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ route('employee-profile.edit') }}" class="inline-flex flex-1 items-center justify-center rounded-2xl bg-white px-5 py-3 text-sm font-semibold text-slate-900 transition hover:bg-slate-100">Edit Data Lengkap</a>
                        <a href="{{ route('probation-onboarding.edit') }}" class="inline-flex flex-1 items-center justify-center rounded-2xl border border-white/15 bg-white/5 px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/10">Payroll & Rekening</a>
                    </div>
                </div>
            </section>

            <section class="card p-6">
                <div class="mb-5 flex items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-slate-900">Dokumen Profile</h2>
                    <span class="text-sm text-slate-500">Candidate profile parity</span>
                </div>
                <div class="space-y-4">
                    @foreach([
                        ['label' => 'Pas Foto', 'meta' => $photoDocument],
                        ['label' => 'Scan KTP', 'meta' => $ktpDocument],
                        ['label' => 'CV', 'meta' => $cvDocument],
                    ] as $document)
                        <div class="overflow-hidden rounded-3xl border border-slate-200 bg-slate-50">
                            <div class="flex h-48 items-center justify-center border-b border-slate-200 bg-white p-4">
                                @if($document['meta']['exists'] && $document['meta']['is_image'])
                                    <img src="{{ $document['meta']['url'] }}" alt="{{ $document['label'] }}" class="h-full w-full rounded-2xl object-contain">
                                @elseif($document['meta']['exists'])
                                    <div class="flex flex-col items-center justify-center text-center text-slate-600">
                                        <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-lg font-bold text-slate-700">{{ $document['meta']['extension'] }}</div>
                                        <div class="mt-3 text-sm font-semibold">Dokumen tersimpan</div>
                                        <div class="text-xs text-slate-500">Preview file non-gambar dibuka melalui link.</div>
                                    </div>
                                @else
                                    <div class="flex flex-col items-center justify-center text-center text-slate-400">
                                        <div class="flex h-16 w-16 items-center justify-center rounded-2xl border border-dashed border-slate-300 text-2xl">+</div>
                                        <div class="mt-3 text-sm font-medium">Belum ada dokumen</div>
                                    </div>
                                @endif
                            </div>
                            <div class="flex items-center justify-between gap-3 px-4 py-4">
                                <div>
                                    <div class="text-sm font-semibold text-slate-900">{{ $document['label'] }}</div>
                                    <div class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-500">{{ $document['meta']['exists'] ? $document['meta']['extension'] : 'EMPTY' }}</div>
                                </div>
                                @if($document['meta']['exists'])
                                    <a href="{{ $document['meta']['url'] }}" target="_blank" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100">Buka File</a>
                                @else
                                    <span class="text-sm text-slate-400">Belum diunggah</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        </aside>

        <section class="xl:col-span-8 space-y-6">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach($summaryCards as $card)
                    @php
                        $toneClasses = [
                            'slate' => 'border-slate-200 bg-white text-slate-900',
                            'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
                            'amber' => 'border-amber-200 bg-amber-50 text-amber-900',
                            'blue' => 'border-blue-200 bg-blue-50 text-blue-900',
                        ][$card['tone']] ?? 'border-slate-200 bg-white text-slate-900';
                    @endphp
                    <div class="rounded-3xl border p-5 shadow-sm {{ $toneClasses }}">
                        <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">{{ $card['label'] }}</div>
                        <div class="mt-3 text-2xl font-bold">{{ $card['value'] }}</div>
                    </div>
                @endforeach
            </div>

            @if($pendingRequest || $latestRejected || $latestApproved)
                <section class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                    @if($pendingRequest)
                        <div class="rounded-3xl border border-amber-300 bg-amber-50 p-5 text-amber-950 shadow-sm">
                            <div class="text-xs font-semibold uppercase tracking-[0.22em] text-amber-700">Pending HRD</div>
                            <div class="mt-2 text-lg font-semibold">Pengajuan perubahan sedang diproses</div>
                            <p class="mt-2 text-sm leading-6">Request terakhir dikirim pada {{ $formatDateTime(optional($pendingRequest)->submitted_at) }}. Data utama Anda belum berubah sampai HRD menyetujui pengajuan ini.</p>
                        </div>
                    @endif
                    @if($latestRejected)
                        <div class="rounded-3xl border border-rose-300 bg-rose-50 p-5 text-rose-950 shadow-sm">
                            <div class="text-xs font-semibold uppercase tracking-[0.22em] text-rose-700">Revisi Dibutuhkan</div>
                            <div class="mt-2 text-lg font-semibold">Pengajuan terakhir ditolak</div>
                            <p class="mt-2 text-sm leading-6">Alasan HRD: {{ $latestRejected->review_note ?: '-' }}</p>
                        </div>
                    @endif
                    @if($latestApproved)
                        <div class="rounded-3xl border border-emerald-300 bg-emerald-50 p-5 text-emerald-950 shadow-sm">
                            <div class="text-xs font-semibold uppercase tracking-[0.22em] text-emerald-700">Approved</div>
                            <div class="mt-2 text-lg font-semibold">Pengajuan terakhir disetujui</div>
                            <p class="mt-2 text-sm leading-6">Disetujui pada {{ $formatDateTime(optional($latestApproved)->reviewed_at) }}.</p>
                        </div>
                    @endif
                </section>
            @endif

            @if($missingSections->isNotEmpty())
                <div class="rounded-3xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.22em] text-amber-700">Masih Perlu Dilengkapi</div>
                            <h2 class="mt-2 text-lg font-semibold text-amber-950">Beberapa bagian profil belum lengkap</h2>
                            <p class="mt-2 max-w-3xl text-sm leading-6 text-amber-900">Lengkapi bagian berikut agar profil employee tetap sejalan dengan candidate profile dan memudahkan HRD melakukan verifikasi.</p>
                        </div>
                        <a href="{{ route('application-form.edit') }}" class="inline-flex items-center justify-center rounded-2xl bg-amber-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-amber-950">Lengkapi di Application Form</a>
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                        @foreach($missingSections as $section)
                            <div class="rounded-2xl border border-amber-200 bg-white/70 p-4 text-sm text-amber-950">
                                <div class="font-semibold">{{ $section['label'] }}</div>
                                <div class="mt-2 text-amber-800">{{ collect($section['fields'] ?? [])->take(2)->implode(', ') }}{{ collect($section['fields'] ?? [])->count() > 2 ? ' dan lainnya' : '' }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($applicantProfile)
                <div class="rounded-3xl border border-blue-200 bg-blue-50 p-5 shadow-sm">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-700">Kandidat ke Karyawan</div>
                            <h2 class="mt-2 text-lg font-semibold text-blue-950">Employee profile ini meneruskan candidate profile</h2>
                            <p class="mt-2 max-w-3xl text-sm leading-6 text-blue-900">Saat data master employee belum lengkap, sistem otomatis membaca biodata, keluarga, pendidikan, bahasa, kursus, pengalaman kerja, organisasi, riwayat medis, sosial media, serta dokumen dari applicant profile yang sebelumnya diisi saat masih menjadi kandidat.</p>
                        </div>
                        <a href="{{ route('application-form.edit') }}" class="inline-flex items-center justify-center rounded-2xl border border-blue-300 bg-white px-4 py-2 text-sm font-semibold text-blue-900 transition hover:bg-blue-100">Lengkapi Format Terbaru</a>
                    </div>
                </div>
            @endif

            <section class="card p-6">
                <h2 class="text-xl font-semibold text-slate-900">Informasi Dasar</h2>
                <p class="mt-1 text-sm text-slate-600">Field employee diprioritaskan, lalu otomatis fallback ke candidate/applicant profile bila kosong.</p>
                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($basicFacts as $label => $value)
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ $label }}</div>
                            <div class="mt-2 text-sm font-medium leading-6 text-slate-900">{{ filled($value) ? $value : '-' }}</div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="card p-6">
                <div class="mb-5 flex items-center justify-between gap-3">
                    <h2 class="text-xl font-semibold text-slate-900">Keluarga</h2>
                    <span class="text-sm text-slate-500">{{ count($families) }} data</span>
                </div>
                @if(empty($families))
                    <div class="rounded-2xl border border-dashed border-slate-300 p-4 text-sm text-slate-500">Belum ada data keluarga yang bisa dibawa dari candidate profile.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                                <tr>
                                    <th class="px-4 py-3 text-left">Hubungan</th>
                                    <th class="px-4 py-3 text-left">Nama</th>
                                    <th class="px-4 py-3 text-left">Jenis Kelamin</th>
                                    <th class="px-4 py-3 text-left">Tanggal Lahir</th>
                                    <th class="px-4 py-3 text-left">Pendidikan</th>
                                    <th class="px-4 py-3 text-left">Pekerjaan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($families as $family)
                                    <tr class="border-b border-slate-100">
                                        <td class="px-4 py-3 font-semibold">{{ $family['relation'] ?: '-' }}</td>
                                        <td class="px-4 py-3">{{ $family['name'] ?: '-' }}</td>
                                        <td class="px-4 py-3">{{ $family['gender'] ?: '-' }}</td>
                                        <td class="px-4 py-3">{{ $family['dob'] ?: '-' }}</td>
                                        <td class="px-4 py-3">{{ $family['education'] ?: '-' }}</td>
                                        <td class="px-4 py-3">{{ $family['job'] ?: '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="card p-6">
                <h2 class="text-xl font-semibold text-slate-900">Riwayat Pendidikan</h2>
                <div class="mt-5 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                            <tr>
                                <th class="px-4 py-3 text-left">Jenjang</th>
                                <th class="px-4 py-3 text-left">Institusi</th>
                                <th class="px-4 py-3 text-left">Jurusan</th>
                                <th class="px-4 py-3 text-left">Tahun Masuk</th>
                                <th class="px-4 py-3 text-left">Tahun Lulus</th>
                                <th class="px-4 py-3 text-left">Nilai/IPK</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($educations as $education)
                                <tr class="border-b border-slate-100">
                                    <td class="px-4 py-3">{{ $education['level'] ?: '-' }}</td>
                                    <td class="px-4 py-3 font-semibold">{{ $education['school'] ?: '-' }}</td>
                                    <td class="px-4 py-3">{{ $education['major'] ?: '-' }}</td>
                                    <td class="px-4 py-3">{{ $education['year_in'] ?: '-' }}</td>
                                    <td class="px-4 py-3">{{ $education['year_out'] ?: '-' }}</td>
                                    <td class="px-4 py-3">{{ $education['gpa'] ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-3 text-slate-400">Belum ada riwayat pendidikan.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card p-6">
                <h2 class="text-xl font-semibold text-slate-900">Bahasa & Kursus</h2>
                <div class="mt-5 grid grid-cols-1 gap-6 xl:grid-cols-2">
                    <div>
                        <div class="mb-3 text-sm font-semibold text-slate-900">Kemampuan Bahasa</div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                                    <tr>
                                        <th class="px-4 py-3 text-left">Bahasa</th>
                                        <th class="px-4 py-3 text-left">Lisan</th>
                                        <th class="px-4 py-3 text-left">Tulisan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($languages as $language)
                                        <tr class="border-b border-slate-100">
                                            <td class="px-4 py-3">{{ $language['language'] ?: '-' }}</td>
                                            <td class="px-4 py-3">{{ $language['speaking'] ?: '-' }}</td>
                                            <td class="px-4 py-3">{{ $language['writing'] ?: '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="px-4 py-3 text-slate-400">Belum ada data bahasa.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div>
                        <div class="mb-3 text-sm font-semibold text-slate-900">Kursus & Pelatihan</div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                                    <tr>
                                        <th class="px-4 py-3 text-left">Nama Kursus</th>
                                        <th class="px-4 py-3 text-left">Penyelenggara</th>
                                        <th class="px-4 py-3 text-left">Tahun</th>
                                        <th class="px-4 py-3 text-left">Sertifikat</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($courses as $course)
                                        <tr class="border-b border-slate-100">
                                            <td class="px-4 py-3">{{ $course['name'] ?: '-' }}</td>
                                            <td class="px-4 py-3">{{ $course['organizer'] ?: '-' }}</td>
                                            <td class="px-4 py-3">{{ $course['year'] ?: '-' }}</td>
                                            <td class="px-4 py-3">{{ $course['certificate'] ?: '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="px-4 py-3 text-slate-400">Belum ada data kursus & pelatihan.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card p-6">
                <h2 class="text-xl font-semibold text-slate-900">Pengalaman, Referensi, & Organisasi</h2>
                <div class="mt-5 space-y-6">
                    <div>
                        <div class="mb-3 text-sm font-semibold text-slate-900">Riwayat Pekerjaan</div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                                    <tr>
                                        <th class="px-4 py-3 text-left">Perusahaan</th>
                                        <th class="px-4 py-3 text-left">Jabatan</th>
                                        <th class="px-4 py-3 text-left">Periode</th>
                                        <th class="px-4 py-3 text-left">Gaji</th>
                                        <th class="px-4 py-3 text-left">Alasan/Catatan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($workExperiences as $experience)
                                        <tr class="border-b border-slate-100">
                                            <td class="px-4 py-3">{{ $experience['company'] ?: '-' }}</td>
                                            <td class="px-4 py-3">{{ $experience['position'] ?: '-' }}</td>
                                            <td class="px-4 py-3">{{ $experience['date_start'] ?: '-' }} - {{ $experience['date_end'] ?: '-' }}</td>
                                            <td class="px-4 py-3">{{ $experience['salary'] ?: '-' }}</td>
                                            <td class="px-4 py-3">{{ $experience['reason'] ?: '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="px-4 py-3 text-slate-400">Belum ada riwayat pekerjaan.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                        <div>
                            <div class="mb-3 text-sm font-semibold text-slate-900">Kontak Referensi</div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                                        <tr>
                                            <th class="px-4 py-3 text-left">Nama</th>
                                            <th class="px-4 py-3 text-left">Hubungan</th>
                                            <th class="px-4 py-3 text-left">Perusahaan</th>
                                            <th class="px-4 py-3 text-left">No. HP</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($referenceContacts as $reference)
                                            <tr class="border-b border-slate-100">
                                                <td class="px-4 py-3">{{ $reference['name'] ?: '-' }}</td>
                                                <td class="px-4 py-3">{{ $reference['relation'] ?: '-' }}</td>
                                                <td class="px-4 py-3">{{ $reference['company'] ?: '-' }}</td>
                                                <td class="px-4 py-3">{{ $reference['phone'] ?: '-' }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4" class="px-4 py-3 text-slate-400">Belum ada data kontak referensi.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div>
                            <div class="mb-3 text-sm font-semibold text-slate-900">Pengalaman Organisasi</div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                                        <tr>
                                            <th class="px-4 py-3 text-left">Nama Organisasi</th>
                                            <th class="px-4 py-3 text-left">Jabatan</th>
                                            <th class="px-4 py-3 text-left">Tahun</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($organizations as $organization)
                                            <tr class="border-b border-slate-100">
                                                <td class="px-4 py-3">{{ $organization['name'] ?: '-' }}</td>
                                                <td class="px-4 py-3">{{ $organization['role'] ?: '-' }}</td>
                                                <td class="px-4 py-3">{{ $organization['year'] ?: '-' }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="3" class="px-4 py-3 text-slate-400">Belum ada data organisasi.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card p-6">
                <h2 class="text-xl font-semibold text-slate-900">Riwayat Medis & Sosial Media</h2>
                <div class="mt-5 grid grid-cols-1 gap-6 xl:grid-cols-2">
                    <div>
                        <div class="mb-3 text-sm font-semibold text-slate-900">Riwayat Medis</div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                                    <tr>
                                        <th class="px-4 py-3 text-left">Penyakit</th>
                                        <th class="px-4 py-3 text-left">Tahun</th>
                                        <th class="px-4 py-3 text-left">Rawat Inap</th>
                                        <th class="px-4 py-3 text-left">Catatan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($medicalHistories as $medical)
                                        <tr class="border-b border-slate-100">
                                            <td class="px-4 py-3">{{ $medical['illness'] ?: '-' }}</td>
                                            <td class="px-4 py-3">{{ $medical['year'] ?: '-' }}</td>
                                            <td class="px-4 py-3">{{ $medical['hospitalized'] ?: '-' }}</td>
                                            <td class="px-4 py-3">{{ $medical['note'] ?: '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="px-4 py-3 text-slate-400">Belum ada riwayat medis.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div>
                        <div class="mb-3 text-sm font-semibold text-slate-900">Sosial Media</div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                                    <tr>
                                        <th class="px-4 py-3 text-left">Platform</th>
                                        <th class="px-4 py-3 text-left">Username / Link</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($socialMedias as $social)
                                        <tr class="border-b border-slate-100">
                                            <td class="px-4 py-3">{{ $social['platform'] ?: '-' }}</td>
                                            <td class="px-4 py-3 break-all">{{ $social['handle'] ?: '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="2" class="px-4 py-3 text-slate-400">Belum ada data sosial media.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card p-6">
                <h2 class="text-xl font-semibold text-slate-900">Data Karyawan & Payroll</h2>
                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($employmentFacts as $label => $value)
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ $label }}</div>
                            <div class="mt-2 text-sm font-medium leading-6 text-slate-900">{{ filled($value) ? $value : '-' }}</div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="card p-6">
                <div class="mb-5 flex items-center justify-between gap-3">
                    <h2 class="text-xl font-semibold text-slate-900">Ringkasan Appraisal</h2>
                    <a href="{{ route('appraisals.my') }}" class="text-sm font-semibold text-brand underline">Lihat semua</a>
                </div>
                @if($appraisals->isEmpty())
                    <div class="rounded-2xl border border-dashed border-slate-300 p-4 text-sm text-slate-500">Belum ada appraisal yang ditugaskan ke akun Anda saat ini.</div>
                @else
                    <div class="space-y-3">
                        @foreach($appraisals->take(3) as $appraisal)
                            <div class="rounded-2xl border border-slate-200 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-semibold text-slate-900">{{ $appraisal->period?->name ?? 'Periode appraisal' }}</div>
                                        <div class="mt-1 text-sm text-slate-500">Status: {{ strtoupper((string) ($appraisal->status ?? 'draft')) }}</div>
                                    </div>
                                    <a href="{{ route('appraisals.show', $appraisal) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-medium hover:bg-slate-50">Detail</a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="card p-6">
                <div class="mb-5 flex items-center justify-between gap-3">
                    <h2 class="text-xl font-semibold text-slate-900">Rekening Bank</h2>
                    <span class="text-sm text-slate-500">{{ count($bankAccounts) }} aktif</span>
                </div>
                <div class="space-y-3">
                    @forelse($bankAccounts as $account)
                        <div class="rounded-2xl border border-slate-200 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-semibold text-slate-900">{{ $account['bank_name'] ?: '-' }}</div>
                                    <div class="mt-1 text-sm text-slate-500">{{ $account['account_number'] ?: '-' }}</div>
                                    <div class="mt-1 text-sm text-slate-500">a.n. {{ $account['account_holder_name'] ?: '-' }}</div>
                                </div>
                                @if($account['is_primary'])
                                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">Utama</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 p-4 text-sm text-slate-500">Belum ada rekening bank yang aktif. Lengkapi dari halaman payroll & rekening.</div>
                    @endforelse
                </div>
            </section>
        </section>
    </div>
</div>
@endsection

