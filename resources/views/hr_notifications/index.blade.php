@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-6 space-y-5">

    {{-- ── Header ── --}}
    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap;">
        <div>
            <h1 style="font-size:22px; font-weight:700; color:#1e293b; margin:0 0 4px;">
                Notifikasi
                @if($totalUnread > 0)
                <span style="background:#EF4444; color:white; padding:2px 10px;
                             border-radius:99px; font-size:13px; margin-left:8px;
                             vertical-align:middle;">
                    {{ $totalUnread }}
                </span>
                @endif
            </h1>
            <p style="font-size:13px; color:#64748B; margin:0;">
                Reminder, approval, dan tindak lanjut penting.
            </p>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <a href="{{ route('hr-notifications.index', array_merge(request()->except(['unread']), $unreadOnly ? [] : ['unread'=>1])) }}"
               style="{{ $unreadOnly
                         ? 'background:#F59E0B;color:white;'
                         : 'background:#F1F5F9;color:#475569;' }}
                       padding:7px 14px; border-radius:8px; font-size:12px;
                       font-weight:600; text-decoration:none; display:inline-flex;
                       align-items:center; gap:5px;">
                🔴 Belum Dibaca{{ $unreadOnly ? ' (aktif)' : '' }}
            </a>
            @if(Route::has('hr-notifications.readAll'))
            <form method="POST" action="{{ route('hr-notifications.readAll') }}">
                @csrf
                <button type="submit"
                        style="background:#F1F5F9; color:#475569; border:none;
                               padding:7px 14px; border-radius:8px; font-size:12px;
                               font-weight:600; cursor:pointer;">
                    ✓ Tandai Semua Dibaca
                </button>
            </form>
            @endif
        </div>
    </div>

    @if(!empty($moduleWarning))
    <div style="background:#FEF3C7; border:1px solid #FCD34D; border-radius:10px;
                padding:12px 16px; font-size:13px; color:#92400E;">
        {{ $moduleWarning }}
    </div>
    @endif

    {{-- ── Tab navigasi per tipe ── --}}
    <div style="display:flex; flex-wrap:wrap; gap:8px;">

        {{-- Tab Semua --}}
        <a href="{{ route('hr-notifications.index', array_merge(request()->except(['type','page']), ['type'=>'all'])) }}"
           style="{{ $activeType === 'all'
                     ? 'background:#1D4ED8;color:white;'
                     : 'background:#F1F5F9;color:#475569;' }}
                   padding:8px 16px; border-radius:8px; font-size:13px;
                   font-weight:600; text-decoration:none; display:inline-flex;
                   align-items:center; gap:6px;">
            🔔 Semua
            @if($totalUnread > 0)
            <span style="{{ $activeType === 'all' ? 'background:rgba(255,255,255,0.25);' : 'background:#E2E8F0;' }}
                          padding:2px 8px; border-radius:99px; font-size:11px; font-weight:700;">
                {{ $totalUnread }}
            </span>
            @endif
        </a>

        {{-- Tab per tipe --}}
        @foreach($availableTypes as $type)
        @php
            $tLabel   = $typeLabels[$type] ?? $type;
            $tIcon    = $typeIcons[$type]  ?? '🔔';
            $tCount   = $typeCounts[$type] ?? 0;
            $tActive  = $activeType === $type;
        @endphp
        <a href="{{ route('hr-notifications.index', array_merge(request()->except(['type','page']), ['type'=>$type])) }}"
           style="{{ $tActive
                     ? 'background:#1D4ED8;color:white;'
                     : 'background:#F1F5F9;color:#475569;' }}
                   padding:8px 16px; border-radius:8px; font-size:13px;
                   font-weight:600; text-decoration:none; display:inline-flex;
                   align-items:center; gap:6px;">
            {{ $tIcon }} {{ $tLabel }}
            @if($tCount > 0)
            <span style="{{ $tActive ? 'background:rgba(255,255,255,0.25);' : 'background:#E2E8F0;' }}
                          padding:2px 8px; border-radius:99px; font-size:11px; font-weight:700;">
                {{ $tCount }}
            </span>
            @endif
        </a>
        @endforeach
    </div>

    {{-- ── Filter tanggal (collapsible) ── --}}
    <div x-data="{ showDateFilter: {{ ($dateFrom || $dateTo) ? 'true' : 'false' }} }">
        <button @click="showDateFilter = !showDateFilter"
                type="button"
                style="background:none; border:1px solid #E2E8F0; border-radius:8px;
                       padding:6px 14px; font-size:12px; color:#64748B; cursor:pointer;
                       display:inline-flex; align-items:center; gap:6px;">
            📅 Filter Tanggal
            <span x-text="showDateFilter ? '▲' : '▼'" style="font-size:10px;"></span>
            @if($dateFrom || $dateTo)
            <span style="background:#DBEAFE; color:#1D4ED8; padding:2px 8px;
                         border-radius:99px; font-size:11px; font-weight:600;">
                Aktif
            </span>
            @endif
        </button>

        <div x-show="showDateFilter" x-transition style="margin-top:10px;">
            <form method="GET" action="{{ route('hr-notifications.index') }}"
                  style="display:flex; flex-wrap:wrap; gap:8px; align-items:flex-end;">
                <input type="hidden" name="type" value="{{ $activeType }}">
                @if($unreadOnly)
                <input type="hidden" name="unread" value="1">
                @endif
                <div>
                    <label style="display:block; font-size:11px; color:#64748B; margin-bottom:4px;">
                        Dari Tanggal
                    </label>
                    <input type="date" name="date_from" value="{{ $dateFrom }}"
                           style="height:34px; border:1px solid #D1D5DB; border-radius:8px;
                                  padding:0 10px; font-size:12px; outline:none;">
                </div>
                <div>
                    <label style="display:block; font-size:11px; color:#64748B; margin-bottom:4px;">
                        Hingga Tanggal
                    </label>
                    <input type="date" name="date_to" value="{{ $dateTo }}"
                           style="height:34px; border:1px solid #D1D5DB; border-radius:8px;
                                  padding:0 10px; font-size:12px; outline:none;">
                </div>
                <button type="submit"
                        style="height:34px; padding:0 14px; background:#1D4ED8; color:white;
                               border:none; border-radius:8px; font-size:12px;
                               font-weight:600; cursor:pointer;">
                    Terapkan
                </button>
                @if($dateFrom || $dateTo)
                <a href="{{ route('hr-notifications.index', array_merge(
                               array_diff_key(request()->query(), ['date_from'=>'','date_to'=>'']),
                               ['type'=>$activeType]
                           )) }}"
                   style="height:34px; padding:0 14px; background:#F1F5F9; color:#64748B;
                          border-radius:8px; font-size:12px; font-weight:600;
                          text-decoration:none; display:inline-flex; align-items:center;">
                    Reset
                </a>
                @endif
            </form>
        </div>
    </div>

    {{-- ── List notifikasi ── --}}
    @forelse($notifications as $notif)
    @php
        $nIcon  = $typeIcons[$notif->type]  ?? '🔔';
        $nLabel = $typeLabels[$notif->type] ?? $notif->type;
        $nColor = match($notif->type) {
            'appraisal_invitation', 'appraisal_reminder',
            'appraisal_probation_reminder'  => 'background:#FEF9C3;color:#854D0E;',
            'daily_worker_contract'         => 'background:#DBEAFE;color:#1D4ED8;',
            'profile_change_request',
            'probation_official_profile'    => 'background:#F3E8FF;color:#7C3AED;',
            'probation_reminder'            => 'background:#DCFCE7;color:#166534;',
            'candidate_status_accepted',
            'candidate_status_shortlisted'  => 'background:#DCFCE7;color:#166534;',
            default                         => 'background:#F1F5F9;color:#64748B;',
        };
    @endphp
    <div style="background:white; border-radius:12px;
                border:1px solid #E2E8F0;
                {{ !$notif->is_read ? 'border-left:4px solid #1D4ED8;' : '' }}
                padding:16px; display:flex; gap:14px; align-items:flex-start;">

        <div style="font-size:24px; line-height:1; padding-top:2px; flex-shrink:0;">
            {{ $nIcon }}
        </div>

        <div style="flex:1; min-width:0;">
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px; flex-wrap:wrap;">
                <span style="{{ $nColor }} padding:2px 10px; border-radius:99px;
                              font-size:11px; font-weight:600;">
                    {{ $nLabel }}
                </span>
                <span style="font-size:11px; color:#94A3B8;">
                    {{ $notif->created_at->diffForHumans() }}
                </span>
                @if(!$notif->is_read)
                <span style="background:#1D4ED8; width:8px; height:8px;
                             border-radius:50%; display:inline-block;"></span>
                @endif
            </div>

            <div style="font-weight:600; color:#1e293b; font-size:14px; margin-bottom:4px;">
                {{ $notif->title }}
            </div>

            @if($notif->body)
            <div style="font-size:13px; color:#475569; margin-bottom:10px; line-height:1.5;">
                {{ $notif->body }}
            </div>
            @endif

            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                @if(isset($notif->meta['route']))
                <a href="{{ $notif->meta['route'] }}"
                   style="background:#1D4ED8; color:white; padding:6px 14px;
                          border-radius:8px; font-size:12px; font-weight:600;
                          text-decoration:none;">
                    Buka Detail →
                </a>
                @endif

                @if(!$notif->is_read)
                <form method="POST" action="{{ route('hr-notifications.read', $notif->id) }}">
                    @csrf
                    <button type="submit"
                            style="background:#F1F5F9; color:#64748B; border:none;
                                   padding:6px 14px; border-radius:8px; font-size:12px;
                                   font-weight:600; cursor:pointer;">
                        ✓ Tandai Dibaca
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>
    @empty
    <div style="text-align:center; padding:48px 16px; color:#94A3B8;">
        <div style="font-size:48px; margin-bottom:12px;">🔔</div>
        <div style="font-size:15px; font-weight:600; color:#64748B; margin-bottom:6px;">
            Tidak ada notifikasi
        </div>
        <div style="font-size:13px;">
            @if($activeType !== 'all')
                Belum ada notifikasi tipe "{{ $typeLabels[$activeType] ?? $activeType }}"
            @elseif($unreadOnly)
                Semua notifikasi sudah dibaca 🎉
            @else
                Belum ada notifikasi apapun
            @endif
        </div>
    </div>
    @endforelse

    {{-- ── Pagination ── --}}
    @if($notifications instanceof \Illuminate\Pagination\LengthAwarePaginator && $notifications->hasPages())
    <div>{{ $notifications->links() }}</div>
    @endif

</div>
@endsection
