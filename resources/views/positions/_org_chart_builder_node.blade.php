{{--
    Node tree Builder Struktur: bisa tipe department/brand/outlet/employee
    dalam SATU tree yang sama (org_chart_nodes, polimorfik).
    $node: model OrgChartNode (relasi department/outlet/employee sudah eager-loaded)
    $childrenByParent: array<int, Collection<OrgChartNode>> hasil grouping di controller
--}}
@php
    $children  = $childrenByParent[$node->id] ?? collect();
    $isLeader  = $node->effective_is_leader;
    $employee  = $node->employee;
    $photoUrl  = $employee?->user?->applicantProfile?->photo_path
        ? asset('storage/' . $employee->user->applicantProfile->photo_path)
        : null;
    $jsNode = [
        'id' => $node->id,
        'node_type' => $node->node_type,
        'employee_id' => $node->employee_id,
        'department_id' => $node->department_id,
        'outlet_id' => $node->outlet_id,
        'brand_name' => $node->brand_name,
        'is_leader_override' => $node->is_leader_override,
    ];
@endphp

@if($node->node_type === 'employee' && ! $isLeader)
{{-- ANGGOTA: lingkaran kecil, daun (tidak bercabang) --}}
<div class="oc-node">
    <div class="oc-anggota-wrap"
         :class="{ 'oc-box-dragover': dragOverId === {{ $node->id }} }"
         :draggable="editMode ? 'true' : 'false'"
         @dragstart="dragStart({{ $node->id }}, 'node')"
         @dragover.prevent="dragOver({{ $node->id }})"
         @dragleave="dragLeave({{ $node->id }})"
         @drop.prevent="drop({{ $node->id }})"
         @click="editMode && nodeEditor.openEdit(@js($jsNode))">
        <div class="oc-photo oc-photo-sm">
            @if($photoUrl)
                <img src="{{ $photoUrl }}" alt="{{ $employee->full_name }}">
            @else
                <span>{{ mb_strtoupper(mb_substr($employee->full_name ?? '?', 0, 1)) }}</span>
            @endif
        </div>
        <div class="oc-anggota-name">{{ $employee->full_name ?? '(karyawan terhapus)' }}</div>
    </div>
</div>
@else
{{-- LEADER / DEPARTMENT / BRAND / OUTLET: kotak besar, bisa bercabang --}}
<div class="oc-node">
    <template x-if="editMode">
        <div style="display:flex; flex-direction:column; align-items:center;">
            <button type="button" class="oc-add-superior-btn"
                    @click="nodeEditor.openInsertAbove(@js(['id' => $node->id, 'parent_id' => $node->parent_id]))">
                + Tambah Atasan
            </button>
            <div class="oc-stem"></div>
        </div>
    </template>
    <div class="oc-box"
         :class="{ 'oc-box-dragover': dragOverId === {{ $node->id }} }"
         :draggable="editMode ? 'true' : 'false'"
         @dragstart="dragStart({{ $node->id }}, 'node')"
         @dragover.prevent="dragOver({{ $node->id }})"
         @dragleave="dragLeave({{ $node->id }})"
         @drop.prevent="drop({{ $node->id }})">

        <button type="button" x-show="editMode" x-cloak class="oc-node-edit-btn"
                @click.stop="nodeEditor.openEdit(@js($jsNode))" title="Edit node ini">
            ✏️
        </button>

        @switch($node->node_type)
            @case('department')
                <div class="oc-orgunit-icon">🏢</div>
                <div class="oc-ribbon">{{ $node->department->name ?? '(departemen terhapus)' }}</div>
                <div class="oc-posname">Departemen</div>
                @break
            @case('brand')
                <div class="oc-orgunit-icon">🏬</div>
                <div class="oc-ribbon">{{ $node->brand_name }}</div>
                <div class="oc-posname">Brand</div>
                @break
            @case('outlet')
                <div class="oc-orgunit-icon">📍</div>
                <div class="oc-ribbon">{{ $node->outlet->name ?? '(outlet terhapus)' }}</div>
                <div class="oc-posname">Outlet</div>
                @break
            @case('employee')
                <div class="oc-photo">
                    @if($photoUrl)
                        <img src="{{ $photoUrl }}" alt="{{ $employee->full_name }}">
                    @else
                        <span>{{ mb_strtoupper(mb_substr($employee->full_name ?? '?', 0, 1)) }}</span>
                    @endif
                </div>
                <div class="oc-ribbon">{{ $employee->full_name ?? '(karyawan terhapus)' }}</div>
                <div class="oc-leader-tag">👑 Team Leader</div>
                <div class="oc-posname">{{ $employee->position->name ?? 'Belum ada jabatan' }}</div>
                @if($employee?->department)
                <div class="oc-deptname">{{ $employee->department->name }}</div>
                @endif
                @break
        @endswitch
    </div>

    <div class="oc-stem"></div>
    @if($node->node_type === 'employee')
    <button type="button" x-show="editMode" x-cloak class="oc-add-child-btn"
            @click="nodeEditor.openCreate({{ $node->id }}, { nodeType: 'employee', leaderStatus: 'anggota', lockType: true })">
        + Tambah Anggota
    </button>
    @else
    <button type="button" x-show="editMode" x-cloak class="oc-add-child-btn"
            @click="nodeEditor.openCreate({{ $node->id }})">
        + Tambah Node
    </button>
    @endif

    @if($children->isNotEmpty())
    <div class="oc-children">
        @foreach($children as $child)
        <div class="oc-branch">
            @include('positions._org_chart_builder_node', [
                'node'             => $child,
                'childrenByParent' => $childrenByParent,
            ])
        </div>
        @endforeach
    </div>
    @endif
</div>
@endif
