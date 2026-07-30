@extends('layouts.app')
@section('title', 'Export Kehadiran Saya')
@section('content')
<div class="max-w-4xl mx-auto py-8 px-4">

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Export Rekap Kehadiran</h1>
        <p class="text-gray-500 mt-1">Pilih rentang tanggal (maks. 31 hari) untuk mengunduh bukti kehadiran.</p>
    </div>

    @if($employee)
    <div class="bg-indigo-50 border border-indigo-200 rounded-xl px-5 py-4 mb-6 flex items-start gap-4">
        <div class="w-10 h-10 rounded-full bg-indigo-600 flex items-center justify-center flex-shrink-0">
            <span class="text-white text-sm font-bold">{{ strtoupper(substr((string) ($employee->full_name ?? '?'), 0, 1)) }}</span>
        </div>
        <div>
            <p class="font-semibold text-indigo-900">{{ $employee->full_name }}</p>
            <p class="text-sm text-indigo-700">
                {{ $employee->jabatan ?? '-' }}
                @if($employee->nokom ?? $employee->employee_number ?? null)
                    · No. {{ $employee->nokom ?? $employee->employee_number }}
                @endif
            </p>
        </div>
    </div>
    @else
    <div class="bg-amber-50 border border-amber-200 rounded-xl px-5 py-4 mb-6 text-amber-800 text-sm">
        ⚠️ Data karyawan belum terhubung ke akun ini. Hubungi HRD untuk menghubungkan data.
    </div>
    @endif

    {{-- Form Filter --}}
    <form method="GET" action="{{ route('employee.attendance.export.index') }}"
          class="bg-white border border-gray-200 rounded-xl p-5 mb-6">
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Tanggal Mulai <span class="text-red-500">*</span>
                </label>
                <input type="date" name="date_from"
                       value="{{ $dateFrom ?? '' }}"
                       max="{{ date('Y-m-d') }}"
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none"
                       onchange="limitMaxDate(this)">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Tanggal Akhir <span class="text-red-500">*</span>
                </label>
                <input type="date" name="date_to" id="date_to"
                       value="{{ $dateTo ?? '' }}"
                       max="{{ date('Y-m-d') }}"
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none">
            </div>
            <button type="submit"
                    class="px-5 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">
                Tampilkan
            </button>
            @if($dateFrom && $dateTo)
            <a href="{{ route('employee.attendance.export.index') }}"
               class="px-4 py-2 text-sm text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                Reset
            </a>
            @endif
        </div>
        <p class="text-xs text-gray-400 mt-2">* Maksimal periode 31 hari</p>
    </form>

    @if($dateFrom && $dateTo)
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden mb-5">
        <div class="px-5 py-3 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-700">
                Preview —
                {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }}
                s/d
                {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}
            </h2>
            <span class="text-xs text-gray-400">{{ count($attendanceData) }} sesi</span>
        </div>

        @if(count($attendanceData) > 0)
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">No</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Tanggal</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Hari</th>
                        <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Jam Masuk</th>
                        <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Jam Keluar</th>
                        <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Durasi</th>
                        <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($attendanceData as $i => $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 text-gray-400">{{ $i + 1 }}</td>
                        <td class="px-4 py-2 font-medium text-gray-800">{{ $row['tanggal'] }}</td>
                        <td class="px-4 py-2 text-gray-600">{{ $row['hari'] }}</td>
                        <td class="px-4 py-2 text-center font-mono text-gray-700">{{ $row['jam_masuk'] ?? '—' }}</td>
                        <td class="px-4 py-2 text-center font-mono text-gray-700">{{ $row['jam_keluar'] ?? '—' }}</td>
                        <td class="px-4 py-2 text-center text-gray-500">{{ $row['durasi'] }}</td>
                        <td class="px-4 py-2 text-center">
                            @php
                                $badge = match($row['raw_status'] ?? '') {
                                    'present'    => 'bg-green-100 text-green-700',
                                    'late'       => 'bg-yellow-100 text-yellow-700',
                                    'incomplete' => 'bg-orange-100 text-orange-700',
                                    'absent'     => 'bg-red-100 text-red-700',
                                    default      => 'bg-gray-100 text-gray-600',
                                };
                            @endphp
                            <span class="inline-block px-2 py-0.5 text-xs rounded-full {{ $badge }}">
                                {{ $row['status'] }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="px-5 py-10 text-center text-gray-400 text-sm">
            Tidak ada data kehadiran pada periode ini.
        </div>
        @endif
    </div>

    <form method="POST" action="{{ route('employee.attendance.export.download') }}">
        @csrf
        <input type="hidden" name="date_from" value="{{ $dateFrom }}">
        <input type="hidden" name="date_to" value="{{ $dateTo }}">
        <button type="submit"
                class="inline-flex items-center gap-2 px-6 py-3 text-sm font-semibold text-white
                       bg-green-600 rounded-xl hover:bg-green-700 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Download Excel (.xlsx)
        </button>
        <p class="text-xs text-gray-400 mt-2">File akan diunduh otomatis ke perangkat Anda.</p>
    </form>
    @endif

</div>

<script>
function limitMaxDate(fromInput) {
    var fromVal = fromInput.value;
    if (!fromVal) return;
    var from = new Date(fromVal);
    var max  = new Date(from);
    max.setDate(max.getDate() + 30);
    var maxStr = max.toISOString().split('T')[0];
    var toInput = document.getElementById('date_to');
    toInput.min = fromVal;
    toInput.max = maxStr;
    if (toInput.value && toInput.value > maxStr) toInput.value = maxStr;
    if (toInput.value && toInput.value < fromVal) toInput.value = fromVal;
}
(function () {
    var fi = document.querySelector('input[name="date_from"]');
    if (fi && fi.value) limitMaxDate(fi);
})();
</script>
@endsection
