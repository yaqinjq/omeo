@extends('layouts.app')
@section('title', 'Riwayat Import Karyawan')
@section('content')

<div class="max-w-6xl mx-auto py-8 px-4">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Import Karyawan (HR XLS)</h1>
            <p class="text-sm text-gray-500 mt-0.5">Upload file Employee List dari sistem HR internal (sheet "Page 0")</p>
        </div>
        <a href="{{ route('hr.employee-import.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium
                  text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
            </svg>
            Import Baru
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 text-sm">
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs font-medium text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">File</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3 text-right">Baru</th>
                    <th class="px-4 py-3 text-right">Update</th>
                    <th class="px-4 py-3 text-right">Skip</th>
                    <th class="px-4 py-3 text-right">Akun</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-left">Waktu</th>
                    <th class="px-4 py-3 text-left">Oleh</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($sessions as $s)
                    @php
                        $badgeClass = match($s->status) {
                            'completed'  => 'bg-green-100 text-green-800',
                            'failed'     => 'bg-red-100 text-red-800',
                            'processing' => 'bg-yellow-100 text-yellow-800',
                            default      => 'bg-gray-100 text-gray-600',
                        };
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900 max-w-xs truncate">{{ $s->source_file_name }}</td>
                        <td class="px-4 py-3 text-right text-gray-600">{{ number_format($s->total_rows) }}</td>
                        <td class="px-4 py-3 text-right text-emerald-600 font-medium">{{ number_format($s->new_count) }}</td>
                        <td class="px-4 py-3 text-right text-blue-600 font-medium">{{ number_format($s->updated_count) }}</td>
                        <td class="px-4 py-3 text-right text-gray-500">{{ number_format($s->skipped_count) }}</td>
                        <td class="px-4 py-3 text-right text-indigo-600">{{ number_format($s->account_created_count) }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {{ $badgeClass }}">
                                {{ $s->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-xs">{{ $s->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3 text-gray-500 text-xs">{{ $s->importer?->name ?? '-' }}</td>
                        <td class="px-4 py-3">
                            @if($s->status === 'draft')
                                <a href="{{ route('hr.employee-import.preview', $s) }}"
                                   class="text-xs text-indigo-600 hover:underline">Lanjutkan</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-4 py-8 text-center text-gray-400 text-sm">
                            Belum ada riwayat import.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($sessions->hasPages())
        <div class="mt-4">{{ $sessions->links() }}</div>
    @endif

</div>
@endsection
