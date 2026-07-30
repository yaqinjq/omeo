@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-50 dark:bg-gray-900 pb-12 font-sans">

    {{-- Header --}}
    <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 flex items-center justify-between">
            <div>
                <h2 class="font-bold text-xl text-gray-800 dark:text-white leading-tight">Dashboard BPJS</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Rekap potongan BPJS per outlet per periode</p>
            </div>
            @can('finance.import')
            <a href="{{ route('finance.import.create') }}"
               class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                Import Payroll
            </a>
            @endcan
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
        @endif

        {{-- Filter Bar --}}
        <form method="GET" action="{{ route('finance.bpjs.index') }}" class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Dari:</label>
                <input type="month" name="periode_from" value="{{ $periodeFrom }}"
                       class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm px-3 py-1.5 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="flex items-center gap-2">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Sampai:</label>
                <input type="month" name="periode_to" value="{{ $periodeTo }}"
                       class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm px-3 py-1.5 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="flex items-center gap-2">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Outlet:</label>
                <select name="outlet_id" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm px-3 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Semua Outlet</option>
                    @foreach($outlets as $outlet)
                        <option value="{{ $outlet->id }}" @selected($outletId == $outlet->id)>{{ $outlet->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="bg-gray-800 hover:bg-gray-700 dark:bg-gray-600 text-white text-sm font-medium px-4 py-1.5 rounded-lg transition">
                Tampilkan
            </button>
        </form>

        {{-- Aggregate Stat Cards --}}
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <div class="text-xs font-bold uppercase text-gray-400 mb-1">Total Karyawan</div>
                <div class="text-2xl font-extrabold text-gray-900 dark:text-white">{{ number_format($totalKaryawan) }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-blue-200 p-4">
                <div class="text-xs font-bold uppercase text-blue-400 mb-1">Terdaftar BPJS</div>
                <div class="text-2xl font-extrabold text-blue-700">{{ number_format($totalTerdaftar) }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <div class="text-xs font-bold uppercase text-gray-400 mb-1">BPJS TK (Rp)</div>
                <div class="text-xl font-extrabold text-gray-900 dark:text-white">{{ number_format($totalTk, 0, ',', '.') }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <div class="text-xs font-bold uppercase text-gray-400 mb-1">BPJS JKes (Rp)</div>
                <div class="text-xl font-extrabold text-gray-900 dark:text-white">{{ number_format($totalJkes, 0, ',', '.') }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-indigo-200 p-4">
                <div class="text-xs font-bold uppercase text-indigo-400 mb-1">Total BPJS (Rp)</div>
                <div class="text-xl font-extrabold text-indigo-700">{{ number_format($totalBpjs, 0, ',', '.') }}</div>
            </div>
        </div>

        {{-- Outlet Summaries with Payment Status --}}
        @if($summaries->count() > 0)
        <div>
            <h3 class="text-base font-bold text-gray-800 dark:text-white mb-3">Status Pembayaran per Outlet</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($summaries as $summary)
                @php
                    $colorMap = ['belum_bayar' => 'red', 'sudah_bayar' => 'yellow', 'verified' => 'green'];
                    $c = $colorMap[$summary->status_bayar] ?? 'gray';
                @endphp
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-3">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="font-semibold text-gray-900 dark:text-white">{{ $summary->outlet?->name ?? '—' }}</div>
                            <div class="text-xs text-gray-500 mt-0.5">{{ $summary->total_karyawan }} karyawan &bull; {{ $summary->karyawan_terdaftar_bpjs }} terdaftar</div>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold
                            {{ $c === 'red' ? 'bg-red-100 text-red-700' : ($c === 'yellow' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700') }}">
                            {{ $summary->status_label }}
                        </span>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-xs">
                        <div>
                            <div class="text-gray-400 uppercase font-bold">BPJS TK</div>
                            <div class="font-mono font-semibold text-gray-800 dark:text-gray-200">{{ number_format($summary->total_bpjs_tk, 0, ',', '.') }}</div>
                        </div>
                        <div>
                            <div class="text-gray-400 uppercase font-bold">BPJS JKes</div>
                            <div class="font-mono font-semibold text-gray-800 dark:text-gray-200">{{ number_format($summary->total_bpjs_jkes, 0, ',', '.') }}</div>
                        </div>
                        <div>
                            <div class="text-gray-400 uppercase font-bold">Total</div>
                            <div class="font-mono font-bold text-indigo-700">{{ number_format($summary->total_bpjs_keseluruhan, 0, ',', '.') }}</div>
                        </div>
                    </div>
                    @can('finance.bpjs.manage')
                    <details class="group">
                        <summary class="text-xs text-blue-600 cursor-pointer hover:text-blue-800 font-medium list-none">
                            Update Status Bayar ▾
                        </summary>
                        <form method="POST" action="{{ route('finance.bpjs.payment.update', $summary) }}" class="mt-3 space-y-2">
                            @csrf
                            <select name="status_bayar"
                                    class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-xs px-2 py-1.5">
                                <option value="belum_bayar" @selected($summary->status_bayar === 'belum_bayar')>Belum Bayar</option>
                                <option value="sudah_bayar" @selected($summary->status_bayar === 'sudah_bayar')>Sudah Bayar</option>
                                <option value="verified" @selected($summary->status_bayar === 'verified')>Terverifikasi</option>
                            </select>
                            <input type="date" name="tanggal_bayar"
                                   value="{{ $summary->tanggal_bayar?->format('Y-m-d') }}"
                                   class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-xs px-2 py-1.5"
                                   placeholder="Tanggal Bayar">
                            <input type="text" name="nomor_referensi"
                                   value="{{ $summary->nomor_referensi }}"
                                   class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-xs px-2 py-1.5"
                                   placeholder="No. Referensi">
                            <textarea name="catatan" rows="2"
                                      class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-xs px-2 py-1.5"
                                      placeholder="Catatan (opsional)">{{ $summary->catatan }}</textarea>
                            <button type="submit"
                                    class="w-full bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold py-1.5 rounded transition">
                                Simpan
                            </button>
                        </form>
                    </details>
                    @endcan
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Employee Detail Table --}}
        <div>
            <h3 class="text-base font-bold text-gray-800 dark:text-white mb-3">Detail per Karyawan</h3>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">No. Komp</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">NIK</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Nama</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Outlet</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Posisi</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Gaji Pokok</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">BPJS TK</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">BPJS JKes</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($records as $record)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                            <td class="px-4 py-2.5 font-mono text-xs text-gray-700 dark:text-gray-300">{{ $record->no_komp }}</td>
                            <td class="px-4 py-2.5 font-mono text-xs text-gray-500">{{ $record->nik ?: $record->employee?->nik ?: '—' }}</td>
                            <td class="px-4 py-2.5 font-semibold text-gray-900 dark:text-white">{{ $record->nama }}</td>
                            <td class="px-4 py-2.5 text-gray-600 dark:text-gray-400 text-xs">{{ $record->outlet_name ?? $record->outlet?->name }}</td>
                            <td class="px-4 py-2.5 text-gray-500 text-xs">{{ $record->posisi }}</td>
                            <td class="px-4 py-2.5 text-right font-mono text-xs text-gray-700 dark:text-gray-300">{{ number_format($record->gaji_pokok, 0, ',', '.') }}</td>
                            <td class="px-4 py-2.5 text-right font-mono text-xs text-gray-700 dark:text-gray-300">{{ number_format($record->bpjs_tk_employee, 0, ',', '.') }}</td>
                            <td class="px-4 py-2.5 text-right font-mono text-xs text-gray-700 dark:text-gray-300">{{ number_format($record->bpjs_jkes_employee, 0, ',', '.') }}</td>
                            <td class="px-4 py-2.5 text-center">
                                @if($record->is_bpjs_registered)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">Terdaftar</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-500">Tidak</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="px-4 py-10 text-center text-gray-400 text-sm">
                                Belum ada data BPJS untuk periode <strong>{{ $periodeFrom }}{{ $periodeFrom !== $periodeTo ? ' – ' . $periodeTo : '' }}</strong>.
                                <a href="{{ route('finance.import.create') }}" class="text-blue-600 hover:underline">Import payroll sekarang →</a>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $records->appends(request()->only(['periode_from', 'periode_to', 'outlet_id']))->links() }}
        </div>

    </div>
</div>
@endsection
