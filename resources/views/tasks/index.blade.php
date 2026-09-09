@extends('layouts.app')
@section('title', 'Task & Project')
@section('content')
<div class="p-4" x-data="taskBoard()" x-init="initDragula()">

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
        <div>
            <h1 style="font-size:22px; font-weight:800; color:#1e293b; margin:0;">📋 Task & Project</h1>
            <p style="color:#64748B; font-size:13px; margin:4px 0 0 0;">
                Drag kartu antar kolom untuk ubah status — klik kartu untuk lihat/tambah breakdown tugas tanpa batas
            </p>
        </div>
        <div style="display:flex; gap:10px;">
            <button type="button" @click="projectsPanel = true"
                    style="background:#F3E8FF; color:#7C3AED; border:1px solid #DDD6FE; padding:9px 16px; border-radius:10px; font-size:13px; font-weight:600; cursor:pointer;">
                📁 Kelola Project
            </button>
            <button type="button" @click="openCreate()"
                    style="background:#7C3AED; color:white; border:none; padding:9px 18px; border-radius:10px; font-size:13px; font-weight:600; cursor:pointer;">
                + Tambah Tugas
            </button>
        </div>
    </div>

    @if(session('success'))
    <div style="background:#F0FDF4; border:1px solid #BBF7D0; color:#166534; padding:10px 14px; border-radius:10px; font-size:13px; margin-bottom:16px;">✓ {{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="background:#FEF2F2; border:1px solid #FECACA; color:#991B1B; padding:10px 14px; border-radius:10px; font-size:13px; margin-bottom:16px;">✗ {{ session('error') }}</div>
    @endif

    {{-- Filter --}}
    <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:18px; align-items:center;">
        <span style="font-size:12px; font-weight:600; color:#94A3B8;">Filter:</span>
        <select name="project_id" onchange="this.form.submit()" style="border:1.5px solid #E2E8F0; border-radius:8px; padding:7px 12px; font-size:13px;">
            <option value="">Semua Project</option>
            @foreach($projects as $p)
            <option value="{{ $p->id }}" @selected(($filters['project_id'] ?? null) == $p->id)>{{ $p->name }}</option>
            @endforeach
        </select>
        <select name="employee_id" onchange="this.form.submit()" style="border:1.5px solid #E2E8F0; border-radius:8px; padding:7px 12px; font-size:13px; max-width:220px;">
            <option value="">Semua Karyawan</option>
            @foreach($employeeOptions as $e)
            <option value="{{ $e->id }}" @selected(($filters['employee_id'] ?? null) == $e->id)>{{ $e->full_name }}</option>
            @endforeach
        </select>
        @if(($filters['project_id'] ?? null) || ($filters['employee_id'] ?? null) || ($filters['position_id'] ?? null))
        <a href="{{ route('tasks.index') }}" style="padding:7px 14px; background:#F1F5F9; color:#64748B; border-radius:8px; font-size:13px; font-weight:600; text-decoration:none;">Reset</a>
        @endif
    </form>

    {{-- Board --}}
    <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px;">
        @foreach([
            ['key' => 'open', 'label' => '📥 Open', 'color' => '#1D4ED8', 'bg' => '#EFF6FF'],
            ['key' => 'in_progress', 'label' => '🔧 In Progress', 'color' => '#B45309', 'bg' => '#FFFBEB'],
            ['key' => 'done', 'label' => '✅ Done', 'color' => '#166534', 'bg' => '#F0FDF4'],
        ] as $col)
        <div style="background:#FAFAFA; border-radius:14px; border:1.5px solid #E2E8F0; overflow:hidden; display:flex; flex-direction:column;">
            <div style="background:{{ $col['bg'] }}; color:{{ $col['color'] }}; padding:12px 16px; font-weight:700; font-size:13px; display:flex; justify-content:space-between;">
                <span>{{ $col['label'] }}</span>
                <span id="count-{{ $col['key'] }}">{{ $board[$col['key']]->count() }}</span>
            </div>
            <div class="oc-drop-col" data-status="{{ $col['key'] }}" id="col-{{ $col['key'] }}"
                 style="padding:10px; display:flex; flex-direction:column; gap:10px; min-height:200px; flex:1;">
                @forelse($board[$col['key']] as $task)
                <div class="oc-task-card" data-task-id="{{ $task->id }}"
                     style="background:white; border:1.5px solid #E2E8F0; border-radius:12px; padding:12px; cursor:grab;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                        <div style="font-weight:700; color:#1e293b; font-size:13px; margin-bottom:4px; flex:1;"
                             @click="openEditById({{ $task->id }})">
                            {{ $task->title }}
                        </div>
                        <div x-data="{ open: false }" style="position:relative;">
                            <button type="button" @click="open = !open" @click.outside="open = false"
                                    style="background:none; border:none; color:#94A3B8; font-size:14px; cursor:pointer; padding:0 4px;">⋯</button>
                            <div x-show="open" x-cloak style="position:absolute; right:0; top:20px; background:white; border:1px solid #E2E8F0; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.1); z-index:10; min-width:110px;">
                                <button type="button" @click="open=false; openEditById({{ $task->id }})"
                                        style="display:block; width:100%; text-align:left; background:none; border:none; padding:8px 12px; font-size:12px; color:#1D4ED8; cursor:pointer;">✏️ Edit</button>
                                <form method="POST" action="{{ route('tasks.destroy', $task) }}" onsubmit="return confirm('Hapus tugas ini{{ $task->subtasks_count > 0 ? ' beserta '.$task->subtasks_count.' sub-tugasnya' : '' }}?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" style="display:block; width:100%; text-align:left; background:none; border:none; padding:8px 12px; font-size:12px; color:#DC2626; cursor:pointer;">🗑️ Hapus</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    @if($task->project)
                    <div style="font-size:10.5px; color:#7C3AED; font-weight:600; margin-bottom:6px;">📁 {{ $task->project->name }}</div>
                    @endif
                    <div style="display:flex; align-items:center; gap:6px; margin-bottom:8px;">
                        <div style="width:20px; height:20px; border-radius:50%; background:#7C3AED; color:white; font-size:9px; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                            {{ mb_strtoupper(mb_substr($task->assignment_type === 'position' ? ($task->position?->name ?? '?') : ($task->employee?->full_name ?? '?'), 0, 1)) }}
                        </div>
                        <span style="font-size:11.5px; color:#64748B;">
                            {{ $task->assignment_type === 'position' ? ($task->position?->name ?? '—') . ' (semua)' : ($task->employee?->full_name ?? '—') }}
                        </span>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:10px; font-weight:700; padding:2px 8px; border-radius:99px;
                            {{ $task->priority === 'high' ? 'background:#FEE2E2;color:#991B1B;' : ($task->priority === 'low' ? 'background:#F1F5F9;color:#64748B;' : 'background:#FEF9C3;color:#854D0E;') }}">
                            {{ ucfirst($task->priority) }}
                        </span>
                        @if($task->due_date)
                        <span style="font-size:10.5px; color:{{ $task->due_date->isPast() && $task->status !== 'done' ? '#DC2626' : '#94A3B8' }};">
                            📅 {{ $task->due_date->format('d M') }}
                        </span>
                        @endif
                    </div>
                    @if($task->subtasks_count > 0)
                    <div style="margin-top:8px; padding-top:8px; border-top:1px dashed #F1F5F9;">
                        <span style="font-size:10.5px; color:#7C3AED; font-weight:600; cursor:pointer;" @click="openEditById({{ $task->id }})">
                            🧩 {{ $task->subtasks_count }} breakdown
                        </span>
                    </div>
                    @endif
                </div>
                @empty
                @endforelse
            </div>
        </div>
        @endforeach
    </div>

    {{-- Modal create/edit tugas --}}
    <div x-show="show" x-cloak style="position:fixed; inset:0; z-index:9999; background:rgba(15,23,42,0.5); display:flex; align-items:center; justify-content:center; padding:16px;"
         @click.self="show = false">
        <div style="background:white; border-radius:16px; width:100%; max-width:480px; max-height:92vh; overflow-y:auto; padding:22px;">

            <div x-show="parentInfo" x-cloak style="margin-bottom:10px;">
                <button type="button" @click="openEditById(parentInfo.id)"
                        style="background:none; border:none; color:#7C3AED; font-size:12px; font-weight:600; cursor:pointer; padding:0;">
                    ← Kembali ke "<span x-text="parentInfo?.title"></span>"
                </button>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 style="font-size:16px; font-weight:800; color:#1e293b; margin:0;">
                    <span x-show="parentTaskId" x-cloak>🧩 </span><span x-text="editId ? 'Edit Tugas' : (parentTaskId ? 'Tambah Sub-tugas' : 'Tambah Tugas')"></span>
                </h3>
                <button type="button" @click="show = false" style="background:none; border:none; font-size:18px; color:#94A3B8; cursor:pointer;">✕</button>
            </div>

            <div x-show="error" x-cloak style="background:#FEF2F2; border:1px solid #FECACA; color:#991B1B; padding:8px 12px; border-radius:8px; font-size:12.5px; margin-bottom:14px;" x-text="error"></div>

            <div style="margin-bottom:12px;">
                <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Judul Tugas</label>
                <input type="text" x-model="title" required maxlength="200"
                       style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:8px 10px; font-size:13px; box-sizing:border-box;">
            </div>

            <div style="margin-bottom:12px;">
                <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Deskripsi (opsional)</label>
                <textarea x-model="description" rows="2"
                          style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:8px 10px; font-size:13px; box-sizing:border-box;"></textarea>
            </div>

            <div style="margin-bottom:12px;">
                <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Project (opsional)</label>
                <select x-model="projectId" style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:8px 10px; font-size:13px;">
                    <option value="">— Tidak ada —</option>
                    @foreach($projects as $p)
                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>

            <div style="margin-bottom:12px;">
                <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:6px;">Ditugaskan Ke</label>
                <div style="display:flex; gap:8px; margin-bottom:8px;">
                    <button type="button" @click="assignmentType = 'employee'"
                            :style="assignmentType === 'employee' ? 'background:#F3E8FF;border-color:#7C3AED;color:#7C3AED;' : 'background:white;border-color:#E2E8F0;color:#64748B;'"
                            style="flex:1; padding:8px; border-radius:8px; border:1.5px solid; font-size:12px; font-weight:600; cursor:pointer;">
                        🙂 1 Karyawan
                    </button>
                    <button type="button" @click="assignmentType = 'position'"
                            :style="assignmentType === 'position' ? 'background:#F3E8FF;border-color:#7C3AED;color:#7C3AED;' : 'background:white;border-color:#E2E8F0;color:#64748B;'"
                            style="flex:1; padding:8px; border-radius:8px; border:1.5px solid; font-size:12px; font-weight:600; cursor:pointer;">
                        🧩 Seluruh Posisi
                    </button>
                </div>
                <div x-show="assignmentType === 'employee'">
                    @include('positions._org_chart_search_picker', [
                        'items' => 'window.TASK_EMPLOYEES',
                        'model' => 'employeeId',
                        'placeholder' => 'Cari nama karyawan...',
                        'syncWhen' => 'show',
                    ])
                </div>
                <div x-show="assignmentType === 'position'">
                    @include('positions._org_chart_search_picker', [
                        'items' => 'window.TASK_POSITIONS',
                        'model' => 'positionId',
                        'placeholder' => 'Cari posisi...',
                        'syncWhen' => 'show',
                    ])
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:16px;">
                <div>
                    <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Deadline (opsional)</label>
                    <input type="date" x-model="dueDate"
                           style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:8px 10px; font-size:13px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Prioritas</label>
                    <select x-model="priority" style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:8px 10px; font-size:13px;">
                        <option value="low">Low</option>
                        <option value="normal">Normal</option>
                        <option value="high">High</option>
                    </select>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:8px; margin-bottom:20px;">
                <button type="button" @click="show = false" style="background:#F1F5F9; color:#475569; border:none; padding:8px 16px; border-radius:8px; font-size:12.5px; font-weight:600; cursor:pointer;">Batal</button>
                <button type="button" @click="save()" :disabled="saving" style="background:#7C3AED; color:white; border:none; padding:8px 18px; border-radius:8px; font-size:12.5px; font-weight:600; cursor:pointer;">
                    <span x-text="saving ? 'Menyimpan…' : 'Simpan'"></span>
                </button>
            </div>

            {{-- Breakdown / sub-tugas — tanpa batas kedalaman --}}
            <div x-show="editId" x-cloak style="border-top:1.5px solid #F1F5F9; padding-top:16px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <h4 style="font-size:13px; font-weight:800; color:#1e293b; margin:0;">🧩 Breakdown Tugas</h4>
                    <button type="button" @click="openCreate(editId)"
                            style="background:#F3E8FF; color:#7C3AED; border:1px solid #DDD6FE; padding:5px 12px; border-radius:8px; font-size:11.5px; font-weight:600; cursor:pointer;">
                        + Sub-tugas
                    </button>
                </div>
                <div style="display:flex; flex-direction:column; gap:6px;">
                    <template x-for="st in subtasks" :key="st.id">
                        <div @click="openEditById(st.id)" style="background:#FAFAFA; border:1px solid #F1F5F9; border-radius:10px; padding:8px 10px; cursor:pointer; display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <div style="font-size:12.5px; font-weight:600; color:#1e293b;" x-text="st.title"></div>
                                <div style="font-size:10.5px; color:#94A3B8;" x-text="st.assignee + (st.subtasks_count > 0 ? ' · ' + st.subtasks_count + ' breakdown' : '')"></div>
                            </div>
                            <span :style="st.status === 'done' ? 'background:#DCFCE7;color:#166534;' : (st.status === 'in_progress' ? 'background:#FFFBEB;color:#B45309;' : 'background:#EFF6FF;color:#1D4ED8;')"
                                  style="font-size:9.5px; font-weight:700; padding:2px 8px; border-radius:99px;" x-text="st.status === 'done' ? 'Done' : (st.status === 'in_progress' ? 'Progress' : 'Open')"></span>
                        </div>
                    </template>
                    <div x-show="subtasks.length === 0" style="font-size:12px; color:#94A3B8; text-align:center; padding:10px;">Belum ada breakdown.</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Slide-over: Kelola Project --}}
    <div x-show="projectsPanel" x-cloak style="position:fixed; inset:0; z-index:9998; background:rgba(15,23,42,0.5);" @click.self="projectsPanel = false">
        <div style="position:fixed; top:0; right:0; height:100vh; width:420px; max-width:95vw; background:white; box-shadow:-12px 0 40px rgba(0,0,0,0.15); display:flex; flex-direction:column;">
            <div style="padding:20px; border-bottom:1px solid #F1F5F9; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="font-size:16px; font-weight:800; color:#1e293b; margin:0;">📁 Kelola Project</h3>
                <button type="button" @click="projectsPanel = false" style="background:none; border:none; font-size:18px; color:#94A3B8; cursor:pointer;">✕</button>
            </div>
            <div style="padding:16px 20px; border-bottom:1px solid #F1F5F9;">
                <button type="button" @click="projectModalOpen = true; projectEditId = null; projectName = ''; projectDescription = ''"
                        style="width:100%; background:#7C3AED; color:white; border:none; padding:9px; border-radius:10px; font-size:13px; font-weight:600; cursor:pointer;">
                    + Project Baru
                </button>
            </div>
            <div style="flex:1; overflow-y:auto; padding:12px 20px;">
                @forelse($allProjects as $project)
                <div style="border:1.5px solid #E2E8F0; border-radius:12px; padding:12px; margin-bottom:10px;">
                    <div style="display:flex; justify-content:space-between; align-items:start;">
                        <div>
                            <div style="font-weight:700; color:#1e293b; font-size:13px;">{{ $project->name }}</div>
                            <div style="font-size:11px; color:#94A3B8; margin-top:2px;">{{ $project->tasks_count }} tugas</div>
                        </div>
                        <span style="padding:2px 8px; border-radius:99px; font-size:10px; font-weight:600;
                                     {{ $project->status === 'active' ? 'background:#DCFCE7;color:#166534;' : 'background:#F1F5F9;color:#64748B;' }}">
                            {{ $project->status === 'active' ? 'Aktif' : 'Arsip' }}
                        </span>
                    </div>
                    <div style="display:flex; gap:10px; margin-top:8px;">
                        <button type="button"
                                @click="projectModalOpen = true; projectEditId = {{ $project->id }}; projectName = @js($project->name); projectDescription = @js($project->description)"
                                style="background:none; border:none; color:#1D4ED8; font-size:11.5px; font-weight:600; cursor:pointer;">Edit</button>
                        <form method="POST" action="{{ route('projects.destroy', $project) }}" onsubmit="return confirm('Hapus project {{ addslashes($project->name) }}?')">
                            @csrf @method('DELETE')
                            <button type="submit" style="background:none; border:none; color:#DC2626; font-size:11.5px; font-weight:600; cursor:pointer;">Hapus</button>
                        </form>
                    </div>
                </div>
                @empty
                <div style="text-align:center; color:#94A3B8; padding:30px; font-size:13px;">Belum ada project.</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Modal create/edit project --}}
    <div x-show="projectModalOpen" x-cloak style="position:fixed; inset:0; z-index:10000; background:rgba(15,23,42,0.5); display:flex; align-items:center; justify-content:center; padding:16px;"
         @click.self="projectModalOpen = false">
        <div style="background:white; border-radius:16px; width:100%; max-width:400px; padding:22px;">
            <h3 style="font-size:16px; font-weight:800; color:#1e293b; margin:0 0 16px 0;" x-text="projectEditId ? 'Edit Project' : 'Project Baru'"></h3>
            <form :action="projectEditId ? `{{ url('projects') }}/${projectEditId}` : '{{ route('projects.store') }}'" method="POST">
                @csrf
                <template x-if="projectEditId"><input type="hidden" name="_method" value="PUT"></template>
                <div style="margin-bottom:12px;">
                    <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Nama Project</label>
                    <input type="text" name="name" x-model="projectName" required maxlength="150"
                           style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:8px 10px; font-size:13px; box-sizing:border-box;">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Deskripsi (opsional)</label>
                    <textarea name="description" x-model="projectDescription" rows="3"
                              style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:8px 10px; font-size:13px; box-sizing:border-box;"></textarea>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px;">
                    <button type="button" @click="projectModalOpen = false" style="background:#F1F5F9; color:#475569; border:none; padding:8px 16px; border-radius:8px; font-size:12.5px; font-weight:600; cursor:pointer;">Batal</button>
                    <button type="submit" style="background:#7C3AED; color:white; border:none; padding:8px 18px; border-radius:8px; font-size:12.5px; font-weight:600; cursor:pointer;">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="{{ asset('js/search-picker.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/dragula@3.7.3/dist/dragula.min.js"></script>
<script>
window.TASK_EMPLOYEES = @json($employeeOptions->map(fn ($e) => ['id' => $e->id, 'name' => $e->full_name])->values());
window.TASK_POSITIONS = @json($positionOptions->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values());

function taskBoard() {
    return {
        show: false,
        saving: false,
        error: '',
        editId: null,
        parentTaskId: null,
        parentInfo: null,
        subtasks: [],
        title: '', description: '', projectId: '',
        assignmentType: 'employee', employeeId: '', positionId: '',
        dueDate: '', priority: 'normal',

        projectsPanel: false,
        projectModalOpen: false,
        projectEditId: null,
        projectName: '', projectDescription: '',

        csrfHeaders() {
            return {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            };
        },

        openCreate(parentTaskId = null) {
            this.show = true;
            this.error = '';
            this.editId = null;
            this.parentTaskId = parentTaskId;
            this.title = ''; this.description = ''; this.projectId = '';
            this.assignmentType = 'employee'; this.employeeId = ''; this.positionId = '';
            this.dueDate = ''; this.priority = 'normal';
            this.subtasks = [];
            // parentInfo tetap ditampilkan kalau sedang menambah sub-tugas
            // (biar breadcrumb "← Kembali" tetap kelihatan)
        },

        async openEditById(id) {
            this.show = true;
            this.error = '';
            try {
                const r = await fetch(`{{ url('tasks') }}/${id}/detail`, { headers: { 'Accept': 'application/json' } });
                if (!r.ok) throw new Error('Gagal memuat tugas.');
                const data = await r.json();
                const t = data.task;
                this.editId = t.id;
                this.parentTaskId = t.parent_task_id;
                this.parentInfo = data.parent;
                this.subtasks = data.subtasks;
                this.title = t.title; this.description = t.description || ''; this.projectId = t.project_id || '';
                this.assignmentType = t.assignment_type; this.employeeId = t.employee_id || ''; this.positionId = t.position_id || '';
                this.dueDate = t.due_date || ''; this.priority = t.priority;
            } catch (e) {
                this.error = e.message || 'Gagal memuat tugas.';
            }
        },

        async save() {
            this.error = '';
            this.saving = true;

            const payload = {
                title: this.title,
                description: this.description,
                project_id: this.projectId || null,
                parent_task_id: this.parentTaskId || null,
                assignment_type: this.assignmentType,
                employee_id: this.assignmentType === 'employee' ? (this.employeeId || null) : null,
                position_id: this.assignmentType === 'position' ? (this.positionId || null) : null,
                due_date: this.dueDate || null,
                priority: this.priority,
            };

            const url = this.editId ? `{{ url('tasks') }}/${this.editId}` : '{{ route('tasks.store') }}';
            const method = this.editId ? 'PUT' : 'POST';

            try {
                const r = await fetch(url, { method, headers: this.csrfHeaders(), body: JSON.stringify(payload) });
                const data = await r.json();
                this.saving = false;
                if (!r.ok) { this.error = data.message || 'Gagal menyimpan.'; return; }

                if (this.parentTaskId) {
                    // baru buat/edit sub-tugas — tetap di modal, refresh breakdown parent-nya
                    await this.openEditById(this.parentTaskId);
                } else {
                    // tugas level atas — reload papan supaya kolom & badge ikut update
                    window.location.reload();
                }
            } catch (e) {
                this.saving = false;
                this.error = 'Gagal menyimpan, coba lagi.';
            }
        },

        initDragula() {
            const cols = [
                document.getElementById('col-open'),
                document.getElementById('col-in_progress'),
                document.getElementById('col-done'),
            ].filter(Boolean);

            if (!cols.length || typeof dragula === 'undefined') return;

            const drake = dragula(cols, { revertOnSpill: true });

            drake.on('drop', (el, target) => {
                const taskId = el.getAttribute('data-task-id');
                const newStatus = target.getAttribute('data-status');

                fetch(`{{ url('tasks') }}/${taskId}/status`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ status: newStatus }),
                })
                    .then(r => { if (!r.ok) throw new Error('failed'); })
                    .then(() => {
                        document.querySelectorAll('[id^="count-"]').forEach(el2 => {
                            const colKey = el2.id.replace('count-', '');
                            const colEl = document.getElementById('col-' + colKey);
                            if (colEl) el2.textContent = colEl.querySelectorAll('.oc-task-card').length;
                        });
                    })
                    .catch(() => {
                        alert('Gagal menyimpan status, halaman akan dimuat ulang.');
                        window.location.reload();
                    });
            });
        },
    };
}
</script>
@endsection
