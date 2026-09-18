@extends('layouts.app')
@section('title', 'Tugas Saya')
@section('content')
<div class="p-4" x-data="myTaskBoard()" x-init="initDragula()">

    <div style="margin-bottom:16px;">
        <h1 style="font-size:22px; font-weight:800; color:#1e293b; margin:0;">✅ Tugas Saya</h1>
        <p style="color:#64748B; font-size:13px; margin:4px 0 0 0;">
            Tugas yang ditugaskan langsung ke Anda dan tugas rutin posisi Anda — drag kartu untuk ubah status, klik untuk lihat detail & breakdown
        </p>
    </div>

    @if(session('success'))
    <div style="background:#F0FDF4; border:1px solid #BBF7D0; color:#166534; padding:10px 14px; border-radius:10px; font-size:13px; margin-bottom:16px;">✓ {{ session('success') }}</div>
    @endif

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
                     style="background:white; border:1.5px solid #E2E8F0; border-radius:12px; padding:12px; cursor:grab;"
                     @click="openDetail({{ $task->id }})">
                    <div style="font-weight:700; color:#1e293b; font-size:13px; margin-bottom:4px;">{{ $task->title }}</div>
                    @if($task->project)
                    <div style="font-size:10.5px; color:#7C3AED; font-weight:600; margin-bottom:6px;">📁 {{ $task->project->name }}</div>
                    @endif
                    @if($task->assignment_type === 'position')
                    <div style="font-size:10.5px; color:#B45309; font-weight:600; margin-bottom:6px;">🧩 Tugas rutin posisi</div>
                    @endif
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
                        <span style="font-size:10.5px; color:#7C3AED; font-weight:600;">🧩 {{ $task->subtasks_count }} breakdown</span>
                    </div>
                    @endif
                </div>
                @empty
                @endforelse
            </div>
        </div>
        @endforeach
    </div>

    {{-- Modal detail tugas + breakdown --}}
    <div x-show="show" x-cloak style="position:fixed; inset:0; z-index:9999; background:rgba(15,23,42,0.5); display:flex; align-items:center; justify-content:center; padding:16px;"
         @click.self="show = false">
        <div style="background:white; border-radius:16px; width:100%; max-width:480px; max-height:92vh; overflow-y:auto; padding:22px;">

            <div x-show="parentInfo" x-cloak style="margin-bottom:10px;">
                <button type="button" @click="openDetail(parentInfo.id)"
                        style="background:none; border:none; color:#7C3AED; font-size:12px; font-weight:600; cursor:pointer; padding:0;">
                    ← Kembali ke "<span x-text="parentInfo?.title"></span>"
                </button>
            </div>

            <template x-if="!editingSubtask">
                <div>
                    <div style="display:flex; justify-content:space-between; align-items:start; margin-bottom:6px;">
                        <h3 style="font-size:16px; font-weight:800; color:#1e293b; margin:0;" x-text="task.title"></h3>
                        <button type="button" @click="show = false" style="background:none; border:none; font-size:18px; color:#94A3B8; cursor:pointer;">✕</button>
                    </div>
                    <div x-show="task.project_name" style="font-size:11.5px; color:#7C3AED; font-weight:600; margin-bottom:10px;">📁 <span x-text="task.project_name"></span></div>
                    <div x-show="task.description" style="font-size:13px; color:#475569; background:#FAFAFA; border-radius:10px; padding:10px 12px; margin-bottom:12px; white-space:pre-line;" x-text="task.description"></div>

                    <div style="display:flex; gap:16px; margin-bottom:16px; flex-wrap:wrap;">
                        <div>
                            <div style="font-size:10.5px; color:#94A3B8; font-weight:600;">PRIORITAS</div>
                            <div style="font-size:13px; font-weight:700; color:#1e293b;" x-text="task.priority"></div>
                        </div>
                        <div x-show="task.due_date">
                            <div style="font-size:10.5px; color:#94A3B8; font-weight:600;">DEADLINE</div>
                            <div style="font-size:13px; font-weight:700; color:#1e293b;" x-text="task.due_date"></div>
                        </div>
                        <div>
                            <div style="font-size:10.5px; color:#94A3B8; font-weight:600;">STATUS</div>
                            <select x-model="task.status" @change="changeStatus()" style="font-size:12.5px; border:1.5px solid #E2E8F0; border-radius:8px; padding:4px 8px; margin-top:2px;">
                                <option value="open">Open</option>
                                <option value="in_progress">In Progress</option>
                                <option value="done">Done</option>
                            </select>
                        </div>
                    </div>

                    <div style="border-top:1.5px solid #F1F5F9; padding-top:16px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                            <h4 style="font-size:13px; font-weight:800; color:#1e293b; margin:0;">🧩 Breakdown Tugas</h4>
                            <button type="button" @click="openCreateSub()"
                                    style="background:#F3E8FF; color:#7C3AED; border:1px solid #DDD6FE; padding:5px 12px; border-radius:8px; font-size:11.5px; font-weight:600; cursor:pointer;">
                                + Sub-tugas
                            </button>
                        </div>
                        <div style="display:flex; flex-direction:column; gap:6px;">
                            <template x-for="st in subtasks" :key="st.id">
                                <div @click="openDetail(st.id)" style="background:#FAFAFA; border:1px solid #F1F5F9; border-radius:10px; padding:8px 10px; cursor:pointer; display:flex; justify-content:space-between; align-items:center;">
                                    <div>
                                        <div style="font-size:12.5px; font-weight:600; color:#1e293b;" x-text="st.title"></div>
                                        <div style="font-size:10.5px; color:#94A3B8;" x-text="st.assignee + (st.subtasks_count > 0 ? ' · ' + st.subtasks_count + ' breakdown' : '')"></div>
                                    </div>
                                    <span :style="st.status === 'done' ? 'background:#DCFCE7;color:#166534;' : (st.status === 'in_progress' ? 'background:#FFFBEB;color:#B45309;' : 'background:#EFF6FF;color:#1D4ED8;')"
                                          style="font-size:9.5px; font-weight:700; padding:2px 8px; border-radius:99px;" x-text="st.status === 'done' ? 'Done' : (st.status === 'in_progress' ? 'Progress' : 'Open')"></span>
                                </div>
                            </template>
                            <div x-show="subtasks.length === 0" style="font-size:12px; color:#94A3B8; text-align:center; padding:10px;">Belum ada breakdown. Pecah tugas ini jadi langkah-langkah kecil lewat "+ Sub-tugas".</div>
                        </div>
                    </div>
                </div>
            </template>

            {{-- Form buat/edit sub-tugas --}}
            <template x-if="editingSubtask">
                <div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                        <h3 style="font-size:16px; font-weight:800; color:#1e293b; margin:0;" x-text="subEditId ? 'Edit Sub-tugas' : 'Tambah Sub-tugas'"></h3>
                        <button type="button" @click="editingSubtask = false" style="background:none; border:none; font-size:18px; color:#94A3B8; cursor:pointer;">✕</button>
                    </div>
                    <div x-show="error" x-cloak style="background:#FEF2F2; border:1px solid #FECACA; color:#991B1B; padding:8px 12px; border-radius:8px; font-size:12.5px; margin-bottom:14px;" x-text="error"></div>

                    <div style="margin-bottom:12px;">
                        <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Judul Sub-tugas</label>
                        <input type="text" x-model="subTitle" required maxlength="200"
                               style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:8px 10px; font-size:13px; box-sizing:border-box;">
                    </div>
                    <div style="margin-bottom:12px;">
                        <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Deskripsi (opsional)</label>
                        <textarea x-model="subDescription" rows="2"
                                  style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:8px 10px; font-size:13px; box-sizing:border-box;"></textarea>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:18px;">
                        <div>
                            <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Deadline (opsional)</label>
                            <input type="date" x-model="subDueDate"
                                   style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:8px 10px; font-size:13px; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Prioritas</label>
                            <select x-model="subPriority" style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:8px 10px; font-size:13px;">
                                <option value="low">Low</option>
                                <option value="normal">Normal</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                    </div>
                    <div style="display:flex; justify-content:flex-end; gap:8px;">
                        <button type="button" @click="editingSubtask = false" style="background:#F1F5F9; color:#475569; border:none; padding:8px 16px; border-radius:8px; font-size:12.5px; font-weight:600; cursor:pointer;">Batal</button>
                        <button type="button" @click="saveSub()" :disabled="saving" style="background:#7C3AED; color:white; border:none; padding:8px 18px; border-radius:8px; font-size:12.5px; font-weight:600; cursor:pointer;">
                            <span x-text="saving ? 'Menyimpan…' : 'Simpan'"></span>
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/dragula@3.7.3/dist/dragula.min.js"></script>
<script>
function myTaskBoard() {
    return {
        show: false,
        editingSubtask: false,
        error: '',
        saving: false,
        task: {},
        parentInfo: null,
        subtasks: [],
        currentTaskId: null,

        subEditId: null,
        subTitle: '', subDescription: '', subDueDate: '', subPriority: 'normal',

        csrfHeaders() {
            return {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            };
        },

        async openDetail(id) {
            this.show = true;
            this.editingSubtask = false;
            this.error = '';
            try {
                const r = await fetch(`{{ url('my-tasks') }}/${id}/detail`, { headers: { 'Accept': 'application/json' } });
                if (!r.ok) throw new Error('Gagal memuat tugas.');
                const data = await r.json();
                this.task = data.task;
                this.currentTaskId = data.task.id;
                this.parentInfo = data.parent;
                this.subtasks = data.subtasks;
            } catch (e) {
                this.show = false;
                alert(e.message || 'Gagal memuat tugas.');
            }
        },

        async changeStatus() {
            try {
                const r = await fetch(`{{ url('my-tasks') }}/${this.currentTaskId}/status`, {
                    method: 'PATCH',
                    headers: this.csrfHeaders(),
                    body: JSON.stringify({ status: this.task.status }),
                });
                if (!r.ok) throw new Error('failed');
                window.location.reload();
            } catch (e) {
                alert('Gagal mengubah status, halaman akan dimuat ulang.');
                window.location.reload();
            }
        },

        openCreateSub() {
            this.editingSubtask = true;
            this.error = '';
            this.subEditId = null;
            this.subTitle = ''; this.subDescription = ''; this.subDueDate = ''; this.subPriority = 'normal';
        },

        async saveSub() {
            this.error = '';
            this.saving = true;

            const payload = {
                parent_task_id: this.currentTaskId,
                title: this.subTitle,
                description: this.subDescription,
                due_date: this.subDueDate || null,
                priority: this.subPriority,
            };

            try {
                const r = await fetch('{{ route('tasks.my.store') }}', {
                    method: 'POST',
                    headers: this.csrfHeaders(),
                    body: JSON.stringify(payload),
                });
                const data = await r.json();
                this.saving = false;
                if (!r.ok) { this.error = data.message || 'Gagal menyimpan.'; return; }

                this.editingSubtask = false;
                await this.openDetail(this.currentTaskId);
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

                fetch(`{{ url('my-tasks') }}/${taskId}/status`, {
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
