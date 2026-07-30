@extends('layouts.app')
@section('title', 'Dashboard Walk-In — ' . $event->title)
@section('content')
<div class="p-6" x-data="{ tab: 'all', copied: false, showLinkBox: {{ $myLinkUrl ? 'true' : 'false' }} }">

    {{-- HEADER --}}
    <div class="flex flex-col gap-1 mb-6">
        <a href="{{ route('walkin.index') }}" class="text-xs text-indigo-500 hover:underline flex items-center gap-1">
            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
            Kembali ke Daftar Event
        </a>
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">{{ $event->title }}</h1>
                <p class="text-sm text-gray-500">
                    @if($event->event_start_datetime)
                        {{ $event->event_start_datetime->format('d M Y, H:i') }} WIB
                    @endif
                    @if($event->location) &middot; {{ $event->location }}@endif
                    &middot;
                    <span class="font-semibold" style="color:{{ $event->status_color }}">{{ $event->status_label }}</span>
                </p>
            </div>
        </div>
    </div>

    @if(session('success'))
    <div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium text-white" style="background:#22c55e">
        {{ session('success') }}
    </div>
    @endif

    {{-- REFERRAL LINK PANEL --}}
    <div class="mb-6 bg-white rounded-2xl border border-indigo-100 shadow-sm p-5">
        <div class="flex items-center justify-between mb-3">
            <div class="font-semibold text-gray-800 flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                    <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                </svg>
                Link Referral Saya
            </div>

            @if(! $myLinkUrl)
            <form method="POST" action="{{ route('walkin.my-link', $event->id) }}">
                @csrf
                <button type="submit"
                        class="px-4 py-2 rounded-xl text-sm font-semibold text-white"
                        style="background:#6366f1">
                    Generate Link Saya
                </button>
            </form>
            @endif
        </div>

        @if($myLinkUrl)
        <div class="flex items-center gap-3 mb-2">
            <div class="flex-1 rounded-xl border border-indigo-200 px-3 py-2 text-sm font-mono text-gray-700 select-all overflow-x-auto hidden sm:block"
                 style="background:#f8f9ff">
                {{ $myLinkUrl }}
            </div>
            <button type="button"
                    @click="navigator.clipboard.writeText('{{ $myLinkUrl }}').then(() => { copied = true; setTimeout(() => copied = false, 2500) })"
                    class="flex-shrink-0 px-4 py-2 rounded-xl text-sm font-semibold transition-all"
                    :style="copied ? 'background:#22c55e;color:white' : 'background:#6366f1;color:white'">
                <span x-show="!copied">Copy Link</span>
                <span x-show="copied">Tersalin ✓</span>
            </button>
        </div>

        {{-- FIX 8: Tampilan link yang mudah dicopy di HP --}}
        <div class="mt-2 p-3 rounded-xl" style="background:#f0f4ff; border:1px solid #c7d2fe">
            <p class="text-xs text-gray-500 mb-1 font-semibold">Link Anda (untuk dikirim ke kandidat):</p>
            <p class="text-sm font-mono break-all" style="color:#4338ca">{{ $myLinkUrl }}</p>
            <p class="text-xs text-gray-400 mt-1">Kandidat buka link ini di browser HP — tanpa perlu login atau install apapun</p>
        </div>

        @if($myLink)
        <p class="text-xs text-gray-400 mt-2">Kode: <span class="font-mono font-semibold text-indigo-600">{{ $myLink->referral_code }}</span> &middot; Total via link ini: <span class="font-semibold">{{ $myLink->total_registrations }}</span></p>
        @endif
        @else
        <p class="text-sm text-gray-400">Klik tombol di atas untuk generate link referral unik Anda.</p>
        @endif
    </div>

    {{-- STATS CARDS --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3 mb-6">
        @php
        $statCards = [
            ['label' => 'Total Daftar', 'value' => $stats['total'],            'color' => '#6366f1', 'bg' => '#eef2ff'],
            ['label' => 'Hadir',        'value' => $stats['checked_in'],        'color' => '#0ea5e9', 'bg' => '#f0f9ff'],
            ['label' => 'Tidak Hadir', 'value' => $stats['no_show'],           'color' => '#94a3b8', 'bg' => '#f8fafc'],
            ['label' => 'Lolos Interview','value'=> $stats['interview_passed'], 'color' => '#10b981', 'bg' => '#f0fdf4'],
            ['label' => 'Lolos Tes',   'value' => $stats['test_passed'],       'color' => '#f59e0b', 'bg' => '#fffbeb'],
            ['label' => 'Diterima',    'value' => $stats['accepted'],          'color' => '#22c55e', 'bg' => '#f0fdf4'],
            ['label' => 'Cadangan',    'value' => $stats['reserved'],          'color' => '#a855f7', 'bg' => '#faf5ff'],
        ];
        @endphp
        @foreach($statCards as $card)
        <div class="rounded-2xl p-4 text-center" style="background:{{ $card['bg'] }}; border:1px solid {{ $card['color'] }}30">
            <div class="text-2xl font-bold" style="color:{{ $card['color'] }}">{{ $card['value'] }}</div>
            <div class="text-xs font-medium mt-0.5" style="color:{{ $card['color'] }}">{{ $card['label'] }}</div>
        </div>
        @endforeach
    </div>

    {{-- ═══ SECTION: AUTO-SORT BY POSISI ═══ --}}
    @if($positionQueues->isNotEmpty())
    <div class="mt-6 mb-4">
        <h3 style="font-size:16px; font-weight:700; color:#1e293b; margin-bottom:16px;">
            📋 Antrian per Posisi
        </h3>

        <div x-data="{ activeTab: '{{ collect($positionQueues)->keys()->first() }}' }">

            {{-- Tab headers --}}
            <div style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:16px;">
                @foreach($positionQueues as $position => $posCandidates)
                @php
                    $checkedInCount = $posCandidates->where('registration_status','checked_in')->count();
                    $totalCount     = $posCandidates->count();
                @endphp
                <button @click="activeTab = '{{ $position }}'"
                        :style="activeTab === '{{ $position }}'
                            ? 'background:#7C3AED; color:white;'
                            : 'background:#F1F5F9; color:#475569;'"
                        style="padding:8px 16px; border:none; border-radius:8px; font-size:13px;
                               font-weight:600; cursor:pointer; display:flex; align-items:center;
                               gap:6px; transition:all 0.15s;">
                    {{ $position }}
                    <span :style="activeTab === '{{ $position }}'
                              ? 'background:rgba(255,255,255,0.25);'
                              : 'background:#E2E8F0;'"
                          style="padding:2px 8px; border-radius:99px; font-size:11px; font-weight:700;">
                        {{ $checkedInCount }}/{{ $totalCount }}
                    </span>
                </button>
                @endforeach
            </div>

            {{-- Tab content per posisi --}}
            @foreach($positionQueues as $position => $posCandidates)
            @php $summary = $positionSummary[$position]; @endphp
            <div x-show="activeTab === '{{ $position }}'" x-transition>

                {{-- Info bar --}}
                <div style="display:flex; gap:12px; margin-bottom:12px; flex-wrap:wrap;">
                    <span style="background:#DCFCE7; color:#166534; padding:4px 12px;
                                 border-radius:99px; font-size:12px; font-weight:600;">
                        ✓ Hadir: {{ $summary['checked_in'] }}
                    </span>
                    <span style="background:#FEF9C3; color:#854D0E; padding:4px 12px;
                                 border-radius:99px; font-size:12px; font-weight:600;">
                        ⏳ Menunggu: {{ $summary['pending'] }}
                    </span>
                    <span style="background:#FEE2E2; color:#991B1B; padding:4px 12px;
                                 border-radius:99px; font-size:12px; font-weight:600;">
                        ✗ No-show: {{ $summary['no_show'] }}
                    </span>
                </div>

                {{-- Tabel kandidat posisi ini --}}
                <div style="background:white; border-radius:12px; border:1px solid #E2E8F0; overflow:hidden;">
                    <table style="width:100%; border-collapse:collapse; font-size:13px;">
                        <thead>
                            <tr style="background:#7C3AED; color:white;">
                                <th style="padding:10px 12px; text-align:center; width:60px;">No. Antrian</th>
                                <th style="padding:10px 12px; text-align:left;">Nama Kandidat</th>
                                <th style="padding:10px 12px; text-align:left;">No. HP</th>
                                <th style="padding:10px 12px; text-align:center;">Waktu Check-in</th>
                                <th style="padding:10px 12px; text-align:center;">Status</th>
                                <th style="padding:10px 12px; text-align:center;">Interview</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($posCandidates as $c)
                            <tr style="border-bottom:1px solid #F1F5F9; {{ $loop->even ? 'background:#FAFAFA;' : '' }}">
                                <td style="padding:10px 12px; text-align:center;">
                                    @if($c->queue_number)
                                        <span style="background:#7C3AED; color:white; width:32px; height:32px;
                                                     border-radius:50%; display:inline-flex; align-items:center;
                                                     justify-content:center; font-weight:700; font-size:13px;">
                                            {{ $c->queue_number }}
                                        </span>
                                    @else
                                        <span style="color:#94A3B8; font-size:12px;">—</span>
                                    @endif
                                </td>
                                <td style="padding:10px 12px; font-weight:600; color:#1e293b;">
                                    {{ $c->candidate_name }}
                                    <div style="font-size:11px; color:#94A3B8; font-weight:400;">
                                        #{{ $c->registration_number }}
                                    </div>
                                </td>
                                <td style="padding:10px 12px; color:#475569;">{{ $c->candidate_phone }}</td>
                                <td style="padding:10px 12px; text-align:center; color:#475569;">
                                    @if($c->check_in_time)
                                        {{ \Carbon\Carbon::parse($c->check_in_time)->format('H:i') }}
                                    @else
                                        <span style="color:#CBD5E1;">Belum</span>
                                    @endif
                                </td>
                                <td style="padding:10px 12px; text-align:center;">
                                    @php
                                        $statusColor = match($c->registration_status) {
                                            'checked_in' => 'background:#DCFCE7;color:#166534;',
                                            'no_show'    => 'background:#FEE2E2;color:#991B1B;',
                                            'withdrawn'  => 'background:#F1F5F9;color:#64748B;',
                                            default      => 'background:#FEF9C3;color:#854D0E;',
                                        };
                                        $statusLabel = match($c->registration_status) {
                                            'checked_in' => 'Hadir',
                                            'no_show'    => 'No-show',
                                            'withdrawn'  => 'Batal',
                                            default      => 'Menunggu',
                                        };
                                    @endphp
                                    <span style="{{ $statusColor }} padding:3px 10px; border-radius:99px;
                                                font-size:11px; font-weight:600;">
                                        {{ $statusLabel }}
                                    </span>
                                </td>
                                <td style="padding:10px 12px; text-align:center;">
                                    @php
                                        $ivColor = match($c->interview_status) {
                                            'passed'  => 'background:#DCFCE7;color:#166534;',
                                            'failed'  => 'background:#FEE2E2;color:#991B1B;',
                                            'skipped' => 'background:#F1F5F9;color:#64748B;',
                                            default   => 'background:#F1F5F9;color:#94A3B8;',
                                        };
                                        $ivLabel = match($c->interview_status) {
                                            'passed'  => 'Lulus',
                                            'failed'  => 'Tidak Lulus',
                                            'skipped' => 'Skip',
                                            default   => 'Pending',
                                        };
                                    @endphp
                                    <span style="{{ $ivColor }} padding:3px 10px; border-radius:99px;
                                                font-size:11px; font-weight:600;">
                                        {{ $ivLabel }}
                                    </span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" style="padding:24px; text-align:center; color:#94A3B8;">
                                    Belum ada kandidat untuk posisi ini.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                        @if($posCandidates->where('registration_status','checked_in')->count() > 0)
                        <tfoot>
                            <tr style="background:#F8F4FF;">
                                <td colspan="2" style="padding:10px 12px; font-weight:700; color:#7C3AED;">
                                    Total hadir: {{ $posCandidates->where('registration_status','checked_in')->count() }} orang
                                </td>
                                <td colspan="4"></td>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>
            </div>
            @endforeach

        </div>{{-- end x-data --}}
    </div>
    @endif
    {{-- ═══ END SECTION AUTO-SORT ═══ --}}

    {{-- TAB NAV --}}
    <div class="flex gap-1 mb-4 border-b border-gray-200">
        <button @click="tab = 'all'"
                :class="tab === 'all' ? 'border-b-2 border-indigo-500 text-indigo-600 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                class="px-4 py-2 text-sm transition-colors">
            Semua Kandidat
            <span class="ml-1 text-xs rounded-full px-2 py-0.5" style="background:#eef2ff;color:#6366f1">{{ $allCandidates->count() }}</span>
        </button>
        <button @click="tab = 'mine'"
                :class="tab === 'mine' ? 'border-b-2 border-indigo-500 text-indigo-600 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                class="px-4 py-2 text-sm transition-colors">
            Kandidat Saya
            <span class="ml-1 text-xs rounded-full px-2 py-0.5" style="background:#eef2ff;color:#6366f1">{{ $myCandidates->count() }}</span>
        </button>
    </div>

    {{-- TABEL KANDIDAT --}}
    @php
    $tableHeaders = ['No. Antrian', 'Nama', 'Posisi', 'Via (Referrer)', 'Status Daftar', 'Check-In', 'Aksi'];
    @endphp

    {{-- Semua Kandidat --}}
    <div x-show="tab === 'all'" x-transition>
        @include('recruitment.walkin._candidate_table', ['candidates' => $allCandidates, 'event' => $event])
    </div>

    {{-- Kandidat Saya --}}
    <div x-show="tab === 'mine'" x-transition>
        @include('recruitment.walkin._candidate_table', ['candidates' => $myCandidates, 'event' => $event])
    </div>

</div>
@endsection
