@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-50 dark:bg-gray-900 pb-12 font-sans">

    {{-- Page Header --}}
    <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 flex items-center justify-between">
            <div>
                <h2 class="font-bold text-xl text-gray-800 dark:text-white leading-tight">Import Payroll</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Riwayat import data payroll (Ah Pek Kopitiam & Tokio-O!)</p>
            </div>
            <div class="flex items-center gap-3">
                @if(\Illuminate\Support\Facades\Route::has('finance.export.mandiri'))
                    {{-- Tombol Export Template Mandiri --}}
                    <form method="GET" action="{{ route('finance.export.mandiri') }}" class="flex items-center gap-2">
                        <input type="month" name="periode" value="{{ now()->format('Y-m') }}"
                               class="border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white text-sm rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <button type="submit"
                                class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Template Mandiri
                        </button>
                    </form>
                @endif

                @can('finance.import')
                <a href="{{ route('finance.import.create') }}"
                   class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    Import Baru
                </a>
                @endcan
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
        @endif
        @if(session('info'))
            <div class="bg-blue-50 border border-blue-200 text-blue-800 rounded-lg px-4 py-3 text-sm">{{ session('info') }}</div>
        @endif

        {{-- Sessions Table --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden overflow-x-auto">
            <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Periode</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Brand</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider max-w-xs">File</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Baru</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Update</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tidak Cocok</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Oleh</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tanggal</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($sessions as $session)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                        <td class="px-4 py-3 font-mono font-semibold text-gray-900 dark:text-white">{{ $session->periode }}</td>
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $session->brand_label }}</td>
                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400 max-w-[160px] truncate" title="{{ $session->source_file_name }}">
                            {{ $session->source_file_name }}
                        </td>
                        <td class="px-4 py-3 text-center text-gray-700 dark:text-gray-300">{{ $session->total_rows }}</td>
                        <td class="px-4 py-3 text-center text-green-700 font-semibold">{{ $session->new_count }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($session->updated_count > 0)
                                <span class="text-yellow-700 font-semibold">{{ $session->updated_count }}</span>
                            @else
                                <span class="text-gray-400">0</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($session->unmatched_count > 0)
                                <span class="text-red-600 font-semibold">{{ $session->unmatched_count }}</span>
                            @else
                                <span class="text-gray-400">0</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $statusColors = [
                                    'completed'    => 'bg-green-100 text-green-800',
                                    'needs_review' => 'bg-yellow-100 text-yellow-800',
                                    'processing'   => 'bg-blue-100 text-blue-800',
                                    'draft'        => 'bg-gray-100 text-gray-600',
                                    'cancelled'    => 'bg-red-100 text-red-700',
                                ];
                                $statusLabels = [
                                    'completed'    => 'Selesai',
                                    'needs_review' => 'Perlu Review',
                                    'processing'   => 'Diproses',
                                    'draft'        => 'Draft',
                                    'cancelled'    => 'Dibatalkan',
                                ];
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $statusColors[$session->status] ?? 'bg-gray-100 text-gray-600' }}">
                                {{ $statusLabels[$session->status] ?? $session->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $session->importer?->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $session->created_at->format('d M Y H:i') }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <a href="{{ route('finance.import.show', $session) }}"
                                   class="text-blue-600 hover:text-blue-800 text-xs font-medium whitespace-nowrap">Detail</a>
                                @can('finance.import')
                                <form method="POST"
                                      action="{{ route('finance.import.destroy', $session) }}"
                                      onsubmit="return confirm('Hapus sesi import {{ $session->periode }} ({{ $session->brand_label }})?\n\nData staging akan dihapus. Data BPJS yang sudah tersimpan tetap ada.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-medium">Hapus</button>
                                </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="11" class="px-4 py-10 text-center text-gray-400 text-sm">Belum ada riwayat import.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $sessions->links() }}

    </div>
</div>
@endsection
