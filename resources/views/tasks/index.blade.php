@extends('layouts.app')
@section('title', 'Task & Project')
@section('content')
<div class="p-4" x-data="taskBoard()" x-init="initDragula()">

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
        <div>
            <h1 style="font-size:22px; font-weight:800; color:#1e293b; margin:0;">📋 Task & Project</h1>
            <p style="color:#64748B; font-size:13px; margin:4px 0 0 0;">
                Drag kartu antar kolom untuk ubah status — tugas bisa ditugaskan ke 1 karyawan atau ke seluruh pemegang 1 posisi
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
                             @click="openEdit(@js([
                                'id' => $task->id, 'title' => $task->title, 'description' => $task->description,
                                'project_id' => $task->project_id, 'assignment_type' => $task->assignment_type,
                                'position_id' => $task->position_id, 'employee_id' => $task->employee_id,
                                'due_date' => $task->due_date?->format('Y-m-d'), 'priority' => $task->priority,
                             ]))">
                            {{ $task->title }}
                        </div>
                        <div x-data="{ open: false }" style="position:relative;">
                            <button type="button" @click="open = !open" @click.outside="open = false"
                                    style="background:none; border:none; color:#94A3B8; font-size:14px; cursor:pointer; padding:0 4px;">⋯</button>
                            <div x-show="open" x-cloak style="position:absolute; right:0; top:20px; background:white; border:1px solid #E2E8F0; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.1); z-index:10; min-width:110px;">
                                <button type="button" @click="open=false; openEdit(@js([
                                    'id' => $task->id, 'title' => $task->title, 'description' => $task->description,
                                    'project_id' => $task->project_id, 'assignment_type' => $task->assignment_type,
                                    'position_id' => $task->position_id, 'employee_id' => $task->employee_id,
                                    'due_date' => $task->due_date?->format('Y-m-d'), 'priority' => $task->priority,
                                ]))" style="display:block; width:100%; text-align:left; background:none; border:none; padding:8px 12px; font-size:12px; color:#1D4ED8; cursor:pointer;">✏️ Edit</button>
                                <form method="POST" action="{{ route('tasks.destroy', $task) }}" onsubmit="return confirm('Hapus tugas ini?')">
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
        <div style="background:white; border-radius:16px; width:100%; max-width:460px; max-height:90vh; overflow-y:auto; padding:22px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 style="font-size:16px; font-weight:800; color:#1e293b; margin:0;" x-text="editId ? 'Edit Tugas' : 'Tambah Tugas'"></h3>
                <button type="button" @click="show = false" style="background:none; border:none; font-size:18px; color:#94A3B8; cursor:pointer;">✕</button>
            </div>
            <form :action="editId ? `{{ url('tasks') }}/${editId}` : '{{ route('tasks.store') }}'" method="POST">
                @csrf
                <template x-if="editId"><input type="hidden" name="_method" value="PUT"></template>

                <div style="margin-bottom:12px;">
                    <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Judul Tugas</label>
                    <input type="text" name="title" x-model="title" required maxlength="200"
                           style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:8px 10px; font-size:13px; box-sizing:border-box;">
                </div>

                <div style="margin-bottom:12px;">
                    <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Deskripsi (opsional)</label>
                    <textarea name="description" x-model="description" rows="2"
                              style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:8px 10px; font-size:13px; box-sizing:border-box;"></textarea>
                </div>

                <div style="margin-bottom:12px;">
                    <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Project (opsional)</label>
                    <select name="project_id" x-model="projectId" style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:8px 10px; font-size:13px;">
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
                    <input type="hidden" name="assignment_type" x-model="assignmentType">
                    <select x-show="assignmentType === 'employee'" name="employee_id" x-model="employeeId"
                            style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:8px 10px; font-size:13px;">
                        <option value="">— Pilih Karyawan —</option>
                        @foreach($employeeOptions as $e)
                        <option value="{{ $e->id }}">{{ $e->full_name }}</option>
                        @endforeach
                    </select>
                    <select x-show="assignmentType === 'position'" name="position_id" x-model="positionId"
                            style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:8px 10px; font-size:13px;">
                        <option value="">— Pilih Posisi —</option>
                        @foreach($positionOptions as $pos)
                        <option value="{{ $pos->id }}">{{ $pos->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:16px;">
                    <div>
                        <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Deadline (opsional)</label>
                        <input type="date" name="due_date" x-model="dueDate"
                               style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:8px 10px; font-size:13px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Prioritas</label>
                        <select name="priority" x-model="priority" style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:8px 10px; font-size:13px;">
                            <option value="low">Low</option>
                            <option value="normal">Normal</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:8px;">
                    <button type="button" @click="show = false" style="background:#F1F5F9; color:#475569; border:none; padding:8px 16px; border-radius:8px; font-size:12.5px; font-weight:600; cursor:pointer;">Batal</button>
                    <button type="submit" style="background:#7C3AED; color:white; border:none; padding:8px 18px; border-radius:8px; font-size:12.5px; font-weight:600; cursor:pointer;">Simpan</button>
                </div>
            </form>
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

<script src="https://cdn.jsdelivr.net/npm/dragula@3.7.3/dist/dragula.min.js"></script>
<script>
function taskBoard() {
    return {
        show: false,
        editId: null,
        title: '', description: '', projectId: '',
        assignmentType: 'employee', employeeId: '', positionId: '',
        dueDate: '', priority: 'normal',

        projectsPanel: false,
        projectModalOpen: false,
        projectEditId: null,
        projectName: '', projectDescription: '',

        openCreate() {
            this.show = true;
            this.editId = null;
            this.title = ''; this.description = ''; this.projectId = '';
            this.assignmentType = 'employee'; this.employeeId = ''; this.positionId = '';
            this.dueDate = ''; this.priority = 'normal';
        },

        openEdit(task) {
            this.show = true;
            this.editId = task.id;
            this.title = task.title; this.description = task.description || ''; this.projectId = task.project_id || '';
            this.assignmentType = task.assignment_type; this.employeeId = task.employee_id || ''; this.positionId = task.position_id || '';
            this.dueDate = task.due_date || ''; this.priority = task.priority;
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
