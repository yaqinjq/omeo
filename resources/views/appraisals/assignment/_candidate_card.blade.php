@php
    $probEnd  = $emp->computed_probation_end_date;
    $daysLeft = $probEnd ? (int) now()->diffInDays($probEnd, false) : null;
    $parts    = explode(' ', trim($emp->full_name));
    $initials = strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1));
    $jsName   = addslashes($emp->full_name);
    $jsEnd    = $probEnd?->format('Y-m-d') ?? '';

    $colorMap = [
        'emerald' => ['avatar_bg' => 'bg-emerald-100', 'avatar_text' => 'text-emerald-700', 'hover' => 'hover:border-emerald-300'],
        'amber'   => ['avatar_bg' => 'bg-amber-100',   'avatar_text' => 'text-amber-700',   'hover' => 'hover:border-amber-300'],
        'red'     => ['avatar_bg' => 'bg-red-100',     'avatar_text' => 'text-red-700',     'hover' => 'hover:border-red-300'],
    ];
    $c = $colorMap[$color] ?? $colorMap['emerald'];

    if ($daysLeft === null) {
        $uLabel = 'Tidak diketahui';
    } elseif ($daysLeft < 0) {
        $uLabel = 'Lewat ' . abs($daysLeft) . 'h';
    } elseif ($daysLeft === 0) {
        $uLabel = 'Hari ini';
    } else {
        $uLabel = $daysLeft . 'h lagi';
    }
@endphp

<button type="button"
    @click="selectEmployee({ id: {{ $emp->id }}, full_name: '{{ $jsName }}', employee_number: '{{ $emp->employee_number }}', display_probation_end: '{{ $jsEnd }}' })"
    :class="isInBatch({{ $emp->id }})
        ? 'ring-2 ring-emerald-400 bg-emerald-50 border-emerald-200'
        : (currentEmployee && currentEmployee.id === {{ $emp->id }}
            ? 'ring-2 ring-indigo-500 bg-indigo-50 border-indigo-200'
            : 'bg-white {{ $c['hover'] }}')"
    class="flex w-full items-center gap-2 rounded-lg border border-slate-200 p-2 text-left transition">
    <div class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full text-[10px] font-bold {{ $c['avatar_bg'] }} {{ $c['avatar_text'] }}">
        {{ $initials }}
    </div>
    <div class="min-w-0 flex-1">
        <p class="truncate text-xs font-semibold leading-tight text-slate-800">{{ $emp->full_name }}</p>
        <p class="text-[10px] text-slate-400">{{ $probEnd?->format('d M Y') ?? '-' }}</p>
        <p class="text-[10px] font-medium {{ $c['avatar_text'] }}">{{ $uLabel }}</p>
    </div>
</button>
