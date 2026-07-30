@extends('layouts.app')

@section('content')
@php
    $resolvedBaseUrl = $baseUrl !== '' ? $baseUrl : url('/');
    $listEndpoint = $resolvedBaseUrl . '/api/outlets';
    $detailEndpoint = $resolvedBaseUrl . '/api/outlets/{outlet}';
    $loginEndpoint = $resolvedBaseUrl . '/api/login';
    $branchesEndpoint = $resolvedBaseUrl . '/api/branches';
    $testEndpoint = $resolvedBaseUrl . '/api/outlets?per_page=1';
    $headerSnippet = "Authorization: Bearer YOUR_OUTLET_API_DIRECT_TOKEN\nAccept: application/json";
    $wiproMappingSnippet = "Base URL: {$resolvedBaseUrl}\nAuth Type: Bearer Token\nBearer Token: OUTLET_API_DIRECT_TOKEN\nTest Connection: GET /api/outlets?per_page=1";
    $loginBodySnippet = "{\n  \"email\": \"your-api-email@example.com\",\n  \"password\": \"YOUR_API_PASSWORD\"\n}";
    $curlSnippet = "curl --request GET \"{$resolvedBaseUrl}/api/outlets?updated_since=2026-04-01T00:00:00Z&per_page=50\" \\\n  --header \"Authorization: Bearer YOUR_OUTLET_API_DIRECT_TOKEN\" \\\n  --header \"Accept: application/json\"";
    $testCurlSnippet = "curl --request GET \"{$testEndpoint}\" \\\n  --header \"Authorization: Bearer YOUR_OUTLET_API_DIRECT_TOKEN\" \\\n  --header \"Accept: application/json\"";
    $compatCurlSnippet = "curl --request POST \"{$resolvedBaseUrl}/api/login\" \\\n  --header \"Accept: application/json\" \\\n  --header \"Content-Type: application/json\" \\\n  --data '{\"email\":\"your-api-email@example.com\",\"password\":\"YOUR_API_PASSWORD\"}'";
    $branchesCurlSnippet = "curl --request GET \"{$resolvedBaseUrl}/api/branches?per_page=100\" \\\n  --header \"Authorization: Bearer YOUR_LOGIN_ACCESS_TOKEN\" \\\n  --header \"Accept: application/json\"";
    $laravelSnippet = "use Illuminate\\Support\\Facades\\Http;\n\n\$response = Http::withToken(env('OUTLET_API_DIRECT_TOKEN'))\n    ->acceptJson()\n    ->get('{$resolvedBaseUrl}/api/outlets', [\n        'updated_since' => now()->subDay()->toIso8601String(),\n        'per_page' => 100,\n    ]);\n\n\$data = \$response->throw()->json();";
@endphp
<div class="min-h-screen bg-slate-100 pb-12">
    <div class="border-b border-slate-200 bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-start justify-between gap-6 px-4 py-6">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-700">Superadmin Only</p>
                <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-900">Dokumentasi API OMEO</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                    Halaman ini adalah panduan internal untuk integrasi aplikasi lain ke data Outlet OMEO. Anda sekarang bisa memakai dua mode: langsung Bearer token ke endpoint outlet, atau mode kompatibilitas login lalu ambil branches untuk project lama.
                </p>
            </div>
            <a href="{{ route('settings.index') }}" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-sky-400 hover:text-sky-700">
                Kembali ke Settings
            </a>
        </div>
    </div>

    <div class="mx-auto max-w-6xl space-y-6 px-4 py-8">
        <div class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-slate-400">Base URL</p>
                    <button type="button" class="copy-trigger inline-flex items-center rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:border-sky-400 hover:text-sky-700" data-copy="{{ $resolvedBaseUrl }}">Copy</button>
                </div>
                <p class="mt-3 break-all rounded-xl bg-slate-950 px-4 py-3 font-mono text-sm text-slate-100">{{ $resolvedBaseUrl }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-slate-400">Autentikasi</p>
                <p class="mt-3 text-sm font-semibold {{ $outletApiDirectTokenConfigured ? 'text-emerald-600' : 'text-amber-600' }}">
                    {{ $outletApiDirectTokenConfigured ? 'Direct token outlet sudah terpasang di server.' : 'Direct token outlet belum terdeteksi di konfigurasi aktif.' }}
                </p>
                <p class="mt-2 text-sm font-semibold {{ $outletApiLoginConfigured ? 'text-emerald-600' : 'text-amber-600' }}">
                    {{ $outletApiLoginConfigured ? 'Compatibility login sudah dikonfigurasi lewat allowlist user.' : 'Compatibility login belum aktif karena allowlist user belum diisi.' }}
                </p>
                <p class="mt-2 text-sm text-slate-500">Mode direct memakai token statis khusus. Mode compatibility menghasilkan bearer token dari login user yang diizinkan.</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-slate-400">Mode Integrasi</p>
                <p class="mt-3 text-sm text-slate-600"><span class="font-semibold text-slate-900">WIPRO gunakan Mode 1</span>: Bearer token langsung ke <code>/api/outlets</code>. Mode 2 tetap tersedia untuk project lama yang memakai <code>/api/login</code> lalu <code>/api/branches</code>.</p>
            </div>
        </div>

        <section class="rounded-3xl border border-sky-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 border-b border-sky-100 pb-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Mapping UI WIPRO</h2>
                    <p class="mt-1 text-sm text-slate-500">Gunakan konfigurasi ini untuk integrasi outlet OMEO ke WIPRO / Inventory WIP Production.</p>
                </div>
                <button type="button" class="copy-trigger inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:border-sky-400 hover:text-sky-700" data-copy="{{ $wiproMappingSnippet }}">Copy Mapping</button>
            </div>

            <div class="mt-5 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Base URL</p>
                    <p class="mt-2 break-all font-mono text-sm text-slate-800">{{ $resolvedBaseUrl }}</p>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Auth Type</p>
                    <p class="mt-2 text-sm font-semibold text-slate-800">Bearer Token</p>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Token</p>
                    <p class="mt-2 text-sm text-slate-600">Isi dengan nilai <code>OUTLET_API_DIRECT_TOKEN</code> dari server OMEO.</p>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Test Connection</p>
                    <p class="mt-2 break-all font-mono text-sm text-slate-800">GET /api/outlets?per_page=1</p>
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-4">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Mode 1: Direct Outlet API</h2>
                    <p class="mt-1 text-sm text-slate-500">Mode paling sederhana untuk aplikasi baru yang bisa langsung memakai Bearer token.</p>
                </div>
                <span class="rounded-full bg-sky-50 px-3 py-1 text-xs font-bold uppercase tracking-[0.2em] text-sky-700">Recommended</span>
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
                <div class="space-y-5">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="rounded-lg bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-700">GET</span>
                                <code class="text-sm font-semibold text-slate-800">{{ $listEndpoint }}</code>
                            </div>
                            <button type="button" class="copy-trigger inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:border-sky-400 hover:text-sky-700" data-copy="{{ $listEndpoint }}">Copy Endpoint</button>
                        </div>
                        <p class="mt-3 text-sm text-slate-600">Mengambil daftar outlet. Mendukung filter <code>brand_name</code>, <code>external_id</code>, <code>updated_since</code>, dan <code>per_page</code>.</p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="rounded-lg bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-700">GET</span>
                                <code class="text-sm font-semibold text-slate-800">{{ $detailEndpoint }}</code>
                            </div>
                            <button type="button" class="copy-trigger inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:border-sky-400 hover:text-sky-700" data-copy="{{ $detailEndpoint }}">Copy Endpoint</button>
                        </div>
                        <p class="mt-3 text-sm text-slate-600">Mengambil detail satu outlet berdasarkan ID outlet internal OMEO.</p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="rounded-lg bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-700">GET</span>
                                <code class="text-sm font-semibold text-slate-800">{{ $testEndpoint }}</code>
                            </div>
                            <button type="button" class="copy-trigger inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:border-sky-400 hover:text-sky-700" data-copy="{{ $testEndpoint }}">Copy Test</button>
                        </div>
                        <p class="mt-3 text-sm text-slate-600">Endpoint ringan yang disarankan untuk tombol <code>Test Connection</code> di WIPRO.</p>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-lg font-bold text-slate-900">Header Wajib</h2>
                            <button type="button" class="copy-trigger inline-flex items-center rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:border-sky-400 hover:text-sky-700" data-copy="{{ $headerSnippet }}">Copy Header</button>
                        </div>
                        <div class="mt-4 rounded-2xl bg-slate-950 p-4 text-sm text-slate-100">
<pre class="whitespace-pre-wrap break-words font-mono">{{ $headerSnippet }}</pre>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-lg font-bold text-slate-900">Test Connection cURL</h2>
                            <button type="button" class="copy-trigger inline-flex items-center rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:border-sky-400 hover:text-sky-700" data-copy="{{ $testCurlSnippet }}">Copy Test</button>
                        </div>
                        <div class="mt-4 rounded-2xl bg-slate-950 p-4 text-sm text-slate-100">
<pre class="whitespace-pre-wrap break-words font-mono">{{ $testCurlSnippet }}</pre>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-lg font-bold text-slate-900">Contoh Sync cURL</h2>
                            <button type="button" class="copy-trigger inline-flex items-center rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:border-sky-400 hover:text-sky-700" data-copy="{{ $curlSnippet }}">Copy cURL</button>
                        </div>
                        <div class="mt-4 rounded-2xl bg-slate-950 p-4 text-sm text-slate-100">
<pre class="whitespace-pre-wrap break-words font-mono">{{ $curlSnippet }}</pre>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-lg font-bold text-slate-900">Contoh Laravel HTTP Client</h2>
                            <button type="button" class="copy-trigger inline-flex items-center rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:border-sky-400 hover:text-sky-700" data-copy="{{ $laravelSnippet }}">Copy Laravel</button>
                        </div>
                        <div class="mt-4 rounded-2xl bg-slate-950 p-4 text-sm text-slate-100">
<pre class="whitespace-pre-wrap break-words font-mono">{{ $laravelSnippet }}</pre>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-4">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Mode 2: Compatibility Login + Branches</h2>
                    <p class="mt-1 text-sm text-slate-500">Mode ini disediakan agar project lama Anda yang memakai pola <code>login</code> lalu <code>branches</code> bisa langsung terhubung.</p>
                </div>
                <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold uppercase tracking-[0.2em] text-amber-700">Legacy Friendly</span>
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                <div class="space-y-5">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="rounded-lg bg-sky-100 px-2.5 py-1 text-xs font-bold text-sky-700">POST</span>
                                <code class="text-sm font-semibold text-slate-800">{{ $loginEndpoint }}</code>
                            </div>
                            <button type="button" class="copy-trigger inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:border-sky-400 hover:text-sky-700" data-copy="{{ $loginEndpoint }}">Copy Endpoint</button>
                        </div>
                        <p class="mt-3 text-sm text-slate-600">Login API untuk mengambil token Bearer. Endpoint ini menerima <code>email/password</code> dan hanya mengizinkan user yang masuk allowlist konfigurasi server.</p>
                        <div class="mt-4 rounded-2xl bg-slate-950 p-4 text-sm text-slate-100">
<pre class="whitespace-pre-wrap break-words font-mono">{{ $loginBodySnippet }}</pre>
                        </div>
                        <div class="mt-3 flex justify-end">
                            <button type="button" class="copy-trigger inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:border-sky-400 hover:text-sky-700" data-copy="{{ $loginBodySnippet }}">Copy Body</button>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="rounded-lg bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-700">GET</span>
                                <code class="text-sm font-semibold text-slate-800">{{ $branchesEndpoint }}</code>
                            </div>
                            <button type="button" class="copy-trigger inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:border-sky-400 hover:text-sky-700" data-copy="{{ $branchesEndpoint }}">Copy Endpoint</button>
                        </div>
                        <p class="mt-3 text-sm text-slate-600">Alias kompatibilitas untuk outlet. Gunakan bearer token hasil <code>/api/login</code>. Response tetap membawa data outlet, ditambah field alias seperti <code>branch_id</code>, <code>branch_name</code>, <code>branch_code</code>, <code>code</code>, dan <code>title</code>.</p>
                        <p class="mt-2 text-sm text-amber-700">Catatan: token hasil <code>/api/login</code> hanya ditujukan untuk <code>/api/branches</code>. Endpoint <code>/api/outlets</code> tetap memakai direct bearer token.</p>
                    </div>
                </div>

                <div class="space-y-5">
                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-lg font-bold text-slate-900">Contoh Login cURL</h2>
                            <button type="button" class="copy-trigger inline-flex items-center rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:border-sky-400 hover:text-sky-700" data-copy="{{ $compatCurlSnippet }}">Copy Login</button>
                        </div>
                        <div class="mt-4 rounded-2xl bg-slate-950 p-4 text-sm text-slate-100">
<pre class="whitespace-pre-wrap break-words font-mono">{{ $compatCurlSnippet }}</pre>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-lg font-bold text-slate-900">Contoh Branches cURL</h2>
                            <button type="button" class="copy-trigger inline-flex items-center rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:border-sky-400 hover:text-sky-700" data-copy="{{ $branchesCurlSnippet }}">Copy Branches</button>
                        </div>
                        <div class="mt-4 rounded-2xl bg-slate-950 p-4 text-sm text-slate-100">
<pre class="whitespace-pre-wrap break-words font-mono">{{ $branchesCurlSnippet }}</pre>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-100 pb-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Kesiapan External ID</h2>
                    <p class="mt-1 text-sm text-slate-500">External ID adalah key yang disarankan untuk sinkronisasi lintas sistem, selama nilainya terisi dan unik.</p>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-slate-600">Outlet Master</span>
            </div>

            @if(! data_get($outletExternalIdStats, 'available'))
                <p class="mt-5 text-sm text-amber-700">Tabel outlet belum tersedia, statistik External ID belum bisa dihitung.</p>
            @else
                <div class="mt-5 grid gap-4 md:grid-cols-3">
                    <div class="rounded-2xl bg-slate-50 p-5 ring-1 ring-slate-200">
                        <p class="text-xs font-bold uppercase tracking-[0.2em] text-slate-400">Total Outlet</p>
                        <p class="mt-2 text-2xl font-black text-slate-900">{{ number_format((int) data_get($outletExternalIdStats, 'total', 0)) }}</p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-5 ring-1 ring-slate-200">
                        <p class="text-xs font-bold uppercase tracking-[0.2em] text-slate-400">External ID Kosong</p>
                        <p class="mt-2 text-2xl font-black {{ (int) data_get($outletExternalIdStats, 'missing', 0) > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ number_format((int) data_get($outletExternalIdStats, 'missing', 0)) }}</p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-5 ring-1 ring-slate-200">
                        <p class="text-xs font-bold uppercase tracking-[0.2em] text-slate-400">Duplikat External ID</p>
                        <p class="mt-2 text-2xl font-black {{ (int) data_get($outletExternalIdStats, 'duplicate_groups', 0) > 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ number_format((int) data_get($outletExternalIdStats, 'duplicate_groups', 0)) }}</p>
                    </div>
                </div>

                @if((int) data_get($outletExternalIdStats, 'duplicate_groups', 0) > 0)
                    <div class="mt-5 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                        <p class="font-semibold">Rapikan duplikat sebelum WIPRO memakai External ID sebagai key utama.</p>
                        @foreach(data_get($outletExternalIdStats, 'duplicate_samples', []) as $sample)
                            <p class="mt-1 font-mono">{{ $sample['external_id'] }}: {{ $sample['count'] }} outlet</p>
                        @endforeach
                    </div>
                @endif
            @endif
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-100 pb-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Alur Penggunaan yang Disarankan</h2>
                    <p class="mt-1 text-sm text-slate-500">Pilih alur integrasi sesuai kemampuan aplikasi target.</p>
                </div>
                <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-amber-700">Internal Guide</span>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-3">
                <div class="rounded-2xl bg-slate-50 p-5 ring-1 ring-slate-200">
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-slate-400">1. App Baru</p>
                    <p class="mt-3 text-sm leading-6 text-slate-600">Untuk aplikasi baru, langsung gunakan <code>/api/outlets</code> dengan Bearer token. Mode ini paling rapi dan paling sedikit langkah.</p>
                </div>
                <div class="rounded-2xl bg-slate-50 p-5 ring-1 ring-slate-200">
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-slate-400">2. App Lama</p>
                    <p class="mt-3 text-sm leading-6 text-slate-600">Jika project lama Anda sudah punya flow <code>login</code> lalu <code>branches</code>, arahkan saja ke endpoint kompatibilitas baru agar minim perubahan kode.</p>
                </div>
                <div class="rounded-2xl bg-slate-50 p-5 ring-1 ring-slate-200">
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-slate-400">3. Sinkron Aman</p>
                    <p class="mt-3 text-sm leading-6 text-slate-600">Gunakan <code>updated_since</code> dan simpan <code>id</code> atau <code>external_id</code> supaya sinkronisasi outlet antaraplikasi tetap stabil.</p>
                </div>
            </div>
        </section>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const buttons = document.querySelectorAll('.copy-trigger');

        buttons.forEach((button) => {
            button.addEventListener('click', async () => {
                const text = button.dataset.copy || '';
                const originalText = button.textContent;

                try {
                    await navigator.clipboard.writeText(text);
                    button.textContent = 'Copied';
                    button.classList.add('border-emerald-400', 'text-emerald-700');
                } catch (error) {
                    button.textContent = 'Failed';
                    button.classList.add('border-rose-400', 'text-rose-700');
                }

                window.setTimeout(() => {
                    button.textContent = originalText;
                    button.classList.remove('border-emerald-400', 'text-emerald-700', 'border-rose-400', 'text-rose-700');
                }, 1600);
            });
        });
    });
</script>
@endsection
