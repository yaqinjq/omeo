
@extends('layouts.app')

@section('content')
@php
    $personal = $formData['personal'] ?? [];
    $address = $formData['address'] ?? [];
    $payroll = $formData['payroll'] ?? [];

    $families = old('families', $formData['families'] ?? [[]]);
    $educations = old('educations', $formData['educations'] ?? [[]]);
    $languages = old('languages', $formData['languages'] ?? [[]]);
    $courses = old('courses', $formData['courses'] ?? [[]]);
    $workExperiences = old('work_experiences', $formData['work_experiences'] ?? [[]]);
    $referenceContacts = old('reference_contacts', data_get($personal, 'reference_contacts', [[]]));
    $organizations = old('organizations', $formData['organizations'] ?? [[]]);
    $medicalHistories = old('medical_histories', $formData['medical_histories'] ?? [[]]);
    $socialMedias = old('social_medias', $formData['social_medias'] ?? [[]]);

    $storageUrl = static fn (?string $path) => $path ? asset('storage/' . ltrim($path, '/')) : null;
    $makeDocument = static function (string $label, string $field, string $accept, string $hint, ?string $path, string $inputId, string $previewId, string $placeholderId, string $metaId) use ($storageUrl): array {
        $extension = strtolower((string) pathinfo((string) $path, PATHINFO_EXTENSION));
        return [
            'label' => $label,
            'field' => $field,
            'accept' => $accept,
            'hint' => $hint,
            'path' => $path,
            'url' => $storageUrl($path),
            'extension' => $extension !== '' ? strtoupper($extension) : '-',
            'is_image' => in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true),
            'exists' => filled($path),
            'input_id' => $inputId,
            'preview_id' => $previewId,
            'placeholder_id' => $placeholderId,
            'meta_id' => $metaId,
        ];
    };

    $documents = [
        $makeDocument('Pas Foto', 'photo_ktp_file', '.jpg,.jpeg,.png,.pdf,image/*,application/pdf', 'JPG, PNG, PDF • maks 2MB', $personal['photo_path'] ?? null, 'photoKtpFile', 'photoPreview', 'photoPlaceholder', 'photoMeta'),
        $makeDocument('Scan KTP', 'scan_ktp_file', '.jpg,.jpeg,.png,.pdf,image/*,application/pdf', 'JPG, PNG, PDF • maks 2MB', $personal['ktp_path'] ?? null, 'scanKtpFile', 'ktpPreview', 'ktpPlaceholder', 'ktpMeta'),
        $makeDocument('CV', 'cv_file', '.pdf,application/pdf', 'PDF • maks 5MB', $personal['cv_path'] ?? null, 'cvFile', 'cvPreview', 'cvPlaceholder', 'cvMeta'),
    ];

    $genderOptions = ['Laki-laki', 'Perempuan'];
    $religionOptions = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'];
    $bloodTypeOptions = ['A', 'B', 'AB', 'O'];
    $maritalOptions = ['Belum Menikah', 'Menikah', 'Cerai Hidup', 'Cerai Mati'];
    $payrollDocuments = [
        ['number' => 'sim_number', 'file' => 'sim_file', 'label' => 'SIM'],
        ['number' => 'npwp_number', 'file' => 'npwp_file', 'label' => 'NPWP'],
        ['number' => 'bpjs_kes_number', 'file' => 'bpjs_kes_file', 'label' => 'BPJS Kesehatan'],
        ['number' => 'bpjs_tk_number', 'file' => 'bpjs_tk_file', 'label' => 'BPJS Ketenagakerjaan'],
        ['number' => 'passport_number', 'file' => 'passport_file', 'label' => 'Passport'],
        ['number' => 'kk_number', 'file' => 'kk_file', 'label' => 'Kartu Keluarga'],
    ];
@endphp
<div class="space-y-6">
    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-gradient-to-br from-white via-slate-50 to-slate-100 shadow-sm">
        <div class="grid gap-6 px-6 py-6 xl:grid-cols-[1.5fr_1fr] xl:px-8 xl:py-8">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.26em] text-slate-500">Profile Change Request</div>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900">Edit My Profile</h1>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600">Perubahan dari halaman ini tidak langsung menimpa data utama. Sistem akan membuat pengajuan perubahan dan menunggu verifikasi HRD agar flow existing tetap aman.</p>
                <div class="mt-5 flex flex-wrap gap-3">
                    <a href="{{ route('employee-profile.show') }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Kembali ke My Profile</a>
                    <a href="{{ route('probation-onboarding.edit') }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Kelola Payroll & Rekening</a>
                </div>
            </div>
            <div class="space-y-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Status Submit</div>
                <div class="text-lg font-semibold text-slate-900">{{ $pendingRequest ? 'Ada pengajuan yang sedang diproses' : 'Siap kirim ke HRD' }}</div>
                <p class="text-sm leading-6 text-slate-600">{{ $pendingRequest ? 'Selama request sebelumnya masih pending, Anda tetap bisa meninjau form tetapi submit baru akan ditahan.' : 'Pastikan data teks, dokumen, dan lampiran payroll sudah lengkap sebelum mengirim pengajuan.' }}</p>
                <div class="space-y-2 text-sm text-slate-600">
                    <div class="rounded-2xl bg-slate-50 px-4 py-3">Data utama employee tidak berubah langsung.</div>
                    <div class="rounded-2xl bg-slate-50 px-4 py-3">File baru akan menggantikan file lama pada saat pengajuan disetujui.</div>
                    <div class="rounded-2xl bg-slate-50 px-4 py-3">Gunakan satu pengajuan untuk seluruh perubahan agar HRD mudah memverifikasi.</div>
                </div>
            </div>
        </div>
    </section>

    @if($errors->any())
        <div class="rounded-3xl border border-rose-300 bg-rose-50 p-5 text-rose-950 shadow-sm">
            <div class="text-sm font-semibold">Form belum bisa dikirim karena masih ada data yang perlu diperbaiki.</div>
            <ul class="mt-3 list-disc space-y-1 pl-5 text-sm">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($pendingRequest)
        <div class="rounded-3xl border border-amber-300 bg-amber-50 p-5 text-sm text-amber-950 shadow-sm">
            Masih ada pengajuan edit data yang sedang menunggu verifikasi HRD. Anda belum dapat mengirim pengajuan baru sebelum request sebelumnya diproses.
        </div>
    @endif

    <div class="rounded-3xl border border-blue-200 bg-blue-50 p-5 text-sm text-blue-950 shadow-sm">Halaman ini sekarang diposisikan sebagai pusat pengajuan edit data karyawan. Untuk melengkapi format kandidat terbaru, Anda juga bisa buka <a href="{{ route('application-form.edit') }}" class="font-semibold underline">Application Form</a>.</div>

<form action="{{ route('employee-profile.update') }}" method="POST" enctype="multipart/form-data" class="space-y-6" id="employeeProfileEditForm">
        @csrf

        <section class="card p-6 space-y-5">
            <div class="flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">1. Data Pribadi & Dokumen</h2>
                    <p class="mt-1 text-sm text-slate-600">Tampilan dibuat lebih dekat ke application form agar familiar, tetapi tetap tunduk pada flow approval HRD.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">Login email: <span class="font-semibold text-slate-900">{{ $snapshot['profile']['email_login'] }}</span></div>
            </div>

            <div class="grid grid-cols-1 gap-5 xl:grid-cols-3">
                @foreach($documents as $document)
                    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-slate-50 shadow-sm">
                        <div class="flex h-56 items-center justify-center border-b border-slate-200 bg-white p-4">
                            @if($document['exists'] && $document['is_image'])
                                <img id="{{ $document['preview_id'] }}" src="{{ $document['url'] }}" alt="{{ $document['label'] }}" class="h-full w-full rounded-2xl object-contain">
                                <div id="{{ $document['placeholder_id'] }}" class="hidden"></div>
                            @elseif($document['exists'])
                                <img id="{{ $document['preview_id'] }}" src="" alt="{{ $document['label'] }}" class="hidden h-full w-full rounded-2xl object-contain">
                                <div id="{{ $document['placeholder_id'] }}" class="flex flex-col items-center justify-center text-center text-slate-600">
                                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-lg font-bold text-slate-700">{{ $document['extension'] }}</div>
                                    <div class="mt-3 text-sm font-semibold">{{ $document['label'] }} saat ini tersedia</div>
                                    <div class="mt-1 text-xs text-slate-500">Preview file non-gambar dibuka melalui link di bawah.</div>
                                </div>
                            @else
                                <img id="{{ $document['preview_id'] }}" src="" alt="{{ $document['label'] }}" class="hidden h-full w-full rounded-2xl object-contain">
                                <div id="{{ $document['placeholder_id'] }}" class="flex flex-col items-center justify-center text-center text-slate-400">
                                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl border border-dashed border-slate-300 text-2xl">+</div>
                                    <div class="mt-3 text-sm font-semibold text-slate-700">{{ $document['label'] }}</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ $document['hint'] }}</div>
                                </div>
                            @endif
                        </div>
                        <div class="space-y-3 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-sm font-semibold text-slate-900">{{ $document['label'] }}</div>
                                    <div id="{{ $document['meta_id'] }}" class="mt-1 text-xs uppercase tracking-[0.16em] text-slate-500">{{ $document['exists'] ? $document['extension'] : 'BELUM ADA FILE' }}</div>
                                </div>
                                @if($document['exists'])
                                    <a href="{{ $document['url'] }}" target="_blank" class="text-sm font-medium text-brand underline">Buka</a>
                                @endif
                            </div>
                            <input type="file" id="{{ $document['input_id'] }}" name="{{ $document['field'] }}" accept="{{ $document['accept'] }}" data-preview-id="{{ $document['preview_id'] }}" data-placeholder-id="{{ $document['placeholder_id'] }}" data-meta-id="{{ $document['meta_id'] }}" class="block w-full rounded-2xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 file:mr-4 file:rounded-xl file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-slate-800">
                            @error($document['field'])<p class="text-sm text-rose-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div class="xl:col-span-2">
                    <label class="text-sm font-semibold text-slate-700">Nama Lengkap</label>
                    <input type="text" name="full_name" value="{{ old('full_name', $personal['full_name'] ?? '') }}" class="mt-2 w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm shadow-sm" required>
                    @error('full_name')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">NIK</label>
                    <input type="text" name="ktp_number" value="{{ old('ktp_number', $personal['ktp_number'] ?? '') }}" class="mt-2 w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm shadow-sm" required>
                    @error('ktp_number')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Email Kontak Pribadi</label>
                    <input type="email" name="email_private" value="{{ old('email_private', $snapshot['profile']['email_private'] === '-' ? '' : $snapshot['profile']['email_private']) }}" class="mt-2 w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm shadow-sm">
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">No. HP</label>
                    <input type="text" name="phone_number" value="{{ old('phone_number', $snapshot['profile']['phone_number'] === '-' ? '' : $snapshot['profile']['phone_number']) }}" class="mt-2 w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm shadow-sm" required>
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Tempat Lahir</label>
                    <input type="text" name="place_of_birth" value="{{ old('place_of_birth', $personal['place_of_birth'] ?? '') }}" class="mt-2 w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm shadow-sm" required>
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Tanggal Lahir</label>
                    <input type="date" name="date_of_birth" value="{{ old('date_of_birth', $personal['date_of_birth'] ?? '') }}" class="mt-2 w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm shadow-sm" required>
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Jenis Kelamin</label>
                    <select name="gender" class="mt-2 w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm shadow-sm" required>
                        <option value="">Pilih jenis kelamin</option>
                        @foreach($genderOptions as $option)
                            <option value="{{ $option }}" @selected(old('gender', $personal['gender'] ?? '') === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Agama</label>
                    <select name="religion" class="mt-2 w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm shadow-sm" required>
                        <option value="">Pilih agama</option>
                        @foreach($religionOptions as $option)
                            <option value="{{ $option }}" @selected(old('religion', $personal['religion'] ?? '') === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Golongan Darah</label>
                    <select name="blood_type" class="mt-2 w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm shadow-sm">
                        <option value="">Pilih golongan darah</option>
                        @foreach($bloodTypeOptions as $option)
                            <option value="{{ $option }}" @selected(old('blood_type', $personal['blood_type'] ?? '') === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Status Pernikahan</label>
                    <select name="marital_status" class="mt-2 w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm shadow-sm" required>
                        <option value="">Pilih status</option>
                        @foreach($maritalOptions as $option)
                            <option value="{{ $option }}" @selected(old('marital_status', $personal['marital_status'] ?? '') === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Tanggal Menikah</label>
                    <input type="date" name="marriage_date" value="{{ old('marriage_date', $personal['marriage_date'] ?? '') }}" class="mt-2 w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm shadow-sm">
                </div>
                <div class="xl:col-span-2">
                    <label class="text-sm font-semibold text-slate-700">Nomor WhatsApp Aktif</label>
                    <input type="text" name="whatsapp" value="{{ old('whatsapp', $personal['whatsapp'] ?? '') }}" class="mt-2 w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm shadow-sm" required>
                </div>
            </div>
        </section>
        <section class="card p-6 space-y-5">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">2. Data Kontak & Alamat</h2>
                <p class="mt-1 text-sm text-slate-600">Pastikan alamat KTP dan domisili benar agar verifikasi HRD dan kebutuhan administrasi tidak terhambat.</p>
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="text-sm font-semibold text-slate-700">Alamat KTP</label>
                    <textarea name="ktp_address" rows="4" class="mt-2 w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm shadow-sm" required>{{ old('ktp_address', $address['ktp_address'] ?? '') }}</textarea>
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Provinsi</label>
                    <input type="text" name="ktp_province" value="{{ old('ktp_province', $address['ktp_province'] ?? '') }}" class="mt-2 w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm shadow-sm">
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Kota/Kabupaten</label>
                    <input type="text" name="ktp_city" value="{{ old('ktp_city', $address['ktp_city'] ?? '') }}" class="mt-2 w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm shadow-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm font-semibold text-slate-700">Alamat Domisili</label>
                    <textarea name="domicile_address" rows="4" class="mt-2 w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm shadow-sm" required>{{ old('domicile_address', $address['domicile_address'] ?? '') }}</textarea>
                </div>
            </div>
            @include('employee-profile.partials.repeatable-table', ['title' => 'Data Keluarga', 'name' => 'families', 'rows' => $families, 'columns' => [
                ['key' => 'relation', 'label' => 'Hubungan'],
                ['key' => 'name', 'label' => 'Nama'],
                ['key' => 'gender', 'label' => 'Jenis Kelamin'],
                ['key' => 'dob', 'label' => 'Tanggal Lahir', 'type' => 'date'],
                ['key' => 'education', 'label' => 'Pendidikan'],
                ['key' => 'job', 'label' => 'Pekerjaan'],
            ]])
        </section>

        <section class="card p-6 space-y-5">
            <h2 class="text-lg font-semibold text-slate-900">3. Pendidikan, Skill, dan Organisasi</h2>
            @include('employee-profile.partials.repeatable-table', ['title' => 'Riwayat Pendidikan', 'name' => 'educations', 'rows' => $educations, 'columns' => [
                ['key' => 'level', 'label' => 'Jenjang'],
                ['key' => 'school', 'label' => 'Sekolah / Kampus'],
                ['key' => 'major', 'label' => 'Jurusan'],
                ['key' => 'year_in', 'label' => 'Tahun Masuk'],
                ['key' => 'year_out', 'label' => 'Tahun Lulus'],
                ['key' => 'gpa', 'label' => 'Nilai / IPK'],
            ]])
            @include('employee-profile.partials.repeatable-table', ['title' => 'Kemampuan Bahasa', 'name' => 'languages', 'rows' => $languages, 'columns' => [
                ['key' => 'language', 'label' => 'Bahasa'],
                ['key' => 'speaking', 'label' => 'Lisan'],
                ['key' => 'writing', 'label' => 'Tulisan'],
            ]])
            @include('employee-profile.partials.repeatable-table', ['title' => 'Kursus & Pelatihan', 'name' => 'courses', 'rows' => $courses, 'columns' => [
                ['key' => 'name', 'label' => 'Nama Kursus'],
                ['key' => 'organizer', 'label' => 'Penyelenggara'],
                ['key' => 'year', 'label' => 'Tahun'],
                ['key' => 'certificate', 'label' => 'Sertifikat'],
            ]])
            @include('employee-profile.partials.repeatable-table', ['title' => 'Organisasi', 'name' => 'organizations', 'rows' => $organizations, 'columns' => [
                ['key' => 'name', 'label' => 'Nama Organisasi'],
                ['key' => 'role', 'label' => 'Peran / Jabatan'],
                ['key' => 'year', 'label' => 'Tahun'],
            ]])
        </section>

        <section class="card p-6 space-y-5">
            <h2 class="text-lg font-semibold text-slate-900">4. Karir & Referensi</h2>
            @include('employee-profile.partials.repeatable-table', ['title' => 'Riwayat Pekerjaan', 'name' => 'work_experiences', 'rows' => $workExperiences, 'columns' => [
                ['key' => 'company', 'label' => 'Perusahaan'],
                ['key' => 'position', 'label' => 'Jabatan'],
                ['key' => 'date_start', 'label' => 'Mulai', 'type' => 'date'],
                ['key' => 'date_end', 'label' => 'Selesai', 'type' => 'date'],
                ['key' => 'salary', 'label' => 'Gaji'],
                ['key' => 'reason', 'label' => 'Alasan Berhenti'],
            ]])
            @include('employee-profile.partials.repeatable-table', ['title' => 'Kontak Referensi', 'name' => 'reference_contacts', 'rows' => $referenceContacts, 'columns' => [
                ['key' => 'name', 'label' => 'Nama'],
                ['key' => 'relation', 'label' => 'Hubungan'],
                ['key' => 'company', 'label' => 'Perusahaan'],
                ['key' => 'phone', 'label' => 'Telepon'],
            ]])
        </section>

        <section class="card p-6 space-y-5">
            <h2 class="text-lg font-semibold text-slate-900">5. Medis & Sosial</h2>
            @include('employee-profile.partials.repeatable-table', ['title' => 'Riwayat Medis', 'name' => 'medical_histories', 'rows' => $medicalHistories, 'columns' => [
                ['key' => 'illness', 'label' => 'Penyakit'],
                ['key' => 'year', 'label' => 'Tahun'],
                ['key' => 'hospitalized', 'label' => 'Rawat Inap'],
                ['key' => 'note', 'label' => 'Catatan'],
            ]])
            @include('employee-profile.partials.repeatable-table', ['title' => 'Social Media', 'name' => 'social_medias', 'rows' => $socialMedias, 'columns' => [
                ['key' => 'platform', 'label' => 'Platform'],
                ['key' => 'handle', 'label' => 'Username / Link'],
            ]])
        </section>

        <section class="card p-6 space-y-5">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">6. Kelengkapan Payroll</h2>
                <p class="mt-1 text-sm text-slate-600">Nomor dan lampiran payroll tetap masuk ke alur approval yang sama. File baru akan dipakai setelah request disetujui HRD.</p>
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach($payrollDocuments as $document)
                    @php($currentPath = $payroll[$document['file']] ?? null)
                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                        <label class="text-sm font-semibold text-slate-700">{{ $document['label'] }}</label>
                        <input type="text" name="{{ $document['number'] }}" value="{{ old($document['number'], $payroll[$document['number']] ?? '') }}" class="mt-2 w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm shadow-sm">
                        <div class="mt-3 rounded-2xl border border-slate-200 bg-white p-3 text-sm text-slate-600">
                            @if($currentPath)
                                <div class="font-medium text-slate-900">Lampiran saat ini tersedia</div>
                                <a href="{{ $storageUrl($currentPath) }}" target="_blank" class="mt-1 inline-block text-brand underline">Buka lampiran saat ini</a>
                            @else
                                <div>Belum ada lampiran saat ini.</div>
                            @endif
                        </div>
                        <input type="file" name="{{ $document['file'] }}" class="mt-3 block w-full rounded-2xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 file:mr-4 file:rounded-xl file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-slate-800">
                    </div>
                @endforeach
            </div>
        </section>

        <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
            <a href="{{ route('employee-profile.show') }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Batal</a>
            <button type="submit" class="btn inline-flex items-center justify-center px-6 py-3 text-sm font-semibold" @if($pendingRequest) disabled @endif>Ajukan Pengajuan Edit Data ke HRD</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    function addRepeatableRow(name, encodedColumns) {
        const columns = JSON.parse(atob(encodedColumns));
        const wrapper = document.querySelector(`[data-repeatable-body="${name}"]`);
        const index = Date.now();
        if (!wrapper) return;

        const rowNumber = wrapper.querySelectorAll('[data-repeatable-row]').length + 1;
        let html = '<div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" data-repeatable-row>';
        html += `<div class="mb-4 flex items-center justify-between gap-3"><div class="text-sm font-semibold text-slate-900">Baris ${rowNumber}</div><button type="button" class="text-sm font-medium text-red-600" onclick="this.closest('[data-repeatable-row]').remove()">Hapus</button></div>`;
        html += '<div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">';
        columns.forEach((column) => {
            const type = column.type || 'text';
            html += `<label class="block text-sm"><span class="mb-1 block font-medium text-slate-700">${column.label}</span><input type="${type}" name="${name}[${index}][${column.key}]" class="w-full rounded-lg border px-3 py-2 text-sm"></label>`;
        });
        html += '</div></div>';
        wrapper.insertAdjacentHTML('beforeend', html);
    }

    function bindDocumentPreview(input) {
        const preview = document.getElementById(input.dataset.previewId);
        const placeholder = document.getElementById(input.dataset.placeholderId);
        const meta = document.getElementById(input.dataset.metaId);
        if (!preview || !placeholder || !meta) return;

        input.addEventListener('change', function () {
            const file = input.files && input.files[0];
            if (!file) return;

            const extension = (file.name.split('.').pop() || '').toUpperCase();
            meta.textContent = extension || file.name;

            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function (event) {
                    preview.src = event.target && event.target.result ? event.target.result : '';
                    preview.classList.remove('hidden');
                    placeholder.classList.add('hidden');
                };
                reader.readAsDataURL(file);
                return;
            }

            preview.src = '';
            preview.classList.add('hidden');
            placeholder.classList.remove('hidden');
            placeholder.innerHTML = `
                <div class="flex flex-col items-center justify-center text-center text-slate-600">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-lg font-bold text-slate-700">${extension || 'FILE'}</div>
                    <div class="mt-3 text-sm font-semibold">File baru siap diunggah</div>
                    <div class="mt-1 text-xs text-slate-500 break-all">${file.name}</div>
                </div>
            `;
        });
    }

    document.querySelectorAll('input[data-preview-id]').forEach(bindDocumentPreview);
</script>
@endpush



