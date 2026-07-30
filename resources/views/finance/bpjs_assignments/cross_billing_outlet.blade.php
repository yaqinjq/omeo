@extends('layouts.app')
@section('title', 'Cross-billing BPJS Per Outlet')
@section('content')

@php
    $bulanID = [
        '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April',
        '05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus',
        '09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember',
    ];
    [$periodeYear, $periodeMonth] = explode('-', $periode);
    $periodeLabel = ($bulanID[$periodeMonth] ?? $periodeMonth) . ' ' . $periodeYear;
@endphp

<div class="max-w-7xl mx-auto px-4 py-6 space-y-5">

    {{-- ── Header ── --}}
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Cross-billing BPJS Per Outlet</h1>
            <p class="text-sm text-gray-500 mt-0.5">Outlet mana membayar BPJS untuk karyawan outlet lain, dan harus menagih ke outlet asalnya.</p>
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
        <a href="{{ route('finance.bpjs-assignments.cross-billing', ['periode' => $periode]) }}"
           class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700">
            Per PT
        </a>
        <span class="px-4 py-2 text-sm font-semibold text-gray-900 border-b-2" style="border-color:#7C3AED;">
            Per Outlet
        </span>
    </div>

    {{-- ── Filter ── --}}
    <form method="GET" action="{{ route('finance.bpjs-assignments.cross-billing-outlet') }}"
          class="flex flex-wrap items-center gap-2">
        <div class="flex items-center gap-2">
            <label class="text-sm font-medium text-gray-600 whitespace-nowrap">Periode:</label>
            <input type="month" name="periode" value="{{ $periode }}"
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none
                          focus:ring-2 focus:ring-blue-400">
        </div>
        <button type="submit" class="px-4 py-2 bg-gray-900 text-white text-sm rounded-lg">
            Tampilkan
        </button>
    </form>

    {{-- ── Summary Cards ── --}}
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border p-4 text-center">
            <p class="text-3xl font-bold text-gray-900">{{ $grandTotal['pairs'] }}</p>
            <p class="text-xs text-gray-500 mt-1">Pasangan outlet (bayar → asal)</p>
            <p class="text-xs text-gray-400 mt-0.5">Periode {{ $periodeLabel }}</p>
        </div>
        <div class="bg-white rounded-xl border p-4 text-center">
            <p class="text-3xl font-bold text-gray-900">{{ $grandTotal['karyawan'] }}</p>
            <p class="text-xs text-gray-500 mt-1">Karyawan lintas outlet</p>
        </div>
        <div class="bg-white rounded-xl border p-4 text-center md:col-span-1 col-span-2">
            <p class="text-2xl font-bold" style="color:#7C3AED;">
                Rp {{ number_format($grandTotal['iuran'], 0, ',', '.') }}
            </p>
            <p class="text-xs text-gray-500 mt-1">Total iuran yang perlu ditagihkan</p>
        </div>
    </div>

    @if($pairs->isEmpty())
        <div class="bg-white rounded-xl border px-6 py-14 text-center text-gray-400">
            <svg class="w-12 h-12 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
            <p class="text-sm font-medium text-gray-500">
                Tidak ada kasus lintas outlet untuk periode <strong>{{ $periodeLabel }}</strong>.
            </p>
            <p class="text-xs mt-2 max-w-md mx-auto">
                Ini bisa berarti memang tidak ada karyawan rolling di periode ini, atau kolom
                <strong>PT / Badan Hukum</strong> outlet asal karyawan belum diisi di halaman
                <a href="{{ route('outlets.index') }}" style="color:#1D4ED8;">Master Outlet</a>.
            </p>
        </div>
    @else
        {{-- ── Rincian per pasangan outlet ── --}}
        <div class="space-y-4">
            <h2 class="text-lg font-semibold text-gray-900">Rincian Per Pasangan Outlet</h2>
            @foreach($pairs as $pair)
                <div class="bg-white rounded-xl border overflow-hidden">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between px-5 py-4 border-b"
                         style="background-color:#F5F3FF;">
                        <div class="text-sm text-gray-800">
                            <strong>{{ $pair->payer_outlet_name }}</strong>
                            <span class="mx-1 text-gray-400">membayar untuk</span>
                            <strong>{{ $pair->total_karyawan }}</strong> karyawan asal
                            <strong>{{ $pair->origin_outlet_name }}</strong>
                            <span class="mx-1 text-gray-400">→ tagihkan ke</span>
                            <strong>{{ $pair->origin_outlet_name }}</strong>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-gray-500">Total Ditagihkan</p>
                            <p class="text-lg font-bold" style="color:#7C3AED;">
                                Rp {{ number_format($pair->total_iuran, 0, ',', '.') }}
                            </p>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                                <tr>
                                    <th class="px-4 py-3 text-center w-10">No</th>
                                    <th class="px-4 py-3 text-left">Nama Karyawan</th>
                                    <th class="px-4 py-3 text-left">No. Komp</th>
                                    <th class="px-4 py-3 text-right">Iuran BPJS</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($pair->employees as $i => $emp)
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-4 py-3 text-center text-gray-400 text-xs">{{ $i + 1 }}</td>
                                        <td class="px-4 py-3 font-medium text-gray-900">{{ $emp->full_name }}</td>
                                        <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $emp->no_komp ?? '—' }}</td>
                                        <td class="px-4 py-3 text-right text-gray-700">
                                            {{ $emp->total_iuran > 0 ? 'Rp '.number_format($emp->total_iuran, 0, ',', '.') : '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- ── Rekap per outlet ── --}}
        <div class="space-y-4">
            <h2 class="text-lg font-semibold text-gray-900">Rekap Per Outlet</h2>
            @foreach($byOutlet as $outlet)
                <div class="bg-white rounded-xl border p-5 space-y-3">
                    <p class="font-semibold text-gray-900">{{ $outlet->name }}</p>

                    @if($outlet->pays->isNotEmpty())
                        <div class="text-sm">
                            <p class="text-gray-600 mb-1">💰 Outlet ini <strong>membayar</strong> BPJS untuk karyawan rolling dari:</p>
                            <ul class="list-disc list-inside text-gray-700 space-y-0.5">
                                @foreach($outlet->pays as $p)
                                    <li>{{ $p->origin_outlet_name }} — {{ $p->total_karyawan }} karyawan, Rp {{ number_format($p->total_iuran, 0, ',', '.') }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if($outlet->billed_by->isNotEmpty())
                        <div class="text-sm">
                            <p class="text-gray-600 mb-1">📩 Outlet ini akan <strong>ditagih</strong> oleh:</p>
                            <ul class="list-disc list-inside text-gray-700 space-y-0.5">
                                @foreach($outlet->billed_by as $b)
                                    <li>{{ $b->payer_outlet_name }} — {{ $b->total_karyawan }} karyawan, Rp {{ number_format($b->total_iuran, 0, ',', '.') }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

</div>
@endsection
