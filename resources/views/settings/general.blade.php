@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-50 dark:bg-gray-900 pb-12">
    <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
        <div class="max-w-6xl mx-auto py-6 px-4">
            <h2 class="font-bold text-xl text-gray-800 dark:text-white leading-tight">Pengaturan Aplikasi</h2>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-300">Sub menu khusus agar pengaturan umum dan email tidak bercampur dalam satu halaman panjang.</p>
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
                    <h3 class="font-bold text-gray-700 dark:text-gray-200 mb-4">Logo Aplikasi</h3>
                    <div class="flex flex-col items-center">
                        <div class="w-32 h-32 bg-gray-100 rounded-xl flex items-center justify-center mb-4 border overflow-hidden">
                            @if($setting->app_logo_path)
                                <img src="{{ asset('storage/'.$setting->app_logo_path) }}" class="w-full h-full object-contain">
                            @else
                                <span class="text-gray-400 text-xs">No Logo</span>
                            @endif
                        </div>
                        <div class="text-xs text-gray-500 text-center">Logo akan muncul di sidebar dan halaman login.</div>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <form action="{{ route('settings.update') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                    @csrf

                    <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700">
                        <h3 class="font-bold text-gray-700 dark:text-gray-200 mb-4 border-b pb-2">Identitas Umum</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nama Aplikasi</label>
                                <input type="text" name="app_name" value="{{ old('app_name', $setting->app_name) }}" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Meta Title</label>
                                <input type="text" name="meta_title" value="{{ old('meta_title', $setting->meta_title) }}" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Meta Description</label>
                                <textarea name="meta_description" rows="3" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">{{ old('meta_description', $setting->meta_description) }}</textarea>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Upload Logo</label>
                                    <input type="file" name="app_logo" class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"/>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Upload Favicon</label>
                                    <input type="file" name="app_favicon" class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"/>
                                </div>
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
                            <div class="text-xs text-gray-500 dark:text-gray-400">Terakhir dijalankan: {{ optional($setting->retention_last_run_at)->format('d M Y H:i') ?? '-' }}</div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="px-6 py-3 bg-blue-600 text-white font-bold rounded-xl shadow-lg hover:bg-blue-700 transition transform hover:-translate-y-0.5">
                            Simpan Pengaturan Umum
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
