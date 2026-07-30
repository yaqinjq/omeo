@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-50 dark:bg-gray-900 pb-12 font-sans">

    <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
        <div class="max-w-3xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="flex items-center gap-3">
                <a href="{{ route('finance.import.index') }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <h2 class="font-bold text-xl text-gray-800 dark:text-white">Import Payroll Baru</h2>
            </div>
        </div>
    </div>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 text-sm">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Format Info --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-xl p-4">
                <div class="font-semibold text-blue-800 dark:text-blue-300 text-sm mb-2">📄 Format CSV — Ah Pek Kopitiam</div>
                <ul class="text-xs text-blue-700 dark:text-blue-400 space-y-1">
                    <li>• Semicolon-delimited (;)</li>
                    <li>• 40 kolom</li>
                    <li>• BPJS TK = kolom 35 (0-indexed)</li>
                    <li>• JKes = kolom 36 (0-indexed)</li>
                    <li>• No. Kompetensi = kolom 2</li>
                </ul>
            </div>
            <div class="bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-700 rounded-xl p-4">
                <div class="font-semibold text-purple-800 dark:text-purple-300 text-sm mb-2">📊 Format XLSX — Tokio-O!</div>
                <ul class="text-xs text-purple-700 dark:text-purple-400 space-y-1">
                    <li>• 43 kolom (3 NaN merged cell)</li>
                    <li>• BPJS TK = kolom 38 (1-indexed)</li>
                    <li>• JKes = kolom 39 (1-indexed)</li>
                    <li>• Sheet otomatis dari periode (JAN, FEB, MEI...)</li>
                    <li>• No. Kompetensi = kolom 2</li>
                </ul>
            </div>
        </div>

        {{-- Upload Form --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <form method="POST" action="{{ route('finance.import.store') }}" enctype="multipart/form-data" id="importForm">
                @csrf

                <div class="space-y-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                            File Payroll <span class="text-red-500">*</span>
                        </label>
                        <div id="dropzone"
                             class="relative border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl p-8 text-center cursor-pointer hover:border-blue-400 transition"
                             onclick="document.getElementById('payroll_file').click()">
                            <input type="file" id="payroll_file" name="payroll_file"
                                   accept=".csv,.xlsx,.xls"
                                   class="sr-only"
                                   onchange="handleFileSelect(this)">
                            <div id="dropzonePrompt">
                                <svg class="mx-auto w-10 h-10 text-gray-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                </svg>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Klik atau drag & drop file di sini</p>
                                <p class="text-xs text-gray-400 mt-1">CSV, XLSX, XLS — maks. 10 MB</p>
                            </div>
                            <div id="fileSelected" class="hidden">
                                <svg class="mx-auto w-10 h-10 text-green-500 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="text-sm font-semibold text-gray-800 dark:text-white" id="fileName">-</p>
                                <p class="text-xs text-gray-400 mt-1" id="fileSize">-</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg px-4 py-3 text-xs text-yellow-800 dark:text-yellow-300">
                        <strong>Catatan:</strong> Periode dan brand akan dideteksi otomatis dari isi file.
                        Baris baru akan langsung diproses. Baris yang berbeda dari data existing akan masuk antrian review.
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <button type="submit" id="submitBtn"
                                class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-sm font-semibold px-6 py-2.5 rounded-lg transition">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            Proses Import
                        </button>
                        <a href="{{ route('finance.import.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">Batal</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function handleFileSelect(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        document.getElementById('dropzonePrompt').classList.add('hidden');
        document.getElementById('fileSelected').classList.remove('hidden');
        document.getElementById('fileName').textContent = file.name;
        document.getElementById('fileSize').textContent = (file.size / 1024).toFixed(1) + ' KB';
    }
}

document.getElementById('importForm').addEventListener('submit', function () {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg> Memproses...';
});
</script>
@endsection
