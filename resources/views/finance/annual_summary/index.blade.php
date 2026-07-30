@extends('layouts.app')
@section('title', 'Summary Gaji Tahunan ' . $tahun)
@section('content')

@php
$bulanLabels = [
    1=>'Jan', 2=>'Feb', 3=>'Mar', 4=>'Apr', 5=>'Mei', 6=>'Jun',
    7=>'Jul', 8=>'Aug', 9=>'Sep', 10=>'Okt', 11=>'Nov', 12=>'Des'
];
@endphp

<div class="max-w-full mx-auto py-8 px-4">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Summary Gaji Tahunan</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                Histori gaji per karyawan — data dari file Summary Tokio-O!
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            @can('finance.bpjs.view')
            <div class="relative inline-block text-left" x-data="{ openExport: false }"
                 @click.outside="openExport = false">
                <button @click="openExport = !openExport"
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
                     class="absolute right-0 mt-1 bg-white rounded-lg shadow-lg
                            border border-gray-200 z-50"
                     style="min-width:220px;">
                    <a href="{{ route('finance.annual-summary.export', request()->query()) }}"
                       class="flex items-center gap-2 px-4 py-3 text-sm text-gray-700
                              hover:bg-gray-50 rounded-t-lg border-b">
                        📊 Export Summary
                    </a>
                    <a href="{{ route('finance.annual-summary.export-detail', request()->query()) }}"
                       class="flex items-center gap-2 px-4 py-3 text-sm text-gray-700
                              hover:bg-gray-50 border-b">
                        📋 Export Detail
                    </a>
                    <div class="px-3 pt-3 pb-3">
                        <p class="text-xs font-medium text-gray-500 mb-2">Total Gaji per Brand</p>
                        <form method="GET" action="{{ route('finance.export.total-gaji') }}" class="space-y-2"
                              @submit="openExport = false">
                            <input type="month" name="periode" value="{{ now()->format('Y-m') }}"
                                   class="w-full border border-gray-300 rounded px-2 py-1.5 text-xs
                                          outline-none focus:ring-2 focus:ring-purple-400">
                            <button type="submit"
                                    style="background-color:#7C3AED;color:#ffffff;"
                                    class="w-full flex items-center justify-center gap-1.5 px-3 py-2
                                           text-xs font-medium rounded-lg transition">
                                <svg width="12" height="12" fill="none" stroke="currentColor"
                                     stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                Export Total Gaji
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            @endcan
            {{-- Template Mandiri — dropdown sejajar Export Excel --}}
            @can('finance.bpjs.view')
            <div class="relative inline-block text-left"
                 x-data="{ openMandiri: false, periode: '{{ now()->format('Y-m') }}', keterangan: '' }"
                 @click.outside="openMandiri = false">

                {{-- Trigger button — style persis sama dengan Export Excel --}}
                <button @click="openMandiri = !openMandiri"
                        type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-green-600
                               hover:bg-green-700 text-white text-sm font-medium
                               rounded-lg transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                    Template Mandiri
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                {{-- Dropdown panel --}}
                <div x-show="openMandiri"
                     x-transition
                     class="absolute right-0 mt-1 bg-white rounded-lg shadow-lg
                            border border-gray-200 z-50"
                     style="min-width:290px;">
                    <div class="p-4">

                        {{-- Input Periode --}}
                        <div class="mb-3">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Periode</label>
                            <input type="month"
                                   x-model="periode"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2
                                          text-sm outline-none focus:ring-2 focus:ring-green-400
                                          focus:border-transparent">
                        </div>

                        {{-- Input Keterangan --}}
                        <div class="mb-4">
                            <label class="block text-xs font-medium text-gray-500 mb-1">
                                Keterangan Transfer <span class="text-gray-400">(Kolom H)</span>
                            </label>
                            <input type="text"
                                   x-model="keterangan"
                                   placeholder="cth: GAJI AH PEK JUNI 2026"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2
                                          text-sm outline-none focus:ring-2 focus:ring-green-400
                                          focus:border-transparent">
                        </div>

                        {{-- 2 tombol download --}}
                        <div class="flex gap-2">
                            <a :href="'{{ route('finance.export.mandiri') }}?periode=' + periode + '&format=xlsx' + (keterangan ? '&keterangan=' + encodeURIComponent(keterangan) : '')"
                               @click="openMandiri = false"
                               style="background-color:#1D4ED8;color:#ffffff;text-decoration:none;"
                               class="flex flex-1 items-center justify-center gap-1.5 px-3 py-2
                                      text-xs font-medium rounded-lg transition">
                                <svg width="13" height="13" fill="none" stroke="currentColor"
                                     stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                Excel
                            </a>
                            <a :href="'{{ route('finance.export.mandiri') }}?periode=' + periode + '&format=csv' + (keterangan ? '&keterangan=' + encodeURIComponent(keterangan) : '')"
                               @click="openMandiri = false"
                               style="background-color:#15803D;color:#ffffff;text-decoration:none;"
                               class="flex flex-1 items-center justify-center gap-1.5 px-3 py-2
                                      text-xs font-medium rounded-lg transition">
                                <svg width="13" height="13" fill="none" stroke="currentColor"
                                     stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                CSV
                            </a>
                        </div>

                    </div>
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
        <a href="{{ route('finance.annual-summary.index', request()->only(['tahun', 'outlet_id', 'bulan', 'per_page'])) }}"
           class="px-5 py-2.5 text-sm font-medium border-b-2 border-indigo-600 text-indigo-600 -mb-px">
            Ringkasan
        </a>
        <a href="{{ route('finance.annual-summary.detail-view', request()->only(['tahun', 'outlet_id'])) }}"
           class="px-5 py-2.5 text-sm font-medium text-gray-500 hover:text-gray-700 border-b-2 border-transparent -mb-px transition-colors">
            Detail Per Bulan
        </a>
    </div>

    @if(session('success'))
    <div class="mb-4 bg-green-50 border border-green-200 text-green-800
                rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif

    {{-- Filter --}}
    <form method="GET"
          class="bg-white rounded-xl border border-gray-200 p-4 mb-6 flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Tahun</label>
            <select name="tahun"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm
                           focus:ring-2 focus:ring-indigo-300">
                @foreach($availableYears as $y)
                    <option value="{{ $y }}" {{ $y == $tahun ? 'selected' : '' }}>{{ $y }}</option>
                @endforeach
                @if($availableYears->isEmpty())
                    <option value="{{ now()->year }}" selected>{{ now()->year }}</option>
                @endif
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Outlet</label>
            <select name="outlet_id"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm
                           focus:ring-2 focus:ring-indigo-300">
                <option value="">Semua Outlet</option>
                @foreach($outlets as $o)
                    <option value="{{ $o->id }}" {{ $outletId == $o->id ? 'selected' : '' }}>
                        {{ $o->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Filter Bulan</label>
            <select name="bulan"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm
                           focus:ring-2 focus:ring-indigo-300">
                <option value="">Semua Bulan</option>
                @foreach($bulanLabels as $num => $label)
                    <option value="{{ $num }}" {{ $bulan == $num ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Per Halaman</label>
            <select name="per_page"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm
                           focus:ring-2 focus:ring-indigo-300">
                @foreach([50, 100, 200, 500] as $n)
                    <option value="{{ $n }}" {{ $perPage == $n ? 'selected' : '' }}>{{ $n }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit"
                class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium
                       rounded-lg hover:bg-indigo-700 transition-colors">
            Tampilkan
        </button>
        <a href="{{ route('finance.annual-summary.index') }}"
           class="px-4 py-2 bg-gray-100 text-gray-600 text-sm font-medium
                  rounded-lg hover:bg-gray-200 transition-colors">
            Reset
        </a>
    </form>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-2xl font-bold text-indigo-600">
                {{ number_format($totalKaryawan) }}
            </p>
            <p class="text-xs text-gray-500 mt-0.5">Total Karyawan</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xl font-bold text-gray-800">
                Rp {{ number_format($totalGajiTahunan, 0, ',', '.') }}
            </p>
            <p class="text-xs text-gray-500 mt-0.5">Total Gaji Tahunan</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xl font-bold text-gray-800">
                Rp {{ number_format($totalThr, 0, ',', '.') }}
            </p>
            <p class="text-xs text-gray-500 mt-0.5">Total THR</p>
        </div>
    </div>

    {{-- Ringkasan per Bulan --}}
    @if(!empty($monthlyTotals) && $totalKaryawan > 0)
    <div class="bg-white rounded-xl border border-gray-200 mb-6 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 bg-gray-50">
            <h2 class="text-sm font-semibold text-gray-700">
                Ringkasan per Bulan — {{ $tahun }}
            </h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-3 py-2 text-left text-xs text-gray-500 font-semibold uppercase">Bulan</th>
                        @foreach($bulanLabels as $num => $label)
                        <th class="px-3 py-2 text-center text-xs text-gray-500 font-semibold uppercase
                                   {{ $bulan == $num ? 'bg-indigo-50 text-indigo-700' : '' }}">
                            {{ $label }}
                        </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-t border-gray-100">
                        <td class="px-3 py-2 text-xs font-medium text-gray-600">Karyawan Aktif</td>
                        @foreach($bulanLabels as $num => $label)
                        <td class="px-3 py-2 text-center text-xs
                                   {{ $bulan == $num ? 'bg-indigo-50 font-semibold text-indigo-700' : 'text-gray-700' }}">
                            {{ $monthlyTotals[$num]?->jumlah_karyawan ?? 0 }}
                        </td>
                        @endforeach
                    </tr>
                    <tr class="border-t border-gray-100">
                        <td class="px-3 py-2 text-xs font-medium text-gray-600">Total Gaji (Jt)</td>
                        @foreach($bulanLabels as $num => $label)
                        @php $val = $monthlyTotals[$num]?->total_gaji ?? 0; @endphp
                        <td class="px-3 py-2 text-center text-xs
                                   {{ $bulan == $num ? 'bg-indigo-50 font-semibold text-indigo-700' : 'text-gray-700' }}">
                            {{ $val > 0 ? number_format($val / 1000000, 1) : '—' }}
                        </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Tabel Detail Per Karyawan --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-700">Detail Per Karyawan</h2>
            <p class="text-xs text-gray-400">{{ $records->total() }} karyawan ditemukan</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider">
                    <tr>
                        <th class="px-3 py-2 text-left whitespace-nowrap">No. Komp</th>
                        <th class="px-3 py-2 text-left whitespace-nowrap">Nama</th>
                        <th class="px-3 py-2 text-left whitespace-nowrap">Jabatan</th>
                        <th class="px-3 py-2 text-center whitespace-nowrap">Bln Aktif</th>
                        @foreach($bulanLabels as $num => $label)
                        <th class="px-3 py-2 text-right whitespace-nowrap
                                   {{ $bulan == $num ? 'bg-indigo-50 text-indigo-700' : '' }}">
                            {{ $label }}
                        </th>
                        @endforeach
                        <th class="px-3 py-2 text-right whitespace-nowrap">THR</th>
                        <th class="px-3 py-2 text-right whitespace-nowrap font-semibold">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($records as $r)
                    @php $gaji = $r->gajiPerBulan(); @endphp
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-3 py-2 font-mono text-gray-500">{{ $r->no_komp }}</td>
                        <td class="px-3 py-2">
                            <p class="font-medium text-gray-900 whitespace-nowrap">{{ $r->nama }}</p>
                            <p class="text-gray-400 text-xs">{{ $r->outlet_name_raw }}</p>
                        </td>
                        <td class="px-3 py-2 text-gray-500 whitespace-nowrap">{{ $r->posisi }}</td>
                        <td class="px-3 py-2 text-center">
                            <span class="inline-block px-1.5 py-0.5 text-xs rounded
                                         {{ $r->bulan_aktif >= 10 ? 'bg-green-100 text-green-700' :
                                            ($r->bulan_aktif >= 6  ? 'bg-yellow-100 text-yellow-700' :
                                            'bg-red-100 text-red-600') }}">
                                {{ $r->bulan_aktif }}/12
                            </span>
                        </td>
                        @foreach($bulanLabels as $num => $label)
                        @php
                            $cellVal     = $gaji[$num] ?? 0;
                            $periodeStr  = $tahun . '-' . str_pad($num, 2, '0', STR_PAD_LEFT);
                            $isMulti     = $multiOutletSet[$r->no_komp . '|' . $periodeStr] ?? false;
                        @endphp
                        <td class="px-3 py-2 text-right whitespace-nowrap
                                   {{ $bulan == $num ? 'bg-indigo-50' : '' }}">
                            @if($cellVal > 0)
                                <button type="button"
                                        onclick="showOutletDetail('{{ $r->no_komp }}', '{{ $periodeStr }}')"
                                        class="text-gray-800 hover:text-indigo-600 hover:underline cursor-pointer">
                                    {{ number_format($cellVal / 1000, 0) }}k
                                    @if($isMulti)
                                        <span class="ml-0.5 inline-block text-[9px] font-bold
                                                     bg-amber-100 text-amber-700 px-1 rounded">M</span>
                                    @endif
                                </button>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        @endforeach
                        <td class="px-3 py-2 text-right whitespace-nowrap text-gray-600">
                            {{ $r->thr ? number_format($r->thr / 1000, 0) . 'k' : '—' }}
                        </td>
                        <td class="px-3 py-2 text-right whitespace-nowrap font-semibold text-gray-900">
                            {{ $r->total_tahunan ? 'Rp ' . number_format($r->total_tahunan / 1000000, 1) . 'jt' : '—' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="18"
                            class="px-4 py-12 text-center text-gray-400 text-sm">
                            @if($availableYears->isEmpty())
                                Belum ada data. Klik "Upload Summary" untuk mulai.
                            @else
                                Tidak ada data untuk filter yang dipilih.
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-gray-100">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-4">
                    <span class="text-sm text-gray-500">
                        Menampilkan {{ $records->firstItem() ?? 0 }}–{{ $records->lastItem() ?? 0 }}
                        dari {{ $records->total() }} karyawan
                    </span>
                    <x-per-page-selector :per-page="$perPage" :options="[20, 50, 100, 200]" />
                </div>
                <div>{{ $records->appends(request()->query())->links() }}</div>
            </div>
        </div>
    </div>

    {{-- Ringkasan Kategori per Bulan --}}
    @php $hasCategoryData = array_sum(array_map('array_sum', $categoryBreakdown)) > 0; @endphp
    @if($hasCategoryData)
    <div class="bg-white rounded-xl border border-gray-200 mt-6 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 bg-gray-50">
            <h2 class="text-sm font-semibold text-gray-700">Ringkasan Gaji per Kategori per Bulan — {{ $tahun }}</h2>
            <p class="text-xs text-gray-400 mt-0.5">Data dari catatan payroll bulanan (gaji pokok karyawan per kategori departemen)</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase sticky left-0 bg-gray-50 z-10">Kategori</th>
                        @foreach($bulanCols as $m => $label)
                        <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase whitespace-nowrap">{{ strtoupper($label) }}</th>
                        @endforeach
                        <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase whitespace-nowrap">TOTAL</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @php
                    $categoryLabels = ['bar'=>'BAR','kitchen'=>'KITCHEN','service'=>'SERVICE','office'=>'OFFICE','prive'=>'PRIVE','lainnya'=>'LAINNYA'];
                    $categoryColors = ['bar'=>'text-orange-600','kitchen'=>'text-yellow-600','service'=>'text-cyan-600','office'=>'text-indigo-600','prive'=>'text-purple-600','lainnya'=>'text-gray-500'];
                    @endphp
                    @foreach($categories as $cat)
                    @php $rowTotal = array_sum($categoryBreakdown[$cat]); @endphp
                    @if($rowTotal > 0)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 font-medium sticky left-0 bg-white z-10 {{ $categoryColors[$cat] ?? '' }}">
                            {{ $categoryLabels[$cat] ?? strtoupper($cat) }}
                        </td>
                        @foreach($bulanCols as $m => $label)
                        @php $val = $categoryBreakdown[$cat][$label] ?? 0; @endphp
                        <td class="px-3 py-2 text-right whitespace-nowrap text-gray-700">
                            @if($val > 0){{ number_format($val/1000000, 1) }}jt
                            @else<span class="text-gray-300">—</span>@endif
                        </td>
                        @endforeach
                        <td class="px-3 py-2 text-right whitespace-nowrap font-semibold text-gray-800">
                            {{ number_format($rowTotal/1000000, 1) }}jt
                        </td>
                    </tr>
                    @endif
                    @endforeach
                    <tr class="bg-gray-50 border-t-2 border-gray-200 font-bold">
                        <td class="px-4 py-2 text-gray-900 sticky left-0 bg-gray-50 z-10">TOTAL</td>
                        @foreach($bulanCols as $m => $label)
                        @php $val = $monthlyTotalByCategory[$label] ?? 0; @endphp
                        <td class="px-3 py-2 text-right whitespace-nowrap text-gray-900">
                            @if($val > 0){{ number_format($val/1000000, 1) }}jt
                            @else<span class="text-gray-300">—</span>@endif
                        </td>
                        @endforeach
                        <td class="px-3 py-2 text-right whitespace-nowrap text-gray-900">
                            {{ number_format(array_sum($monthlyTotalByCategory)/1000000, 1) }}jt
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Riwayat Import --}}
    @if($importSessions->count() > 0)
    <div class="mt-6 bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 bg-gray-50">
            <h2 class="text-sm font-semibold text-gray-700">Riwayat Import — {{ $tahun }}</h2>
        </div>
        <table class="min-w-full text-xs">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-gray-500 uppercase font-semibold">File</th>
                    <th class="px-4 py-2 text-center text-gray-500 uppercase font-semibold">Total</th>
                    <th class="px-4 py-2 text-center text-gray-500 uppercase font-semibold">Baru</th>
                    <th class="px-4 py-2 text-center text-gray-500 uppercase font-semibold">Diperbarui</th>
                    <th class="px-4 py-2 text-center text-gray-500 uppercase font-semibold">Status</th>
                    <th class="px-4 py-2 text-left text-gray-500 uppercase font-semibold">Oleh</th>
                    <th class="px-4 py-2 text-left text-gray-500 uppercase font-semibold">Waktu</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($importSessions as $s)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 text-gray-700 font-medium">{{ $s->source_file_name }}</td>
                    <td class="px-4 py-2 text-center text-gray-600">{{ $s->total_rows }}</td>
                    <td class="px-4 py-2 text-center text-green-700">{{ $s->new_count }}</td>
                    <td class="px-4 py-2 text-center text-amber-700">{{ $s->updated_count }}</td>
                    <td class="px-4 py-2 text-center">
                        <span class="inline-block px-2 py-0.5 rounded text-xs font-medium
                            {{ $s->status === 'completed' ? 'bg-green-100 text-green-700' :
                               ($s->status === 'failed'    ? 'bg-red-100 text-red-700'    :
                               'bg-yellow-100 text-yellow-700') }}">
                            {{ $s->status }}
                        </span>
                    </td>
                    <td class="px-4 py-2 text-gray-500">{{ $s->importer?->name ?? '—' }}</td>
                    <td class="px-4 py-2 text-gray-400">{{ $s->created_at->format('d/m/Y H:i') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

</div>

{{-- Modal Detail Per Outlet --}}
<div id="outletDetailModal"
     class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
     onclick="if(event.target===this)closeOutletDetail()">
    <div class="bg-white rounded-xl max-w-lg w-full max-h-[80vh] overflow-y-auto shadow-xl">
        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800 text-sm">Detail Gaji Per Outlet</h3>
            <button onclick="closeOutletDetail()"
                    class="text-gray-400 hover:text-gray-600 text-lg leading-none">✕</button>
        </div>
        <div id="outletDetailContent" class="p-5">
            <p class="text-gray-400 text-sm text-center py-4">Memuat...</p>
        </div>
    </div>
</div>

<script>
function showOutletDetail(noKomp, periode) {
    document.getElementById('outletDetailModal').classList.remove('hidden');
    document.getElementById('outletDetailContent').innerHTML =
        '<p class="text-gray-400 text-sm text-center py-4">Memuat...</p>';

    fetch(`/finance/annual-summary/detail/${encodeURIComponent(noKomp)}/${encodeURIComponent(periode)}`)
        .then(r => r.json())
        .then(data => {
            let html = `<p class="text-xs text-gray-400 mb-3">No.Komp <strong>${data.no_komp}</strong> — Periode <strong>${data.periode}</strong></p>`;
            if (data.records.length === 0) {
                html += '<p class="text-sm text-gray-400 text-center py-2">Tidak ada data.</p>';
            } else {
                html += '<table class="w-full text-sm"><thead><tr class="border-b border-gray-200">' +
                        '<th class="text-left py-1.5 pr-3 text-xs text-gray-500 font-semibold">Outlet</th>' +
                        '<th class="text-right py-1.5 text-xs text-gray-500 font-semibold">Gaji Pokok</th>' +
                        '<th class="text-right py-1.5 pl-3 text-xs text-gray-500 font-semibold">Total</th>' +
                        '</tr></thead><tbody>';
                data.records.forEach(r => {
                    const fmt = v => Number(v).toLocaleString('id-ID');
                    html += `<tr class="border-b border-gray-50 hover:bg-gray-50">
                        <td class="py-1.5 pr-3 text-gray-700">${r.outlet_name || '<span class="text-gray-400">—</span>'}</td>
                        <td class="py-1.5 text-right text-gray-500">${r.gaji_pokok > 0 ? fmt(r.gaji_pokok) : '—'}</td>
                        <td class="py-1.5 pl-3 text-right font-medium text-gray-800">${fmt(r.total)}</td>
                    </tr>`;
                });
                html += '</tbody></table>';
                html += `<div class="flex justify-between items-center pt-3 mt-1 border-t border-gray-200 font-bold text-sm">
                    <span class="text-gray-700">Grand Total</span>
                    <span class="text-gray-900">Rp ${Number(data.grand_total).toLocaleString('id-ID')}</span>
                </div>`;
            }
            document.getElementById('outletDetailContent').innerHTML = html;
        })
        .catch(() => {
            document.getElementById('outletDetailContent').innerHTML =
                '<p class="text-sm text-red-500 text-center py-4">Gagal memuat data.</p>';
        });
}
function closeOutletDetail() {
    document.getElementById('outletDetailModal').classList.add('hidden');
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeOutletDetail(); });
</script>
@endsection
