@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-50 dark:bg-gray-900 pb-12 font-sans">

    {{-- Header --}}
    <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-3">
                    <a href="{{ route('finance.import.index') }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </a>
                    <div>
                        <h2 class="font-bold text-xl text-gray-800 dark:text-white">
                            Review Import — <span class="font-mono">{{ $session->periode }}</span>
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                            {{ $session->brand_label }} &bull; {{ $session->source_file_name }}
                            &bull; Import oleh {{ $session->importer?->name ?? '-' }}
                        </p>
                    </div>
                </div>
                @if($session->updated_count > 0)
                <form method="POST" action="{{ route('finance.import.confirm-all', $session) }}"
                      onsubmit="return confirm('Konfirmasi semua {{ $session->updated_count }} perubahan sekaligus?')">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Konfirmasi Semua ({{ $session->updated_count }})
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-8">

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
        @endif

        {{-- Search / Sort / Per-page toolbar --}}
        <form method="GET" action="{{ route('finance.import.show', $session) }}"
              class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Cari</label>
                <input type="text" name="search" value="{{ $search }}"
                       placeholder="No. Komp, Nama, Outlet..."
                       class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Urutkan</label>
                <select name="sort"
                        class="border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    <option value="no_komp"         @selected($sort === 'no_komp')>No. Komp</option>
                    <option value="nama"            @selected($sort === 'nama')>Nama</option>
                    <option value="outlet_name_raw" @selected($sort === 'outlet_name_raw')>Outlet</option>
                    <option value="gaji_pokok"      @selected($sort === 'gaji_pokok')>Gaji Pokok</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Arah</label>
                <select name="dir"
                        class="border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    <option value="asc"  @selected($dir === 'asc')>A &rarr; Z</option>
                    <option value="desc" @selected($dir === 'desc')>Z &rarr; A</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Per Halaman</label>
                <select name="per_page"
                        class="border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    <option value="15"  @selected((string)$perPage === '15')>15</option>
                    <option value="50"  @selected((string)$perPage === '50')>50</option>
                    <option value="100" @selected((string)$perPage === '100')>100</option>
                    <option value="200" @selected((string)$perPage === '200')>200</option>
                    <option value="all" @selected($perPage === 'all')>Semua</option>
                </select>
            </div>
            <button type="submit"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition">
                Terapkan
            </button>
            @if($search || $sort !== 'no_komp' || $dir !== 'asc' || (string)$perPage !== '15')
            <a href="{{ route('finance.import.show', $session) }}"
               class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                Reset
            </a>
            @endif
        </form>

        {{-- Summary Counters --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <div class="text-xs font-bold uppercase text-gray-400 mb-1">Total Baris</div>
                <div class="text-2xl font-extrabold text-gray-900 dark:text-white">{{ $session->total_rows }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-green-200 p-4">
                <div class="text-xs font-bold uppercase text-green-500 mb-1">Baru (Auto)</div>
                <div class="text-2xl font-extrabold text-green-700">{{ $session->new_count }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-yellow-200 p-4">
                <div class="text-xs font-bold uppercase text-yellow-600 mb-1">Perlu Konfirmasi</div>
                <div class="text-2xl font-extrabold text-yellow-700">{{ $pendingRows->total() }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-red-200 p-4">
                <div class="text-xs font-bold uppercase text-red-500 mb-1">Tidak Cocok</div>
                <div class="text-2xl font-extrabold text-red-600">{{ $unmatchedRows->total() }}</div>
            </div>
        </div>

        {{-- Pending Updates --}}
        @if($pendingRows->total() > 0)
        <div>
            <h3 class="text-base font-bold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-yellow-400 inline-block"></span>
                Perubahan Data — Perlu Konfirmasi
            </h3>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">No. Komp</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Nama</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Outlet</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Perubahan</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($pendingRows as $row)
                        <tr class="hover:bg-yellow-50/50 dark:hover:bg-gray-700/40 transition">
                            <td class="px-4 py-3 font-mono text-xs text-gray-700 dark:text-gray-300">{{ $row->no_komp }}</td>
                            <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">
                                {{ $row->nama }}
                                @if($row->employee)
                                    <div class="text-xs text-gray-400 font-normal">{{ $row->employee->full_name }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400 text-xs">{{ $row->outlet_name_raw }}</td>
                            <td class="px-4 py-3">
                                @if($row->diff_snapshot)
                                    <div class="space-y-1">
                                        @foreach($row->diff_snapshot as $field => $change)
                                        <div class="flex items-center gap-2 text-xs">
                                            <span class="font-medium text-gray-500">{{ str_replace('_', ' ', $field) }}:</span>
                                            <span class="text-red-600 line-through">{{ number_format($change['old'], 2, ',', '.') }}</span>
                                            <svg class="w-3 h-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                            <span class="text-green-700 font-semibold">{{ number_format($change['new'], 2, ',', '.') }}</span>
                                        </div>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-2">
                                    <form method="POST" action="{{ route('finance.import.row.action', [$session, $row]) }}">
                                        @csrf
                                        <input type="hidden" name="action" value="confirm">
                                        <button type="submit" class="text-xs bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded-lg font-semibold transition">Konfirmasi</button>
                                    </form>
                                    <form method="POST" action="{{ route('finance.import.row.action', [$session, $row]) }}">
                                        @csrf
                                        <input type="hidden" name="action" value="skip">
                                        <button type="submit" class="text-xs bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-500 text-gray-700 dark:text-gray-200 px-3 py-1 rounded-lg font-semibold transition">Skip</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @if($pendingRows->hasPages())
                    <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">{{ $pendingRows->appends(request()->except('pending_page'))->links() }}</div>
                @endif
            </div>
        </div>
        @endif

        {{-- Unmatched Rows --}}
        @if($unmatchedRows->total() > 0)
        <div>
            <h3 class="text-base font-bold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-amber-400 inline-block"></span>
                Karyawan Belum Terhubung ke Data OMEO
            </h3>
            <div class="mb-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg px-4 py-3 text-sm text-blue-800 dark:text-blue-300">
                <p class="font-semibold">Data sudah tersimpan otomatis</p>
                <p class="mt-1 text-blue-700 dark:text-blue-400">
                    Semua {{ $unmatchedRows->total() }} karyawan berikut sudah tersimpan ke database BPJS
                    dengan No. Komp sebagai identitas utama dan dapat ditampilkan di Dashboard BPJS.
                    Penghubungan ke data karyawan OMEO dapat dilakukan kemudian jika diperlukan.
                </p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">No. Komp</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Nama di File</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Outlet (File)</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Posisi</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Gaji Pokok</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">BPJS TK</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">BPJS JKes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($unmatchedRows as $row)
                        <tr class="hover:bg-amber-50/40 dark:hover:bg-gray-700/40">
                            <td class="px-4 py-3 font-mono text-xs text-amber-600 font-semibold">{{ $row->no_komp ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-200">{{ $row->nama }}</td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400 text-xs">{{ $row->outlet_name_raw }}</td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400 text-xs">{{ $row->posisi }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 font-mono text-xs">{{ number_format($row->gaji_pokok, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 font-mono text-xs">{{ number_format(abs($row->bpjs_tk_raw), 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 font-mono text-xs">{{ number_format(abs($row->bpjs_jkes_raw), 0, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @if($unmatchedRows->hasPages())
                    <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">{{ $unmatchedRows->appends(request()->except('unmatched_page'))->links() }}</div>
                @endif
            </div>
        </div>
        @endif

        {{-- New Rows (auto-committed) --}}
        @if($newRows->total() > 0)
        <div>
            <h3 class="text-base font-bold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-green-400 inline-block"></span>
                Baru — Langsung Diproses
            </h3>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">No. Komp</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Nama</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Outlet</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Gaji Pokok</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">BPJS TK</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">BPJS JKes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($newRows as $row)
                        <tr class="hover:bg-green-50/40 dark:hover:bg-gray-700/40">
                            <td class="px-4 py-3 font-mono text-xs text-gray-700 dark:text-gray-300">{{ $row->no_komp }}</td>
                            <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">{{ $row->nama }}</td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400 text-xs">{{ $row->outlet?->name ?? $row->outlet_name_raw }}</td>
                            <td class="px-4 py-3 text-right font-mono text-xs text-gray-700 dark:text-gray-300">{{ number_format($row->gaji_pokok, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-mono text-xs text-gray-700 dark:text-gray-300">{{ number_format(abs($row->bpjs_tk_raw), 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-mono text-xs text-gray-700 dark:text-gray-300">{{ number_format(abs($row->bpjs_jkes_raw), 0, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @if($newRows->hasPages())
                    <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">{{ $newRows->appends(request()->except('new_page'))->links() }}</div>
                @endif
            </div>
        </div>
        @endif

    </div>
</div>
@endsection
