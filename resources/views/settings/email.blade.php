@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-50 dark:bg-gray-900 pb-12">
    <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
        <div class="max-w-6xl mx-auto py-6 px-4">
            <h2 class="font-bold text-xl text-gray-800 dark:text-white leading-tight">SMTP & Email</h2>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-300">Sub menu khusus untuk reset password kandidat, test email, dan konfigurasi pengirim.</p>
        </div>
    </div>

    <div class="max-w-6xl mx-auto px-4 py-8">
        @if(session('success'))
            <div class="bg-green-50 text-green-700 p-4 rounded-xl border border-green-200 mb-6 flex items-start gap-3">
                <svg class="w-5 h-5 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <span class="font-bold">{{ session('success') }}</span>
            </div>
        @endif

        @if($errors->any())
            <div class="bg-red-50 text-red-700 p-4 rounded-xl border border-red-200 mb-6">
                <div class="font-bold mb-2">Masih ada data yang perlu diperbaiki.</div>
                <ul class="list-disc pl-5 text-sm space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 xl:grid-cols-[280px_minmax(0,1fr)] gap-6">
            <div class="space-y-6">
                @include('settings.partials.subnav')

                <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="text-xs font-bold uppercase tracking-[0.2em] text-gray-400">Status SMTP</div>
                    <div class="mt-3 inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $smtpConfigured ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                        {{ $smtpConfigured ? 'Siap dipakai reset password' : 'Belum siap dipakai' }}
                    </div>
                    <div class="mt-3 text-sm text-gray-500 dark:text-gray-300">Terakhir uji kirim: <span class="font-semibold text-gray-700 dark:text-gray-100">{{ optional($setting->mail_test_last_ran_at)->format('d M Y H:i') ?? '-' }}</span></div>
                    <div class="mt-2 text-sm text-gray-500 dark:text-gray-300">Email tujuan terakhir: <span class="font-semibold text-gray-700 dark:text-gray-100">{{ $setting->mail_test_last_email ?: '-' }}</span></div>
                    @if($setting->mail_test_last_status)
                        <div class="mt-3 text-xs {{ $setting->mail_test_last_status === 'success' ? 'text-emerald-600' : 'text-red-600' }}">Status terakhir: {{ strtoupper($setting->mail_test_last_status) }}</div>
                    @endif
                    @if($setting->mail_test_last_error)
                        <div class="mt-2 rounded-xl bg-red-50 border border-red-100 px-3 py-3 text-xs text-red-700">Error terakhir: {{ $setting->mail_test_last_error }}</div>
                    @endif
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="flex items-start justify-between gap-4 border-b pb-2 mb-4">
                        <div>
                            <h3 class="font-bold text-gray-700 dark:text-gray-200">Panduan SMTP Mandiri</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-300">Isi satu per satu lalu kirim email percobaan agar HRD bisa setting sendiri tanpa bantuan tim IT.</p>
                        </div>
                        @if($canManageSmtp)
                            <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">Editable oleh Superadmin / HRD</span>
                        @else
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">Readonly untuk Manager</span>
                        @endif
                    </div>

                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                        <div class="font-bold">Urutan yang disarankan</div>
                        <ol class="mt-2 list-decimal pl-5 space-y-1">
                            <li>Pilih mailer `smtp`.</li>
                            <li>Isi host, port, username, password, dan enkripsi sesuai penyedia email Anda.</li>
                            <li>Isi `From Email` dengan email resmi yang valid.</li>
                            <li>Simpan konfigurasi SMTP.</li>
                            <li>Kirim email percobaan ke inbox yang bisa Anda akses.</li>
                            <li>Jika email percobaan masuk, fitur lupa password kandidat otomatis siap dipakai.</li>
                        </ol>
                        <div class="mt-3 text-xs leading-5">Contoh umum Gmail Workspace: host `smtp.gmail.com`, port `587`, enkripsi `tls`. Contoh umum email hosting: port `465` untuk `ssl` atau `587` untuk `tls` sesuai provider.</div>
                    </div>
                </div>

                <form action="{{ route('settings.email.update') }}" method="POST" class="space-y-6">
                    @csrf
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700">
                        <h3 class="font-bold text-gray-700 dark:text-gray-200 mb-4 border-b pb-2">Konfigurasi SMTP</h3>

                        @if(!$canManageSmtp)
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-600 mb-4">
                                Anda masih bisa melihat status SMTP, tetapi perubahan konfigurasi hanya dapat dilakukan oleh superadmin atau HRD.
                            </div>
                        @endif

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Mailer</label>
                                <select name="mail_mailer" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" {{ $canManageSmtp ? '' : 'disabled' }}>
                                    <option value="smtp" @selected(old('mail_mailer', $setting->mail_mailer ?: 'smtp') === 'smtp')>SMTP</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Encryption</label>
                                <select name="mail_encryption" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" {{ $canManageSmtp ? '' : 'disabled' }}>
                                    <option value="">Tanpa enkripsi</option>
                                    <option value="tls" @selected(old('mail_encryption', $setting->mail_encryption) === 'tls')>TLS</option>
                                    <option value="ssl" @selected(old('mail_encryption', $setting->mail_encryption) === 'ssl')>SSL</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Host</label>
                                <input type="text" name="mail_host" value="{{ old('mail_host', $setting->mail_host) }}" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" {{ $canManageSmtp ? '' : 'readonly' }}>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Port</label>
                                <input type="number" name="mail_port" value="{{ old('mail_port', $setting->mail_port) }}" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" {{ $canManageSmtp ? '' : 'readonly' }}>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Username</label>
                                <input type="text" name="mail_username" value="{{ old('mail_username', $setting->mail_username) }}" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" {{ $canManageSmtp ? '' : 'readonly' }}>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Password SMTP</label>
                                <input type="password" name="mail_password" value="" placeholder="Kosongkan jika tidak ingin mengganti password" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" {{ $canManageSmtp ? '' : 'readonly' }}>
                                <p class="text-xs text-gray-400 mt-1">Password lama tetap aman dan tidak ditampilkan kembali.</p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">From Email</label>
                                <input type="email" name="mail_from_address" value="{{ old('mail_from_address', $setting->mail_from_address) }}" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" {{ $canManageSmtp ? '' : 'readonly' }}>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">From Name</label>
                                <input type="text" name="mail_from_name" value="{{ old('mail_from_name', $setting->mail_from_name ?: $setting->app_name) }}" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" {{ $canManageSmtp ? '' : 'readonly' }}>
                            </div>
                        </div>

                        @if($canManageSmtp)
                            <div class="mt-5 flex justify-end">
                                <button type="submit" class="px-6 py-3 bg-blue-600 text-white font-bold rounded-xl shadow-lg hover:bg-blue-700 transition transform hover:-translate-y-0.5">Simpan SMTP</button>
                            </div>
                        @endif
                    </div>
                </form>

                @if($canManageSmtp)
                    <form action="{{ route('settings.email.test') }}" method="POST" class="space-y-6">
                        @csrf
                        <input type="hidden" name="mail_mailer" value="{{ old('mail_mailer', $setting->mail_mailer ?: 'smtp') }}">
                        <input type="hidden" name="mail_host" value="{{ old('mail_host', $setting->mail_host) }}">
                        <input type="hidden" name="mail_port" value="{{ old('mail_port', $setting->mail_port) }}">
                        <input type="hidden" name="mail_username" value="{{ old('mail_username', $setting->mail_username) }}">
                        <input type="hidden" name="mail_encryption" value="{{ old('mail_encryption', $setting->mail_encryption) }}">
                        <input type="hidden" name="mail_from_address" value="{{ old('mail_from_address', $setting->mail_from_address) }}">
                        <input type="hidden" name="mail_from_name" value="{{ old('mail_from_name', $setting->mail_from_name ?: $setting->app_name) }}">
                        <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700">
                            <h3 class="font-bold text-gray-700 dark:text-gray-200 mb-4 border-b pb-2">Email Percobaan</h3>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Email Tujuan Percobaan</label>
                                <input type="email" name="smtp_test_recipient" value="{{ old('smtp_test_recipient', $setting->mail_test_last_email) }}" class="w-full rounded-lg border-blue-200 focus:border-blue-500 focus:ring-blue-500" placeholder="contoh: hrd@perusahaan.com">
                                <p class="text-xs text-gray-500 mt-2">Gunakan inbox yang bisa Anda buka sekarang. Sistem akan mengirim email uji untuk memastikan reset password kandidat berjalan normal.</p>
                            </div>
                            <div class="mt-5 flex justify-end">
                                <button type="submit" class="px-6 py-3 bg-emerald-600 text-white font-bold rounded-xl shadow-lg hover:bg-emerald-700 transition transform hover:-translate-y-0.5">Kirim Email Percobaan</button>
                            </div>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
