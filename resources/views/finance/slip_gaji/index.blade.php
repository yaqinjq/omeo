@extends('layouts.app')
@section('title', 'Slip Gaji')
@section('content')

@php
    $bulanID = [
        '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April',
        '05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus',
        '09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember',
    ];
@endphp

<div class="max-w-6xl mx-auto px-4 py-6 space-y-5">

    <div>
        <h1 class="text-2xl font-semibold text-gray-900">Slip Gaji Karyawan</h1>
        <p class="text-sm text-gray-500 mt-0.5">Pilih periode, cari karyawan, lalu unduh slip gaji dalam format PDF.</p>
    </div>

    <form method="GET" action="{{ route('finance.slip-gaji.index') }}"
          class="flex flex-wrap items-end gap-3 bg-white rounded-xl border p-4">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Periode</label>
            <select name="periode" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                @forelse($availablePeriods as $p)
                    @php [$py, $pm] = explode('-', $p); @endphp
                    <option value="{{ $p }}" {{ $periode === $p ? 'selected' : '' }}>
                        {{ ($bulanID[$pm] ?? $pm) . ' ' . $py }}
                    </option>
                @empty
                    <option value="">Belum ada data payroll</option>
                @endforelse
            </select>
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Outlet (opsional)</label>
            <select name="outlet_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Semua Outlet</option>
                @foreach($outlets as $outlet)
                    <option value="{{ $outlet->id }}" {{ (string) $outletId === (string) $outlet->id ? 'selected' : '' }}>
                        {{ $outlet->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Cari Karyawan</label>
            <input type="text" name="search" value="{{ $search }}" placeholder="Nama / No. Komp"
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-56">
        </div>

        <button type="submit" class="px-4 py-2 bg-gray-900 text-white text-sm rounded-lg">Tampilkan</button>
    </form>

    <div class="bg-white rounded-xl border overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3 text-center w-10">No</th>
                    <th class="px-4 py-3 text-left">Nama Karyawan</th>
                    <th class="px-4 py-3 text-left">No. Komp</th>
                    <th class="px-4 py-3 text-left">Jabatan</th>
                    <th class="px-4 py-3 text-center">Slip Gaji</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($employees as $i => $emp)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-center text-gray-400 text-xs">
                            {{ $paginator->firstItem() + $i }}
                        </td>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $emp->full_name }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $emp->nokom ?? $emp->employee_number ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $emp->jabatan ?? '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            <a href="{{ route('finance.slip-gaji.pdf', ['periode' => $periode, 'employeeId' => $emp->id, 'outlet_id' => $outletId]) }}"
                               target="_blank"
                               style="color:#1D4ED8;text-decoration:none;"
                               class="inline-flex items-center gap-1 text-sm font-medium">
                                Preview
                            </a>
                            <span class="text-gray-300 mx-1">|</span>
                            <a href="{{ route('finance.slip-gaji.pdf', ['periode' => $periode, 'employeeId' => $emp->id, 'outlet_id' => $outletId, 'download' => 1]) }}"
                               style="color:#059669;text-decoration:none;"
                               class="inline-flex items-center gap-1 text-sm font-medium">
                                Unduh
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-14 text-center text-gray-400">
                            @if($availablePeriods->isEmpty())
                                Belum ada data payroll yang diimport.
                            @else
                                Tidak ada karyawan yang cocok untuk periode/filter ini.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if($paginator->hasPages())
            <div class="px-4 py-3 border-t">{{ $paginator->links() }}</div>
        @endif
    </div>
</div>
@endsection
