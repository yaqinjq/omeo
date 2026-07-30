@extends('layouts.app')
@section('title', 'Import Karyawan — Upload File')
@section('content')

<div class="max-w-2xl mx-auto py-8 px-4">

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Import Karyawan dari HR XLS</h1>
        <p class="text-sm text-gray-500 mt-0.5">
            Upload file Employee List dari sistem HR internal. File akan dianalisa terlebih dahulu
            sebelum data disimpan.
        </p>
    </div>

    @if($errors->any())
        <div class="mb-4 bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-800">
            @foreach($errors->all() as $e)
                <p>{{ $e }}</p>
            @endforeach
        </div>
    @endif

    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
        <p class="text-sm font-semibold text-blue-800 mb-1">
            Format File yang Didukung
        </p>
        <ul class="text-sm text-blue-700 space-y-1">
            <li>• File XLS/XLSX dari sistem payroll HRD</li>
            <li>• Sheet bernama: <strong>"Page 0"</strong></li>
            <li>• Kolom wajib: NIK KTP, Nama, Email, Join Date</li>
            <li>• Ini <strong>BUKAN</strong> template OMEO biasa — gunakan file export dari sistem HRD</li>
        </ul>
    </div>

    <form method="POST" action="{{ route('hr.employee-import.store') }}" enctype="multipart/form-data"
          class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                File XLS / XLSX <span class="text-red-500">*</span>
            </label>
            <input type="file" name="file" accept=".xlsx,.xls"
                   class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg
                          px-3 py-2 focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400"/>
            <p class="mt-1 text-xs text-gray-500">Maksimal 20MB. Format XLS atau XLSX.</p>
        </div>

        <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-sm text-amber-800">
            <p class="font-semibold mb-1">Strategi jika karyawan sudah ada di OMEO:</p>
            <p>Data yang sudah ada di OMEO <strong>tidak akan ditimpa</strong>.
               Import hanya mengisi kolom yang masih kosong (nik, outlet, posisi, dll.).
               Nama dan email yang sudah ada tetap dipertahankan.</p>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit"
                    class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium
                           rounded-lg hover:bg-indigo-700 transition-colors">
                Analisis File
            </button>
            <a href="{{ route('hr.employee-import.index') }}"
               class="px-4 py-2.5 bg-gray-100 text-gray-600 text-sm font-medium
                      rounded-lg hover:bg-gray-200 transition-colors">
                Batal
            </a>
        </div>
    </form>

</div>
@endsection
