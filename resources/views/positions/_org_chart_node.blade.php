{{--
    Node tree org-chart infografis: 1 posisi + rekursif ke anak-anaknya.
    $position: model Position (perlu ->id, ->name, ->level, ->employees_count)
    $childrenByParent: array<int, Collection<Position>> hasil buildPositionTree()
    $representatives: array<int, array{employee_id,full_name,photo_url,is_manual}>
    $deptName: string, dikirim ke openPanel() saat kartu diklik
--}}
@php
    $rep      = $representatives[$position->id] ?? null;
    $children = $childrenByParent[$position->id] ?? collect();
    $extra    = max(0, ($position->employees_count ?? 0) - ($rep ? 1 : 0));
    $initial  = $rep ? mb_strtoupper(mb_substr($rep['full_name'], 0, 1)) : '?';
@endphp
<div class="oc-node">
    <button type="button" class="oc-box"
        @click="openPanel({
            id: {{ $position->id }},
            name: '{{ addslashes($position->name) }}',
            level: {{ $position->level ?? 0 }},
            count: {{ $position->employees_count }},
            dept: '{{ addslashes($deptName) }}',
            description: @js($position->description ?? '')
        })">
        <span class="oc-badge-level">L{{ $position->level ?? '-' }}</span>
        <div class="oc-photo">
            @if($rep && $rep['photo_url'])
                <img src="{{ $rep['photo_url'] }}" alt="{{ $rep['full_name'] }}">
            @else
                <span>{{ $initial }}</span>
            @endif
        </div>
        <div class="oc-ribbon">{{ $rep['full_name'] ?? 'Belum ada karyawan' }}</div>
        <div class="oc-posname">{{ $position->name }}</div>
        @if($extra > 0)
        <span class="oc-badge-more">+{{ $extra }} lainnya</span>
        @endif
    </button>

    @if($children->isNotEmpty())
    <div class="oc-stem"></div>
    <div class="oc-children">
        @foreach($children as $child)
        <div class="oc-branch">
            @include('positions._org_chart_node', [
                'position'         => $child,
                'childrenByParent' => $childrenByParent,
                'representatives'  => $representatives,
                'deptName'         => $deptName,
            ])
        </div>
        @endforeach
    </div>
    @endif
</div>
