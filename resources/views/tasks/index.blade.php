@extends('layouts.app')
@section('title', 'Papan Tugas')
@section('content')
<div class="p-4" x-data="taskBoard()">

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
        <div>
            <h1 style="font-size:22px; font-weight:800; color:#1e293b; margin:0;">📋 Papan Tugas</h1>
            <p style="color:#64748B; font-size:13px; margin:4px 0 0 0;">
                Prototipe — tugas bisa ditugaskan ke 1 karyawan atau ke seluruh pemegang 1 posisi (tugas standing)
            </p>
        </div>
        <div style="display:flex; gap:10px;">
            <a href="{{ route('projects.index') }}" style="background:#F3E8FF; color:#7C3AED; border:1px solid #DDD6FE; padding:9px 16px; border-radius:10px; font-size:13px; font-weight:600; text-decoration:none;">
                📁 Kelola Project
            </a>
            <button type="button" @click="openCreate()"
                    style="background:#7C3AED; color:white; border:none; padding:9px 18px; border-radius:10px; font-size:13px; font-weight:600; cursor:pointer;">
                + Tambah Tugas
            </button>
        </div>
    </div>

    @if(session('success'))
    <div style="background:#F0FDF4; border:1px solid #BBF7D0; color:#166534; padding:10px 14px; border-radius:10px; font-size:13px; margin-bottom:16px;">✓ {{ session('success') }}</div>
    @endif

    {{-- Filter --}}
    <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px;">
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
        <div style="background:#FAFAFA; border-radius:14px; border:1.5px solid #E2E8F0; overflow:hidden;">
            <div style="background:{{ $col['bg'] }}; color:{{ $col['color'] }}; padding:12px 16px; font-weight:700; font-size:13px; display:flex; justify-content:space-between;">
                <span>{{ $col['label'] }}</span>
                <span>{{ $board[$col['key']]->count() }}</span>
            </div>
            <div style="padding:10px; display:flex; flex-direction:column; gap:10px; min-height:100px;">
                @forelse($board[$col['key']] as $task)
                <div style="background:white; border:1.5px solid #E2E8F0; border-radius:12px; padding:12px; cursor:pointer;"
                     @click="openEdit(@js([
                        'id' => $task->id, 'title' => $task->title, 'description' => $task->description,
                        'project_id' => $task->project_id, 'assignment_type' => $task->assignment_type,
                        'position_id' => $task->position_id, 'employee_id' => $task->employee_id,
                        'due_date' => $task->due_date?->format('Y-m-d'), 'priority' => $task->priority,
                     ]))">
                    <div style="font-weight:700; color:#1e293b; font-size:13px; margin-bottom:4px;">{{ $task->title }}</div>
                    @if($task->project)
                    <div style="font-size:10.5px; color:#7C3AED; font-weight:600; margin-bottom:4px;">📁 {{ $task->project->name }}</div>
                    @endif
                    <div style="font-size:11.5px; color:#64748B; margin-bottom:6px;">
                        {{ $task->assignment_type === 'position' ? '🧩 ' . ($task->position?->name ?? '—') . ' (semua)' : '🙂 ' . ($task->employee?->full_name ?? '—') }}
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:10px; font-weight:700; padding:2px 8px; border-radius:99px;
                            {{ $task->priority === 'high' ? 'background:#FEE2E2;color:#991B1B;' : ($task->priority === 'low' ? 'background:#F1F5F9;color:#64748B;' : 'background:#FEF9C3;color:#854D0E;') }}">
                            {{ ucfirst($task->priority) }}
                        </span>
                        @if($task->due_date)
                        <span style="font-size:10.5px; color:{{ $task->due_date->isPast() && $task->status !== 'done' ? '#DC2626' : '#94A3B8' }};">
                            {{ $task->due_date->format('d M') }}
                        </span>
                        @endif
                    </div>
                    <div style="display:flex; gap:5px; margin-top:8px;" @click.stop>
                        @foreach(['open' => 'Open', 'in_progress' => 'Progress', 'done' => 'Done'] as $st => $stLabel)
                        @if($st !== $task->status)
                        <form method="POST" action="{{ route('tasks.status', $task) }}" style="display:inline;">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="{{ $st }}">
                            <button type="submit" style="font-size:9.5px; background:#F1F5F9; color:#475569; border:none; padding:2px 7px; border-radius:6px; cursor:pointer;">→ {{ $stLabel }}</button>
                        </form>
                        @endif
                        @endforeach
                    </div>
                </div>
                @empty
                <div style="text-align:center; color:#CBD5E1; font-size:12px; padding:20px;">Kosong</div>
                @endforelse
            </div>
        </div>
        @endforeach
    </div>

    {{-- Modal create/edit --}}
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

                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <template x-if="editId">
                        <button type="button" @click="destroy()" style="background:#FEF2F2; color:#DC2626; border:1px solid #FECACA; padding:8px 14px; border-radius:8px; font-size:12.5px; font-weight:600; cursor:pointer;">Hapus</button>
                    </template>
                    <span x-show="!editId"></span>
                    <div style="display:flex; gap:8px;">
                        <button type="button" @click="show = false" style="background:#F1F5F9; color:#475569; border:none; padding:8px 16px; border-radius:8px; font-size:12.5px; font-weight:600; cursor:pointer;">Batal</button>
                        <button type="submit" style="background:#7C3AED; color:white; border:none; padding:8px 18px; border-radius:8px; font-size:12.5px; font-weight:600; cursor:pointer;">Simpan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function taskBoard() {
    return {
        show: false,
        editId: null,
        title: '', description: '', projectId: '',
        assignmentType: 'employee', employeeId: '', positionId: '',
        dueDate: '', priority: 'normal',

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

        destroy() {
            if (!this.editId) return;
            if (!confirm('Hapus tugas ini?')) return;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `{{ url('tasks') }}/${this.editId}`;
            form.innerHTML = `@csrf @method('DELETE')`;
            document.body.appendChild(form);
            form.submit();
        },
    };
}
</script>
@endsection
