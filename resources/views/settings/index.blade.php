@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-50 dark:bg-gray-900 pb-12">
    <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
        <div class="max-w-5xl mx-auto py-6 px-4">
            <h2 class="font-bold text-xl text-gray-800 dark:text-white leading-tight">
                Pengaturan Aplikasi
            </h2>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-300">
                Kelola identitas aplikasi, retensi kandidat, dan konfigurasi SMTP dari satu halaman agar HRD bisa mengatur reset password secara mandiri.
            </p>
        </div>
    </div>

    <div class="max-w-5xl mx-auto px-4 py-8">
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

        <form action="{{ route('settings.update') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 xl:grid-cols-[280px_minmax(0,1fr)] gap-6">
                <div class="space-y-6">
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700">
                        <h3 class="font-bold text-gray-700 dark:text-gray-200 mb-4">Logo Aplikasi</h3>
                        <div class="flex flex-col items-center">
                            <div class="w-32 h-32 bg-gray-100 rounded-xl flex items-center justify-center mb-4 border overflow-hidden">
                                @if($setting->app_logo_path)
                                    <img src="{{ asset('storage/'.$setting->app_logo_path) }}" class="w-full h-full object-contain">
                                @else
                                    <span class="text-gray-400 text-xs">No Logo</span>
                                @endif
                            </div>
                            <input type="file" name="app_logo" class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"/>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700">
                        <h3 class="font-bold text-gray-700 dark:text-gray-200 mb-4">Favicon</h3>
                        <div class="flex flex-col items-center">
                            <div class="w-16 h-16 bg-gray-100 rounded-lg flex items-center justify-center mb-4 border overflow-hidden">
                                @if($setting->app_favicon_path)
                                    <img src="{{ asset('storage/'.$setting->app_favicon_path) }}" class="w-full h-full object-contain">
                                @else
                                    <span class="text-gray-400 text-xs">No Icon</span>
                                @endif
                            </div>
                            <input type="file" name="app_favicon" class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700"/>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700">
                        <div class="text-xs font-bold uppercase tracking-[0.2em] text-gray-400">Status SMTP</div>
                        <div class="mt-3 inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $smtpConfigured ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                            {{ $smtpConfigured ? 'Siap dipakai reset password' : 'Belum siap dipakai' }}
                        </div>
                        <div class="mt-3 text-sm text-gray-500 dark:text-gray-300">
                            Terakhir uji kirim:
                            <span class="font-semibold text-gray-700 dark:text-gray-100">{{ optional($setting->mail_test_last_ran_at)->format('d M Y H:i') ?? '-' }}</span>
                        </div>
                        <div class="mt-2 text-sm text-gray-500 dark:text-gray-300">
                            Email tujuan terakhir:
                            <span class="font-semibold text-gray-700 dark:text-gray-100">{{ $setting->mail_test_last_email ?: '-' }}</span>
                        </div>
                        @if($setting->mail_test_last_status)
                            <div class="mt-3 text-xs {{ $setting->mail_test_last_status === 'success' ? 'text-emerald-600' : 'text-red-600' }}">
                                Status terakhir: {{ strtoupper($setting->mail_test_last_status) }}
                            </div>
                        @endif
                        @if($setting->mail_test_last_error)
                            <div class="mt-2 rounded-xl bg-red-50 border border-red-100 px-3 py-3 text-xs text-red-700">
                                Error terakhir: {{ $setting->mail_test_last_error }}
                            </div>
                        @endif
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700">
                        <h3 class="font-bold text-gray-700 dark:text-gray-200 mb-4 border-b pb-2">Identitas Umum</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nama Aplikasi</label>
                                <input type="text" name="app_name" value="{{ old('app_name', $setting->app_name) }}" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                                <p class="text-xs text-gray-400 mt-1">Akan muncul di sidebar dan halaman login.</p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Meta Title</label>
                                <input type="text" name="meta_title" value="{{ old('meta_title', $setting->meta_title) }}" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Meta Description</label>
                                <textarea name="meta_description" rows="3" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">{{ old('meta_description', $setting->meta_description) }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700">
                        <h3 class="font-bold text-gray-700 dark:text-gray-200 mb-4 border-b pb-2">Retensi & Blacklist Kandidat</h3>
                        <div class="space-y-4">
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                                <input type="checkbox" name="retention_enabled" value="1" class="rounded border-gray-300" @checked(old('retention_enabled', $setting->retention_enabled ?? true))>
                                Aktifkan auto-delete kandidat gagal via scheduler
                            </label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Retensi Rejected (hari)</label>
                                    <input type="number" min="1" max="3650" name="retention_rejected_days" value="{{ old('retention_rejected_days', $setting->retention_rejected_days ?? 30) }}" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Retensi Blocked (hari)</label>
                                    <input type="number" min="1" max="3650" name="retention_blocked_days" value="{{ old('retention_blocked_days', $setting->retention_blocked_days ?? 365) }}" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                                </div>
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                Terakhir dijalankan: {{ optional($setting->retention_last_run_at)->format('d M Y H:i') ?? '-' }}
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700">
                        <div class="flex items-start justify-between gap-4 border-b pb-2 mb-4">
                            <div>
                                <h3 class="font-bold text-gray-700 dark:text-gray-200">SMTP & Reset Password</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-300">Bagian ini dipakai agar kandidat bisa reset password sendiri tanpa bantuan tim IT.</p>
                            </div>
                            @if($canManageSmtp)
                                <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">Editable oleh Superadmin / HRD</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">Readonly untuk Manager</span>
                            @endif
                        </div>

                        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 mb-5">
                            <div class="font-bold">Panduan singkat pengisian SMTP</div>
                            <ol class="mt-2 list-decimal pl-5 space-y-1">
                                <li>Pilih mailer `smtp`.</li>
                                <li>Isi host, port, username, password, dan enkripsi sesuai penyedia email Anda.</li>
                                <li>Isi `From Email` dengan email pengirim resmi yang valid.</li>
                                <li>Klik `Simpan SMTP` lalu `Simpan + Kirim Email Percobaan` untuk uji mandiri.</li>
                                <li>Jika uji kirim berhasil, fitur lupa password kandidat otomatis siap dipakai.</li>
                            </ol>
                            <div class="mt-3 text-xs leading-5">
                                Contoh umum Gmail Workspace: host `smtp.gmail.com`, port `587`, enkripsi `tls`.<br>
                                Contoh umum hosting email: host sesuai provider, port `465` untuk `ssl` atau `587` untuk `tls`.
                            </div>
                        </div>

                        @if(!$canManageSmtp)
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-600">
                                Anda masih bisa melihat status SMTP, tetapi perubahan konfigurasi hanya dapat dilakukan oleh superadmin atau HRD.
                            </div>
                        @endif

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Mailer</label>
                                <select name="mail_mailer" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" {{ $canManageSmtp ? '' : 'disabled' }}>
                                    <option value="">Pilih mailer</option>
                                    <option value="smtp" @selected(old('mail_mailer', $setting->mail_mailer) === 'smtp')>SMTP</option>
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
                                <p class="text-xs text-gray-400 mt-1">Password lama tetap tersimpan aman dan tidak ditampilkan kembali.</p>
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
                            <div class="mt-5 rounded-2xl border border-blue-100 bg-blue-50 p-4">
                                <label class="block text-xs font-bold text-blue-700 uppercase mb-1">Email Tujuan Percobaan</label>
                                <input type="email" name="smtp_test_recipient" value="{{ old('smtp_test_recipient', $setting->mail_test_last_email) }}" class="w-full rounded-lg border-blue-200 focus:border-blue-500 focus:ring-blue-500" placeholder="contoh: hrd@perusahaan.com">
                                <p class="text-xs text-blue-700 mt-2">Gunakan email yang bisa Anda akses. Sistem akan mengirim email uji untuk memastikan reset password kandidat bisa berjalan.</p>
                            </div>
                        @endif
                    </div>

                    <div class="flex flex-col sm:flex-row sm:justify-end gap-3">
                        <button type="submit" name="settings_action" value="save" class="px-6 py-3 bg-blue-600 text-white font-bold rounded-xl shadow-lg hover:bg-blue-700 transition transform hover:-translate-y-0.5">
                            {{ $canManageSmtp ? 'Simpan Pengaturan & SMTP' : 'Simpan Pengaturan Umum' }}
                        </button>
                        @if($canManageSmtp)
                            <button type="submit" name="settings_action" value="smtp_test" class="px-6 py-3 bg-emerald-600 text-white font-bold rounded-xl shadow-lg hover:bg-emerald-700 transition transform hover:-translate-y-0.5">
                                Simpan + Kirim Email Percobaan
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
