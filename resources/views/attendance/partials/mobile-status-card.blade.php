@php
    $toneClasses = [
        'emerald' => 'border-emerald-200 bg-emerald-50',
        'amber' => 'border-amber-200 bg-amber-50',
        'blue' => 'border-blue-200 bg-blue-50',
        'slate' => 'border-slate-200 bg-slate-50',
    ];
    $badgeClasses = [
        'emerald' => 'bg-emerald-100 text-emerald-700',
        'amber' => 'bg-amber-100 text-amber-700',
        'blue' => 'bg-blue-100 text-blue-700',
        'slate' => 'bg-slate-200 text-slate-700',
    ];
@endphp

<div @if(!empty($extraAttributes) && is_array($extraAttributes)) @foreach($extraAttributes as $attributeKey => $attributeValue) {{ $attributeKey }}="{{ $attributeValue }}" @endforeach @endif class="rounded-[1.6rem] border p-4 shadow-sm {{ $toneClasses[$tone ?? 'slate'] ?? $toneClasses['slate'] }}">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            @if(!empty($eyebrow))
                <div class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">{{ $eyebrow }}</div>
            @endif
            <h3 class="mt-2 text-base font-black text-slate-900">{{ $title ?? '-' }}</h3>
            @if(!empty($subtitle))
                <p class="mt-2 text-xs leading-5 text-slate-600">{{ $subtitle }}</p>
            @endif
        </div>
        @if(!empty($badge))
            <span class="shrink-0 rounded-full px-3 py-1 text-[11px] font-semibold {{ $badgeClasses[$badgeTone ?? 'slate'] ?? $badgeClasses['slate'] }}">{{ $badge }}</span>
        @endif
    </div>
</div>
