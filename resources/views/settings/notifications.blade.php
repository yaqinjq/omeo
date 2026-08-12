@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-50 dark:bg-gray-900 pb-12">
    <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
        <div class="max-w-6xl mx-auto py-6 px-4">
            <h2 class="font-bold text-xl text-gray-800 dark:text-white leading-tight">Pengaturan Notifikasi</h2>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-300">Atur inbox internal, email peserta, dan persiapan WhatsApp Official API untuk karyawan.</p>
        </div>
    </div>

    <div class="max-w-6xl mx-auto px-4 py-8">
        @if(session('success'))
            <div class="bg-green-50 text-green-700 p-4 rounded-xl border border-green-200 mb-6">{{ session('success') }}</div>
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

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 dark:bg-gray-800">
                    <div class="text-xs font-bold uppercase tracking-[0.2em] text-gray-400">Strategi Kanal</div>
                    <div class="mt-3 space-y-3 text-sm text-gray-600 dark:text-gray-300">
                        <div class="rounded-2xl bg-blue-50 border border-blue-100 px-4 py-3 text-blue-900">Peserta: email only untuk event yang diaktifkan.</div>
                        <div class="rounded-2xl bg-emerald-50 border border-emerald-100 px-4 py-3 text-emerald-900">Karyawan: inbox internal + email + WhatsApp sesuai event.</div>
                        <div class="rounded-2xl bg-amber-50 border border-amber-100 px-4 py-3 text-amber-900">WhatsApp memakai target Official API dan aman dijadikan fondasi jangka panjang.</div>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <form action="{{ route('settings.notifications.update') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                    @csrf

                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex items-start justify-between gap-4 border-b pb-3 mb-5">
                            <div>
                                <h3 class="font-bold text-gray-800 dark:text-gray-100">Event & Kanal</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-300">Setiap event bisa diaktifkan per kanal. Untuk peserta, checkbox WhatsApp diabaikan secara otomatis.</p>
                            </div>
                            @if($canManageNotifications)
                                <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">Editable oleh Superadmin / HRD</span>
                            @endif
                        </div>

                        <div class="space-y-4">
                            @foreach($notificationLabels as $eventKey => $label)
                                @php($channels = $notificationSettings['events'][$eventKey] ?? [])
                                <div class="rounded-2xl border border-gray-200 p-4">
                                    <div class="font-semibold text-gray-800 dark:text-gray-100">{{ $label }}</div>
                                    <div class="mt-3 grid gap-3 sm:grid-cols-3 text-sm">
                                        @foreach(['internal' => 'Inbox Internal', 'email' => 'Email', 'whatsapp' => 'WhatsApp'] as $channelKey => $channelLabel)
                                            <label class="flex items-center gap-3 rounded-2xl border border-gray-200 px-4 py-3 {{ ($channels[$channelKey] ?? false) ? 'bg-blue-50 border-blue-200 text-blue-800' : 'bg-gray-50 text-gray-700' }}">
                                                <input type="checkbox" name="events[{{ $eventKey }}][{{ $channelKey }}]" value="1" class="rounded border-gray-300" @checked($channels[$channelKey] ?? false) {{ $canManageNotifications ? '' : 'disabled' }}>
                                                <span>{{ $channelLabel }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 dark:bg-gray-800">
                        <h3 class="font-bold text-gray-800 dark:text-gray-100 border-b pb-3 mb-5">Template Pesan Ringkas</h3>
                        <div class="space-y-5">
                            @foreach($notificationLabels as $eventKey => $label)
                                @php($template = $notificationSettings['templates'][$eventKey] ?? ['title' => '', 'body' => '', 'attachment_path' => null, 'attachment_name' => null])
                                @php($hints = collect(['name'])->merge($notificationVariableHints[$eventKey] ?? []))
                                <div class="rounded-2xl border border-gray-200 p-4">
                                    <div class="font-semibold text-gray-800 dark:text-gray-100">{{ $label }}</div>
                                    <div class="mt-3 grid gap-4">
                                        <div>
                                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Judul</label>
                                            <input type="text" name="templates[{{ $eventKey }}][title]" value="{{ old("templates.$eventKey.title", $template['title']) }}" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" {{ $canManageNotifications ? '' : 'readonly' }}>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Isi Pesan</label>
                                            <textarea name="templates[{{ $eventKey }}][body]" rows="3" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" {{ $canManageNotifications ? '' : 'readonly' }}>{{ old("templates.$eventKey.body", $template['body']) }}</textarea>
                                            <p class="mt-2 text-xs text-gray-500">Placeholder yang bisa dipakai di event ini:
                                                @foreach($hints as $hint)<code>{{ '{'.$hint.'}' }}</code>@if(!$loop->last), @endif@endforeach
                                            </p>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Lampiran Email (opsional)</label>
                                            @if($template['attachment_path'])
                                                <div class="flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 mb-2">
                                                    <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($template['attachment_path']) }}" target="_blank" class="text-sm text-blue-600 hover:underline">{{ $template['attachment_name'] ?? 'Lihat lampiran saat ini' }}</a>
                                                    @if($canManageNotifications)
                                                        <label class="ml-auto flex items-center gap-1.5 text-xs text-red-600">
                                                            <input type="checkbox" name="templates[{{ $eventKey }}][remove_attachment]" value="1" class="rounded border-gray-300">
                                                            Hapus lampiran
                                                        </label>
                                                    @endif
                                                </div>
                                            @endif
                                            @if($canManageNotifications)
                                                <input type="file" name="templates[{{ $eventKey }}][attachment]" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" class="w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-blue-700">
                                                <p class="mt-1 text-xs text-gray-400">PDF/gambar/dokumen, maks 5MB. Otomatis terlampir di setiap email event ini kalau diisi.</p>
                                            @endif
                                            @error("templates.$eventKey.attachment")
                                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 dark:bg-gray-800">
                        <h3 class="font-bold text-gray-800 dark:text-gray-100 border-b pb-3 mb-5">WhatsApp Official API</h3>
                        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 mb-5">
                            Gunakan kanal ini untuk notifikasi karyawan. Peserta tetap email-only. Jika access token dikosongkan, aplikasi tidak akan mengirim WhatsApp dan hanya mencatat skip di log.
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Provider</label>
                                <select name="notification_whatsapp_provider" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" {{ $canManageNotifications ? '' : 'disabled' }}>
                                    <option value="official_api" @selected(old('notification_whatsapp_provider', $notificationSettings['whatsapp']['provider']) === 'official_api')>Official API</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">API Version</label>
                                <input type="text" name="notification_whatsapp_api_version" value="{{ old('notification_whatsapp_api_version', $notificationSettings['whatsapp']['api_version']) }}" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" {{ $canManageNotifications ? '' : 'readonly' }}>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Business Account ID</label>
                                <input type="text" name="notification_whatsapp_business_account_id" value="{{ old('notification_whatsapp_business_account_id', $notificationSettings['whatsapp']['business_account_id']) }}" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" {{ $canManageNotifications ? '' : 'readonly' }}>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Phone Number ID</label>
                                <input type="text" name="notification_whatsapp_phone_number_id" value="{{ old('notification_whatsapp_phone_number_id', $notificationSettings['whatsapp']['phone_number_id']) }}" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" {{ $canManageNotifications ? '' : 'readonly' }}>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Access Token</label>
                                <input type="password" name="notification_whatsapp_access_token" value="" placeholder="Kosongkan jika tidak ingin mengganti token" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" {{ $canManageNotifications ? '' : 'readonly' }}>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Default Country Code</label>
                                <input type="text" name="notification_whatsapp_default_country_code" value="{{ old('notification_whatsapp_default_country_code', $notificationSettings['whatsapp']['default_country_code']) }}" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" {{ $canManageNotifications ? '' : 'readonly' }}>
                            </div>
                        </div>
                    </div>

                    @if($canManageNotifications)
                        <div class="flex justify-end">
                            <button type="submit" class="px-6 py-3 bg-blue-600 text-white font-bold rounded-xl shadow-lg hover:bg-blue-700 transition">Simpan Pengaturan Notifikasi</button>
                        </div>
                    @endif
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
