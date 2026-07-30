@extends('layouts.app')
@section('title', 'Status Import Karyawan')
@section('content')

<div class="max-w-2xl mx-auto py-12 px-4">

    @if($session->status === 'processing')
    <meta http-equiv="refresh" content="3">
    @endif

    <div class="bg-white rounded-xl border border-gray-200 p-8 text-center">

        @if($session->status === 'processing')
        <div class="w-16 h-16 border-4 border-indigo-600 border-t-transparent
                    rounded-full animate-spin mx-auto mb-6"></div>
        <h1 class="text-xl font-bold text-gray-900 mb-2">Sedang Memproses...</h1>
        <p class="text-gray-500 text-sm">
            {{ number_format($session->total_rows) }} data karyawan sedang diproses.<br>
            Halaman ini otomatis refresh setiap 3 detik.
        </p>

        @elseif($session->status === 'completed')
        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <h1 class="text-xl font-bold text-gray-900 mb-4">Import Selesai!</h1>
        <div class="grid grid-cols-2 gap-4 mb-6 text-left">
            <div class="bg-green-50 rounded-lg p-4">
                <p class="text-2xl font-bold text-green-700">{{ number_format($session->new_count) }}</p>
                <p class="text-sm text-green-600">Karyawan Baru</p>
            </div>
            <div class="bg-blue-50 rounded-lg p-4">
                <p class="text-2xl font-bold text-blue-700">{{ number_format($session->updated_count) }}</p>
                <p class="text-sm text-blue-600">Data Diperbarui</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-4">
                <p class="text-2xl font-bold text-gray-700">{{ number_format($session->skipped_count) }}</p>
                <p class="text-sm text-gray-600">Dilewati</p>
            </div>
            <div class="bg-indigo-50 rounded-lg p-4">
                <p class="text-2xl font-bold text-indigo-700">{{ number_format($session->account_created_count) }}</p>
                <p class="text-sm text-indigo-600">Akun Dibuat</p>
            </div>
        </div>
        @if($session->error_message)
        <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-sm text-amber-800 mb-4 text-left">
            <p class="font-semibold mb-1">Catatan error baris:</p>
            @php $errs = json_decode($session->error_message, true); @endphp
            @if(is_array($errs))
                <ul class="space-y-0.5">
                @foreach(array_slice($errs, 0, 10) as $err)
                    <li>• {{ $err['name'] ?? '?' }}: {{ $err['reason'] ?? '-' }}</li>
                @endforeach
                @if(count($errs) > 10)
                    <li class="text-gray-500">... dan {{ count($errs) - 10 }} lainnya.</li>
                @endif
                </ul>
            @else
                <p>{{ $session->error_message }}</p>
            @endif
        </div>
        @endif

        @elseif($session->status === 'failed')
        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
            <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </div>
        <h1 class="text-xl font-bold text-gray-900 mb-2">Import Gagal</h1>
        <p class="text-red-600 text-sm mb-4">{{ $session->error_message }}</p>
        <a href="{{ route('hr.employee-import.create') }}"
           class="inline-block px-4 py-2 text-sm font-medium text-white bg-red-600
                  rounded-lg hover:bg-red-700 transition-colors mr-2">
            Upload Ulang
        </a>

        @else
        <h1 class="text-xl font-bold text-gray-900 mb-2">Status: {{ ucfirst($session->status) }}</h1>
        @endif

        <a href="{{ route('hr.employee-import.index') }}"
           class="inline-block px-6 py-2 text-sm font-medium text-white
                  bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">
            ← Kembali ke Daftar Import
        </a>
    </div>

</div>
@endsection
