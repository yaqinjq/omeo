@extends('layouts.app')
@section('title', 'Detail Per Bulan ' . $tahun)
@section('content')

<div class="max-w-full mx-auto py-8 px-4">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Summary Gaji Tahunan</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                Histori gaji per karyawan — data dari file Summary Tokio-O!
            </p>
        </div>
        <div class="flex items-center gap-2">
            @can('finance.bpjs.view')
            <div class="relative inline-block text-left" x-data="{ openExport: false }">
                <button @click="openExport = !openExport"
                        @click.outside="openExport = false"
                        type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-green-600
                               hover:bg-green-700 text-white text-sm font-medium
                               rounded-lg transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                    </svg>
                    Export Excel
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="openExport"
                     x-transition
                     class="absolute right-0 mt-1 w-52 bg-white rounded-lg shadow-lg
                            border border-gray-200 z-50">
                    <a href="{{ route('finance.annual-summary.export', ['tahun' => $tahun, 'outlet_id' => $outletId]) }}"
                       class="flex items-center gap-2 px-4 py-3 text-sm text-gray-700
                              hover:bg-gray-50 rounded-t-lg border-b">
                        📊 Export Summary
                    </a>
                    <a href="{{ route('finance.annual-summary.export-detail', ['tahun' => $tahun, 'outlet_id' => $outletId]) }}"
                       class="flex items-center gap-2 px-4 py-3 text-sm text-gray-700
                              hover:bg-gray-50 rounded-b-lg">
                        📋 Export Detail
                    </a>
                </div>
            </div>
            @endcan
            @can('finance.import')
            <a href="{{ route('finance.annual-summary.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium
                      text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                Upload Summary
            </a>
            @endcan
        </div>
    </div>

    {{-- Tab Navigation --}}
    <div class="flex border-b border-gray-200 mb-6">
        <a href="{{ route('finance.annual-summary.index', ['tahun' => $tahun, 'outlet_id' => $outletId]) }}"
           class="px-5 py-2.5 text-sm font-medium text-gray-500 hover:text-gray-700 border-b-2 border-transparent -mb-px transition-colors">
            Ringkasan
        </a>
        <a href="{{ route('finance.annual-summary.detail-view', ['tahun' => $tahun, 'outlet_id' => $outletId]) }}"
           class="px-5 py-2.5 text-sm font-medium border-b-2 border-indigo-600 text-indigo-600 -mb-px">
            Detail Per Bulan
        </a>
    </div>

    @if(session('success'))
    <div class="mb-4 bg-green-50 border border-green-200 text-green-800
                rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif

    {{-- Filter --}}
    <form method="GET"
          action="{{ route('finance.annual-summary.detail-view') }}"
          class="bg-white rounded-xl border border-gray-200 p-4 mb-6 flex flex-wrap gap-3 items-end">

        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-600">Tahun</label>
            <select name="tahun"
                    class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                @foreach($availableYears as $y)
                <option value="{{ $y }}" {{ $y == $tahun ? 'selected' : '' }}>{{ $y }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-600">Outlet</label>
            <select name="outlet_id"
                    class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                <option value="">Semua Outlet</option>
                @foreach($outlets as $outlet)
                <option value="{{ $outlet->id }}" {{ $outlet->id == $outletId ? 'selected' : '' }}>
                    {{ $outlet->name }}
                </option>
                @endforeach
            </select>
        </div>

        <div class="flex flex-col gap-1 flex-1 min-w-48">
            <label class="text-xs font-medium text-gray-600">Cari Karyawan</label>
            <input type="text" name="search" value="{{ $search }}"
                   placeholder="No. Komp atau Nama…"
                   class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
        </div>

        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-600">Per Halaman</label>
            <select name="per_page"
                    class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                @foreach([20, 50, 100, 200] as $pp)
                <option value="{{ $pp }}" {{ $pp == $perPage ? 'selected' : '' }}>{{ $pp }}</option>
                @endforeach
            </select>
        </div>

        <button type="submit"
                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
            Terapkan
        </button>
        @if($search || $outletId)
        <a href="{{ route('finance.annual-summary.detail-view', ['tahun' => $tahun]) }}"
           class="px-4 py-2 bg-gray-100 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-200 transition-colors">
            Reset
        </a>
        @endif
    </form>

    {{-- Info bulan aktif --}}
    @if(count($activeBulans) === 0)
    <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-xl px-4 py-3 text-sm mb-6">
        Tidak ada data payroll untuk tahun {{ $tahun }}{{ $outletId ? ' pada outlet yang dipilih' : '' }}.
    </div>
    @else

    {{-- Tabel detail --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-6">
        <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
            <span class="text-sm font-medium text-gray-700">
                {{ $employees->total() }} karyawan &mdash;
                {{ count($activeBulans) }} bulan aktif ({{ implode(', ', array_map(fn($m) => substr($bulanLabels[$m], 0, 3), $activeBulans)) }})
            </span>
            <span class="text-xs text-gray-400">Scroll kanan untuk lihat semua bulan →</span>
        </div>

        <div class="overflow-x-auto">
            <table style="font-size:0.75rem;border-collapse:collapse;width:100%;">
                <thead>
                    {{-- Baris 1: fixed cols + merged bulan headers --}}
                    <tr>
                        <th rowspan="2" style="position:sticky;left:0;z-index:20;background-color:#1F2937;color:#FFFFFF;border:1px solid #4B5563;padding:6px 8px;text-align:center;white-space:nowrap;min-width:2.5rem;">No</th>
                        <th rowspan="2" style="position:sticky;left:40px;z-index:20;background-color:#1F2937;color:#FFFFFF;border:1px solid #4B5563;padding:6px 8px;text-align:left;white-space:nowrap;min-width:6rem;">No. Komp</th>
                        <th rowspan="2" style="position:sticky;left:136px;z-index:20;background-color:#1F2937;color:#FFFFFF;border:1px solid #4B5563;padding:6px 8px;text-align:left;white-space:nowrap;min-width:10rem;">Nama</th>
                        <th rowspan="2" style="background-color:#374151;color:#FFFFFF;border:1px solid #4B5563;padding:6px 8px;text-align:left;white-space:nowrap;min-width:8rem;">Posisi</th>
                        <th rowspan="2" style="background-color:#374151;color:#FFFFFF;border:1px solid #4B5563;padding:6px 8px;text-align:left;white-space:nowrap;min-width:8rem;">Outlet</th>
                        @foreach($activeBulans as $bulan)
                        <th colspan="{{ count($subCols) }}"
                            style="background-color:#B91C1C;color:#FFFFFF;padding:6px 8px;text-align:center;font-weight:bold;border:1px solid #7F1D1D;white-space:nowrap;">
                            {{ $bulanLabels[$bulan] }} {{ $tahun }}
                        </th>
                        @endforeach
                        <th rowspan="2" style="background-color:#065F46;color:#FFFFFF;border:1px solid #064E3B;padding:6px 8px;text-align:right;white-space:nowrap;min-width:7rem;">Total Tahunan</th>
                    </tr>
                    {{-- Baris 2: sub-header per bulan --}}
                    <tr>
                        @foreach($activeBulans as $bulan)
                            @foreach($subCols as $sub)
                            <th style="background-color:#DC2626;color:#FFFFFF;border:1px solid #991B1B;padding:4px 8px;text-align:right;white-space:nowrap;min-width:6rem;">{{ $sub }}</th>
                            @endforeach
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($employees as $i => $emp)
                    @php
                        $no = $employees->firstItem() + $i;
                        $bgColor = $no % 2 === 0 ? '#F9FAFB' : '#FFFFFF';
                    @endphp
                    <tr style="background-color:{{ $bgColor }};">
                        <td style="position:sticky;left:0;z-index:10;background-color:{{ $bgColor }};border:1px solid #E5E7EB;padding:6px 8px;text-align:center;color:#6B7280;">{{ $no }}</td>
                        <td style="position:sticky;left:40px;z-index:10;background-color:{{ $bgColor }};border:1px solid #E5E7EB;padding:6px 8px;font-family:monospace;color:#374151;white-space:nowrap;">{{ $emp->no_komp }}</td>
                        <td style="position:sticky;left:136px;z-index:10;background-color:{{ $bgColor }};border:1px solid #E5E7EB;padding:6px 8px;font-weight:500;color:#111827;white-space:nowrap;">{{ $emp->nama }}</td>
                        <td style="border:1px solid #E5E7EB;padding:6px 8px;color:#4B5563;white-space:nowrap;">{{ $emp->posisi }}</td>
                        <td style="border:1px solid #E5E7EB;padding:6px 8px;color:#4B5563;white-space:nowrap;">{{ $emp->outlet_name_raw }}</td>
                        @foreach($activeBulans as $bulan)
                        @php
                            $periode = sprintf('%04d-%02d', $tahun, $bulan);
                            $vals = $lookup[$emp->no_komp][$periode] ?? null;
                        @endphp
                            @if($vals)
                                @foreach($vals as $vi => $v)
                                <td style="border:1px solid #E5E7EB;padding:6px 8px;text-align:right;white-space:nowrap;{{ $vi === 8 ? 'font-weight:600;color:#4338CA;background-color:#EEF2FF;' : 'color:#374151;' }}">
                                    {{ $v > 0 ? number_format($v, 0, ',', '.') : '' }}
                                </td>
                                @endforeach
                            @else
                                @foreach($subCols as $sub)
                                <td style="border:1px solid #E5E7EB;padding:6px 8px;text-align:center;color:#D1D5DB;">—</td>
                                @endforeach
                            @endif
                        @endforeach
                        <td style="border:1px solid #E5E7EB;padding:6px 8px;text-align:right;font-weight:600;color:#065F46;white-space:nowrap;">
                            {{ number_format((float)$emp->total_tahunan, 0, ',', '.') }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ 5 + count($activeBulans) * count($subCols) + 1 }}"
                            style="padding:2rem 1rem;text-align:center;color:#9CA3AF;font-size:0.875rem;">
                            Tidak ada data karyawan yang cocok.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pagination --}}
    @if($employees->hasPages())
    <div class="flex items-center justify-between mt-4 text-sm text-gray-600">
        <span>
            Menampilkan {{ $employees->firstItem() }}–{{ $employees->lastItem() }}
            dari {{ $employees->total() }} karyawan
        </span>
        <div class="flex items-center gap-1">
            @if($employees->onFirstPage())
            <span class="px-3 py-1.5 rounded border border-gray-200 text-gray-300 cursor-not-allowed text-xs">&laquo;</span>
            @else
            <a href="{{ $employees->previousPageUrl() }}"
               class="px-3 py-1.5 rounded border border-gray-300 hover:bg-gray-50 text-xs">&laquo;</a>
            @endif

            @foreach($employees->getUrlRange(max(1, $employees->currentPage() - 2), min($employees->lastPage(), $employees->currentPage() + 2)) as $page => $url)
            @if($page === $employees->currentPage())
            <span class="px-3 py-1.5 rounded border border-indigo-600 bg-indigo-600 text-white text-xs font-medium">{{ $page }}</span>
            @else
            <a href="{{ $url }}" class="px-3 py-1.5 rounded border border-gray-300 hover:bg-gray-50 text-xs">{{ $page }}</a>
            @endif
            @endforeach

            @if($employees->hasMorePages())
            <a href="{{ $employees->nextPageUrl() }}"
               class="px-3 py-1.5 rounded border border-gray-300 hover:bg-gray-50 text-xs">&raquo;</a>
            @else
            <span class="px-3 py-1.5 rounded border border-gray-200 text-gray-300 cursor-not-allowed text-xs">&raquo;</span>
            @endif
        </div>
    </div>
    @endif

    @endif {{-- end activeBulans check --}}

</div>
@endsection
