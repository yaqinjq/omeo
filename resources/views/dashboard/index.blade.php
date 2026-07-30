@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-50 dark:bg-gray-900 pb-12 font-sans">
    <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <h2 class="font-bold text-xl text-gray-800 dark:text-white leading-tight">
                HR Dashboard (Admin)
            </h2>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 md:py-8 space-y-6">
        <div class="grid grid-cols-2 gap-3 md:hidden">
            <div class="bg-white p-4 rounded-xl border">
                <div class="text-[11px] font-bold uppercase text-gray-400">Total Pelamar</div>
                <div class="mt-1 text-2xl font-extrabold text-gray-900">{{ $candidatesApplied ?? 0 }}</div>
            </div>
            <div class="bg-white p-4 rounded-xl border">
                <div class="text-[11px] font-bold uppercase text-gray-400">Diterima</div>
                <div class="mt-1 text-2xl font-extrabold text-gray-900">{{ $candidatesAccepted ?? 0 }}</div>
            </div>
            <div class="bg-white p-4 rounded-xl border">
                <div class="text-[11px] font-bold uppercase text-gray-400">Probation Due</div>
                <div class="mt-1 text-2xl font-extrabold text-gray-900">{{ $totalUpcomingProbation ?? 0 }}</div>
            </div>
            <div class="bg-white p-4 rounded-xl border">
                <div class="text-[11px] font-bold uppercase text-gray-400">Total Karyawan</div>
                <div class="mt-1 text-2xl font-extrabold text-gray-900">{{ $totalEmployees ?? 0 }}</div>
            </div>
        </div>

        <div class="md:hidden bg-white border rounded-xl p-4 space-y-3">
            <div class="text-sm font-semibold text-gray-800">Aksi Cepat</div>
            <div class="grid grid-cols-2 gap-2">
                <a href="{{ route('hrd.applicants.index') }}" class="rounded-lg border px-3 py-2 text-xs text-center">Review Kandidat</a>
                <a href="{{ route('employees.index') }}" class="rounded-lg border px-3 py-2 text-xs text-center">Input Karyawan</a>
            </div>
            <details>
                <summary class="cursor-pointer text-xs font-semibold text-blue-600">Lihat detail dashboard</summary>
                <div class="mt-2 text-xs text-gray-600 space-y-1">
                    <div>Reminder unread: <strong>{{ $hrUnreadCount ?? 0 }}</strong></div>
                    <div>NOKOM menunggu setup: <strong>{{ count($nokomSetupEmployees ?? []) }}</strong></div>
                </div>
            </details>
        </div>

        <div class="hidden md:grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Total Pelamar</div>
                <div class="flex items-end justify-between">
                    <span class="text-3xl font-extrabold text-gray-900 dark:text-white">{{ $candidatesApplied ?? 0 }}</span>
                    <span class="p-2 bg-blue-50 text-blue-600 rounded-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    </span>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Diterima</div>
                <div class="flex items-end justify-between">
                    <span class="text-3xl font-extrabold text-gray-900 dark:text-white">{{ $candidatesAccepted ?? 0 }}</span>
                    <span class="p-2 bg-green-50 text-green-600 rounded-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </span>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Probation Due</div>
                <div class="flex items-end justify-between">
                    <span class="text-3xl font-extrabold text-gray-900 dark:text-white">{{ $totalUpcomingProbation ?? 0 }}</span>
                    <span class="p-2 bg-amber-50 text-amber-600 rounded-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </span>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Total Karyawan</div>
                <div class="flex items-end justify-between">
                    <span class="text-3xl font-extrabold text-gray-900 dark:text-white">{{ $totalEmployees ?? 0 }}</span>
                    <span class="p-2 bg-purple-50 text-purple-600 rounded-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    </span>
                </div>
            </div>
        </div>

        <div class="hidden md:grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center bg-gray-50 dark:bg-gray-700/50">
                    <h3 class="font-bold text-gray-800 dark:text-white">Habis Masa Percobaan (14 Hari)</h3>
                    <a href="#" class="text-xs font-bold text-blue-600 hover:underline">Lihat Semua</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-gray-500 uppercase bg-white dark:bg-gray-800 border-b">
                            <tr>
                                <th class="px-6 py-3">Nama Karyawan</th>
                                <th class="px-6 py-3">Tgl Berakhir</th>
                                <th class="px-6 py-3">Sisa Hari</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse($probationDue ?? [] as $emp)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-3 font-medium text-gray-900 dark:text-white">{{ $emp->full_name }}</td>
                                <td class="px-6 py-3">{{ \Carbon\Carbon::parse($emp->probation_end_date)->format('d M Y') }}</td>
                                <td class="px-6 py-3 text-amber-600 font-bold">
                                    {{ \Carbon\Carbon::now()->diffInDays($emp->probation_end_date, false) }} Hari
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="px-6 py-4 text-center text-gray-500">Tidak ada data probation mendekati expired.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                    <h3 class="font-bold text-gray-800 dark:text-white">Aksi Cepat</h3>
                </div>
                <div class="p-6 grid grid-cols-2 gap-4">
                    <a href="{{ route('hrd.applicants.index') }}" class="p-4 border rounded-xl hover:bg-blue-50 hover:border-blue-200 transition group text-center">
                        <div class="w-10 h-10 mx-auto bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mb-2 group-hover:scale-110 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        </div>
                        <span class="text-sm font-bold text-gray-700">Review Kandidat</span>
                    </a>
                    <a href="{{ route('employees.index') }}" class="p-4 border rounded-xl hover:bg-green-50 hover:border-green-200 transition group text-center">
                        <div class="w-10 h-10 mx-auto bg-green-100 text-green-600 rounded-full flex items-center justify-center mb-2 group-hover:scale-110 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                        </div>
                        <span class="text-sm font-bold text-gray-700">Input Karyawan</span>
                    </a>
                </div>
            </div>
        </div>

        <div class="hidden md:block mt-8 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50 flex items-center justify-between">
                <h3 class="font-bold text-gray-800 dark:text-white">Reminder HRD</h3>
                <span class="text-xs font-bold text-amber-600">Unread: {{ $hrUnreadCount ?? 0 }}</span>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($hrReminders ?? [] as $item)
                    <div class="px-6 py-4">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <div class="text-sm font-bold text-gray-900 dark:text-white">{{ $item->title }}</div>
                                <div class="text-sm text-gray-600 dark:text-gray-300">{{ $item->body }}</div>
                            </div>
                            <div class="text-xs text-gray-500 whitespace-nowrap">{{ optional($item->due_date)->format('d M Y') ?: '-' }}</div>
                        </div>
                        @if(data_get($item->meta, 'route'))
                            <a href="{{ data_get($item->meta, 'route') }}" class="inline-block mt-2 text-xs font-semibold text-blue-600 hover:underline">Buka detail</a>
                        @endif
                    </div>
                @empty
                    <div class="px-6 py-4 text-sm text-gray-500">Belum ada reminder HRD.</div>
                @endforelse
            </div>
        </div>

        <div class="hidden md:block mt-8 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-amber-200 dark:border-amber-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-amber-100 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 flex items-center justify-between">
                <h3 class="font-bold text-amber-800 dark:text-amber-200">Karyawan Probation Siap Dibuatkan NOKOM</h3>
                <span class="text-xs font-bold text-amber-700">{{ count($nokomSetupEmployees ?? []) }} item</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-white dark:bg-gray-800 border-b text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-6 py-3 text-left">Nama</th>
                            <th class="px-6 py-3 text-left">Email</th>
                            <th class="px-6 py-3 text-left">NIK</th>
                            <th class="px-6 py-3 text-left">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($nokomSetupEmployees ?? [] as $emp)
                            <tr>
                                <td class="px-6 py-3 font-semibold text-gray-900 dark:text-white">{{ $emp->full_name }}</td>
                                <td class="px-6 py-3 text-gray-700 dark:text-gray-200">{{ $emp->email }}</td>
                                <td class="px-6 py-3 text-gray-700 dark:text-gray-200">{{ $emp->nik }}</td>
                                <td class="px-6 py-3">
                                    <a href="{{ $emp->edit_url }}" class="inline-flex items-center rounded-lg bg-amber-600 px-3 py-1.5 text-white text-xs font-semibold hover:bg-amber-700">Isi Data Kepegawaian</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-gray-500">Tidak ada karyawan probation yang menunggu setup NOKOM.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
