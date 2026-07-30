@extends('layouts.app')

@section('content')
@php
    $isApplicant = auth()->user()->employee_id === null && !($isProbationUser ?? false);
    $isComplete = isset($applicant) && $applicant->is_complete;
    $portalTitle = $isApplicant ? 'Dashboard Kandidat' : (($isProbationUser ?? false) ? 'Portal Probation' : 'Portal Karyawan');
@endphp
<div class="space-y-6 pb-8">
    <div class="card p-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">OMEO HR Suite</div>
                <h1 class="mt-2 text-2xl font-bold text-slate-900">{{ $portalTitle }}</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-600">
                    Halo <strong>{{ auth()->user()->name }}</strong>,
                    @if($isApplicant)
                        pantau status rekrutmen dan lanjutkan langkah yang masih menunggu tindakan Anda.
                    @elseif($isProbationUser ?? false)
                        kelola profil, payroll, appraisal, dan presensi dari satu tempat tanpa perlu mencari menu satu per satu.
                    @else
                        gunakan halaman ini untuk membuka flow kerja utama harian Anda dengan lebih cepat.
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if($isApplicant)
                    <a href="{{ route('application-form.edit') }}" class="btn inline-flex">Lengkapi Data</a>
                @else
                    <a href="{{ route('employee-profile.show') }}" class="btn inline-flex">Buka Profil Saya</a>
                    <a href="{{ route('hr-notifications.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">Inbox Notifikasi</a>
                @endif
            </div>
        </div>
    </div>

    @if($isApplicant)
        <div class="grid grid-cols-1 gap-4 xl:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 xl:col-span-2">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status saat ini</div>
                <div class="mt-2 text-lg font-bold text-slate-900">{{ $isComplete ? 'Dalam proses review' : 'Draft belum lengkap' }}</div>
                <p class="mt-2 text-sm text-slate-600">
                    {{ $isComplete ? 'Data Anda sudah terkirim dan menunggu proses dari tim HRD.' : 'Lengkapi biodata, pendidikan, dan dokumen agar proses rekrutmen bisa lanjut.' }}
                </p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Application form</div>
                <div class="mt-2 text-lg font-bold text-slate-900">{{ $isComplete ? 'Sudah lengkap' : 'Perlu dilanjutkan' }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tes aktif</div>
                <div class="mt-2 text-lg font-bold text-slate-900">{{ ($showPendingStartPopup ?? false) ? 'Ada tes siap dimulai' : 'Belum ada tes aktif' }}</div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="card p-6 xl:col-span-2">
                <h2 class="text-lg font-semibold text-slate-900">Alur Rekrutmen</h2>
                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                        <div class="text-xs font-semibold uppercase tracking-wide text-emerald-600">1</div>
                        <div class="mt-2 font-semibold text-emerald-900">Registrasi akun</div>
                        <div class="mt-1 text-sm text-emerald-800">Selesai</div>
                    </div>
                    <div class="rounded-2xl border {{ $isComplete ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }} p-4">
                        <div class="text-xs font-semibold uppercase tracking-wide {{ $isComplete ? 'text-emerald-600' : 'text-amber-600' }}">2</div>
                        <div class="mt-2 font-semibold {{ $isComplete ? 'text-emerald-900' : 'text-amber-900' }}">Lengkapi data</div>
                        <div class="mt-1 text-sm {{ $isComplete ? 'text-emerald-800' : 'text-amber-800' }}">{{ $isComplete ? 'Terkirim' : 'Menunggu Anda' }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">3</div>
                        <div class="mt-2 font-semibold text-slate-900">Review HRD</div>
                        <div class="mt-1 text-sm text-slate-600">Menunggu proses</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">4</div>
                        <div class="mt-2 font-semibold text-slate-900">Interview</div>
                        <div class="mt-1 text-sm text-slate-600">Dijadwalkan bila lolos review</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">5</div>
                        <div class="mt-2 font-semibold text-slate-900">Offering</div>
                        <div class="mt-1 text-sm text-slate-600">Tahap akhir</div>
                    </div>
                </div>
            </div>

            <div class="card p-6">
                <h2 class="text-lg font-semibold text-slate-900">Prioritas Hari Ini</h2>
                <div class="mt-4 space-y-3 text-sm">
                    @if(!$isComplete)
                        <a href="{{ route('application-form.edit') }}" class="block rounded-2xl border border-amber-200 bg-amber-50 p-4 hover:bg-amber-100">
                            <div class="font-semibold text-amber-900">Lengkapi application form</div>
                            <div class="mt-1 text-amber-800">Pastikan biodata, pendidikan, dan dokumen sudah lengkap.</div>
                        </a>
                    @endif
                    <a href="{{ route('applicant.tests.index') }}" class="block rounded-2xl border border-slate-200 p-4 hover:bg-slate-50">
                        <div class="font-semibold text-slate-900">Cek tes seleksi</div>
                        <div class="mt-1 text-slate-600">Lihat apakah ada test yang siap dimulai.</div>
                    </a>
                    <a href="{{ route('applicant.contracts.index') }}" class="block rounded-2xl border border-slate-200 p-4 hover:bg-slate-50">
                        <div class="font-semibold text-slate-900">Pantau kontrak / inbox</div>
                        <div class="mt-1 text-slate-600">Buka update dokumen yang dikirim HRD.</div>
                    </a>
                </div>
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 xl:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Profil saya</div>
                <div class="mt-2 text-lg font-bold text-slate-900">Siap ditinjau</div>
                <div class="mt-1 text-sm text-slate-600">Lihat ringkasan data kerja dan data pribadi.</div>
            </div>
            <div class="rounded-2xl border {{ ($showPayrollReminderPopup ?? false) ? 'border-amber-200 bg-amber-50' : 'border-emerald-200 bg-emerald-50' }} p-4">
                <div class="text-xs font-semibold uppercase tracking-wide {{ ($showPayrollReminderPopup ?? false) ? 'text-amber-600' : 'text-emerald-600' }}">Payroll</div>
                <div class="mt-2 text-lg font-bold {{ ($showPayrollReminderPopup ?? false) ? 'text-amber-900' : 'text-emerald-900' }}">{{ ($showPayrollReminderPopup ?? false) ? 'Perlu tindakan' : 'Sudah aman' }}</div>
                <div class="mt-1 text-sm {{ ($showPayrollReminderPopup ?? false) ? 'text-amber-800' : 'text-emerald-800' }}">
                    {{ ($pendingPayrollChange ?? null) ? 'Sedang menunggu verifikasi HRD.' : (($showPayrollReminderPopup ?? false) ? 'Masih ada data wajib payroll yang perlu dilengkapi atau dicek.' : 'Data payroll utama sudah lengkap atau terverifikasi.') }}
                </div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Appraisal</div>
                <div class="mt-2 text-lg font-bold text-slate-900">Pantau penugasan</div>
                <div class="mt-1 text-sm text-slate-600">Buka daftar appraisal pribadi saat evaluator mengundang Anda.</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Presensi</div>
                <div class="mt-2 text-lg font-bold text-slate-900">Akses cepat</div>
                <div class="mt-1 text-sm text-slate-600">Check-in dan check-out dari menu yang lebih mudah ditemukan.</div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="card p-6 xl:col-span-2">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Aksi Cepat Karyawan</h2>
                    <p class="mt-1 text-sm text-slate-600">Flow utama disusun agar lebih mudah ditemukan dari desktop tanpa perlu berburu menu.</p>
                </div>
                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <a href="{{ route('employee-profile.show') }}" class="rounded-2xl border border-slate-200 p-5 hover:bg-slate-50">
                        <div class="text-sm font-semibold text-slate-900">My Profile</div>
                        <div class="mt-1 text-sm text-slate-600">Lihat data kerja, rekening aktif, payroll, dan status approval terakhir.</div>
                    </a>
                    <a href="{{ route('employee-profile.edit') }}" class="rounded-2xl border border-slate-200 p-5 hover:bg-slate-50">
                        <div class="text-sm font-semibold text-slate-900">Ajukan edit profile</div>
                        <div class="mt-1 text-sm text-slate-600">Kirim perubahan data lengkap ke antrian verifikasi HRD.</div>
                    </a>
                    <a href="{{ route('attendance.index') }}" class="rounded-2xl border border-slate-200 p-5 hover:bg-slate-50">
                        <div class="text-sm font-semibold text-slate-900">Presensi saya</div>
                        <div class="mt-1 text-sm text-slate-600">Buka halaman check-in / check-out dengan kamera dan lokasi.</div>
                    </a>
                    <a href="{{ route('appraisals.my') }}" class="rounded-2xl border border-slate-200 p-5 hover:bg-slate-50">
                        <div class="text-sm font-semibold text-slate-900">Appraisal saya</div>
                        <div class="mt-1 text-sm text-slate-600">Lihat assignment appraisal dan lanjutkan jika sudah tersedia.</div>
                    </a>
                    <a href="{{ route('my-training.index') }}" class="rounded-2xl border border-slate-200 p-5 hover:bg-slate-50">
                        <div class="text-sm font-semibold text-slate-900">My training</div>
                        <div class="mt-1 text-sm text-slate-600">Pantau materi training, progress, dan event yang perlu diikuti.</div>
                    </a>
                    <a href="{{ route('hr-notifications.index') }}" class="rounded-2xl border border-slate-200 p-5 hover:bg-slate-50">
                        <div class="text-sm font-semibold text-slate-900">Inbox notifikasi</div>
                        <div class="mt-1 text-sm text-slate-600">Baca reminder payroll, approval, dan tindak lanjut dari HRD.</div>
                    </a>
                </div>
            </div>

            <div class="card p-6">
                <h2 class="text-lg font-semibold text-slate-900">Checklist Prioritas</h2>
                <div class="mt-4 space-y-3 text-sm">
                    <div class="rounded-2xl border {{ ($showPayrollReminderPopup ?? false) ? 'border-amber-200 bg-amber-50' : 'border-emerald-200 bg-emerald-50' }} p-4">
                        <div class="font-semibold {{ ($showPayrollReminderPopup ?? false) ? 'text-amber-900' : 'text-emerald-900' }}">Payroll & rekening</div>
                        <div class="mt-1 {{ ($showPayrollReminderPopup ?? false) ? 'text-amber-800' : 'text-emerald-800' }}">{{ ($showPayrollReminderPopup ?? false) ? 'Cek kelengkapan wajib payroll dan pastikan rekening utama benar.' : 'Tidak ada kendala payroll yang perlu segera ditindak.' }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 p-4">
                        <div class="font-semibold text-slate-900">Perubahan profil</div>
                        <div class="mt-1 text-slate-600">Gunakan flow edit profile jika alamat, keluarga, pendidikan, atau dokumen berubah.</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 p-4">
                        <div class="font-semibold text-slate-900">Presensi dan appraisal</div>
                        <div class="mt-1 text-slate-600">Dua menu ini sekarang ditaruh sebagai aksi cepat agar tidak tertutup flow lain.</div>
                    </div>
                </div>
                @if($isProbationUser ?? false)
                    <a href="{{ route('probation-onboarding.edit') }}" class="btn mt-5 inline-flex w-full justify-center">Buka Kelengkapan Payroll</a>
                @endif
            </div>
        </div>
    @endif

    @if(($showPendingStartPopup ?? false) && isset($pendingStartAssignment) && $pendingStartAssignment)
    <div id="pendingTestModal" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-75 backdrop-blur-sm transition-opacity duration-300" style="display: none;">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden transform transition-all scale-100">
            <div class="bg-gradient-to-r from-emerald-600 to-teal-700 p-6 text-white text-center">
                <div class="mx-auto bg-white/20 w-16 h-16 rounded-full flex items-center justify-center mb-4 backdrop-blur-md">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L8 13l1.75-6L12 9l2.25-2L16 13l-1.75 4h-4.5z"></path></svg>
                </div>
                <h3 class="text-2xl font-bold">Test Sudah Aktif</h3>
                <p class="text-emerald-100 text-sm mt-1">Segera mulai test Anda.</p>
            </div>
            <div class="p-6 md:p-8">
                <p class="text-gray-600 dark:text-gray-300 text-center mb-6">Test sudah aktif. Silakan mulai sekarang. Klik Mulai untuk memulai timer.</p>
                <div class="flex flex-col gap-3">
                    <a href="{{ $pendingStartUrl }}" class="w-full py-3 px-4 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-center shadow-lg transition">Mulai Test</a>
                    <button id="pendingTestLaterBtn" class="w-full py-3 px-4 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl text-center transition">Nanti</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const modal = document.getElementById('pendingTestModal');
            if (!modal) return;

            const assignmentId = '{{ $pendingStartAssignment->id }}';
            const key = 'pending-test-popup-seen-' + assignmentId;
            if (!localStorage.getItem(key)) {
                modal.style.display = 'flex';
                localStorage.setItem(key, '1');
            }

            const laterBtn = document.getElementById('pendingTestLaterBtn');
            if (laterBtn) {
                laterBtn.addEventListener('click', function () {
                    modal.style.display = 'none';
                });
            }
        })();
    </script>
    @endif

    @if(($showPayrollReminderPopup ?? false) && ($isProbationUser ?? false))
    <div id="probationOnboardingModal" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-75 backdrop-blur-sm transition-opacity duration-300" style="display: none;">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden transform transition-all scale-100">
            <div class="bg-gradient-to-r from-amber-500 to-orange-600 p-6 text-white text-center">
                <div class="mx-auto bg-white/20 w-16 h-16 rounded-full flex items-center justify-center mb-4 backdrop-blur-md">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-3-3v6m8-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <h3 class="text-2xl font-bold">Reminder Onboarding Probation</h3>
                <p class="text-amber-100 text-sm mt-1">Lengkapi data payroll wajib untuk proses HRD.</p>
            </div>
            <div class="p-6 md:p-8">
                @if($pendingPayrollChange)
                    <p class="text-gray-600 dark:text-gray-300 text-center mb-6">Pengajuan data payroll Anda sedang <b>menunggu verifikasi HRD</b>. Anda tetap bisa membuka halaman onboarding untuk memantau status.</p>
                @else
                    <p class="text-gray-600 dark:text-gray-300 text-center mb-6">Mohon lengkapi data wajib payroll: <b>SIM, NPWP, BPJS Kesehatan, KK</b> beserta upload dokumennya.</p>
                @endif
                <div class="flex flex-col gap-3">
                    <a href="{{ $probationOnboardingUrl }}" class="w-full py-3 px-4 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl text-center shadow-lg transition">Buka Form Onboarding</a>
                    <button id="probationOnboardingLaterBtn" class="w-full py-3 px-4 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl text-center transition">Nanti</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const modal = document.getElementById('probationOnboardingModal');
            if (!modal) return;

            const key = 'probation-onboarding-popup-seen';
            const isPending = {{ $pendingPayrollChange ? 'true' : 'false' }};
            if (!localStorage.getItem(key) || isPending) {
                modal.style.display = 'flex';
                localStorage.setItem(key, '1');
            }

            const laterBtn = document.getElementById('probationOnboardingLaterBtn');
            if (laterBtn) {
                laterBtn.addEventListener('click', function () {
                    modal.style.display = 'none';
                });
            }
        })();
    </script>
    @endif

    @if(!$isComplete && (auth()->user()->employee_id === null))
    <div id="welcomeModal" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-75 backdrop-blur-sm transition-opacity duration-300">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden transform transition-all scale-100">
            <div class="bg-gradient-to-r from-blue-600 to-indigo-700 p-6 text-white text-center">
                <div class="mx-auto bg-white/20 w-16 h-16 rounded-full flex items-center justify-center mb-4 backdrop-blur-md">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                </div>
                <h3 class="text-2xl font-bold">Selamat Datang!</h3>
                <p class="text-blue-100 text-sm mt-1">Langkah awal karir Anda dimulai di sini.</p>
            </div>
            <div class="p-6 md:p-8">
                <p class="text-gray-600 dark:text-gray-300 text-center mb-6">Halo <b>{{ Auth::user()->name }}</b>, untuk melanjutkan proses rekrutmen, mohon lengkapi <b>Biodata, Pendidikan, & Dokumen</b> Anda terlebih dahulu.</p>
                <div class="flex flex-col gap-3">
                    <a href="{{ route('application-form.edit') }}" class="w-full py-3 px-4 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-center shadow-lg transition">Lengkapi Profil Sekarang</a>
                    <button onclick="document.getElementById('welcomeModal').style.display='none'" class="w-full py-3 px-4 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl text-center transition">Nanti Saja</button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
