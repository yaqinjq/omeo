@extends('layouts.app')
@section('title', 'Walk-In Interview')
@section('content')
<div class="p-6">

    {{-- HEADER --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Walk-In Interview</h1>
            <p class="text-sm text-gray-500 mt-1">Manajemen event rekrutmen walk-in dan referral link HRD</p>
        </div>
        <a href="{{ route('walkin.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white"
           style="background:#6366f1">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M12 5v14M5 12h14"/>
            </svg>
            Buat Event Baru
        </a>
    </div>

    @if(session('success'))
    <div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium text-white" style="background:#22c55e">
        {{ session('success') }}
    </div>
    @endif

    {{-- TABLE --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0">
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Event</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Tanggal</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Lokasi</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Status</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Pendaftar</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($events as $event)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3">
                        <div class="font-semibold text-gray-800">{{ $event->title }}</div>
                        @if($event->target_positions)
                        <div class="flex flex-wrap gap-1 mt-1">
                            @foreach(array_slice($event->target_positions, 0, 3) as $pos)
                            <span class="inline-block px-2 py-0.5 rounded-full text-xs" style="background:#f1f5f9;color:#475569">{{ $pos }}</span>
                            @endforeach
                            @if(count($event->target_positions) > 3)
                            <span class="inline-block px-2 py-0.5 rounded-full text-xs" style="background:#f1f5f9;color:#94a3b8">+{{ count($event->target_positions) - 3 }} lagi</span>
                            @endif
                        </div>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-700">
                        @if($event->event_start_datetime)
                            {{ $event->event_start_datetime->format('d M Y') }}
                            <div class="text-xs text-gray-400 mt-0.5">
                                {{ $event->event_start_datetime->format('H:i') }}
                                @if($event->event_end_datetime)– {{ $event->event_end_datetime->format('H:i') }}@endif WIB
                            </div>
                        @else
                            <span class="text-gray-400">-</span>
                        @endif
                        @if($event->registration_open_until)
                        <div class="text-xs text-gray-400 mt-0.5">
                            Tutup daftar: {{ $event->registration_open_until->format('d M H:i') }}
                        </div>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-700">{{ $event->location ?: '-' }}</td>
                    <td class="px-4 py-3">
                        @php $cs = $event->computed_status; @endphp
                        <span class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold"
                              style="background:{{ $event->status_bg }};color:{{ $event->status_color }}">
                            {{ match($cs) {
                                'draft'     => 'Draft',
                                'published' => 'Terbuka',
                                'ongoing'   => '🟢 Berlangsung',
                                'completed' => '✅ Selesai',
                                default     => $cs,
                            } }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="font-bold text-gray-800">{{ $event->candidates_count }}</span>
                        <span class="text-gray-400 text-xs"> kandidat</span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('walkin.dashboard', $event->id) }}"
                               class="px-3 py-1.5 rounded-lg text-xs font-semibold text-white"
                               style="background:#6366f1">Dashboard</a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-12 text-center text-gray-400">
                        Belum ada event Walk-In. <a href="{{ route('walkin.create') }}" class="text-indigo-600 font-semibold">Buat sekarang</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>

        @if($events->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $events->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
