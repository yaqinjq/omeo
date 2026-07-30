@extends('layouts.app')
@section('title', 'Preview Import Karyawan')
@section('content')

<div class="max-w-full mx-auto py-8 px-4">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Preview Import Karyawan</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                File: <strong>{{ $session->source_file_name }}</strong> —
                {{ $session->total_rows }} baris data terdeteksi
            </p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('hr.employee-import.index') }}"
               class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100
                      rounded-lg hover:bg-gray-200 transition-colors">
                Batalkan
            </a>
            @php $newCount = count($analysis['new']); $updateCount = count($analysis['update']); @endphp
            <form method="POST" action="{{ route('hr.employee-import.confirm', $session) }}"
                  onsubmit="return confirm('Konfirmasi import {{ $newCount }} karyawan baru dan {{ $updateCount }} update ke database?')">
                @csrf
                <button type="submit"
                        class="px-6 py-2 text-sm font-medium text-white bg-emerald-600
                               rounded-lg hover:bg-emerald-700 transition-colors">
                    Konfirmasi Import
                    ({{ $newCount }} baru + {{ $updateCount }} update)
                </button>
            </form>
        </div>
    </div>

    {{-- Stats summary --}}
    <div class="grid grid-cols-4 gap-4 mb-6">
        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 text-center">
            <p class="text-3xl font-bold text-emerald-700">{{ count($analysis['new']) }}</p>
            <p class="text-sm text-emerald-600 mt-0.5">Karyawan Baru</p>
        </div>
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-center">
            <p class="text-3xl font-bold text-blue-700">{{ count($analysis['update']) }}</p>
            <p class="text-sm text-blue-600 mt-0.5">Akan Diupdate</p>
        </div>
        <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 text-center">
            <p class="text-3xl font-bold text-gray-600">{{ count($analysis['skip']) }}</p>
            <p class="text-sm text-gray-500 mt-0.5">Dilewati</p>
        </div>
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-center">
            <p class="text-3xl font-bold text-red-700">{{ count($analysis['errors']) }}</p>
            <p class="text-sm text-red-600 mt-0.5">Error</p>
        </div>
    </div>

    {{-- NEW --}}
    @if(count($analysis['new']) > 0)
    <div class="mb-6">
        <h2 class="text-base font-semibold text-emerald-700 mb-3">
            Karyawan Baru: {{ count($analysis['new']) }} orang
        </h2>
        <div class="bg-white rounded-xl border border-gray-200 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-emerald-50 text-xs font-medium text-gray-500 uppercase">
                    <tr>
                        <th class="px-4 py-2 text-left">Nama</th>
                        <th class="px-4 py-2 text-left">Email</th>
                        <th class="px-4 py-2 text-left">NIK Internal</th>
                        <th class="px-4 py-2 text-left">Outlet/Dept</th>
                        <th class="px-4 py-2 text-left">Posisi</th>
                        <th class="px-4 py-2 text-left">Status</th>
                        <th class="px-4 py-2 text-left">Join Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($analysis['new'] as $r)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 font-medium">{{ $r['full_name'] }}</td>
                            <td class="px-4 py-2 text-gray-500 text-xs">{{ $r['email'] ?: '-' }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ $r['employee_number'] ?: '-' }}</td>
                            <td class="px-4 py-2 text-gray-500 text-xs">{{ $r['department'] ?: '-' }}</td>
                            <td class="px-4 py-2 text-gray-500 text-xs">{{ $r['posisi'] ?: '-' }}</td>
                            <td class="px-4 py-2">
                                <span class="inline-block px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-700">
                                    {{ $r['status_kerja'] ?: '-' }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-gray-500 text-xs">{{ $r['join_date'] ?: '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- UPDATE --}}
    @if(count($analysis['update']) > 0)
    <div class="mb-6">
        <h2 class="text-base font-semibold text-blue-700 mb-3">
            Akan Diupdate: {{ count($analysis['update']) }} orang
            <span class="text-xs font-normal text-gray-500">(hanya kolom kosong yang diisi)</span>
        </h2>
        <div class="bg-white rounded-xl border border-gray-200 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-blue-50 text-xs font-medium text-gray-500 uppercase">
                    <tr>
                        <th class="px-4 py-2 text-left">Nama (File)</th>
                        <th class="px-4 py-2 text-left">Nama (OMEO)</th>
                        <th class="px-4 py-2 text-left">Email</th>
                        <th class="px-4 py-2 text-left">NIK Internal</th>
                        <th class="px-4 py-2 text-left">Outlet/Dept</th>
                        <th class="px-4 py-2 text-left">Posisi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($analysis['update'] as $r)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 font-medium">{{ $r['full_name'] }}</td>
                            <td class="px-4 py-2 text-gray-500 text-xs">{{ $r['existing_name'] ?? '-' }}</td>
                            <td class="px-4 py-2 text-gray-500 text-xs">{{ $r['email'] ?: '-' }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ $r['employee_number'] ?: '-' }}</td>
                            <td class="px-4 py-2 text-gray-500 text-xs">{{ $r['department'] ?: '-' }}</td>
                            <td class="px-4 py-2 text-gray-500 text-xs">{{ $r['posisi'] ?: '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ERRORS --}}
    @if(count($analysis['errors']) > 0)
    <div class="mb-6">
        <h2 class="text-base font-semibold text-red-700 mb-3">
            Error: {{ count($analysis['errors']) }} baris
        </h2>
        <div class="bg-white rounded-xl border border-red-200 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-red-50 text-xs font-medium text-gray-500 uppercase">
                    <tr>
                        <th class="px-4 py-2 text-left">No Urut</th>
                        <th class="px-4 py-2 text-left">Nama</th>
                        <th class="px-4 py-2 text-left">Alasan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($analysis['errors'] as $r)
                        <tr>
                            <td class="px-4 py-2 text-gray-500">{{ $r['no_urut'] ?? '-' }}</td>
                            <td class="px-4 py-2 font-medium text-red-700">{{ $r['full_name'] ?: '(kosong)' }}</td>
                            <td class="px-4 py-2 text-gray-600 text-xs">{{ $r['reason'] ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Bottom CTA --}}
    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
        <a href="{{ route('hr.employee-import.index') }}"
           class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100
                  rounded-lg hover:bg-gray-200 transition-colors">
            Batalkan
        </a>
        <form method="POST" action="{{ route('hr.employee-import.confirm', $session) }}"
              onsubmit="return confirm('Konfirmasi import {{ $newCount }} karyawan baru dan {{ $updateCount }} update?')">
            @csrf
            <button type="submit"
                    class="px-6 py-2 text-sm font-medium text-white bg-emerald-600
                           rounded-lg hover:bg-emerald-700 transition-colors">
                Konfirmasi Import
                ({{ $newCount }} baru + {{ $updateCount }} update)
            </button>
        </form>
    </div>

</div>
@endsection
