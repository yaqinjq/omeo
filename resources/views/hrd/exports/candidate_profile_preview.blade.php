@extends('layouts.app')

@section('content')
@php
    $photoAsset = data_get($documentAssets, 'photo', []);
    $ktpAsset = data_get($documentAssets, 'ktp', []);
    $cvAsset = data_get($documentAssets, 'cv', []);

    $renderDocumentPanel = static function (array $asset, string $label): string {
        $title = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        $url = data_get($asset, 'url');
        $name = htmlspecialchars((string) (data_get($asset, 'name') ?: 'Dokumen tersimpan'), ENT_QUOTES, 'UTF-8');

        if (data_get($asset, 'available') && data_get($asset, 'is_image') && $url) {
            $src = htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8');
            return '<div class="space-y-3"><div class="flex h-80 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white p-4"><img src="' . $src . '" alt="' . $title . '" class="block max-h-72 w-auto max-w-full rounded-xl"></div><div class="rounded-2xl bg-emerald-50 px-4 py-3 text-xs font-semibold text-emerald-700">Preview gambar proporsional tersedia</div><div class="text-xs text-slate-500">' . $name . '</div></div>';
        }

        if (data_get($asset, 'configured')) {
            $message = htmlspecialchars((string) (data_get($asset, 'preview_message') ?: 'File tersimpan, tetapi preview visual belum tersedia di halaman ini.'), ENT_QUOTES, 'UTF-8');
            $openLink = $url ? '<a href="' . htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8') . '" target="_blank" class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Buka File</a>' : '';
            return '<div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4 text-center text-sm text-slate-600"><div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-white text-lg font-bold text-slate-700">DOC</div><div class="mt-3 font-semibold">' . $name . '</div><div class="mt-2 text-xs leading-5 text-slate-500">' . $message . '</div><div class="mt-3">' . $openLink . '</div></div>';
        }

        return '<div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-600">' . $title . ' belum tersedia.</div>';
    };
@endphp
<div class="space-y-6" id="candidatePreviewAntiCopyRoot">
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900">Preview Export Profil Kandidat</h1>
                <p class="mt-2 text-sm text-slate-600">{{ $candidate->full_name ?: ($profile?->full_name ?: 'Kandidat') }} @if($candidate->email)· {{ $candidate->email }} @endif</p>
                <p class="mt-2 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs leading-6 text-amber-900">Preview ini hanya menampilkan profil kandidat dalam format PDF. CV asli dan paket dokumen kandidat tersedia sebagai aksi terpisah agar alur HRD lebih jelas.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" onclick="printExportPdf()" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Print PDF</button>
                <a href="{{ $downloadUrl }}" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Download Profil PDF</a>
                <a href="{{ $downloadCvUrl }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Download CV Asli</a>
                <a href="{{ $downloadPackageUrl }}" class="rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-100">Download Paket Kandidat</a>
                <a href="{{ url()->previous() }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Kembali</a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
        <section class="xl:col-span-8 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700">Preview Profil PDF Kandidat</div>
            <iframe id="candidate-export-preview-frame" src="{{ $previewUrl }}" class="min-h-[900px] w-full bg-slate-100" title="Preview PDF Profil Kandidat"></iframe>
        </section>
        <aside class="xl:col-span-4 space-y-6">
            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700">Dokumen Visual Kandidat</div>
                <div class="grid gap-4 p-4">
                    <div>
                        <div class="mb-3 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Pas Foto</div>
                        {!! $renderDocumentPanel($photoAsset, 'Pas Foto Kandidat') !!}
                    </div>
                    <div>
                        <div class="mb-3 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Scan KTP</div>
                        {!! $renderDocumentPanel($ktpAsset, 'Scan KTP Kandidat') !!}
                    </div>
                </div>
            </section>
            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700">Lampiran CV Asli</div>
                <div class="p-4">
                    @if(data_get($cvAsset, 'available') && data_get($cvAsset, 'is_pdf') && data_get($cvAsset, 'url'))
                        <div class="mb-3 flex justify-end">
                            <a href="{{ $downloadCvUrl }}" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Download CV Asli</a>
                        </div>
                        <iframe src="{{ data_get($cvAsset, 'url') }}" class="min-h-[900px] w-full rounded-2xl border border-slate-200 bg-slate-50" title="Preview CV PDF"></iframe>
                    @elseif(data_get($cvAsset, 'available') && data_get($cvAsset, 'is_image') && data_get($cvAsset, 'url'))
                        <div class="mb-3 flex justify-end">
                            <a href="{{ $downloadCvUrl }}" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Download CV Asli</a>
                        </div>
                        <div class="flex min-h-[24rem] items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white p-4">
                            <img src="{{ data_get($cvAsset, 'url') }}" alt="CV Kandidat" class="block max-h-[22rem] w-auto max-w-full rounded-xl">
                        </div>
                    @else
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-600">CV belum tersedia.</div>
                    @endif
                </div>
            </section>
        </aside>
    </div>
</div>
<script>
    function printExportPdf() {
        const frame = document.getElementById('candidate-export-preview-frame');
        if (frame && frame.contentWindow) {
            try {
                frame.contentWindow.focus();
                frame.contentWindow.print();
                return;
            } catch (error) {}
        }
        window.open(@json($previewUrl), '_blank', 'noopener');
    }

    document.addEventListener('contextmenu', (event) => event.preventDefault());
    document.addEventListener('copy', (event) => event.preventDefault());
    document.addEventListener('cut', (event) => event.preventDefault());
    document.addEventListener('keydown', (event) => {
        const key = String(event.key || '').toLowerCase();
        if ((event.ctrlKey || event.metaKey) && ['c', 'x', 's', 'p', 'u'].includes(key)) {
            event.preventDefault();
        }
    });
</script>
@endsection

