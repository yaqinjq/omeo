@extends('layouts.app')
@section('title', 'Rekonsiliasi BPJS vs Payroll')
@section('content')
<div class="p-6">

    {{-- HEADER --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Rekonsiliasi BPJS vs Payroll</h1>
            <p class="text-sm text-gray-500 mt-1">
                Pencocokan data tagihan BPJS PDF dengan data payroll CSV
            </p>
        </div>
        @if($periode)
        <a href="{{ route('finance.payroll-bpjs-reconciliation.export', request()->query()) }}"
           style="background-color:#16a34a;color:#FFFFFF;"
           class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg hover:opacity-90 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
            </svg>
            Export Excel
        </a>
        @endif
    </div>

    {{-- FILTER --}}
    <form method="GET"
          action="{{ route('finance.payroll-bpjs-reconciliation.index') }}"
          class="bg-white rounded-xl shadow p-4 mb-6">
        <div class="flex flex-wrap gap-4 items-end">

            <div>
                <label class="text-xs font-medium text-gray-600 block mb-1">Tahun</label>
                <select name="tahun" class="border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                    @foreach([2026, 2025, 2024] as $y)
                    <option value="{{ $y }}" {{ $tahun == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="text-xs font-medium text-gray-600 block mb-1">Periode Payroll</label>
                <select name="periode" class="border rounded-lg px-3 py-2 text-sm w-44 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                    <option value="">-- Pilih Periode --</option>
                    @foreach($periodeList as $p)
                    @php $bk = substr($p, 5, 2); @endphp
                    <option value="{{ $p }}" {{ $periode === $p ? 'selected' : '' }}>
                        {{ ($bulanLabel[$bk] ?? $p) . ' ' . substr($p, 0, 4) }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="text-xs font-medium text-gray-600 block mb-1">Status</label>
                <select name="status" class="border rounded-lg px-3 py-2 text-sm w-52 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                    <option value="">Semua Status</option>
                    <option value="cocok"            {{ $status === 'cocok'            ? 'selected' : '' }}>✅ Cocok</option>
                    <option value="minor"            {{ $status === 'minor'            ? 'selected' : '' }}>⚠️ Selisih Minor</option>
                    <option value="selisih"          {{ $status === 'selisih'          ? 'selected' : '' }}>🔴 Selisih Signifikan</option>
                    <option value="tidak_di_payroll"    {{ $status === 'tidak_di_payroll'    ? 'selected' : '' }}>📄 Tidak di Payroll</option>
                    <option value="tidak_di_bpjs"       {{ $status === 'tidak_di_bpjs'       ? 'selected' : '' }}>📋 Tidak di BPJS</option>
                    <option value="nik_tidak_ditemukan" {{ $status === 'nik_tidak_ditemukan' ? 'selected' : '' }}>❓ NIK Tidak di OMEO</option>
                    <option value="no_nik"              {{ $status === 'no_nik'              ? 'selected' : '' }}>⬜ Tanpa NIK di PDF</option>
                </select>
            </div>

            <button type="submit"
                    style="background-color:#2563EB;color:#FFFFFF;"
                    class="px-4 py-2 text-sm font-medium rounded-lg hover:opacity-90 transition">
                Tampilkan
            </button>

            @if($periode)
            <a href="{{ route('finance.payroll-bpjs-reconciliation.index') }}"
               class="px-4 py-2 text-sm text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                Reset
            </a>
            @endif
        </div>
    </form>

    @if(! $periode)
    {{-- PLACEHOLDER --}}
    <div class="bg-white rounded-xl shadow p-12 text-center">
        <div class="text-6xl mb-4">📊</div>
        <p class="text-gray-500 text-lg font-medium">Pilih periode untuk melihat hasil rekonsiliasi</p>
        <p class="text-gray-400 text-sm mt-2">
            Sistem akan mencocokkan data tagihan BPJS PDF dengan data payroll CSV periode yang dipilih
        </p>
    </div>

    @else

    {{-- STATS CARDS --}}
    @php
    $statCards = [
        ['label' => 'Total Records',         'value' => number_format($stats['total']),                    'bg' => '#1a3c6e'],
        ['label' => '✅ Cocok',               'value' => number_format($stats['cocok']),                    'bg' => '#16a34a'],
        ['label' => '⚠️ Selisih Minor',       'value' => number_format($stats['minor']),                    'bg' => '#ca8a04'],
        ['label' => '🔴 Selisih Signifikan',  'value' => number_format($stats['selisih']),                  'bg' => '#dc2626'],
        ['label' => '📄 Tdk di Payroll',      'value' => number_format($stats['tidak_di_payroll']),         'bg' => '#ea580c'],
        ['label' => '📋 Tdk di BPJS',         'value' => number_format($stats['tidak_di_bpjs']),            'bg' => '#7c3aed'],
        ['label' => '❓ NIK Tdk di OMEO',     'value' => number_format($stats['nik_tidak_ditemukan']),      'bg' => '#0891b2'],
        ['label' => '⬜ Tanpa NIK di PDF',    'value' => number_format($stats['no_nik']),                   'bg' => '#6b7280'],
    ];
    @endphp
    <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-8 gap-4 mb-6">
        @foreach($statCards as $card)
        <div class="rounded-xl p-4 text-center" style="background-color:{{ $card['bg'] }}">
            <div class="text-2xl font-bold text-white">{{ $card['value'] }}</div>
            <div class="text-xs mt-1 text-white" style="opacity:.85">{{ $card['label'] }}</div>
        </div>
        @endforeach
    </div>

    {{-- SUMMARY IURAN --}}
    <div class="bg-white rounded-xl shadow p-4 mb-6">
        <div class="grid grid-cols-3 gap-4 text-center divide-x divide-gray-100">
            <div>
                <div class="text-xs text-gray-500 mb-1">Total Tagihan BPJS</div>
                <div class="text-xl font-bold text-gray-800">
                    Rp {{ number_format($stats['total_iuran_bpjs'], 0, ',', '.') }}
                </div>
            </div>
            <div>
                <div class="text-xs text-gray-500 mb-1">Total Iuran Payroll</div>
                <div class="text-xl font-bold text-gray-800">
                    Rp {{ number_format($stats['total_iuran_pay'], 0, ',', '.') }}
                </div>
            </div>
            <div>
                <div class="text-xs text-gray-500 mb-1">Selisih</div>
                <div class="text-xl font-bold {{ $stats['total_selisih'] == 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $stats['total_selisih'] >= 0 ? '+' : '' }}Rp
                    {{ number_format(abs($stats['total_selisih']), 0, ',', '.') }}
                </div>
            </div>
        </div>
    </div>

    {{-- TABEL --}}
    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table style="width:100%;font-size:0.75rem;border-collapse:collapse;">
                <thead>
                    <tr style="background-color:#1F2937;">
                        @foreach(['No','NIK','Nama (BPJS)','Nama (Payroll)','No.Komp','Outlet','NPP BPJS','Iuran BPJS','Iuran Payroll','Selisih','Status','Match Via'] as $h)
                        <th style="color:#FFFFFF;padding:10px 12px;text-align:left;white-space:nowrap;font-size:11px;font-weight:600;border-bottom:1px solid #374151;">
                            {{ $h }}
                        </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($result as $i => $row)
                    @php
                    $bgRow = match($row->status_rekon) {
                        'cocok'               => '#F0FDF4',
                        'minor'               => '#FEFCE8',
                        'selisih'             => '#FEF2F2',
                        'tidak_di_payroll'    => '#FFF7ED',
                        'tidak_di_bpjs'       => '#FAF5FF',
                        'nik_tidak_ditemukan' => '#ECFEFF',
                        'no_nik'              => '#F9FAFB',
                        default               => $i % 2 === 0 ? '#FFFFFF' : '#F9FAFB',
                    };
                    [$statusText, $statusColor] = match($row->status_rekon) {
                        'cocok'               => ['✅ Cocok',              '#16a34a'],
                        'minor'               => ['⚠️ Minor',              '#ca8a04'],
                        'selisih'             => ['🔴 Selisih',            '#dc2626'],
                        'tidak_di_payroll'    => ['📄 Tdk di Payroll',     '#ea580c'],
                        'tidak_di_bpjs'       => ['📋 Tdk di BPJS',       '#7c3aed'],
                        'nik_tidak_ditemukan' => ['❓ NIK tdk ada di OMEO','#0891b2'],
                        'no_nik'              => ['⬜ Tanpa NIK',           '#9ca3af'],
                        default               => [$row->status_rekon,      '#6b7280'],
                    };
                    $selisihColor = $row->selisih == 0 ? '#16a34a' : ($row->selisih > 0 ? '#dc2626' : '#ca8a04');
                    @endphp
                    <tr style="background-color:{{ $bgRow }};border-bottom:1px solid #E5E7EB;">
                        <td style="padding:8px 12px;color:#9ca3af;">{{ $i + 1 }}</td>
                        <td style="padding:8px 12px;font-family:monospace;font-size:11px;">{{ $row->nik }}</td>
                        <td style="padding:8px 12px;font-size:12px;white-space:nowrap;">{{ $row->nama_bpjs }}</td>
                        <td style="padding:8px 12px;font-size:12px;color:#374151;white-space:nowrap;">{{ $row->nama_payroll }}</td>
                        <td style="padding:8px 12px;font-family:monospace;font-size:11px;">{{ $row->no_komp }}</td>
                        <td style="padding:8px 12px;font-size:11px;color:#6b7280;white-space:nowrap;">
                            {{ \Illuminate\Support\Str::limit($row->outlet, 25) }}
                        </td>
                        <td style="padding:8px 12px;font-size:11px;font-family:monospace;">{{ $row->pt_bpjs }}</td>
                        <td style="padding:8px 12px;text-align:right;font-size:12px;white-space:nowrap;">
                            {{ $row->iuran_bpjs ? 'Rp ' . number_format($row->iuran_bpjs, 0, ',', '.') : '—' }}
                        </td>
                        <td style="padding:8px 12px;text-align:right;font-size:12px;white-space:nowrap;">
                            {{ $row->iuran_payroll ? 'Rp ' . number_format($row->iuran_payroll, 0, ',', '.') : '—' }}
                        </td>
                        <td style="padding:8px 12px;text-align:right;font-weight:600;font-size:12px;white-space:nowrap;color:{{ $selisihColor }}">
                            @if($row->selisih == 0)
                                —
                            @else
                                {{ $row->selisih > 0 ? '+' : '' }}Rp {{ number_format(abs($row->selisih), 0, ',', '.') }}
                            @endif
                        </td>
                        <td style="padding:8px 12px;white-space:nowrap;">
                            <span style="font-size:11px;font-weight:600;color:{{ $statusColor }}">{{ $statusText }}</span>
                        </td>
                        <td style="padding:8px 12px;white-space:nowrap;">
                            @if(($row->match_via ?? 'not_matched') === 'nik')
                                <span style="font-size:10px;font-weight:700;background:#dcfce7;color:#16a34a;padding:2px 7px;border-radius:999px;">via NIK</span>
                            @else
                                <span style="font-size:11px;color:#d1d5db;">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="12" style="padding:48px;text-align:center;color:#9ca3af;font-size:0.875rem;">
                            Tidak ada data untuk periode ini
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @endif
</div>
@endsection
