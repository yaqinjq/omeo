{{--
    Node tree org-chart per-orang: 1 karyawan + rekursif ke bawahannya lewat
    manager_id. Beda konsep dari _org_chart_node.blade.php (yang per-Posisi)
    — di sini kotak = 1 orang sungguhan, bukan wakil dari sebuah posisi.
    $employee: model Employee (perlu ->id, ->full_name, ->position, ->user.applicantProfile eager-loaded)
    $childrenByParent: array<int, Collection<Employee>> hasil buildEmployeeTree()
--}}
@php
    $photoUrl = $employee->user?->applicantProfile?->photo_path
        ? asset('storage/' . $employee->user->applicantProfile->photo_path)
        : null;
    $initial  = mb_strtoupper(mb_substr($employee->full_name, 0, 1));
    $children = $childrenByParent[$employee->id] ?? collect();
@endphp
<div class="oc-node">
    <button type="button" class="oc-box"
        :class="{ 'oc-box-dragover': dragOverId === {{ $employee->id }}, 'oc-box-editable': editMode }"
        :draggable="editMode ? 'true' : 'false'"
        @dragstart="dragStart({{ $employee->id }}, 'employee')"
        @dragover.prevent="dragOver({{ $employee->id }})"
        @dragleave="dragLeave({{ $employee->id }})"
        @drop.prevent="drop({{ $employee->id }})">
        <div class="oc-photo">
            @if($photoUrl)
                <img src="{{ $photoUrl }}" alt="{{ $employee->full_name }}">
            @else
                <span>{{ $initial }}</span>
            @endif
        </div>
        <div class="oc-ribbon">{{ $employee->full_name }}</div>
        <div class="oc-posname">{{ $employee->position->name ?? 'Belum ada jabatan' }}</div>
        @if($employee->outlet)
        <div class="oc-outlet-label">🏬 {{ $employee->outlet->name }}</div>
        @endif
        @if($children->isNotEmpty())
        <div class="oc-leader-tag">👨‍💼 Atasan ({{ $children->count() }} bawahan)</div>
        @endif
    </button>

    @if($children->isNotEmpty())
    <div class="oc-stem"></div>
    <div class="oc-children">
        @foreach($children as $child)
        <div class="oc-branch">
            @include('positions._org_chart_person_node', [
                'employee'         => $child,
                'childrenByParent' => $childrenByParent,
            ])
        </div>
        @endforeach
    </div>
    @endif
</div>
