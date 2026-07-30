@php
    $navItems = [
        ['route' => 'settings.general', 'label' => 'Pengaturan Umum', 'description' => 'Nama aplikasi, logo, favicon, dan retensi kandidat.'],
        ['route' => 'settings.email', 'label' => 'SMTP & Email', 'description' => 'Reset password, email percobaan, dan pengaturan pengirim.'],
        ['route' => 'settings.notifications', 'label' => 'Notifikasi', 'description' => 'Inbox internal, email peserta, dan WhatsApp karyawan.'],
    ];

    if (auth()->user()?->isSuperAdmin() && Route::has('settings.api-docs')) {
        $navItems[] = ['route' => 'settings.api-docs', 'label' => 'API Docs', 'description' => 'Dokumentasi integrasi endpoint internal.'];
    }
@endphp

<div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
    <div class="text-xs font-bold uppercase tracking-[0.2em] text-gray-400">Sub Menu</div>
    <div class="mt-4 space-y-2">
        @foreach($navItems as $item)
            @php($active = request()->routeIs($item['route']))
            <a href="{{ route($item['route']) }}" class="block rounded-2xl border px-4 py-3 transition {{ $active ? 'border-blue-300 bg-blue-50 text-blue-700' : 'border-gray-200 bg-gray-50 text-gray-700 hover:border-blue-200 hover:bg-blue-50/60 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-200' }}">
                <div class="text-sm font-semibold">{{ $item['label'] }}</div>
                <div class="mt-1 text-xs {{ $active ? 'text-blue-600' : 'text-gray-500 dark:text-gray-400' }}">{{ $item['description'] }}</div>
            </a>
        @endforeach
    </div>
</div>
