@extends('layouts.app')
@section('title', 'Cross-billing BPJS')
@section('content')

@php
    $bulanID = [
        '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April',
        '05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus',
        '09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember',
    ];
    [$periodeYear, $periodeMonth] = explode('-', $periode);
    $periodeLabel = ($bulanID[$periodeMonth] ?? $periodeMonth) . ' ' . $periodeYear;

    // URL dasar untuk export (sama dengan halaman ini + ?export=excel)
    $exportUrl = request()->fullUrlWithQuery(['export' => 'excel']);
@endphp

<div class="max-w-7xl mx-auto px-4 py-6 space-y-5">

    {{-- ── Header ── --}}
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Laporan Cross-billing BPJS</h1>
            <p class="text-sm text-gray-500 mt-0.5">PT yang menanggung iuran BPJS karyawan, dikelompokkan per periode.</p>
        </div>
        <a href="{{ route('finance.bpjs-assignments.index') }}"
           style="color:#1D4ED8;text-decoration:none;"
           class="inline-flex items-center gap-1.5 text-sm font-medium">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Master Assignment
        </a>
    </div>

    {{-- ── Tab nav ── --}}
    <div class="flex gap-1 border-b border-gray-200">
        <span class="px-4 py-2 text-sm font-semibold text-gray-900 border-b-2" style="border-color:#7C3AED;">
            Per PT
        </span>
        <a href="{{ route('finance.bpjs-assignments.cross-billing-outlet', ['periode' => $periode]) }}"
           class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700">
            Per Outlet
        </a>
    </div>

    {{-- ── Filter ── --}}
    <form method="GET" action="{{ route('finance.bpjs-assignments.cross-billing') }}"
          class="flex flex-wrap items-center gap-2">
        <div class="flex items-center gap-2">
            <label class="text-sm font-medium text-gray-600 whitespace-nowrap">Periode:</label>
            <input type="month" name="periode" value="{{ $periode }}"
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none
                          focus:ring-2 focus:ring-blue-400">
        </div>
        <select name="legal_entity_id"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none
                       focus:ring-2 focus:ring-blue-400">
            <option value="">Semua PT</option>
            @foreach($legalEntities as $pt)
                <option value="{{ $pt->id }}" {{ $selectedPt == $pt->id ? 'selected' : '' }}>
                    {{ $pt->short_name ?? $pt->name }}
                </option>
            @endforeach
        </select>
        <button type="submit" class="px-4 py-2 bg-gray-900 text-white text-sm rounded-lg">
            Tampilkan
        </button>
        @if($grandTotal['karyawan'] > 0)
            <a href="{{ $exportUrl }}"
               style="background-color:#059669;color:#ffffff;text-decoration:none;"
               class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Export Excel
            </a>
        @endif
    </form>

    {{-- ── Summary Cards ── --}}
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border p-4 text-center">
            <p class="text-3xl font-bold text-gray-900">{{ $grandTotal['karyawan'] }}</p>
            <p class="text-xs text-gray-500 mt-1">Karyawan aktif BPJS</p>
            <p class="text-xs text-gray-400 mt-0.5">Periode {{ $periodeLabel }}</p>
        </div>
        <div class="bg-white rounded-xl border p-4 text-center">
            <p class="text-2xl font-bold" style="color:#7C3AED;">
                Rp {{ number_format($grandTotal['iuran'], 0, ',', '.') }}
            </p>
            <p class="text-xs text-gray-500 mt-1">Total iuran bulan ini</p>
            <p class="text-xs text-gray-400 mt-0.5">Semua PT gabungan</p>
        </div>
        <div class="bg-white rounded-xl border p-4 text-center md:col-span-1 col-span-2">
            <p class="text-3xl font-bold" style="color:#1D4ED8;">{{ $grandTotal['pt'] }}</p>
            <p class="text-xs text-gray-500 mt-1">PT yang terlibat</p>
            <p class="text-xs text-gray-400 mt-0.5">Menanggung BPJS karyawan</p>
        </div>
    </div>

    {{-- ── Data per PT ── --}}
    @forelse($groupedByPt as $ptId => $ptRows)
        @php
            $summary = $summaryByPt[$ptId];
            $hasMissing = $summary['missing_data'] > 0;
        @endphp

        <div class="bg-white rounded-xl border overflow-hidden">

            {{-- Card header --}}
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between px-5 py-4 border-b"
                 style="background-color:#F5F3FF;">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold text-white flex-shrink-0"
                         style="background-color:#7C3AED;">
                        {{ substr($summary['pt_name'], 0, 1) }}
                    </div>
                    <div>
                        <p class="font-semibold text-gray-900">{{ $summary['pt_name'] }}</p>
                        <div class="flex flex-wrap items-center gap-2 mt-0.5">
                            <span class="text-xs px-2 py-0.5 rounded-full"
                                  style="background-color:#EDE9FE;color:#5B21B6;">
                                {{ $summary['total_karyawan'] }} karyawan
                            </span>
                            @if($hasMissing)
                                <span class="text-xs px-2 py-0.5 rounded-full"
                                      style="background-color:#FEF3C7;color:#92400E;">
                                    ⚠ {{ $summary['missing_data'] }} belum ada data BPJS
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-xs text-gray-500">Total Iuran</p>
                    <p class="text-lg font-bold" style="color:#7C3AED;">
                        Rp {{ number_format($summary['total_iuran'], 0, ',', '.') }}
                    </p>
                </div>
            </div>

            {{-- Tabel karyawan --}}
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-center w-10">No</th>
                            <th class="px-4 py-3 text-left">Nama Karyawan</th>
                            <th class="px-4 py-3 text-left">NIK</th>
                            <th class="px-4 py-3 text-left">No. Komp</th>
                            <th class="px-4 py-3 text-right">BPJS TK Pekerja</th>
                            <th class="px-4 py-3 text-right">BPJS TK Perusahaan</th>
                            <th class="px-4 py-3 text-right">BPJS JKES</th>
                            <th class="px-4 py-3 text-right">Total Iuran</th>
                            <th class="px-4 py-3 text-center">Status Data</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($ptRows as $i => $r)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 text-center text-gray-400 text-xs">{{ $i + 1 }}</td>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $r->full_name }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $r->nik ?? '—' }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $r->no_komp ?? '—' }}</td>
                                <td class="px-4 py-3 text-right text-gray-700">
                                    {{ $r->bpjs_tk_employee > 0 ? 'Rp '.number_format($r->bpjs_tk_employee, 0, ',', '.') : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right text-gray-700">
                                    {{ $r->bpjs_tk_employer > 0 ? 'Rp '.number_format($r->bpjs_tk_employer, 0, ',', '.') : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right text-gray-700">
                                    @php $jkes = $r->bpjs_jkes_employee + $r->bpjs_jkes_employer; @endphp
                                    {{ $jkes > 0 ? 'Rp '.number_format($jkes, 0, ',', '.') : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right font-medium text-gray-900">
                                    {{ $r->total_iuran > 0 ? 'Rp '.number_format($r->total_iuran, 0, ',', '.') : '—' }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if($r->has_bpjs_data)
                                        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium"
                                              style="background-color:#D1FAE5;color:#065F46;">
                                            ✓ Data BPJS
                                        </span>
                                    @else
                                        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium"
                                              style="background-color:#FEF3C7;color:#92400E;">
                                            ⚠ Belum ada data
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    {{-- Footer subtotal --}}
                    <tfoot>
                        <tr style="background-color:#F5F3FF;">
                            <td colspan="7" class="px-4 py-3 text-right text-sm font-semibold text-gray-700">
                                Subtotal {{ $summary['pt_name'] }}
                            </td>
                            <td class="px-4 py-3 text-right font-bold text-gray-900">
                                Rp {{ number_format($summary['total_iuran'], 0, ',', '.') }}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Info banner cross-billing --}}
            <div class="px-5 py-3 text-sm border-t" style="background-color:#EFF6FF;color:#1E40AF;">
                <svg class="w-4 h-4 inline mr-1 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <strong>{{ $summary['pt_name'] }}</strong> menanggung iuran BPJS karyawan di atas
                meski mereka bekerja di outlet PT lain.
                @if($summary['total_iuran'] > 0)
                    Tagihkan total
                    <strong>Rp {{ number_format($summary['total_iuran'], 0, ',', '.') }}</strong>
                    ke PT ini.
                @else
                    Data iuran belum tersedia — pastikan rekonsiliasi BPJS periode
                    <strong>{{ $periodeLabel }}</strong> sudah diimport.
                @endif
            </div>
        </div>

    @empty
        <div class="bg-white rounded-xl border px-6 py-14 text-center text-gray-400">
            <svg class="w-12 h-12 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
            <p class="text-sm font-medium text-gray-500">
                Belum ada assignment BPJS karyawan rolling untuk periode
                <strong>{{ $periodeLabel }}</strong>.
            </p>
            <p class="text-xs mt-2">
                Tambahkan data di halaman
                <a href="{{ route('finance.bpjs-assignments.index') }}"
                   style="color:#1D4ED8;">Master Assignment</a>.
            </p>
        </div>
    @endforelse

</div>
@endsection
