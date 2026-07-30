@extends('layouts.app')
@section('title', $event->title . ' — Detail Kandidat')
@section('content')
<div class="p-6">

    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('walkin.index') }}" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M15 18l-6-6 6-6"/>
            </svg>
        </a>
        <div>
            <h1 class="text-xl font-bold text-gray-800">{{ $event->title }}</h1>
            <p class="text-sm text-gray-500">{{ $event->event_date?->format('d M Y') }} @if($event->location)· {{ $event->location }}@endif</p>
        </div>
        <a href="{{ route('walkin.dashboard', $event->id) }}"
           class="ml-auto px-4 py-2 rounded-xl text-sm font-semibold text-white"
           style="background:#6366f1">
            Buka Dashboard
        </a>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0">
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">No. Antrian</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Nama</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Posisi</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Via</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Status</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Check-In</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($candidates as $c)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono font-semibold text-indigo-700">{{ $c->registration_number }}</td>
                        <td class="px-4 py-3">
                            <div class="font-semibold text-gray-800">{{ $c->candidate_name }}</div>
                            <div class="text-xs text-gray-400">{{ $c->candidate_phone }}</div>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $c->applied_position ?: '-' }}</td>
                        <td class="px-4 py-3 text-xs text-gray-500">{{ $c->referrer?->name ?: '-' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">
                                {{ $c->registration_status_label }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">{{ $c->check_in_time?->format('H:i') ?: '-' }}</td>
                        <td class="px-4 py-3 text-right">
                            @if($c->registration_status === 'registered')
                            <form method="POST" action="{{ route('walkin.checkin', $c->id) }}">
                                @csrf
                                <button type="submit"
                                        class="px-3 py-1.5 rounded-lg text-xs font-semibold text-white"
                                        style="background:#0ea5e9">Check-In</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-gray-400">Belum ada kandidat.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($candidates->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $candidates->links() }}</div>
        @endif
    </div>

</div>
@endsection
