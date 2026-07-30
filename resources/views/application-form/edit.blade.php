
@extends('layouts.app')

@section('content')
@php
$completion=$completion??$profile->getCompletionProgress();$missingSections=session('missing_sections',[]);$serverValidationErrors=$errors->getMessages();$firstErrorStep=(int)session('first_error_step',0);$personal=is_array($profile->personal_json)?$profile->personal_json:[];$address=is_array($profile->address_json)?$profile->address_json:[];$medical=is_array($profile->medical_json)?$profile->medical_json:[];$graduationDocs=(array)data_get($personal,'graduation_documents',[]);$signaturePath=data_get($personal,'signature_path');$temporaryUploadState=is_array($temporaryUploadState??null)?$temporaryUploadState:[];$heicConversionAvailable=(bool)($heicConversionAvailable??false);$imageDocumentAccept=$heicConversionAvailable?'.jpg,.jpeg,.png,.webp,.pdf,.heic,.heif,image/jpeg,image/png,image/webp,image/heic,image/heif,application/pdf':'.jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf';$imageDocumentNote=$heicConversionAvailable?'JPG, JPEG, PNG, WEBP, PDF, HEIC/HEIF | maks 4MB':'JPG, JPEG, PNG, WEBP, PDF | maks 4MB. HEIC/HEIF iPhone belum didukung server ini.';$pad=function($items,$min){$items=is_array($items)?array_values($items):[];while(count($items)<$min){$items[]=[];}return $items;};$educationItems=$pad(old('educations',$profile->educations),3);$emergencyItems=$pad(old('emergency_contacts',data_get($personal,'emergency_contacts',[])),2);$referenceItems=$pad(old('reference_contacts',data_get($personal,'reference_contacts',[])),2);$stepTitles=[1=>'Data Pribadi',2=>'Alamat & Keluarga',3=>'Pendidikan',4=>'Preferensi & Pengalaman',5=>'Kesehatan',6=>'Finalisasi'];$primaryUploadCards=[['label'=>'Pas Foto','name'=>'photo_ktp_file','value'=>$profile->photo_path,'accept'=>$imageDocumentAccept,'note'=>$imageDocumentNote],['label'=>'Scan KTP','name'=>'scan_ktp_file','value'=>$profile->ktp_path,'accept'=>$imageDocumentAccept,'note'=>$imageDocumentNote],['label'=>'CV PDF','name'=>'cv_file','value'=>$profile->cv_path,'accept'=>'.pdf,application/pdf','note'=>'PDF | maks 5MB']];
@endphp
<div class="min-h-screen bg-slate-50 pb-28 dark:bg-slate-950/40"><div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
<div class="mb-6 flex flex-col gap-4 lg:flex-row lg:justify-between"><div><h1 class="text-2xl font-black text-slate-900 dark:text-white sm:text-3xl">Application Form Kandidat</h1><p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">Mobile-first untuk smartphone dan iPhone. Isi data sesuai dokumen asli, simpan draft bila perlu, lalu kirim final setelah lengkap.</p></div><div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"><div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Status</div><div class="mt-2 text-lg font-bold text-slate-900 dark:text-white">{{ $profile->is_complete ? 'Lengkap' : 'Draft' }}</div><div class="mt-2 text-xs text-slate-500">{{ $completion['completed'] }}/{{ $completion['total'] }} bagian lengkap · {{ $completion['percentage'] }}%</div>@if($hasAdministrativeDocumentStage)<div class="mt-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[11px] font-semibold text-emerald-700">Dokumen lanjutan aktif: {{ strtoupper((string)($candidate?->status??'')) }}</div>@endif</div></div>
@if(session('success')||session('error')||$errors->any())<div class="mb-6 space-y-3">@if(session('success'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm text-emerald-800"><div class="font-semibold">Perubahan berhasil disimpan.</div><div class="mt-1">{{ session('success') }}</div></div>@endif @if(session('error')||$errors->any())<div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-4 text-sm text-red-800" id="applicationFormErrorSummary" tabindex="-1"><div class="font-semibold">Periksa kembali data application form Anda.</div>@if(session('error'))<div class="mt-1">{{ session('error') }}</div>@endif @if($errors->any())<ul class="mt-3 list-disc pl-5 space-y-1">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>@endif</div>@endif</div>@endif
@if(session('doc_reminder'))<div class="mb-6 rounded-2xl border border-yellow-300 bg-yellow-50 px-4 py-4 text-sm text-yellow-800"><p class="font-semibold">📎 Dokumen belum lengkap:</p><ul class="mt-1 list-disc pl-5 text-yellow-700">@foreach(session('doc_reminder') as $doc=>$missing)@if($missing)<li>{{ ucfirst(str_replace('_',' ',$doc)) }}</li>@endif @endforeach</ul><p class="mt-1 text-xs text-yellow-600">Data Anda sudah tersimpan. Silakan lengkapi dokumen ini sesegera mungkin.</p></div>@endif
<div class="grid grid-cols-1 gap-6 lg:grid-cols-[280px_minmax(0,1fr)]"><aside class="lg:sticky lg:top-6 lg:self-start"><div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"><div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Panduan</div><p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">Gunakan ikon <b>i</b> di tiap bagian untuk melihat instruksi singkat.</p></div><nav class="space-y-1 p-3">@foreach($stepTitles as $step=>$label)<button type="button" onclick="changeStep({{ $step }})" id="sidebar-step-{{ $step }}" class="sidebar-step flex w-full items-center justify-between rounded-2xl px-4 py-3 text-left text-sm font-semibold text-slate-600 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800/70"><span>{{ $step }}. {{ $label }}</span><span class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-xs dark:border-slate-700 dark:bg-slate-800">{{ $step }}</span></button>@endforeach</nav></div></aside>
<div class="min-w-0"><form id="appForm" method="POST" action="{{ route('application-form.update') }}" enctype="multipart/form-data" class="space-y-6">@csrf<input type="hidden" name="final_submit" id="finalSubmitFlag" value="0"><input type="hidden" name="current_step" id="applicationFormCurrentStep" value="{{ $firstErrorStep>0?$firstErrorStep:1 }}"><input type="hidden" name="signature_data" id="signatureDataInput" value="{{ old('signature_data','') }}"><input type="hidden" name="signature_cleared" id="signatureClearedFlag" value="{{ old('signature_cleared','0') }}">@foreach($primaryUploadCards as $card)<input type="hidden" name="{{ $card['name'] }}_token" value="{{ old($card['name'].'_token',data_get($temporaryUploadState,$card['name'].'.token','')) }}" data-upload-token="{{ $card['name'] }}">@endforeach
<section class="form-step" id="step-1"><div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"><div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><div class="flex items-start justify-between gap-4"><div><h2 class="text-lg font-bold text-slate-900 dark:text-white">Data Pribadi & Dokumen Awal</h2><p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Isi identitas dasar sesuai dokumen resmi dan upload dokumen awal yang diminta.</p></div><button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-amber-200 bg-amber-50 text-amber-700" onclick="openTutorial(1)">i</button></div></div><div class="space-y-8 p-5 sm:p-6">
<div class="rounded-2xl border border-blue-200 bg-blue-50 px-4 py-4 text-xs leading-6 text-blue-900">Dokumen utama diunggah lebih dulu agar final submit lebih stabil di Android, iPhone, Google Drive, dan Files app. Jika upload ditolak, pesan error akan tampil di kartu dokumen terkait.</div>
<div class="grid gap-4 sm:grid-cols-3">@foreach($primaryUploadCards as $card)@php($temporaryState=data_get($temporaryUploadState,$card['name']))<label class="flex min-h-[180px] cursor-pointer flex-col items-center justify-center rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-center" data-upload-card="{{ $card['name'] }}" data-existing-url="{{ $temporaryState['preview_url'] ?? ($card['value'] ? asset('storage/'.$card['value']) : '') }}" data-existing-filename="{{ $temporaryState['normalized_name'] ?? ($card['value'] ? basename((string) $card['value']) : '') }}" data-existing-token="{{ $temporaryState['token'] ?? old($card['name'].'_token','') }}" data-existing-source="{{ $temporaryState ? 'temporary' : ($card['value'] ? 'saved' : 'missing') }}"><input type="file" class="sr-only" name="{{ $card['name'] }}" accept="{{ $card['accept'] }}" data-upload-input="{{ $card['name'] }}"><div class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $card['label'] }}</div><p class="mt-2 text-xs text-slate-500">{{ $card['note'] }}</p>@if($temporaryState)<p class="mt-3 rounded-full bg-amber-100 px-3 py-1 text-[11px] font-semibold text-amber-700">Tersimpan sementara</p><a class="mt-3 text-xs font-semibold text-blue-600 underline" href="{{ $temporaryState['preview_url'] }}" target="_blank">Lihat file sementara</a>@elseif($card['value'])<p class="mt-3 rounded-full bg-emerald-100 px-3 py-1 text-[11px] font-semibold text-emerald-700">Sudah tersimpan</p><a class="mt-3 text-xs font-semibold text-blue-600 underline" href="{{ asset('storage/'.$card['value']) }}" target="_blank">Lihat file</a>@else<p class="mt-3 text-[11px] font-semibold text-red-600">Wajib diisi</p>@endif @if($errors->has($card['name']))<p class="mt-3 text-xs font-semibold text-red-700" data-upload-error-seed="{{ $card['name'] }}">{{ $errors->first($card['name']) }}</p>@endif</label>@endforeach</div>
<div class="grid gap-4 md:grid-cols-2"><div class="md:col-span-2"><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Nama Lengkap sesuai KTP</label><input type="text" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="full_name" value="{{ old('full_name',$profile->full_name) }}" required></div><div><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">NIK</label><input type="text" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="ktp_number" value="{{ old('ktp_number',$profile->ktp_number) }}" required></div><div><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Email Login</label><input type="email" class="mt-1 w-full cursor-not-allowed rounded-2xl border border-slate-200 bg-slate-100 px-4 py-3 text-slate-500 dark:border-slate-700 dark:bg-slate-800/50" value="{{ auth()->user()->email }}" disabled></div><div><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Tempat Lahir</label><input type="text" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="place_of_birth" value="{{ old('place_of_birth',$profile->place_of_birth) }}" required></div><div><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Tanggal Lahir</label><input type="date" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="date_of_birth" value="{{ old('date_of_birth',$profile->date_of_birth) }}" required></div><div><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Jam Lahir</label><input type="time" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="time_of_birth" value="{{ old('time_of_birth',data_get($personal,'time_of_birth')) }}" required></div><div><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Jenis Kelamin</label><select class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="gender" required><option value="">Pilih</option>@foreach(['Laki-laki','Perempuan'] as $o)<option value="{{ $o }}" @selected(old('gender',$profile->gender)===$o)>{{ $o }}</option>@endforeach</select></div><div><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Agama</label><select class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="religion" required><option value="">Pilih</option>@foreach(['Islam','Kristen','Katolik','Hindu','Buddha','Konghucu','Lainnya'] as $o)<option value="{{ $o }}" @selected(old('religion',$profile->religion)===$o)>{{ $o }}</option>@endforeach</select></div><div><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Golongan Darah</label><select class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="blood_type"><option value="">Pilih</option>@foreach(['A','B','AB','O'] as $o)<option value="{{ $o }}" @selected(old('blood_type',$profile->blood_type)===$o)>{{ $o }}</option>@endforeach</select></div><div><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Status Pernikahan</label><select class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" id="maritalStatus" name="marital_status" required><option value="">Pilih</option>@foreach(['Single','Menikah','Duda','Janda'] as $o)<option value="{{ $o }}" @selected(old('marital_status',$profile->marital_status)===$o)>{{ $o }}</option>@endforeach</select></div><div id="marriageDateBox" class="{{ old('marital_status',$profile->marital_status)==='Menikah'?'':'hidden' }}"><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Tanggal Menikah</label><input type="date" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="marriage_date" value="{{ old('marriage_date',$profile->marriage_date) }}"></div><div><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">WhatsApp Aktif</label><input type="text" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="whatsapp" value="{{ old('whatsapp',$profile->whatsapp) }}" required></div><div><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Nomor Telepon / HP Aktif</label><input type="text" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="phone_number" value="{{ old('phone_number',data_get($personal,'phone_number')) }}" required placeholder="Tulis nomor, bukan merk HP"></div></div></div></div></section>
<section class="form-step hidden" id="step-2"><div class="space-y-6"><div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"><div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><div class="flex items-start justify-between gap-4"><div><h2 class="text-lg font-bold text-slate-900 dark:text-white">Alamat KTP & Domisili</h2><p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Alamat domisili harus sama lengkapnya dengan alamat KTP.</p></div><button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-amber-200 bg-amber-50 text-amber-700" onclick="openTutorial(2)">i</button></div></div><div class="grid gap-6 p-5 sm:p-6 lg:grid-cols-2">@foreach(['ktp'=>'Alamat (KTP)','domicile'=>'Alamat (Tempat Tinggal)'] as $prefix=>$label)<div class="rounded-3xl border border-slate-200 p-4 dark:border-slate-800"><div class="mb-4 text-sm font-bold text-slate-800 dark:text-slate-100">{{ $label }}</div><div class="space-y-4"><div><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Alamat</label><textarea class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" rows="3" name="{{ $prefix }}_address" required>{{ old($prefix.'_address',data_get($address,$prefix.'_address')) }}</textarea></div><div class="grid grid-cols-2 gap-4"><div><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">RT</label><input type="text" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="{{ $prefix }}_rt" value="{{ old($prefix.'_rt',data_get($address,$prefix.'_rt')) }}" required></div><div><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">RW</label><input type="text" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="{{ $prefix }}_rw" value="{{ old($prefix.'_rw',data_get($address,$prefix.'_rw')) }}" required></div></div><div><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Kelurahan</label><input type="text" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="{{ $prefix }}_kelurahan" value="{{ old($prefix.'_kelurahan',data_get($address,$prefix.'_kelurahan')) }}" required></div><div><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Kecamatan</label><input type="text" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="{{ $prefix }}_kecamatan" value="{{ old($prefix.'_kecamatan',data_get($address,$prefix.'_kecamatan')) }}" required></div><div><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Kota/Kabupaten</label><input type="text" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="{{ $prefix }}_city" value="{{ old($prefix.'_city',data_get($address,$prefix.'_city')) }}" required></div><div><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Provinsi</label><input type="text" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="{{ $prefix }}_province" value="{{ old($prefix.'_province',data_get($address,$prefix.'_province')) }}"></div></div></div>@endforeach</div></div>
@include('application-form.partials.repeatable',['title'=>'Susunan Keluarga','name'=>'families','items'=>old('families',$profile->families),'required'=>true,'requiredNotice'=>'Ayah dan Ibu wajib dicantumkan. Jika menikah, suami/istri juga wajib.','fields'=>[['key'=>'relation','label'=>'Hubungan','type'=>'select','options'=>['Ayah','Ibu','Suami','Istri','Anak','Saudara']],['key'=>'name','label'=>'Nama Lengkap','placeholder'=>'Nama'],['key'=>'gender','label'=>'L/P','type'=>'select','options'=>['Laki-laki','Perempuan']],['key'=>'dob','label'=>'Tanggal Lahir','type'=>'date'],['key'=>'education','label'=>'Pendidikan','placeholder'=>'Pendidikan terakhir'],['key'=>'job','label'=>'Pekerjaan','placeholder'=>'Pekerjaan'],['key'=>'status_note','label'=>'Catatan','placeholder'=>'Contoh: Alm.']]])
@include('application-form.partials.repeatable',['title'=>'Kontak Darurat','name'=>'emergency_contacts','items'=>$emergencyItems,'required'=>true,'minRows'=>2,'requiredNotice'=>'Wajib mengisi minimal 2 kontak darurat.','fields'=>[['key'=>'name','label'=>'Nama','placeholder'=>'Nama kontak'],['key'=>'relation','label'=>'Hubungan','placeholder'=>'Ayah/Ibu/Saudara'],['key'=>'phone','label'=>'Nomor HP','placeholder'=>'08xxxxxxxxxx'],['key'=>'address','label'=>'Alamat','type'=>'textarea','placeholder'=>'Alamat lengkap']]])
</div></section>
<section class="form-step hidden" id="step-3"><div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"><div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><div class="flex items-start justify-between gap-4"><div><h2 class="text-lg font-bold text-slate-900 dark:text-white">Pendidikan, Bahasa, dan Kursus</h2><p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Minimal isi 3 jenjang pendidikan.</p></div><button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-amber-200 bg-amber-50 text-amber-700" onclick="openTutorial(3)">i</button></div></div><div class="p-5 sm:p-6">@include('application-form.partials.repeatable',['title'=>'Riwayat Pendidikan Formal','name'=>'educations','items'=>$educationItems,'required'=>true,'minRows'=>3,'requiredNotice'=>'Wajib mengisi minimal 3 jenjang pendidikan lengkap.','fields'=>[['key'=>'level','label'=>'Jenjang','type'=>'select','options'=>['SD','SMP','SMA/SMK','D1','D2','D3','S1','S2','S3']],['key'=>'school','label'=>'Sekolah / Universitas','placeholder'=>'Nama institusi'],['key'=>'major','label'=>'Jurusan','placeholder'=>'Jurusan'],['key'=>'year_in','label'=>'Tahun Masuk','type'=>'number','placeholder'=>'2015'],['key'=>'year_out','label'=>'Tahun Lulus','type'=>'number','placeholder'=>'2018'],['key'=>'gpa','label'=>'Nilai / IPK','placeholder'=>'3.50']]])
@include('application-form.partials.repeatable',['title'=>'Kemampuan Bahasa','name'=>'languages','items'=>old('languages',$profile->languages),'fields'=>[['key'=>'language','label'=>'Bahasa','placeholder'=>'Indonesia'],['key'=>'speaking','label'=>'Lisan','placeholder'=>'Baik'],['key'=>'writing','label'=>'Tulisan','placeholder'=>'Baik']]])
@include('application-form.partials.repeatable',['title'=>'Kursus / Pelatihan','name'=>'courses','items'=>old('courses',$profile->courses),'fields'=>[['key'=>'name','label'=>'Nama Kegiatan','placeholder'=>'Pelatihan'],['key'=>'organizer','label'=>'Penyelenggara','placeholder'=>'Nama lembaga'],['key'=>'year','label'=>'Tahun','type'=>'number','placeholder'=>'2024'],['key'=>'certificate','label'=>'Sertifikat','placeholder'=>'Ada / Tidak ada']]])
</div></div></section>
<section class="form-step hidden" id="step-4"><div class="space-y-6"><div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"><div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><div class="flex items-start justify-between gap-4"><div><h2 class="text-lg font-bold text-slate-900 dark:text-white">Preferensi Kerja, Posisi Dilamar, dan Pengalaman</h2><p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Posisi/jabatan yang dilamar digabung di bagian ini bersama ekspektasi gaji.</p></div><button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-amber-200 bg-amber-50 text-amber-700" onclick="openTutorial(4)">i</button></div></div><div class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
<div><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Posisi / Jabatan yang Ingin Dilamar</label><input type="text" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="applied_position_name" value="{{ old('applied_position_name',data_get($personal,'applied_position_name',$profile->applied_position_name)) }}" required></div><div><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Pilih dari Master Posisi</label>@if(($positions??collect())->isNotEmpty())<select class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="applied_position_id"><option value="">Opsional</option>@foreach(($positions??collect()) as $position)<option value="{{ $position->id }}" @selected((string)old('applied_position_id',data_get($personal,'applied_position_id'))===(string)$position->id)>{{ $position->name }}</option>@endforeach</select>@else<input type="text" class="mt-1 w-full rounded-2xl border border-slate-200 bg-slate-100 px-4 py-3 text-slate-500 dark:border-slate-700 dark:bg-slate-800/50" value="Master posisi belum tersedia" disabled>@endif</div>
<div><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Departemen yang Diminati</label><select class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="applied_department_id"><option value="">Pilih</option>@foreach(($departments??collect()) as $department)<option value="{{ $department->id }}" @selected((string)old('applied_department_id',data_get($personal,'applied_department_id'))===(string)$department->id)>{{ $department->name }}</option>@endforeach</select></div><div><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Outlet / Penempatan yang Diminati</label><select class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="applied_outlet_id"><option value="">Pilih</option>@foreach(($outlets??collect()) as $outlet)<option value="{{ $outlet->id }}" @selected((string)old('applied_outlet_id',data_get($personal,'applied_outlet_id'))===(string)$outlet->id)>{{ $outlet->name }}</option>@endforeach</select></div>
<div><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Ekspektasi Gaji</label><input type="number" min="0" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="salary_expectation" value="{{ old('salary_expectation',data_get($personal,'salary_expectation')) }}" required></div><div><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Ruang Lingkup Pekerjaan yang Disukai</label><select id="preferredJobScope" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="preferred_job_scope"><option value="">Pilih</option>@foreach(['Managerial','Tekhnikal','Klerikal','Lainnya'] as $o)<option value="{{ $o }}" @selected(old('preferred_job_scope',data_get($personal,'preferred_job_scope'))===$o)>{{ $o }}</option>@endforeach</select></div>
<div id="preferredJobScopeOtherWrapper" class="sm:col-span-2 {{ old('preferred_job_scope',data_get($personal,'preferred_job_scope'))==='Lainnya'?'':'hidden' }}"><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Sebutkan Ruang Lingkup Lainnya</label><input type="text" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="preferred_job_scope_other" value="{{ old('preferred_job_scope_other',data_get($personal,'preferred_job_scope_other')) }}"></div>
<div><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Lingkungan Kerja yang Disukai</label><select id="preferredWorkEnvironment" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="preferred_work_environment"><option value="">Pilih</option>@foreach(['Kantor','Luar Kantor','Pabrik','Laboratorium','Mall','Lainnya'] as $o)<option value="{{ $o }}" @selected(old('preferred_work_environment',data_get($personal,'preferred_work_environment'))===$o)>{{ $o }}</option>@endforeach</select></div><div id="preferredWorkEnvironmentOtherWrapper" class="{{ old('preferred_work_environment',data_get($personal,'preferred_work_environment'))==='Lainnya'?'':'hidden' }}"><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Sebutkan Lingkungan Kerja Lainnya</label><input type="text" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="preferred_work_environment_other" value="{{ old('preferred_work_environment_other',data_get($personal,'preferred_work_environment_other')) }}"></div>
@foreach(['willing_out_of_town'=>'Bersedia di luar kota?','willing_outside_java'=>'Bersedia di luar Jawa?','willing_shift'=>'Bersedia shift?','willing_overtime'=>'Bersedia lembur?','is_smoker'=>'Apakah merokok?','has_computer_skill'=>'Punya keahlian komputer?'] as $field=>$label)<div><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $label }}</label><select class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="{{ $field }}" required><option value="">Pilih</option>@foreach(['Ya','Tidak'] as $o)<option value="{{ $o }}" @selected(old($field,data_get($personal,$field))===$o)>{{ $o }}</option>@endforeach</select></div>@endforeach
<div><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Apakah memakai kacamata?</label><select id="wearsGlasses" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="wears_glasses" required><option value="">Pilih</option>@foreach(['Ya','Tidak'] as $o)<option value="{{ $o }}" @selected(old('wears_glasses',data_get($personal,'wears_glasses'))===$o)>{{ $o }}</option>@endforeach</select></div>
<div id="glassesDetails" class="sm:col-span-2 {{ old('wears_glasses',data_get($personal,'wears_glasses'))==='Ya'?'':'hidden' }}"><div class="grid gap-4 sm:grid-cols-2"><div><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Minus / Plus Mata Kanan</label><input type="text" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="glasses_right_eye" value="{{ old('glasses_right_eye',data_get($personal,'glasses_right_eye')) }}"></div><div><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Minus / Plus Mata Kiri</label><input type="text" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="glasses_left_eye" value="{{ old('glasses_left_eye',data_get($personal,'glasses_left_eye')) }}"></div></div></div>
<div class="sm:col-span-2"><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Mengapa ingin bergabung dengan perusahaan ini?</label><textarea class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" rows="4" name="join_reason" required>{{ old('join_reason',data_get($personal,'join_reason')) }}</textarea></div>
<div class="sm:col-span-2"><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Apakah mempunyai saudara atau teman yang bekerja di perusahaan ini? Jika ada, wajib disebutkan.</label><textarea class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" rows="4" name="company_relation_note" required>{{ old('company_relation_note',data_get($personal,'company_relation_note')) }}</textarea></div>
<div class="sm:col-span-2"><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Apa yang ingin dicapai dalam karir Anda?</label><textarea class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" rows="4" name="career_goal" required>{{ old('career_goal',data_get($personal,'career_goal')) }}</textarea></div>
<div class="sm:col-span-2"><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Informasi lain yang berguna untuk lamaran Anda</label><textarea class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" rows="4" name="additional_information">{{ old('additional_information',data_get($personal,'additional_information')) }}</textarea></div>
<div><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Jika diterima, kapan siap bergabung?</label><input type="date" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="available_start_date" value="{{ old('available_start_date',data_get($personal,'available_start_date')) }}" required></div>
</div></div>
@include('application-form.partials.repeatable',['title'=>'Riwayat Pengalaman Kerja','name'=>'work_experiences','items'=>old('work_experiences',$profile->work_experiences),'required'=>true,'requiredNotice'=>'Isi minimal 1 pengalaman kerja lengkap.','fields'=>[['key'=>'company','label'=>'Perusahaan','placeholder'=>'PT ...'],['key'=>'position','label'=>'Jabatan','placeholder'=>'Posisi'],['key'=>'date_start','label'=>'Mulai','type'=>'date'],['key'=>'date_end','label'=>'Selesai','type'=>'date'],['key'=>'salary','label'=>'Gaji Terakhir','placeholder'=>'Rp ...'],['key'=>'reason','label'=>'Alasan Berhenti','placeholder'=>'Alasan']]])
@include('application-form.partials.repeatable',['title'=>'Kontak Referensi','name'=>'reference_contacts','items'=>$referenceItems,'required'=>true,'minRows'=>2,'requiredNotice'=>'Wajib mengisi minimal 2 kontak referensi.','fields'=>[['key'=>'name','label'=>'Nama','placeholder'=>'Nama referensi'],['key'=>'relation','label'=>'Hubungan','placeholder'=>'Atasan / Rekan kerja'],['key'=>'company','label'=>'Perusahaan','placeholder'=>'Nama perusahaan'],['key'=>'phone','label'=>'Nomor HP','placeholder'=>'08xxxxxxxxxx']]])
@include('application-form.partials.repeatable',['title'=>'Pengalaman Organisasi','name'=>'organizations','items'=>old('organizations',$profile->organizations),'fields'=>[['key'=>'name','label'=>'Nama Organisasi','placeholder'=>'Nama organisasi'],['key'=>'role','label'=>'Jabatan','placeholder'=>'Jabatan'],['key'=>'year','label'=>'Tahun','type'=>'number','placeholder'=>'2024']]])
</div></section>
<section class="form-step hidden" id="step-5"><div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"><div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><div class="flex items-start justify-between gap-4"><div><h2 class="text-lg font-bold text-slate-900 dark:text-white">Kesehatan, Legal, dan Psikologi</h2><p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Bagian ini dipakai untuk screening kesehatan dan administrasi dasar kandidat.</p></div><button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-amber-200 bg-amber-50 text-amber-700" onclick="openTutorial(5)">i</button></div></div><div class="space-y-6 p-5 sm:p-6"><div class="grid gap-4 md:grid-cols-2"><div><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Berat Badan (kg)</label><input type="number" min="1" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="weight_kg" value="{{ old('weight_kg',data_get($medical,'weight_kg')) }}" required></div><div><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Tinggi Badan (cm)</label><input type="number" min="1" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="height_cm" value="{{ old('height_cm',data_get($medical,'height_cm')) }}" required></div></div>
@include('application-form.partials.repeatable',['title'=>'Riwayat Penyakit','name'=>'medical_histories','items'=>old('medical_histories',$profile->medical_histories),'required'=>true,'requiredNotice'=>'Jika tidak ada riwayat penyakit, isi satu baris dengan keterangan yang jujur.','fields'=>[['key'=>'illness','label'=>'Nama Penyakit','placeholder'=>'Tidak ada / Asma'],['key'=>'year','label'=>'Tahun','type'=>'number','placeholder'=>'2024'],['key'=>'hospitalized','label'=>'Rawat Inap?','type'=>'select','options'=>['Ya','Tidak']],['key'=>'note','label'=>'Keterangan','placeholder'=>'Catatan tambahan']]])
<div class="grid gap-4 md:grid-cols-2"><div><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Pernah mengalami kecelakaan?</label><select id="hadAccident" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="had_accident" required><option value="">Pilih</option>@foreach(['Ya','Tidak'] as $o)<option value="{{ $o }}" @selected(old('had_accident',data_get($medical,'had_accident'))===$o)>{{ $o }}</option>@endforeach</select></div><div id="accidentDetails" class="{{ old('had_accident',data_get($medical,'had_accident'))==='Ya'?'':'hidden' }}"><div class="grid gap-4"><input type="number" class="w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="accident_year" value="{{ old('accident_year',data_get($medical,'accident_year')) }}" placeholder="Tahun kejadian"><input type="text" class="w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="accident_type" value="{{ old('accident_type',data_get($medical,'accident_type')) }}" placeholder="Kecelakaan apa"><input type="text" class="w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="accident_effect" value="{{ old('accident_effect',data_get($medical,'accident_effect')) }}" placeholder="Akibat apa"></div></div><div><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Pernah berurusan dengan polisi?</label><select id="policeRecord" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="police_record" required><option value="">Pilih</option>@foreach(['Ya','Tidak'] as $o)<option value="{{ $o }}" @selected(old('police_record',data_get($medical,'police_record'))===$o)>{{ $o }}</option>@endforeach</select></div><div id="policeDetails" class="{{ old('police_record',data_get($medical,'police_record'))==='Ya'?'':'hidden' }}"><div class="grid gap-4"><input type="text" class="w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="police_record_case" value="{{ old('police_record_case',data_get($medical,'police_record_case')) }}" placeholder="Dalam urusan apa"><input type="number" class="w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="police_record_year" value="{{ old('police_record_year',data_get($medical,'police_record_year')) }}" placeholder="Tahun berapa"><input type="text" class="w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="police_record_location" value="{{ old('police_record_location',data_get($medical,'police_record_location')) }}" placeholder="Di mana"></div></div><div><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Pernah mengikuti psikotes?</label><select id="psychologyTest" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="psychology_test" required><option value="">Pilih</option>@foreach(['Ya','Tidak'] as $o)<option value="{{ $o }}" @selected(old('psychology_test',data_get($medical,'psychology_test'))===$o)>{{ $o }}</option>@endforeach</select></div><div id="psychologyDetails" class="{{ old('psychology_test',data_get($medical,'psychology_test'))==='Ya'?'':'hidden' }}"><div class="grid gap-4"><input type="number" class="w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="psychology_test_year" value="{{ old('psychology_test_year',data_get($medical,'psychology_test_year')) }}" placeholder="Tahun berapa"><input type="text" class="w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="psychology_test_location" value="{{ old('psychology_test_location',data_get($medical,'psychology_test_location')) }}" placeholder="Di mana"><input type="text" class="w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" name="psychology_test_purpose" value="{{ old('psychology_test_purpose',data_get($medical,'psychology_test_purpose')) }}" placeholder="Untuk keperluan apa"></div></div></div>
<div class="rounded-3xl border border-slate-200 p-4 dark:border-slate-800"><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Upload SKCK Terbaru</label><input type="file" class="mt-2 block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950/50" name="skck_file" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"><p class="mt-2 text-xs text-slate-500">Tidak memblokir submit final. Jika belum ada, sistem akan menampilkan pengingat setelah data tersimpan.</p>@if(data_get($personal,'skck_latest_path'))<input type="hidden" name="skck_existing" value="{{ data_get($personal,'skck_latest_path') }}"><p class="mt-2 text-xs font-semibold text-green-600">✓ File sudah diupload: {{ basename((string) data_get($personal,'skck_latest_path')) }}</p><a href="{{ asset('storage/'.data_get($personal,'skck_latest_path')) }}" target="_blank" class="mt-2 inline-block text-xs font-semibold text-blue-600 underline">Lihat SKCK tersimpan</a>@endif</div>
</div></div></section>
<section class="form-step hidden" id="step-6"><div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"><div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><div class="flex items-start justify-between gap-4"><div><h2 class="text-lg font-bold text-slate-900 dark:text-white">Finalisasi, Tanda Tangan Digital, dan Dokumen Lanjutan</h2><p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Bagian akhir untuk social media, pernyataan, tanda tangan digital, dan dokumen tambahan bila status kandidat sudah lolos administrasi.</p></div><button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-amber-200 bg-amber-50 text-amber-700" onclick="openTutorial(6)">i</button></div></div><div class="space-y-6 p-5 sm:p-6">
@include('application-form.partials.repeatable',['title'=>'Akun Social Media','name'=>'social_medias','items'=>old('social_medias',$profile->social_medias),'fields'=>[['key'=>'platform','label'=>'Platform','type'=>'select','options'=>['Instagram','LinkedIn','Facebook','TikTok','Twitter/X']],['key'=>'handle','label'=>'Username / Link','placeholder'=>'@username']]])
<div class="rounded-3xl border border-amber-200 bg-amber-50/70 p-5 text-sm leading-6 text-amber-900"><div class="text-base font-bold">Term of Use</div><p class="mt-2">Dilarang melakukan screenshot, duplikasi, share informasi, distribusi file, atau penggunaan data form ini di luar proses rekrutmen resmi perusahaan. Sistem menerapkan pembatasan anti-copy untuk membantu menjaga kerahasiaan data.</p></div>
<div><label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Tulis pernyataan kejujuran dengan sebenar-benarnya dan siap dituntut jika di kemudian hari ditemukan data salah / manipulasi / penipuan.</label><textarea class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/50" rows="6" name="honesty_statement" required>{{ old('honesty_statement',data_get($personal,'honesty_statement')) }}</textarea><p class="mt-2 text-xs text-slate-500">Silakan ketik manual. Halaman ini menerapkan deterrent anti-copy.</p></div>
<div class="rounded-3xl border border-slate-200 p-5 dark:border-slate-800" id="signatureSection" data-has-saved-signature="{{ (old('signature_cleared','0') !== '1') && $signaturePath ? 'true' : 'false' }}"><div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><div class="text-base font-bold text-slate-900 dark:text-white">Tanda Tangan Digital Peserta</div><p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Tandatangani menggunakan sentuhan jari, stylus, atau mouse. Setelah selesai menggambar, tanda tangan akan tersimpan otomatis ke form.</p></div><div class="flex gap-2"><button type="button" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" id="clearSignatureBtn">Hapus / Ulangi</button><button type="button" class="rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700" id="saveSignatureBtn">Simpan Tanda Tangan</button></div></div><div class="mt-4 flex flex-wrap items-center gap-3"><div id="signatureStatusBadge" class="inline-flex rounded-full px-3 py-1 text-[11px] font-semibold {{ old('signature_data') || ((old('signature_cleared','0') !== '1') && $signaturePath) ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">{{ old('signature_data') || ((old('signature_cleared','0') !== '1') && $signaturePath) ? 'Tanda tangan tersimpan' : 'Belum ada tanda tangan' }}</div><div class="text-xs text-slate-500" id="signatureHelperText">{{ old('signature_data') ? 'Preview di bawah ini adalah tanda tangan baru yang akan dipakai saat submit.' : (((old('signature_cleared','0') !== '1') && $signaturePath) ? 'Saat ini masih ada tanda tangan tersimpan. Jika Anda menekan Hapus / Ulangi, final submit akan ditolak sampai Anda menggambar ulang.' : 'Gambar tanda tangan di area ini. Sistem akan menyimpannya otomatis setelah Anda selesai.') }}</div></div><div class="mt-4 overflow-hidden rounded-3xl border border-dashed border-slate-300 bg-slate-50 dark:border-slate-700 dark:bg-slate-950/50"><canvas id="signatureCanvas" class="block h-[220px] w-full touch-none" style="touch-action:none;"></canvas></div><div class="mt-4 grid gap-4 md:grid-cols-[minmax(0,1fr)_180px]"><div class="rounded-2xl border border-slate-200 bg-white/80 p-4 text-xs text-slate-600 dark:border-slate-700 dark:bg-slate-950/40 dark:text-slate-300"><div class="font-semibold text-slate-800 dark:text-slate-100">Panduan singkat</div><p class="mt-2 leading-6">Jika tanda tangan belum terlihat di preview atau status masih kosong, tekan tombol simpan lalu coba lagi. Saat Anda menekan kirim final tanpa tanda tangan yang valid, sistem akan menampilkan pesan error yang jelas.</p>@if($signaturePath)<div class="mt-3"><a href="{{ asset('storage/'.$signaturePath) }}" target="_blank" class="font-semibold text-blue-600 underline">Lihat tanda tangan tersimpan saat ini</a><p class="mt-2 text-[11px] leading-5 text-slate-500">Catatan: jika Anda menekan Hapus / Ulangi, tanda tangan lama tidak akan dianggap valid lagi untuk final submit sampai Anda menggambar ulang.</p></div>@endif</div><div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-950/40"><div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Preview</div><div id="signaturePreviewContainer" class="mt-3 flex h-28 items-center justify-center overflow-hidden rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-950/60"><img id="signaturePreviewImage" src="{{ old('signature_data','') }}" alt="Preview tanda tangan" class="{{ old('signature_data') ? '' : 'hidden ' }}max-h-full w-full object-contain"><div id="signaturePreviewPlaceholder" class="{{ old('signature_data') ? 'hidden ' : '' }}text-center text-[11px] leading-5 text-slate-400">Preview tanda tangan akan tampil di sini setelah tersimpan.</div></div></div></div>@if($errors->has('signature_data'))<p class="mt-3 text-sm font-semibold text-red-700" id="signatureInlineError">{{ $errors->first('signature_data') }}</p>@else<p class="mt-3 hidden text-sm font-semibold text-red-700" id="signatureInlineError"></p>@endif</div>
@if($hasAdministrativeDocumentStage)<div class="rounded-3xl border border-emerald-200 bg-emerald-50/70 p-5"><div class="text-base font-bold text-emerald-900">Dokumen Tambahan Setelah Lolos Administrasi</div><p class="mt-1 text-sm text-emerald-800">Status kandidat Anda sudah masuk tahap administrasi lanjut. Dokumen berikut perlu dilengkapi, tapi tidak memblokir submit final.</p><div class="mt-4 grid gap-4 md:grid-cols-2">@foreach(['graduation_diploma_file'=>['label'=>'Ijazah Terakhir','current'=>data_get($graduationDocs,'diploma_path'),'existing'=>'ijazah_existing'],'graduation_transcript_file'=>['label'=>'Transkrip Nilai','current'=>data_get($graduationDocs,'transcript_path'),'existing'=>'transkrip_existing'],'graduation_birth_certificate_file'=>['label'=>'Akta Kelahiran','current'=>data_get($graduationDocs,'birth_certificate_path'),'existing'=>'akta_lahir_existing']] as $name=>$doc)<div class="rounded-2xl border border-emerald-200 bg-white p-4"><label class="block text-sm font-semibold text-slate-700">{{ $doc['label'] }}</label><input type="file" class="mt-2 block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm" name="{{ $name }}" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf">@if($doc['current'])<input type="hidden" name="{{ $doc['existing'] }}" value="{{ $doc['current'] }}"><p class="mt-2 text-xs font-semibold text-green-600">✓ File sudah diupload: {{ basename((string) $doc['current']) }}</p><a href="{{ asset('storage/'.$doc['current']) }}" target="_blank" class="mt-2 inline-block text-xs font-semibold text-blue-600 underline">Lihat file tersimpan</a>@endif</div>@endforeach<div class="rounded-2xl border border-emerald-200 bg-white p-4 md:col-span-2"><label class="block text-sm font-semibold text-slate-700">Dokumen Pendukung Lain (maksimal 5 file)</label><input type="file" class="mt-2 block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm" name="supporting_files[]" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" multiple>@if(collect((array)data_get($graduationDocs,'supporting_files',[]))->isNotEmpty())<div class="mt-3 flex flex-wrap gap-2 text-xs">@foreach((array)data_get($graduationDocs,'supporting_files',[]) as $supportingFile)<a href="{{ asset('storage/'.$supportingFile) }}" target="_blank" class="rounded-full bg-blue-50 px-3 py-1 font-semibold text-blue-700 underline">Dokumen pendukung</a>@endforeach</div>@endif</div></div></div>@endif
</div></div></section></form></div></div></div></div>
<div id="tutorialModal" class="fixed inset-0 z-[80] hidden"><div class="absolute inset-0 bg-slate-950/60"></div><div class="relative mx-auto mt-16 max-w-lg rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900"><div class="flex items-start justify-between gap-4"><div><h3 id="tutorialTitle" class="text-lg font-bold text-slate-900 dark:text-white">Panduan</h3><p id="tutorialBody" class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300"></p></div><button type="button" onclick="closeTutorial()" class="text-2xl text-slate-400">&times;</button></div><div class="mt-6 text-right"><button type="button" onclick="closeTutorial()" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold">Tutup</button></div></div></div>
<script>
const stepTitles = @json(array_values($stepTitles));
const tutorials = {
    1: 'Isi data pribadi sesuai dokumen resmi. Pastikan tanggal lahir, jam lahir, WhatsApp aktif, dan nomor telepon / HP aktif benar. Upload pas foto, scan KTP, dan CV sebelum lanjut.',
    2: 'Alamat domisili harus sama lengkapnya dengan alamat KTP: alamat, RT, RW, kelurahan, kecamatan, dan kota/kabupaten. Data keluarga wajib memuat Ayah dan Ibu. Jika menikah, suami/istri juga wajib. Kontak darurat wajib 2 orang.',
    3: 'Isi minimal 3 jenjang pendidikan langsung pada tabel. Anda tetap bisa menambah jenjang lebih banyak bila diperlukan.',
    4: 'Bagian ini menggabungkan posisi dilamar, ekspektasi gaji, preferensi kerja, pengalaman, dan referensi. Kontak referensi wajib minimal 2 orang yang benar-benar dapat dihubungi.',
    5: 'Jawab bagian kesehatan dan legal dengan jujur. Jika menjawab Ya pada kecelakaan, kepolisian, atau psikotes, detail lanjutannya wajib diisi. Upload SKCK bila diminta.',
    6: 'Baca ketentuan penggunaan, ketik pernyataan kejujuran, lalu buat tanda tangan digital. Jika status Anda sudah lolos administrasi, lengkapi dokumen lanjutan.'
};
const serverValidationErrors = @json($serverValidationErrors);
const missingSections = @json($missingSections);
const applicationFormUploadEndpoint = @json(route('application-form.upload-temp'));
const applicationFormSessionPingEndpoint = @json(route('application-form.session-ping'));
const applicationFormUploadEffectiveLimitBytes = @json(\App\Support\ApplicationFormUploadLimit::effectiveBytes());
let applicationFormCsrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || document.querySelector('#appForm input[name="_token"]')?.value || '';
const uploadCardConfig = {
    photo_ktp_file: { label: 'Pas Foto', note: @json($imageDocumentNote), error: @json($errors->first('photo_ktp_file')), token: @json(old('photo_ktp_file_token',data_get($temporaryUploadState,'photo_ktp_file.token'))) },
    scan_ktp_file: { label: 'Scan KTP', note: @json($imageDocumentNote), error: @json($errors->first('scan_ktp_file')), token: @json(old('scan_ktp_file_token',data_get($temporaryUploadState,'scan_ktp_file.token'))) },
    cv_file: { label: 'CV PDF', note: 'PDF | maks 5MB', error: @json($errors->first('cv_file')), token: @json(old('cv_file_token',data_get($temporaryUploadState,'cv_file.token'))) },
};
let signaturePadController = null;
let submitInFlight = false;
const fieldStepMap = {
    full_name: 1,
    ktp_number: 1,
    place_of_birth: 1,
    date_of_birth: 1,
    time_of_birth: 1,
    gender: 1,
    religion: 1,
    blood_type: 1,
    marital_status: 1,
    marriage_date: 1,
    whatsapp: 1,
    phone_number: 1,
    photo_ktp_file: 1,
    scan_ktp_file: 1,
    cv_file: 1,
    ktp_address: 2,
    ktp_rt: 2,
    ktp_rw: 2,
    ktp_kelurahan: 2,
    ktp_kecamatan: 2,
    ktp_city: 2,
    ktp_province: 2,
    domicile_address: 2,
    domicile_rt: 2,
    domicile_rw: 2,
    domicile_kelurahan: 2,
    domicile_kecamatan: 2,
    domicile_city: 2,
    domicile_province: 2,
    families: 2,
    emergency_contacts: 2,
    educations: 3,
    languages: 3,
    courses: 3,
    applied_position_id: 4,
    applied_position_name: 4,
    applied_department_id: 4,
    applied_outlet_id: 4,
    salary_expectation: 4,
    preferred_job_scope: 4,
    preferred_job_scope_other: 4,
    preferred_work_environment: 4,
    preferred_work_environment_other: 4,
    willing_out_of_town: 4,
    willing_outside_java: 4,
    willing_shift: 4,
    willing_overtime: 4,
    is_smoker: 4,
    has_computer_skill: 4,
    wears_glasses: 4,
    glasses_right_eye: 4,
    glasses_left_eye: 4,
    join_reason: 4,
    company_relation_note: 4,
    career_goal: 4,
    additional_information: 4,
    available_start_date: 4,
    work_experiences: 4,
    reference_contacts: 4,
    organizations: 4,
    medical_histories: 5,
    weight_kg: 5,
    height_cm: 5,
    had_accident: 5,
    accident_year: 5,
    accident_type: 5,
    accident_effect: 5,
    police_record: 5,
    police_record_case: 5,
    police_record_year: 5,
    police_record_location: 5,
    psychology_test: 5,
    psychology_test_year: 5,
    psychology_test_location: 5,
    psychology_test_purpose: 5,
    skck_file: 5,
    social_medias: 6,
    honesty_statement: 6,
    signature_data: 6,
    graduation_diploma_file: 6,
    graduation_transcript_file: 6,
    graduation_birth_certificate_file: 6,
    supporting_files: 6,
};
const stepConfig = {
    1: { selectors: ['[name="full_name"]','[name="ktp_number"]','[name="place_of_birth"]','[name="date_of_birth"]','[name="time_of_birth"]','[name="gender"]','[name="religion"]','[name="marital_status"]','[name="whatsapp"]','[name="phone_number"]'] },
    2: { selectors: ['[name="ktp_address"]','[name="ktp_rt"]','[name="ktp_rw"]','[name="ktp_kelurahan"]','[name="ktp_kecamatan"]','[name="ktp_city"]','[name="domicile_address"]','[name="domicile_rt"]','[name="domicile_rw"]','[name="domicile_kelurahan"]','[name="domicile_kecamatan"]','[name="domicile_city"]'] },
    3: { selectors: [] },
    4: { selectors: ['[name="applied_position_name"]','[name="salary_expectation"]','[name="willing_out_of_town"]','[name="willing_outside_java"]','[name="willing_shift"]','[name="willing_overtime"]','[name="is_smoker"]','[name="has_computer_skill"]','[name="wears_glasses"]','[name="join_reason"]','[name="company_relation_note"]','[name="career_goal"]','[name="available_start_date"]'] },
    5: { selectors: ['[name="weight_kg"]','[name="height_cm"]','[name="had_accident"]','[name="police_record"]','[name="psychology_test"]'] },
    6: { selectors: ['[name="honesty_statement"]', '#signatureDataInput'] },
};
let currentStep = Number(@json($firstErrorStep > 0 ? $firstErrorStep : 1));
const totalSteps = 6;

function openTutorial(step) {
    document.getElementById('tutorialTitle').textContent = stepTitles[step - 1] || 'Panduan';
    document.getElementById('tutorialBody').textContent = tutorials[step] || '';
    document.getElementById('tutorialModal').classList.remove('hidden');
}

function closeTutorial() {
    document.getElementById('tutorialModal').classList.add('hidden');
}

function changeStep(step) {
    currentStep = Math.max(1, Math.min(totalSteps, step));
    document.querySelectorAll('.form-step').forEach((section, index) => {
        section.classList.toggle('hidden', index !== currentStep - 1);
    });
    document.querySelectorAll('.sidebar-step').forEach((button, index) => {
        const active = index === currentStep - 1;
        button.classList.toggle('bg-amber-50', active);
        button.classList.toggle('text-amber-800', active);
        button.classList.toggle('border', active);
        button.classList.toggle('border-amber-200', active);
    });
    const footer = document.getElementById('footerStep');
    if (footer) {
        footer.textContent = String(currentStep);
    }
    const currentStepInput = document.getElementById('applicationFormCurrentStep');
    if (currentStepInput) {
        currentStepInput.value = String(currentStep);
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function toggleBlock(selectId, wrapperId, activeValue = 'Ya') {
    const select = document.getElementById(selectId);
    const wrapper = document.getElementById(wrapperId);
    if (!select || !wrapper) {
        return;
    }
    wrapper.classList.toggle('hidden', select.value !== activeValue);
}

function toggleEqualityBlock(selectId, wrapperId, activeValue) {
    const select = document.getElementById(selectId);
    const wrapper = document.getElementById(wrapperId);
    if (!select || !wrapper) {
        return;
    }
    wrapper.classList.toggle('hidden', select.value !== activeValue);
}

function preventCopyBehavior() {
    ['copy', 'cut', 'contextmenu'].forEach((name) => {
        document.addEventListener(name, (event) => {
            if (event.target.closest('input, textarea')) {
                return;
            }
            event.preventDefault();
        });
    });

    document.addEventListener('keydown', (event) => {
        const key = String(event.key || '').toLowerCase();
        if ((event.ctrlKey || event.metaKey) && ['c', 'x', 's', 'p', 'u'].includes(key)) {
            event.preventDefault();
        }
    });
}

function markAllFieldsForNavigation() {
    document.querySelectorAll('#appForm input[name], #appForm select[name], #appForm textarea[name]').forEach((field) => {
        if (!field.dataset.errorKey) {
            field.dataset.errorKey = field.name;
        }
    });
}

window.removeRow = function (btn) {
    const row = btn.closest('tr');
    if (row && confirm('Hapus baris data ini?')) {
        row.remove();
    }
};

window.addRepeatableRow = function (tableId, fieldConfigBase64, inputName) {
    const tbody = document.querySelector(`#${tableId} tbody`);
    if (!tbody) {
        return;
    }

    const index = Date.now() + Math.floor(Math.random() * 1000);
    const fields = JSON.parse(atob(fieldConfigBase64));
    const inputClass = 'block w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500';
    let tds = '';

    fields.forEach((field) => {
        const fieldName = `${inputName}[${index}][${field.key}]`;
        const errorKey = `${inputName}.${index}.${field.key}`;
        let inputHtml = '';

        if (field.type === 'select') {
            let options = '<option value="">Pilih...</option>';
            field.options.forEach((option) => {
                options += `<option value="${option}">${option}</option>`;
            });
            inputHtml = `<select name="${fieldName}" data-error-key="${errorKey}" class="${inputClass}">${options}</select>`;
        } else if (field.type === 'date') {
            inputHtml = `<input type="date" name="${fieldName}" data-error-key="${errorKey}" class="${inputClass}">`;
        } else if (field.type === 'number') {
            inputHtml = `<input type="number" name="${fieldName}" data-error-key="${errorKey}" class="${inputClass}" placeholder="${field.placeholder || ''}">`;
        } else if (field.type === 'textarea') {
            inputHtml = `<textarea name="${fieldName}" rows="2" data-error-key="${errorKey}" class="${inputClass}" placeholder="${field.placeholder || ''}"></textarea>`;
        } else {
            inputHtml = `<input type="text" name="${fieldName}" data-error-key="${errorKey}" class="${inputClass}" placeholder="${field.placeholder || ''}">`;
        }

        tds += `<td class="p-2 align-top">${inputHtml}</td>`;
    });

    tds += '<td class="p-2 text-center align-top"><button type="button" class="text-red-500 hover:text-red-700 transition" onclick="removeRow(this)"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button></td>';
    tbody.insertAdjacentHTML('beforeend', `<tr class="hover:bg-slate-50 transition">${tds}</tr>`);
};

function buildSignaturePad() {
    const canvas = document.getElementById('signatureCanvas');
    const saveButton = document.getElementById('saveSignatureBtn');
    const clearButton = document.getElementById('clearSignatureBtn');
    const hiddenInput = document.getElementById('signatureDataInput');
    const clearedInput = document.getElementById('signatureClearedFlag');
    const section = document.getElementById('signatureSection');
    const previewImage = document.getElementById('signaturePreviewImage');
    const previewPlaceholder = document.getElementById('signaturePreviewPlaceholder');
    const statusBadge = document.getElementById('signatureStatusBadge');
    const helperText = document.getElementById('signatureHelperText');
    const inlineError = document.getElementById('signatureInlineError');
    if (!canvas || !saveButton || !clearButton || !hiddenInput || !clearedInput || !section || !previewImage || !previewPlaceholder || !statusBadge || !helperText || !inlineError) {
        return;
    }

    const context = canvas.getContext('2d');
    if (!context) {
        return;
    }

    let drawing = false;
    let hasStroke = false;
    let activePointerId = null;
    let savedSignatureData = String(hiddenInput.value || '').trim();
    let resizeTimer = null;
    const hasExistingSavedSignature = section.dataset.hasSavedSignature === 'true';

    const setInlineError = (message = '') => {
        inlineError.textContent = message;
        inlineError.classList.toggle('hidden', message === '');
        canvas.classList.toggle('ring-2', message !== '');
        canvas.classList.toggle('ring-red-300', message !== '');
    };

    const updateSignatureUi = (signatureData = '', options = {}) => {
        const hasPreview = signatureData !== '';
        const fromExisting = Boolean(options.fromExisting);
        if (hasPreview) {
            previewImage.src = signatureData;
            previewImage.classList.remove('hidden');
            previewPlaceholder.classList.add('hidden');
            statusBadge.textContent = 'Tanda tangan tersimpan';
            statusBadge.className = 'inline-flex rounded-full bg-emerald-100 px-3 py-1 text-[11px] font-semibold text-emerald-700';
            helperText.textContent = fromExisting
                ? 'Sistem mendeteksi tanda tangan yang sudah tersimpan sebelumnya. Anda bisa menggambar ulang jika ingin mengganti.'
                : 'Tanda tangan sudah tertangkap dan siap ikut terkirim saat submit.';
            section.dataset.hasSavedSignature = 'true';
            return;
        }

        previewImage.removeAttribute('src');
        previewImage.classList.add('hidden');
        previewPlaceholder.classList.remove('hidden');
        statusBadge.textContent = 'Belum ada tanda tangan';
        statusBadge.className = 'inline-flex rounded-full bg-amber-100 px-3 py-1 text-[11px] font-semibold text-amber-700';
        helperText.textContent = clearedInput.value === '1'
            ? 'Tanda tangan sebelumnya sudah dihapus dari sesi ini. Gambar ulang sebelum kirim final.'
            : 'Gambar tanda tangan di area ini. Sistem akan menyimpannya otomatis setelah Anda selesai.';
        section.dataset.hasSavedSignature = 'false';
    };

    const saveSignatureData = (options = {}) => {
        const trimmed = canvas.toDataURL('image/png');
        hiddenInput.value = trimmed;
        clearedInput.value = '0';
        savedSignatureData = trimmed;
        hasStroke = true;
        updateSignatureUi(trimmed, options);
        setInlineError('');
    };

    const redrawFromDataUrl = (signatureData = '') => {
        if (signatureData === '') {
            return;
        }

        const image = new Image();
        image.onload = () => {
            const rect = canvas.getBoundingClientRect();
            context.clearRect(0, 0, rect.width, rect.height);
            context.fillStyle = '#fff';
            context.fillRect(0, 0, rect.width, rect.height);
            context.drawImage(image, 0, 0, rect.width, rect.height);
        };
        image.src = signatureData;
    };

    const resizeCanvas = (signatureData = savedSignatureData) => {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        const rect = canvas.getBoundingClientRect();
        canvas.width = rect.width * ratio;
        canvas.height = rect.height * ratio;
        context.setTransform(1, 0, 0, 1, 0, 0);
        context.scale(ratio, ratio);
        context.lineWidth = 2.5;
        context.lineCap = 'round';
        context.lineJoin = 'round';
        context.strokeStyle = '#0f172a';
        context.fillStyle = '#fff';
        context.fillRect(0, 0, rect.width, rect.height);
        redrawFromDataUrl(signatureData);
    };

    const point = (event) => {
        const rect = canvas.getBoundingClientRect();
        const source = event.touches ? event.touches[0] : (event.changedTouches ? event.changedTouches[0] : event);
        return { x: source.clientX - rect.left, y: source.clientY - rect.top };
    };

    const start = (event) => {
        if (typeof event.pointerId === 'number') {
            activePointerId = event.pointerId;
            if (canvas.setPointerCapture) {
                canvas.setPointerCapture(event.pointerId);
            }
        }
        drawing = true;
        hasStroke = true;
        const p = point(event);
        context.beginPath();
        context.moveTo(p.x, p.y);
        setInlineError('');
        event.preventDefault();
    };

    const move = (event) => {
        if (typeof event.pointerId === 'number' && activePointerId !== null && event.pointerId !== activePointerId) {
            return;
        }
        if (!drawing) {
            return;
        }
        const p = point(event);
        context.lineTo(p.x, p.y);
        context.stroke();
        event.preventDefault();
    };

    const end = (event) => {
        if (typeof event?.pointerId === 'number' && activePointerId !== null && event.pointerId !== activePointerId) {
            return;
        }
        if (typeof event?.pointerId === 'number' && canvas.releasePointerCapture) {
            try {
                canvas.releasePointerCapture(event.pointerId);
            } catch (error) {
                // Ignore release errors from browsers that already released the pointer.
            }
        }
        drawing = false;
        activePointerId = null;
        if (hasStroke) {
            saveSignatureData();
        }
    };

    const clearSignature = () => {
        drawing = false;
        hasStroke = false;
        activePointerId = null;
        savedSignatureData = '';
        hiddenInput.value = '';
        clearedInput.value = '1';
        resizeCanvas('');
        updateSignatureUi('');
        setInlineError('');
    };

    const handleResize = () => {
        window.clearTimeout(resizeTimer);
        resizeTimer = window.setTimeout(() => {
            resizeCanvas(savedSignatureData);
        }, 160);
    };

    updateSignatureUi(savedSignatureData, { fromExisting: hasExistingSavedSignature && savedSignatureData === '' });
    resizeCanvas(savedSignatureData);

    if (savedSignatureData !== '') {
        hasStroke = true;
        clearedInput.value = '0';
        updateSignatureUi(savedSignatureData);
    } else if (hasExistingSavedSignature) {
        clearedInput.value = '0';
        updateSignatureUi('', { fromExisting: true });
    } else if (clearedInput.value === '1') {
        updateSignatureUi('');
    }

    if (window.PointerEvent) {
        canvas.addEventListener('pointerdown', start);
        canvas.addEventListener('pointermove', move);
        canvas.addEventListener('pointerup', end);
        canvas.addEventListener('pointercancel', end);
        canvas.addEventListener('pointerleave', end);
    } else {
        canvas.addEventListener('touchstart', start, { passive: false });
        canvas.addEventListener('touchmove', move, { passive: false });
        canvas.addEventListener('touchend', end, { passive: false });
        canvas.addEventListener('touchcancel', end, { passive: false });
        canvas.addEventListener('mousedown', start);
        canvas.addEventListener('mousemove', move);
        canvas.addEventListener('mouseup', end);
        canvas.addEventListener('mouseleave', end);
    }

    window.addEventListener('resize', handleResize);
    window.addEventListener('orientationchange', handleResize);

    clearButton.addEventListener('click', () => {
        clearSignature();
    });

    saveButton.addEventListener('click', () => {
        if (!hasStroke) {
            setInlineError('Silakan buat tanda tangan terlebih dahulu sebelum disimpan.');
            highlightTarget(section);
            return;
        }
        saveSignatureData();
        helperText.textContent = 'Tanda tangan sudah disimpan manual dan siap ikut terkirim saat submit.';
    });

    return {
        hasExistingSignature() {
            return Boolean(savedSignatureData) || (section.dataset.hasSavedSignature === 'true' && clearedInput.value !== '1');
        },
        ensureCaptured() {
            if (hasStroke && savedSignatureData === '') {
                saveSignatureData();
            }
            return Boolean(hiddenInput.value || savedSignatureData || (section.dataset.hasSavedSignature === 'true' && clearedInput.value !== '1'));
        },
        showError(message) {
            setInlineError(message);
            highlightTarget(section);
        },
        clearError() {
            setInlineError('');
        },
    };
}

function getExtensionLabel(filename) {
    const extension = String(filename || '').split('.').pop();
    return extension ? extension.toUpperCase() : 'FILE';
}

function isImageFilename(filename) {
    return /\.(jpg|jpeg|png|gif|webp)$/i.test(String(filename || ''));
}

function buildFilePreviewMarkup(sourceUrl, filename, helperText) {
    if (sourceUrl && isImageFilename(filename || sourceUrl)) {
        return `
            <div class="mb-4 flex h-32 w-full items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <img src="${sourceUrl}" alt="${filename || 'Preview file'}" class="h-full w-full object-contain">
            </div>
            <div class="rounded-full bg-emerald-100 px-3 py-1 text-[11px] font-semibold text-emerald-700">Preview siap</div>
            <div class="mt-2 text-xs font-medium text-slate-500 break-all">${filename || 'File gambar'}</div>
            <div class="mt-1 text-[11px] text-slate-400">${helperText}</div>
        `;
    }

    return `
        <div class="mb-4 flex h-32 w-full flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-white px-4 text-center shadow-sm">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-sm font-black text-slate-700">${getExtensionLabel(filename || sourceUrl)}</div>
            <div class="mt-3 text-xs font-semibold text-slate-700 break-all">${filename || 'File tersimpan'}</div>
            <div class="mt-1 text-[11px] text-slate-400">${helperText}</div>
        </div>
    `;
}

function getUploadTokenInput(name) {
    return document.querySelector(`[data-upload-token="${name}"]`);
}

function setUploadCardMessage(card, message, tone) {
    const state = card.querySelector('[data-upload-state]');
    if (!state) {
        return;
    }

    const toneClass = {
        success: 'bg-emerald-100 text-emerald-700',
        warning: 'bg-amber-100 text-amber-700',
        error: 'bg-red-100 text-red-700',
        info: 'bg-blue-100 text-blue-700',
    };

    state.textContent = message;
    state.className = `mt-3 inline-flex rounded-full px-3 py-1 text-[11px] font-semibold ${toneClass[tone] || toneClass.info}`;
}

function setUploadCardError(card, message) {
    const errorBox = card.querySelector('[data-upload-error]');
    if (!errorBox) {
        return;
    }

    if (!message) {
        errorBox.textContent = '';
        errorBox.className = 'hidden';
        return;
    }

    errorBox.textContent = message;
    errorBox.className = 'mt-3 text-xs font-semibold text-red-700';
}

function firstUploadErrorMessage(payload) {
    if (!payload || typeof payload !== 'object') {
        return '';
    }

    const errors = payload.errors || {};
    for (const value of Object.values(errors)) {
        if (Array.isArray(value) && value.length) {
            return String(value[0] || '');
        }
        if (typeof value === 'string' && value.trim()) {
            return value;
        }
    }

    return String(payload.message || '');
}

function normalizeUploadPayload(payload) {
    if (!payload || typeof payload !== 'object') {
        return null;
    }

    const upload = payload.upload && typeof payload.upload === 'object' ? payload.upload : {};
    const token = upload.token || payload.token || payload.upload_token || payload.temporary_token || '';
    if (!token) {
        return null;
    }

    return {
        token,
        field: upload.field || payload.field || '',
        preview_url: upload.preview_url || payload.preview_url || payload.url || '',
        filename: upload.filename || payload.filename || payload.normalized_name || '',
        original_name: upload.original_name || payload.original_name || '',
        mime: upload.mime || payload.mime || '',
        size_bytes: upload.size_bytes || payload.size_bytes || 0,
        source: upload.source || payload.source || 'direct_upload',
    };
}

function describeUnexpectedUploadPayload(payload) {
    if (!payload || typeof payload !== 'object') {
        return 'Respons upload dari server kosong atau tidak valid.';
    }

    const keys = Object.keys(payload).slice(0, 8).join(', ') || 'tanpa key';
    const reason = payload.reason ? ` Reason: ${payload.reason}.` : '';
    const requestId = payload.request_id ? ` Kode log: ${payload.request_id}.` : '';

    return `Server menyimpan respons upload tanpa token dokumen.${reason}${requestId} Key respons: ${keys}. Mohon hubungi HRD/IT.`;
}

function markUploadForSubmitFallback(input, card, file, payload) {
    const actions = card.querySelector('[data-upload-actions]');
    const tokenInput = getUploadTokenInput(input.name);

    if (tokenInput) {
        tokenInput.value = '';
    }

    card.dataset.currentFile = file.name;
    card.dataset.currentToken = '';
    card.dataset.pendingFile = '';
    card.dataset.submitFallback = 'true';
    card.dataset.uploading = 'false';

    setUploadCardMessage(card, 'Siap dikirim saat simpan', 'warning');
    setUploadCardError(card, 'Upload otomatis belum berhasil. File masih dipilih dan akan dicoba lagi saat Anda menekan Simpan Draft atau Kirim Final.');
    if (actions) {
        actions.innerHTML = `
            <div class="text-[11px] font-semibold text-amber-700">File akan dikirim bersama form.</div>
            <div class="text-[11px] text-slate-500 break-all">${file.name}</div>
        `;
    }

    card.classList.remove('border-blue-300', 'bg-blue-50/40', 'border-red-300', 'bg-red-50/60', 'border-emerald-300', 'bg-emerald-50/40');
    card.classList.add('border-amber-300', 'bg-amber-50/50');

    if (payload && typeof payload === 'object') {
        window.console?.warn?.('Application form upload returned no token; falling back to multipart submit.', payload);
    }
}

async function refreshApplicationFormSession() {
    try {
        const response = await fetch(applicationFormSessionPingEndpoint, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            cache: 'no-store',
        });

        if (!response.ok) {
            return false;
        }

        const payload = await response.json().catch(() => ({}));
        if (payload?.csrf_token) {
            applicationFormCsrfToken = payload.csrf_token;
            const metaToken = document.querySelector('meta[name="csrf-token"]');
            const hiddenToken = document.querySelector('#appForm input[name="_token"]');
            if (metaToken) {
                metaToken.setAttribute('content', payload.csrf_token);
            }
            if (hiddenToken) {
                hiddenToken.value = payload.csrf_token;
            }
        }

        return true;
    } catch (error) {
        return false;
    }
}

function selectedFallbackUploadBytes() {
    return ['photo_ktp_file', 'scan_ktp_file', 'cv_file'].reduce((total, name) => {
        const input = document.querySelector(`input[type="file"][name="${name}"]`);
        const tokenInput = getUploadTokenInput(name);
        const hasToken = Boolean(tokenInput?.value);
        const file = input?.files?.[0];
        return total + (!hasToken && file ? Number(file.size || 0) : 0);
    }, 0);
}

function formatClientBytes(bytes) {
    if (!bytes || bytes <= 0) {
        return '0 MB';
    }

    return `${(bytes / 1024 / 1024).toFixed(bytes >= 10 * 1024 * 1024 ? 0 : 1)} MB`;
}

async function parseUploadFailure(response) {
    const contentType = response.headers.get('content-type') || '';
    let payload = null;

    if (contentType.includes('application/json')) {
        payload = await response.json().catch(() => null);
    } else {
        await response.text().catch(() => '');
    }

    const requestId = payload?.request_id ? ` Kode log: ${payload.request_id}.` : '';
    const backendMessage = firstUploadErrorMessage(payload);
    if (backendMessage) {
        return `${backendMessage}${requestId}`;
    }

    if (response.status === 419) {
        return 'Sesi halaman sudah kedaluwarsa. Refresh halaman lalu upload ulang.';
    }
    if (response.status === 401 || response.status === 403) {
        return 'Sesi login sudah habis atau akses ditolak. Silakan login ulang lalu upload kembali.';
    }
    if (response.status === 413) {
        return 'File terlalu besar untuk batas server. Kompres file lalu coba lagi.';
    }
    if (response.status >= 500) {
        return 'Server gagal memproses upload. Silakan coba lagi atau hubungi HRD.';
    }
    if (response.redirected || contentType.includes('text/html')) {
        return 'Server mengembalikan halaman, bukan respons upload. Sesi mungkin habis; refresh halaman lalu coba lagi.';
    }

    return `Server menolak upload dokumen (HTTP ${response.status}). Cek ukuran dan tipe file lalu coba lagi.`;
}

async function uploadDocumentImmediately(input, card, config, file, uploadSequence, rollbackState = null) {
    const preview = card.querySelector('[data-upload-preview]');
    const actions = card.querySelector('[data-upload-actions]');
    const tokenInput = getUploadTokenInput(input.name);
    const previousToken = rollbackState?.token ?? (tokenInput?.value || card.dataset.currentToken || '');
    const previousFile = rollbackState?.file ?? (card.dataset.currentFile || '');
    const previousPreview = rollbackState?.preview ?? (preview ? preview.innerHTML : '');
    const previousActions = rollbackState?.actions ?? (actions ? actions.innerHTML : '');

    card.dataset.uploading = 'true';
    setUploadCardError(card, '');
    setUploadCardMessage(card, 'Mengunggah...', 'info');
    actions.innerHTML = '<div class="text-[11px] text-slate-500">Mohon tunggu, file sedang divalidasi dan diunggah.</div>';
    card.classList.remove('border-red-300', 'bg-red-50/60');
    card.classList.add('border-blue-300', 'bg-blue-50/40');

    const body = new FormData();
    body.append('field', input.name);
    body.append('document', file);
    if (previousToken) {
        body.append('previous_token', previousToken);
    }

    try {
        if (!applicationFormCsrfToken) {
            throw new Error('Token keamanan halaman tidak ditemukan. Refresh halaman lalu upload ulang.');
        }

        const response = await fetch(applicationFormUploadEndpoint, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': applicationFormCsrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body,
            credentials: 'same-origin',
        });

        const contentType = response.headers.get('content-type') || '';
        if (!response.ok) {
            throw new Error(await parseUploadFailure(response));
        }
        if (!contentType.includes('application/json')) {
            throw new Error(await parseUploadFailure(response));
        }

        const payload = await response.json().catch(() => ({}));
        const upload = normalizeUploadPayload(payload);
        if (!upload) {
            markUploadForSubmitFallback(input, card, file, payload);
            return;
        }
        if (uploadSequence && card.dataset.uploadSequence !== uploadSequence) {
            return;
        }

        card.dataset.currentFile = upload.filename || file.name;
        card.dataset.currentToken = upload.token;
        card.dataset.pendingFile = '';
        card.dataset.submitFallback = 'false';
        if (tokenInput) {
            tokenInput.value = upload.token;
        }
        input.value = '';

        preview.innerHTML = buildFilePreviewMarkup(upload.preview_url || '', upload.filename || file.name, upload.source === 'heic_converted' ? 'File iPhone berhasil dikonversi otomatis oleh server.' : 'File sudah lolos validasi dan tersimpan sementara.');
        actions.innerHTML = `
            <a class="text-xs font-semibold text-blue-600 underline" href="${upload.preview_url || '#'}" target="_blank">Lihat file sementara</a>
            <div class="text-[11px] text-slate-500 break-all">${upload.original_name || file.name}</div>
        `;
        setUploadCardMessage(card, upload.source === 'heic_converted' ? 'HEIC berhasil dikonversi' : 'Upload siap dipakai', upload.source === 'heic_converted' ? 'warning' : 'success');
        setUploadCardError(card, '');
        card.classList.remove('border-red-300', 'bg-red-50/60');
        card.classList.add('border-emerald-300', 'bg-emerald-50/40');
    } catch (error) {
        if (uploadSequence && card.dataset.uploadSequence !== uploadSequence) {
            return;
        }
        if (tokenInput) {
            tokenInput.value = previousToken;
        }
        card.dataset.currentFile = previousFile;
        card.dataset.currentToken = previousToken;
        if (preview && previousPreview) {
            preview.innerHTML = previousPreview;
        }
        const message = error instanceof TypeError
            ? 'Koneksi ke server terputus saat upload. Periksa jaringan lalu coba lagi.'
            : (error.message || 'Upload dokumen gagal. Cek ukuran dan tipe file lalu coba lagi.');
        const isValidationLikeFailure = /tidak didukung|melebihi batas|HEIC|HEIF|format|terlalu besar/i.test(message);
        const canSubmitFallback = !isValidationLikeFailure && (error instanceof TypeError || !previousToken);
        if (canSubmitFallback && input.files && input.files.length) {
            markUploadForSubmitFallback(input, card, file, { message });
        } else {
            input.value = '';
            setUploadCardMessage(card, previousToken || previousFile ? 'File lama tetap tersimpan' : 'Upload gagal', 'error');
            setUploadCardError(card, message);
            actions.innerHTML = previousActions || `<div class="text-[11px] text-slate-500 break-all">${file.name}</div>`;
            card.classList.remove('border-blue-300', 'bg-blue-50/40', 'border-emerald-300', 'bg-emerald-50/40');
            card.classList.add('border-red-300', 'bg-red-50/60');
            highlightTarget(card);
        }
    } finally {
        if (!uploadSequence || card.dataset.uploadSequence === uploadSequence) {
            card.dataset.uploading = 'false';
        }
    }
}

function bindUploadPreview(input, card, config, existingUrl) {
    const preview = card.querySelector('[data-upload-preview]');
    const state = card.querySelector('[data-upload-state]');
    const actions = card.querySelector('[data-upload-actions]');
    const errorBox = card.querySelector('[data-upload-error]');
    card.dataset.currentFile = card.dataset.existingFilename || existingUrl || '';
    card.dataset.currentToken = card.dataset.existingToken || '';

    input.addEventListener('change', async () => {
        const file = input.files && input.files[0];
        if (!file) {
            return;
        }

        const uploadSequence = `${Date.now()}-${Math.random().toString(36).slice(2)}`;
        const rollbackState = {
            token: getUploadTokenInput(input.name)?.value || card.dataset.currentToken || '',
            file: card.dataset.currentFile || '',
            preview: preview ? preview.innerHTML : '',
            actions: actions ? actions.innerHTML : '',
        };
        card.dataset.uploadSequence = uploadSequence;
        card.dataset.pendingFile = file.name;
        state.textContent = 'Preview siap, upload berjalan';
        state.className = 'mt-3 inline-flex rounded-full bg-blue-100 px-3 py-1 text-[11px] font-semibold text-blue-700';
        actions.innerHTML = `<div class="text-[11px] text-slate-500 break-all">${file.name}</div>`;
        card.classList.remove('border-red-300', 'bg-red-50/60');
        card.classList.add('border-slate-300', 'bg-slate-50');
        if (errorBox) {
            errorBox.textContent = '';
            errorBox.className = 'hidden';
        }

        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = async (event) => {
                if (card.dataset.uploadSequence !== uploadSequence) {
                    return;
                }
                preview.innerHTML = buildFilePreviewMarkup(String(event.target?.result || ''), file.name, 'Preview langsung dari file yang baru dipilih.');
                await uploadDocumentImmediately(input, card, config, file, uploadSequence, rollbackState);
            };
            reader.readAsDataURL(file);
            return;
        }

        preview.innerHTML = buildFilePreviewMarkup('', file.name, 'File non-gambar siap diunggah dan akan tampil di hasil cetak.');
        await uploadDocumentImmediately(input, card, config, file, uploadSequence, rollbackState);
    });
}

function setupUploadCards() {
    Object.entries(uploadCardConfig).forEach(([name, config]) => {
        const input = document.querySelector(`input[type="file"][name="${name}"]`);
        if (!input) {
            return;
        }

        const card = input.closest('label');
        if (!card) {
            return;
        }

        const existingLink = card.querySelector('a[href]');
        const existingUrl = card.dataset.existingUrl || (existingLink ? existingLink.getAttribute('href') : '');
        const existingFileName = card.dataset.existingFilename || (existingUrl ? decodeURIComponent(existingUrl.split('/').pop()) : '');
        const existingToken = card.dataset.existingToken || config.token || '';
        const existingSource = card.dataset.existingSource || (existingUrl ? 'saved' : 'missing');
        const hasCurrent = Boolean(existingUrl);
        const clonedInput = input.cloneNode();
        clonedInput.className = 'sr-only';
        clonedInput.accept = input.accept;
        clonedInput.name = input.name;
        clonedInput.dataset.errorKey = input.name;

        card.dataset.errorContainer = name;
        card.dataset.currentToken = existingToken;
        card.className = `group flex min-h-[260px] cursor-pointer flex-col justify-between rounded-3xl border border-dashed px-4 py-5 text-center transition ${config.error ? 'border-red-300 bg-red-50/60' : 'border-slate-300 bg-slate-50 hover:border-blue-300 hover:bg-blue-50/40'}`;
        card.innerHTML = '';
        card.appendChild(clonedInput);
        card.insertAdjacentHTML('beforeend', `
            <div>
                <div class="text-sm font-black text-slate-800 dark:text-slate-100">${config.label}</div>
                <p class="mt-2 text-xs text-slate-500">${config.note}</p>
                <div class="mt-4" data-upload-preview>
                    ${hasCurrent ? buildFilePreviewMarkup(existingUrl, existingFileName, 'File tersimpan saat ini.') : `
                        <div class="mb-4 flex h-32 w-full flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-white px-4 text-center shadow-sm">
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl border border-dashed border-slate-300 text-2xl text-slate-400">+</div>
                            <div class="mt-3 text-xs font-semibold text-slate-700">Belum ada preview</div>
                            <div class="mt-1 text-[11px] text-slate-400">Pilih file untuk melihat hasilnya di sini.</div>
                        </div>`}
                </div>
            </div>
            <div>
                <div data-upload-state class="mt-3 inline-flex rounded-full px-3 py-1 text-[11px] font-semibold ${hasCurrent ? (existingSource === 'temporary' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700') : 'bg-red-100 text-red-600'}">${hasCurrent ? (existingSource === 'temporary' ? 'Tersimpan sementara' : 'Sudah tersimpan') : 'Wajib diisi'}</div>
                <div data-upload-actions class="mt-3 space-y-1">${hasCurrent ? `<a class="text-xs font-semibold text-blue-600 underline" href="${existingUrl}" target="_blank">${existingSource === 'temporary' ? 'Lihat file sementara' : 'Lihat file tersimpan'}</a>` : '<div class="text-[11px] text-slate-500">Tap untuk memilih file</div>'}</div>
                <div data-upload-error class="${config.error ? 'mt-3 text-xs font-semibold text-red-700' : 'hidden'}">${config.error || ''}</div>
            </div>
        `);

        const tokenInput = getUploadTokenInput(name);
        if (tokenInput && existingToken) {
            tokenInput.value = existingToken;
        }
        bindUploadPreview(clonedInput, card, config, existingUrl);
    });
}

function escapeAttribute(value) {
    return String(value).replace(/"/g, '\\"');
}

function errorKeyToName(errorKey) {
    if (!String(errorKey).includes('.')) {
        return errorKey;
    }

    const parts = String(errorKey).split('.');
    return parts.reduce((carry, part, index) => {
        if (index === 0) {
            return part;
        }
        return `${carry}[${part}]`;
    }, '');
}

function resolveFieldTarget(errorKey) {
    const normalizedKey = String(errorKey || '');
    if (!normalizedKey) {
        return null;
    }

    if (normalizedKey === 'signature_data') {
        return document.getElementById('signatureSection');
    }

    if (normalizedKey === 'families' || normalizedKey === 'emergency_contacts' || normalizedKey === 'educations' || normalizedKey === 'work_experiences' || normalizedKey === 'reference_contacts' || normalizedKey === 'medical_histories' || normalizedKey === 'social_medias') {
        return document.getElementById(`repeatable-${normalizedKey}`);
    }

    if (normalizedKey === 'supporting_files' || normalizedKey.startsWith('supporting_files.')) {
        return document.querySelector('[name="supporting_files[]"]');
    }

    const direct = document.querySelector(`[name="${escapeAttribute(normalizedKey)}"]`);
    if (direct) {
        return direct;
    }

    const bracketName = errorKeyToName(normalizedKey);
    const nested = document.querySelector(`[name="${escapeAttribute(bracketName)}"]`);
    if (nested) {
        return nested;
    }

    return document.querySelector(`[data-error-container="${escapeAttribute(normalizedKey)}"]`);
}

function resolveStepFromErrorKey(errorKey) {
    const normalizedKey = String(errorKey || '');
    const prefixes = Object.keys(fieldStepMap).sort((a, b) => b.length - a.length);
    for (const prefix of prefixes) {
        if (normalizedKey === prefix || normalizedKey.startsWith(`${prefix}.`)) {
            return fieldStepMap[prefix];
        }
    }
    return 1;
}

function highlightTarget(target) {
    if (!target) {
        return;
    }

    const highlightNode = target.closest('label, td, section, .rounded-3xl, .rounded-2xl') || target;
    highlightNode.classList.add('ring-2', 'ring-amber-300', 'ring-offset-2');
    window.setTimeout(() => {
        highlightNode.classList.remove('ring-2', 'ring-amber-300', 'ring-offset-2');
    }, 2200);
}

function focusFieldByErrorKey(errorKey) {
    const step = resolveStepFromErrorKey(errorKey);
    changeStep(step);
    window.setTimeout(() => {
        const target = resolveFieldTarget(errorKey);
        if (!target) {
            return;
        }

        const focusNode = target.matches('input, select, textarea, button') ? target : target.querySelector('input, select, textarea, button');
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (focusNode && !focusNode.disabled) {
            focusNode.focus({ preventScroll: true });
        }
        highlightTarget(target);
    }, 180);
}

function buildErrorItemsFromServer() {
    const items = [];
    Object.entries(serverValidationErrors || {}).forEach(([key, messages]) => {
        (messages || []).forEach((message) => {
            items.push({
                key,
                message,
                step: resolveStepFromErrorKey(key),
            });
        });
    });

    Object.entries(missingSections || {}).forEach(([sectionKey, section]) => {
        const fieldList = Array.isArray(section?.fields) ? section.fields : [];
        items.push({
            key: sectionKey,
            message: fieldList.length > 0
                ? `${section.label}: ${fieldList.join(', ')}`
                : `${section.label}: masih ada data yang perlu dilengkapi.`,
            step: Number(section?.step || resolveStepFromErrorKey(sectionKey)),
            isSection: true,
        });
    });

    return items;
}

function renderErrorSummary(items, introText) {
    const summary = document.getElementById('applicationFormErrorSummary');
    if (!summary || !items.length) {
        return;
    }

    const buttons = items.map((item) => `
        <li>
            <button type="button" class="w-full rounded-2xl border border-red-200 bg-white px-3 py-3 text-left text-sm font-medium text-red-700 transition hover:border-red-300 hover:bg-red-50" data-error-jump="${escapeAttribute(item.key)}">
                <span class="block text-xs uppercase tracking-[0.16em] text-red-400">Step ${item.step}</span>
                <span class="mt-1 block">${item.message}</span>
            </button>
        </li>
    `).join('');

    summary.innerHTML = `
        <div class="font-semibold">Periksa kembali data application form Anda.</div>
        <div class="mt-1">${introText}</div>
        <ul class="mt-3 space-y-2">${buttons}</ul>
    `;

    summary.querySelectorAll('[data-error-jump]').forEach((button) => {
        button.addEventListener('click', () => focusFieldByErrorKey(button.dataset.errorJump));
    });

    summary.focus({ preventScroll: true });
}

function validateVisibleRequiredFields(step) {
    const config = stepConfig[step] || { selectors: [] };
    const items = [];

    config.selectors.forEach((selector) => {
        const element = document.querySelector(selector);
        if (!element || (selector !== '#signatureDataInput' && element.closest('.hidden'))) {
            return;
        }

        if (selector === '#signatureDataInput') {
            const hasSignature = signaturePadController?.ensureCaptured() || document.getElementById('signatureSection')?.dataset.hasSavedSignature === 'true';
            if (!hasSignature) {
                items.push({ key: 'signature_data', message: 'Tanda tangan digital peserta masih kosong.', step });
            } else {
                signaturePadController?.clearError();
            }
            return;
        }

        if (String(element.value || '').trim() === '') {
            element.classList.add('ring-2', 'ring-red-300');
            const label = element.closest('div')?.querySelector('label')?.textContent?.trim() || element.name;
            items.push({ key: element.name, message: `${label} masih kosong.`, step });
        } else {
            element.classList.remove('ring-2', 'ring-red-300');
        }
    });

    if (step === 1) {
        ['photo_ktp_file', 'scan_ktp_file', 'cv_file'].forEach((name) => {
            const input = document.querySelector(`input[type="file"][name="${name}"]`);
            const card = input?.closest('label');
            const tokenInput = getUploadTokenInput(name);
            const hasSavedFile = Boolean(card?.dataset.currentFile);
            const hasToken = Boolean(tokenInput?.value || card?.dataset.currentToken);
            const hasFailedPendingUpload = card?.classList.contains('border-red-300') && Boolean(card?.dataset.pendingFile) && !hasToken && !hasSavedFile;
            if (hasFailedPendingUpload) {
                items.push({ key: name, message: `${uploadCardConfig[name].label} belum berhasil diunggah. Pilih ulang file lalu tunggu sampai upload selesai.`, step });
                highlightTarget(card || input);
            } else if (!hasSavedFile && !hasToken) {
                items.push({ key: name, message: `${uploadCardConfig[name].label} belum dipilih.`, step });
                highlightTarget(card || input);
            }
        });
    }

    return items;
}

function setSubmittingState(isSubmitting, finalSubmit) {
    submitInFlight = isSubmitting;
    document.querySelectorAll('#applicationFormPrevBtn, #applicationFormNextBtn, #applicationFormDraftBtn, #applicationFormFinalBtn, #saveSignatureBtn, #clearSignatureBtn').forEach((button) => {
        if (!button) {
            return;
        }
        button.disabled = isSubmitting;
        button.classList.toggle('opacity-60', isSubmitting);
        button.classList.toggle('cursor-not-allowed', isSubmitting);
    });

    const finalButton = document.getElementById('applicationFormFinalBtn');
    const draftButton = document.getElementById('applicationFormDraftBtn');
    if (finalButton) {
        finalButton.textContent = isSubmitting && finalSubmit ? 'Mengirim...' : 'Kirim Final';
    }
    if (draftButton) {
        draftButton.textContent = isSubmitting && !finalSubmit ? 'Menyimpan...' : 'Simpan Draft';
    }
}

async function submitForm(finalSubmit) {
    if (submitInFlight) {
        return;
    }

    document.getElementById('finalSubmitFlag').value = finalSubmit ? '1' : '0';
    await refreshApplicationFormSession();

    const fallbackBytes = selectedFallbackUploadBytes();
    const effectiveLimit = Number(applicationFormUploadEffectiveLimitBytes || 0);
    const fallbackLimit = effectiveLimit > 0 ? Math.max(1024 * 1024, effectiveLimit - (1024 * 1024)) : 0;
    if (fallbackLimit > 0 && fallbackBytes > fallbackLimit) {
        const message = `Total file yang masih harus dikirim bersama form sekitar ${formatClientBytes(fallbackBytes)}, melewati batas aman server ${formatClientBytes(fallbackLimit)}. Kompres file atau pilih ulang dokumen satu per satu sampai statusnya "Upload siap dipakai", lalu kirim final lagi.`;
        renderErrorSummary([{ key: 'photo_ktp_file', message, step: 1 }], 'Dokumen belum berhasil diunggah cepat, sehingga file akan ikut terkirim bersama form dan berisiko Page Expired.');
        focusFieldByErrorKey('photo_ktp_file');
        return;
    }

    const uploadingCard = Array.from(document.querySelectorAll('[data-upload-card]')).find((card) => card.dataset.uploading === 'true');
    if (uploadingCard) {
        const field = uploadingCard.getAttribute('data-upload-card') || 'dokumen';
        renderErrorSummary([{ key: field, message: 'Tunggu sampai upload dokumen selesai terlebih dahulu.', step: 1 }], 'Upload dokumen masih berjalan. Mohon tunggu sampai status berubah menjadi siap dipakai.');
        focusFieldByErrorKey(field);
        return;
    }
    if (finalSubmit) {
        const clientErrors = validateVisibleRequiredFields(currentStep);
        if (clientErrors.length) {
            renderErrorSummary(clientErrors, 'Tap salah satu item agar langsung dibawa ke kolom yang perlu dilengkapi.');
            focusFieldByErrorKey(clientErrors[0].key);
            return;
        }
        const hasSignature = signaturePadController?.ensureCaptured() || document.getElementById('signatureSection')?.dataset.hasSavedSignature === 'true';
        if (!hasSignature) {
            signaturePadController?.showError('Tanda tangan digital wajib dibuat atau disimpan sebelum kirim final.');
            renderErrorSummary([{ key: 'signature_data', message: 'Tanda tangan digital wajib dibuat atau disimpan sebelum kirim final.', step: 6 }], 'Silakan buat tanda tangan digital lebih dulu, lalu kirim final kembali.');
            focusFieldByErrorKey('signature_data');
            return;
        }
    }
    setSubmittingState(true, finalSubmit);
    document.getElementById('appForm').submit();
}

document.addEventListener('DOMContentLoaded', () => {
    changeStep(currentStep);
    preventCopyBehavior();
    markAllFieldsForNavigation();
    setupUploadCards();
    signaturePadController = buildSignaturePad();
    refreshApplicationFormSession();
    window.setInterval(refreshApplicationFormSession, 240000);

    ['preferredJobScope', 'preferredWorkEnvironment', 'wearsGlasses', 'hadAccident', 'policeRecord', 'psychologyTest', 'maritalStatus'].forEach((id) => {
        const element = document.getElementById(id);
        if (!element) {
            return;
        }
        element.addEventListener('change', () => {
            toggleEqualityBlock('preferredJobScope', 'preferredJobScopeOtherWrapper', 'Lainnya');
            toggleEqualityBlock('preferredWorkEnvironment', 'preferredWorkEnvironmentOtherWrapper', 'Lainnya');
            toggleBlock('wearsGlasses', 'glassesDetails');
            toggleBlock('hadAccident', 'accidentDetails');
            toggleBlock('policeRecord', 'policeDetails');
            toggleBlock('psychologyTest', 'psychologyDetails');
            toggleEqualityBlock('maritalStatus', 'marriageDateBox', 'Menikah');
        });
        element.dispatchEvent(new Event('change'));
    });

    document.getElementById('applicationFormPrevBtn')?.addEventListener('click', () => changeStep(currentStep - 1));
    document.getElementById('applicationFormNextBtn')?.addEventListener('click', () => {
        const clientErrors = validateVisibleRequiredFields(currentStep);
        if (clientErrors.length) {
            renderErrorSummary(clientErrors, 'Tap salah satu item agar langsung dibawa ke kolom yang masih kosong di langkah ini.');
            focusFieldByErrorKey(clientErrors[0].key);
            return;
        }
        changeStep(currentStep + 1);
    });

    const serverItems = buildErrorItemsFromServer();
    if (serverItems.length) {
        renderErrorSummary(serverItems, 'Tap salah satu item agar langsung diarahkan ke field atau bagian yang belum benar.');
    }

    if (serverValidationErrors.signature_data?.length) {
        signaturePadController?.showError(serverValidationErrors.signature_data[0]);
        changeStep(6);
    }

    window.addEventListener('pageshow', () => {
        setSubmittingState(false, false);
    });
});
</script>
<div class="fixed inset-x-0 bottom-0 z-50 border-t border-slate-200 bg-white/95 px-4 py-3 backdrop-blur dark:border-slate-800 dark:bg-slate-950/95"><div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3"><div class="text-xs text-slate-500">Step <span id="footerStep">{{ $firstErrorStep>0?$firstErrorStep:1 }}</span>/6</div><div class="flex flex-wrap gap-2"><button type="button" id="applicationFormPrevBtn" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold">Sebelumnya</button><button type="button" id="applicationFormNextBtn" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold">Berikutnya</button><button type="button" id="applicationFormDraftBtn" onclick="submitForm(false)" class="rounded-xl border border-blue-300 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700">Simpan Draft</button><button type="button" id="applicationFormFinalBtn" onclick="submitForm(true)" class="rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700">Kirim Final</button></div></div></div>
@endsection






