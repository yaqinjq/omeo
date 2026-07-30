@extends('layouts.app')

@section('content')
@php
    $age = $profile->date_of_birth ? \Carbon\Carbon::parse($profile->date_of_birth)->age : null;
    $iq = is_numeric($stats['iq'] ?? null) ? (int) $stats['iq'] : null;
    $interview = is_numeric($stats['interview_score'] ?? null) ? (int) $stats['interview_score'] : null;
    $personal = $profile->personal_json ?? [];
    $referenceContacts = $profile->reference_contacts ?? [];
    $candidateStatus = $candidate->status ?? 'belum_masuk_recruitment';
    $statusLabel = ucfirst(str_replace('_', ' ', (string) $candidateStatus));
    $discAxis = $stats['disc_axis'] ?? null;
    $governanceStatus = $profile->governance_status ?? 'active';
    $governanceLabel = $profile->governanceStatusLabel();
    $applicationPosition = $candidate->applied_position_name ?? $profile->applied_position_name ?? '-';
    $applicationDepartment = $candidate->applied_department_name ?? $profile->applied_department_name ?? '-';
    $applicationOutlet = $candidate->applied_outlet_name ?? $profile->applied_outlet_name ?? '-';
@endphp

<div class="bg-gray-100 dark:bg-gray-900 min-h-[calc(100vh-140px)] p-4 md:p-6 rounded-2xl">
    <div class="max-w-7xl mx-auto space-y-6">
        @if(session('success'))
            <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
        @endif

        <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">
            <aside class="xl:col-span-4 space-y-6">
                <section class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="relative w-36 h-36 mx-auto rounded-full p-1 bg-gradient-to-tr from-blue-500 via-cyan-400 to-emerald-400">
                        @if($profile->photo_path)
                            <img src="{{ asset('storage/'.$profile->photo_path) }}" class="w-full h-full rounded-full object-cover border-4 border-white dark:border-gray-800" alt="Foto Kandidat">
                        @else
                            <div class="w-full h-full rounded-full bg-gray-200 grid place-items-center text-4xl font-bold text-gray-500 border-4 border-white">{{ strtolower(substr($profile->full_name ?: 'A', 0, 1)) }}</div>
                        @endif
                    </div>

                    <h2 class="text-3xl font-extrabold text-center text-gray-900 dark:text-white mt-6">{{ $profile->full_name ?: '-' }}</h2>
                    <p class="text-center text-gray-500 mt-2 text-sm">{{ $age ?? '-' }} Tahun â€¢ Stage {{ \App\Models\ApplicantProfile::TALENT_POOL_STAGE_LABELS[$stage] ?? ucfirst($stage) }}</p>

                    <div class="mt-5 flex flex-wrap justify-center gap-2">
                        <span class="px-4 py-1.5 rounded-full bg-blue-100 text-blue-700 font-bold text-xs tracking-wide">{{ \App\Models\ApplicantProfile::TALENT_POOL_STAGE_LABELS[$stage] ?? ucfirst($stage) }}</span>
                        <span class="px-4 py-1.5 rounded-full {{ $governanceStatus === 'active' ? 'bg-slate-100 text-slate-700' : ($governanceStatus === 'rejected' ? 'bg-rose-100 text-rose-700' : ($governanceStatus === 'blacklisted' ? 'bg-slate-900 text-white' : 'bg-slate-200 text-slate-700')) }} font-bold text-xs tracking-wide">{{ $governanceLabel }}</span>
                    </div>

                    <div class="mt-6 grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-xl bg-gray-50 border border-gray-200 px-4 py-3">
                            <div class="text-[11px] uppercase tracking-wide text-gray-500 font-bold">Profile Age</div>
                            <div class="mt-1 font-semibold text-gray-800">{{ $profile->created_at?->diffForHumans() }}</div>
                        </div>
                        <div class="rounded-xl bg-gray-50 border border-gray-200 px-4 py-3">
                            <div class="text-[11px] uppercase tracking-wide text-gray-500 font-bold">Recruitment Status</div>
                            <div class="mt-1 font-semibold text-gray-800">{{ $statusLabel }}</div>
                        </div>
                    </div>

                    @if($profile->governance_reason)
                        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            <div class="font-semibold text-amber-800">Alasan governance</div>
                            <div class="mt-1">{{ $profile->governance_reason }}</div>
                        </div>
                    @endif

                    @if(empty($hideSalaryExpectation))
                        <div class="mt-4 rounded-xl bg-blue-50 border border-blue-100 px-4 py-3 text-center">
                            <div class="text-[11px] uppercase tracking-wide text-blue-600 font-bold">Ekspektasi Gaji</div>
                            <div class="text-2xl font-extrabold text-blue-800">{{ $profile->salary_expectation ? 'Rp '.number_format((float) $profile->salary_expectation, 0, ',', '.') : '-' }}</div>
                        </div>
                    @endif

                    <div class="mt-4 space-y-2">
                        @if($profile->is_complete && empty($isVerified) && $profile->isGovernanceActive())
                            <form method="POST" action="{{ route('hrd.applicants.reviewed', $profile) }}" onsubmit="return confirm('Loloskan administrasi pelamar ini dan kirim ke Recruitment Kandidat?')">
                                @csrf
                                <button type="submit" class="w-full py-3 rounded-xl font-bold transition bg-blue-600 text-white hover:bg-blue-700">Lolos Administrasi</button>
                            </form>
                        @elseif(!empty($isVerified))
                            <div class="w-full py-3 rounded-xl bg-slate-100 text-slate-600 font-bold text-center border border-slate-200">Sudah masuk Recruitment Kandidat</div>
                        @else
                            <div class="w-full py-3 rounded-xl bg-amber-100 text-amber-700 font-bold text-center">Profil belum bisa diloloskan administrasi.</div>
                        @endif

                        @if($canGovern)
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                <form method="POST" action="{{ route('hrd.applicants.govern', $profile) }}" onsubmit="return confirm('Tolak applicant ini dari Talent Pool?')">@csrf<input type="hidden" name="action" value="reject"><button type="submit" class="w-full py-2 rounded-xl bg-amber-50 text-amber-700 border border-amber-200 font-semibold">Tolak</button></form>
                                <form method="POST" action="{{ route('hrd.applicants.govern', $profile) }}" onsubmit="return confirm('Blacklist applicant ini?')">@csrf<input type="hidden" name="action" value="blacklist"><button type="submit" class="w-full py-2 rounded-xl bg-slate-50 text-slate-700 border border-slate-300 font-semibold">Blacklist</button></form>
                                <form method="POST" action="{{ route('hrd.applicants.govern', $profile) }}" onsubmit="return confirm('Archive applicant ini? Data tetap disimpan untuk audit.')">@csrf<input type="hidden" name="action" value="archive"><button type="submit" class="w-full py-2 rounded-xl bg-gray-50 text-gray-700 border border-gray-300 font-semibold">Archive</button></form>
                            </div>
                        @endif

                        @if($candidate)
                            <a href="{{ route('hrd.applicants.export-pdf', $profile) }}" class="block w-full py-3 rounded-xl bg-slate-900 text-white font-bold text-center hover:bg-slate-800 transition">Preview PDF Profil + Hasil Test</a>
                        @endif
                    </div>
                </section>

                <section class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-5">Psychometric Stats</h3>
                    <div class="space-y-5">
                        <div><div class="flex justify-between text-lg font-bold mb-2"><span>IQ Score</span><span class="text-blue-600">{{ $iq ?? '-' }}</span></div><div class="w-full h-3 rounded-full bg-gray-200 overflow-hidden"><div class="h-3 bg-blue-600 rounded-full" style="width: {{ $iq ? min(100, max(0, (int) round(($iq / 160) * 100))) : 0 }}%"></div></div></div>
                        <div><div class="flex justify-between text-lg font-bold mb-2"><span>DISC Profile</span><span class="text-purple-600">{{ $stats['disc'] ?? '-' }}{{ $discAxis ? ' ('.$discAxis.')' : '' }}</span></div><div class="w-full h-3 rounded-full bg-gray-200 overflow-hidden"><div class="h-3 bg-purple-600 rounded-full" style="width: {{ !empty($stats['disc']) ? 85 : 0 }}%"></div></div></div>
                        <div><div class="flex justify-between text-lg font-bold mb-2"><span>Interview Score</span><span class="text-green-600">{{ $interview !== null ? $interview.'/100' : '-' }}</span></div><div class="w-full h-3 rounded-full bg-gray-200 overflow-hidden"><div class="h-3 bg-green-500 rounded-full" style="width: {{ $interview ?? 0 }}%"></div></div></div>
                    </div>
                </section>
            </aside>

            <section class="xl:col-span-8 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-4 shadow-sm"><div class="text-gray-500 text-sm font-bold uppercase">Email</div><div class="text-lg font-semibold mt-2 break-all">{{ $profile->user?->email ?? data_get($personal, 'email', '-') }}</div></div>
                    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-4 shadow-sm"><div class="text-gray-500 text-sm font-bold uppercase">No WhatsApp</div><div class="text-lg font-semibold mt-2">{{ $profile->whatsapp ?: '-' }}</div></div>
                    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-4 shadow-sm"><div class="text-gray-500 text-sm font-bold uppercase">Governed By</div><div class="text-lg font-semibold mt-2">{{ $profile->governedBy?->name ?: '-' }}</div></div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-xl font-extrabold text-gray-900 dark:text-white mb-4">Lamaran Kandidat</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm md:text-base">
                        <div class="rounded-xl border border-blue-100 bg-blue-50 px-4 py-4">
                            <div class="text-[11px] uppercase tracking-wide text-blue-600 font-bold">Posisi yang Dilamar</div>
                            <div class="mt-2 font-semibold text-slate-900">{{ $applicationPosition !== '' ? $applicationPosition : '-' }}</div>
                        </div>
                        <div class="rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-4">
                            <div class="text-[11px] uppercase tracking-wide text-emerald-600 font-bold">Departemen Diminati</div>
                            <div class="mt-2 font-semibold text-slate-900">{{ $applicationDepartment !== '' ? $applicationDepartment : '-' }}</div>
                        </div>
                        <div class="rounded-xl border border-amber-100 bg-amber-50 px-4 py-4">
                            <div class="text-[11px] uppercase tracking-wide text-amber-600 font-bold">Outlet Diminati</div>
                            <div class="mt-2 font-semibold text-slate-900">{{ $applicationOutlet !== '' ? $applicationOutlet : '-' }}</div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-xl font-extrabold text-gray-900 dark:text-white mb-4">Preview Dokumen</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-3"> <div class="text-xs uppercase text-gray-500 font-bold mb-2">Foto</div> @if($profile->photo_path)<a href="{{ asset('storage/'.$profile->photo_path) }}" target="_blank"><img src="{{ asset('storage/'.$profile->photo_path) }}" alt="Foto Pelamar" class="w-full h-44 object-cover rounded-lg border border-gray-200"></a>@else<div class="h-44 rounded-lg bg-gray-100 text-gray-400 flex items-center justify-center text-sm">Belum ada foto</div>@endif </div>
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-3"> <div class="text-xs uppercase text-gray-500 font-bold mb-2">KTP</div> @if($profile->ktp_path)<a href="{{ asset('storage/'.$profile->ktp_path) }}" target="_blank"><img src="{{ asset('storage/'.$profile->ktp_path) }}" alt="KTP Pelamar" class="w-full h-44 object-cover rounded-lg border border-gray-200"></a>@else<div class="h-44 rounded-lg bg-gray-100 text-gray-400 flex items-center justify-center text-sm">Belum ada KTP</div>@endif </div>
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-3"> <div class="text-xs uppercase text-gray-500 font-bold mb-2">CV (PDF)</div> @if($profile->cv_path)<a href="{{ asset('storage/'.$profile->cv_path) }}" target="_blank" class="h-44 rounded-lg bg-red-50 border border-red-100 text-red-600 flex items-center justify-center text-2xl font-extrabold">PDF</a>@else<div class="h-44 rounded-lg bg-gray-100 text-gray-400 flex items-center justify-center text-sm">Belum ada CV</div>@endif </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 space-y-8">
                    <div>
                        <h3 class="text-xl font-extrabold text-gray-900 dark:text-white mb-4">Informasi Dasar</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm md:text-base">
                            <div><div class="text-gray-500 uppercase text-xs">Nama Lengkap</div><div class="font-semibold">{{ $profile->full_name ?: '-' }}</div></div>
                            <div><div class="text-gray-500 uppercase text-xs">Email</div><div class="font-semibold">{{ $profile->user?->email ?? '-' }}</div></div>
                            <div><div class="text-gray-500 uppercase text-xs">Tempat Lahir</div><div class="font-semibold">{{ $profile->place_of_birth ?: '-' }}</div></div>
                            <div><div class="text-gray-500 uppercase text-xs">Tanggal Lahir</div><div class="font-semibold">{{ $profile->date_of_birth ?: '-' }}</div></div>
                            <div><div class="text-gray-500 uppercase text-xs">No. KTP</div><div class="font-semibold">{{ $profile->ktp_number ?: '-' }}</div></div>
                            <div><div class="text-gray-500 uppercase text-xs">Jenis Kelamin</div><div class="font-semibold">{{ $profile->gender ?: '-' }}</div></div>
                            <div><div class="text-gray-500 uppercase text-xs">Agama</div><div class="font-semibold">{{ $profile->religion ?: '-' }}</div></div>
                            <div><div class="text-gray-500 uppercase text-xs">Golongan Darah</div><div class="font-semibold">{{ data_get($personal, 'blood_type', '-') ?: '-' }}</div></div>
                            <div><div class="text-gray-500 uppercase text-xs">Status Nikah</div><div class="font-semibold">{{ $profile->marital_status ?: '-' }}</div></div>
                            <div><div class="text-gray-500 uppercase text-xs">Tanggal Menikah</div><div class="font-semibold">{{ data_get($personal, 'marriage_date', '-') ?: '-' }}</div></div>
                            <div><div class="text-gray-500 uppercase text-xs">Alamat KTP</div><div class="font-semibold">{{ $profile->ktp_address ?: '-' }}</div></div>
                            <div><div class="text-gray-500 uppercase text-xs">Alamat Domisili</div><div class="font-semibold">{{ $profile->domicile_address ?: '-' }}</div></div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-xl font-extrabold text-gray-900 dark:text-white mb-4">Keluarga</h3>
                        <div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-gray-50 text-gray-500 uppercase text-xs"><tr><th class="px-4 py-3 text-left">Hubungan</th><th class="px-4 py-3 text-left">Nama</th><th class="px-4 py-3 text-left">Pekerjaan</th></tr></thead><tbody>@forelse($profile->families as $fam)<tr class="border-b border-gray-100"><td class="px-4 py-3 font-semibold">{{ $fam['relation'] ?? '-' }}</td><td class="px-4 py-3">{{ $fam['name'] ?? '-' }}</td><td class="px-4 py-3">{{ $fam['job'] ?? '-' }}</td></tr>@empty<tr><td colspan="3" class="px-4 py-3 text-gray-400">Belum ada data keluarga.</td></tr>@endforelse</tbody></table></div>
                    </div>

                    <div>
                        <h3 class="text-xl font-extrabold text-gray-900 dark:text-white mb-4">Riwayat Pendidikan</h3>
                        <div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-gray-50 text-gray-500 uppercase text-xs"><tr><th class="px-4 py-3 text-left">Jenjang</th><th class="px-4 py-3 text-left">Institusi</th><th class="px-4 py-3 text-left">Jurusan</th><th class="px-4 py-3 text-left">Masuk</th><th class="px-4 py-3 text-left">Lulus</th></tr></thead><tbody>@forelse($profile->educations as $edu)<tr class="border-b border-gray-100"><td class="px-4 py-3">{{ $edu['level'] ?? '-' }}</td><td class="px-4 py-3 font-semibold">{{ $edu['school'] ?? '-' }}</td><td class="px-4 py-3">{{ $edu['major'] ?? '-' }}</td><td class="px-4 py-3">{{ $edu['year_in'] ?? '-' }}</td><td class="px-4 py-3">{{ $edu['year_out'] ?? '-' }}</td></tr>@empty<tr><td colspan="5" class="px-4 py-3 text-gray-400">Belum ada riwayat pendidikan.</td></tr>@endforelse</tbody></table></div>
                    </div>

                    <div>
                        <h3 class="text-xl font-extrabold text-gray-900 dark:text-white mb-4">Riwayat Karir & Referensi</h3>
                        <div class="space-y-6">
                            <div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-gray-50 text-gray-500 uppercase text-xs"><tr><th class="px-4 py-3 text-left">Perusahaan</th><th class="px-4 py-3 text-left">Jabatan</th><th class="px-4 py-3 text-left">Periode</th><th class="px-4 py-3 text-left">Alasan Keluar</th></tr></thead><tbody>@forelse($profile->work_experiences as $work)<tr class="border-b border-gray-100"><td class="px-4 py-3">{{ $work['company'] ?? '-' }}</td><td class="px-4 py-3">{{ $work['position'] ?? '-' }}</td><td class="px-4 py-3">{{ $work['date_start'] ?? '-' }} - {{ $work['date_end'] ?? '-' }}</td><td class="px-4 py-3">{{ $work['reason'] ?? '-' }}</td></tr>@empty<tr><td colspan="4" class="px-4 py-3 text-gray-400">Belum ada riwayat pekerjaan.</td></tr>@endforelse</tbody></table></div>
                            <div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-gray-50 text-gray-500 uppercase text-xs"><tr><th class="px-4 py-3 text-left">Nama</th><th class="px-4 py-3 text-left">Hubungan</th><th class="px-4 py-3 text-left">Perusahaan</th><th class="px-4 py-3 text-left">No. HP</th></tr></thead><tbody>@forelse($referenceContacts as $ref)<tr class="border-b border-gray-100"><td class="px-4 py-3">{{ $ref['name'] ?? '-' }}</td><td class="px-4 py-3">{{ $ref['relation'] ?? '-' }}</td><td class="px-4 py-3">{{ $ref['company'] ?? '-' }}</td><td class="px-4 py-3">{{ $ref['phone'] ?? '-' }}</td></tr>@empty<tr><td colspan="4" class="px-4 py-3 text-gray-400">Belum ada data referensi.</td></tr>@endforelse</tbody></table></div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-xl font-extrabold text-gray-900 dark:text-white mb-4">Audit Timeline</h3>
                        <div class="space-y-3">
                            @forelse($profile->activityLogs->take(20) as $activity)
                                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                                    <div class="flex flex-col gap-1 md:flex-row md:items-center md:justify-between">
                                        <div class="font-semibold text-gray-800">{{ ucfirst(str_replace('_', ' ', $activity->action_type)) }}</div>
                                        <div class="text-xs text-gray-500">{{ $activity->created_at?->diffForHumans() }} â€¢ {{ $activity->actor?->name ?: 'System' }}</div>
                                    </div>
                                    @if($activity->old_status || $activity->new_status)
                                        <div class="mt-1 text-xs text-gray-600">{{ $activity->old_status ?: '-' }} â†’ {{ $activity->new_status ?: '-' }}</div>
                                    @endif
                                    @if(!empty($activity->metadata['reason']))
                                        <div class="mt-1 text-sm text-gray-700">Reason: {{ $activity->metadata['reason'] }}</div>
                                    @endif
                                </div>
                            @empty
                                <div class="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500">Belum ada audit trail applicant.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>
@endsection
