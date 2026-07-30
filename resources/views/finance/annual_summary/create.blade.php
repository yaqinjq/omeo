@extends('layouts.app')
@section('title', 'Upload Summary Gaji Tahunan')
@section('content')
<div class="max-w-xl mx-auto py-8 px-4">

    <div class="flex items-center gap-2 text-sm text-gray-500 mb-5">
        <a href="{{ route('finance.annual-summary.index') }}"
           class="hover:text-indigo-600">Summary Gaji Tahunan</a>
        <span>/</span>
        <span class="text-gray-700 font-medium">Upload File</span>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h1 class="text-lg font-semibold text-gray-800 mb-1">
            Upload Summary Gaji Tahunan
        </h1>
        <p class="text-sm text-gray-500 mb-5">
            Upload file XLS Tokio-O! — sistem akan membaca sheet
            <strong>"SUMMARY TOKIO"</strong> secara otomatis.
        </p>

        {{-- Info format --}}
        <div class="mb-5 bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 text-sm text-blue-800">
            <p class="font-medium mb-1">Format file yang didukung:</p>
            <ul class="text-blue-700 space-y-0.5 text-xs">
                <li>• File XLS/XLSX payroll Tokio-O! (Makmur Ramenjaya Mandiri)</li>
                <li>• Harus ada sheet bernama <strong>SUMMARY TOKIO</strong></li>
                <li>• Kolom: No.Komp, Nama, NIK, Department, Posisi, Join Date, Jan-Des, THR, Total</li>
            </ul>
        </div>

        @if($errors->any())
        <div class="mb-4 bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700">
            @foreach($errors->all() as $e)<p>• {{ $e }}</p>@endforeach
        </div>
        @endif

        <form action="{{ route('finance.annual-summary.store') }}"
              method="POST" enctype="multipart/form-data">
            @csrf

            {{-- Tahun --}}
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Tahun Data <span class="text-red-500">*</span>
                </label>
                <select name="tahun"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                               focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400">
                    @for($y = now()->year; $y >= 2023; $y--)
                        <option value="{{ $y }}" {{ $y == now()->year ? 'selected' : '' }}>
                            {{ $y }}
                        </option>
                    @endfor
                </select>
            </div>

            {{-- Drop zone file --}}
            <div class="mb-5">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    File XLS / XLSX <span class="text-red-500">*</span>
                </label>
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center
                            hover:border-indigo-400 transition-colors cursor-pointer"
                     onclick="document.getElementById('summary_file').click()">
                    <svg class="mx-auto h-10 w-10 text-gray-400 mb-2" fill="none"
                         stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-sm text-gray-600">
                        <span class="font-medium text-indigo-600">Klik untuk pilih file</span>
                        atau drag &amp; drop
                    </p>
                    <p class="text-xs text-gray-400 mt-1">XLS, XLSX — Maks. 15MB</p>
                    <p id="fname" class="mt-2 text-sm text-indigo-700 font-medium hidden"></p>
                </div>
                <input type="file" id="summary_file" name="summary_file"
                       accept=".xls,.xlsx" class="hidden"
                       onchange="document.getElementById('fname').textContent='✓ ' + this.files[0].name;
                                 document.getElementById('fname').classList.remove('hidden')">
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('finance.annual-summary.index') }}"
                   class="px-4 py-2 text-sm text-gray-600 bg-gray-100
                          rounded-lg hover:bg-gray-200 transition-colors">
                    Batal
                </a>
                <button type="submit"
                        class="px-6 py-2 text-sm font-medium text-white bg-indigo-600
                               rounded-lg hover:bg-indigo-700 transition-colors">
                    Proses Upload
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
