@extends('layouts.app')
@section('title', 'Import Group Perusahaan')

@section('content')
<div class="max-w-xl mx-auto py-8 px-4">
    <div class="flex items-center gap-2 text-sm text-gray-500 mb-5">
        <a href="{{ route('master.company-groups.index') }}" class="hover:text-indigo-600">Group Perusahaan</a>
        <span>/</span>
        <span class="text-gray-700 font-medium">Import</span>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h1 class="text-lg font-semibold text-gray-800 mb-1">Import Group Perusahaan</h1>
        <p class="text-sm text-gray-500 mb-5">
            Upload file Excel/CSV. Data yang sudah ada (nama sama) akan dilewati otomatis.
        </p>

        {{-- Download template --}}
        <div class="mb-5 bg-indigo-50 border border-indigo-200 rounded-lg px-4 py-3
                    flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-indigo-800">Template Import</p>
                <p class="text-xs text-indigo-600 mt-0.5">Download dulu, isi datanya, lalu upload</p>
            </div>
            <a href="{{ route('master.company-groups.template') }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium
                      text-indigo-700 bg-white border border-indigo-300 rounded-lg
                      hover:bg-indigo-100 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Download Template
            </a>
        </div>

        @if($errors->any())
        <div class="mb-4 bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700">
            @foreach($errors->all() as $e)<p>• {{ $e }}</p>@endforeach
        </div>
        @endif

        <form action="{{ route('master.company-groups.import.store') }}"
              method="POST" enctype="multipart/form-data">
            @csrf
            <label class="block text-sm font-medium text-gray-700 mb-2">File Excel / CSV</label>
            <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center
                        hover:border-indigo-400 transition-colors cursor-pointer"
                 onclick="document.getElementById('import_file').click()">
                <p class="text-sm text-gray-500">Klik untuk pilih file</p>
                <p class="text-xs text-gray-400 mt-1">XLSX, XLS, atau CSV</p>
                <p id="fname" class="text-sm text-indigo-600 font-medium mt-2 hidden"></p>
            </div>
            <input type="file" id="import_file" name="import_file"
                   accept=".xlsx,.xls,.csv" class="hidden"
                   onchange="document.getElementById('fname').textContent='✓ '+this.files[0].name;
                             document.getElementById('fname').classList.remove('hidden')">

            <div class="flex justify-end gap-3 mt-5">
                <a href="{{ route('master.company-groups.index') }}"
                   class="px-4 py-2 text-sm text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200">
                    Batal
                </a>
                <button type="submit"
                        class="px-5 py-2 text-sm font-medium text-white bg-indigo-600
                               rounded-lg hover:bg-indigo-700">
                    Proses Import
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
