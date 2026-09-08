@extends('layouts.app')
@section('title', 'Project')
@section('content')
<div class="p-4" x-data="{ modalOpen: false, editId: null, name: '', description: '', departmentId: '' }">

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
        <div>
            <div style="display:flex; align-items:center; gap:10px;">
                <a href="{{ route('tasks.index') }}" style="color:#7C3AED; text-decoration:none; font-size:13px; font-weight:500;">← Papan Tugas</a>
                <span style="color:#CBD5E1;">|</span>
                <h1 style="font-size:22px; font-weight:800; color:#1e293b; margin:0;">📁 Project</h1>
            </div>
            <p style="color:#64748B; font-size:13px; margin:4px 0 0 0;">Kelompokkan tugas ke dalam project (opsional)</p>
        </div>
        <button type="button"
                @click="modalOpen = true; editId = null; name = ''; description = ''; departmentId = ''"
                style="background:#7C3AED; color:white; border:none; padding:9px 18px; border-radius:10px; font-size:13px; font-weight:600; cursor:pointer;">
            + Tambah Project
        </button>
    </div>

    @if(session('success'))
    <div style="background:#F0FDF4; border:1px solid #BBF7D0; color:#166534; padding:10px 14px; border-radius:10px; font-size:13px; margin-bottom:16px;">
        ✓ {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div style="background:#FEF2F2; border:1px solid #FECACA; color:#991B1B; padding:10px 14px; border-radius:10px; font-size:13px; margin-bottom:16px;">
        ✗ {{ session('error') }}
    </div>
    @endif

    <div style="background:white; border-radius:14px; border:1.5px solid #E2E8F0; overflow:hidden;">
        <table style="width:100%; border-collapse:collapse; font-size:13px;">
            <thead>
                <tr style="background:#F8FAFC;">
                    <th style="padding:10px 14px; text-align:left; color:#64748B; font-weight:600;">Nama Project</th>
                    <th style="padding:10px 14px; text-align:left; color:#64748B; font-weight:600;">Departemen</th>
                    <th style="padding:10px 14px; text-align:center; color:#64748B; font-weight:600;">Jumlah Tugas</th>
                    <th style="padding:10px 14px; text-align:center; color:#64748B; font-weight:600;">Status</th>
                    <th style="padding:10px 14px; text-align:right; color:#64748B; font-weight:600;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($projects as $project)
                <tr style="border-top:1px solid #F1F5F9;">
                    <td style="padding:12px 14px;">
                        <div style="font-weight:700; color:#1e293b;">{{ $project->name }}</div>
                        @if($project->description)
                        <div style="font-size:11px; color:#94A3B8; margin-top:2px;">{{ \Illuminate\Support\Str::limit($project->description, 80) }}</div>
                        @endif
                    </td>
                    <td style="padding:12px 14px; color:#475569;">{{ $project->department?->name ?? '—' }}</td>
                    <td style="padding:12px 14px; text-align:center;">
                        <a href="{{ route('tasks.index', ['project_id' => $project->id]) }}" style="color:#7C3AED; font-weight:700; text-decoration:none;">{{ $project->tasks_count }}</a>
                    </td>
                    <td style="padding:12px 14px; text-align:center;">
                        <span style="padding:3px 10px; border-radius:99px; font-size:11px; font-weight:600;
                                     {{ $project->status === 'active' ? 'background:#DCFCE7;color:#166534;' : 'background:#F1F5F9;color:#64748B;' }}">
                            {{ $project->status === 'active' ? 'Aktif' : 'Arsip' }}
                        </span>
                    </td>
                    <td style="padding:12px 14px; text-align:right;">
                        <button type="button"
                                @click="modalOpen = true; editId = {{ $project->id }}; name = @js($project->name); description = @js($project->description); departmentId = '{{ $project->department_id }}'"
                                style="background:none; border:none; color:#1D4ED8; font-size:12px; font-weight:600; cursor:pointer; margin-right:10px;">
                            Edit
                        </button>
                        <form method="POST" action="{{ route('projects.destroy', $project) }}" style="display:inline;"
                              onsubmit="return confirm('Hapus project {{ addslashes($project->name) }}?')">
                            @csrf @method('DELETE')
                            <button type="submit" style="background:none; border:none; color:#DC2626; font-size:12px; font-weight:600; cursor:pointer;">Hapus</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" style="padding:40px; text-align:center; color:#94A3B8;">Belum ada project.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal --}}
    <div x-show="modalOpen" x-cloak style="position:fixed; inset:0; z-index:9999; background:rgba(15,23,42,0.5); display:flex; align-items:center; justify-content:center; padding:16px;"
         @click.self="modalOpen = false">
        <div style="background:white; border-radius:16px; width:100%; max-width:420px; padding:22px;">
            <h3 style="font-size:16px; font-weight:800; color:#1e293b; margin:0 0 16px 0;" x-text="editId ? 'Edit Project' : 'Tambah Project'"></h3>
            <form :action="editId ? `{{ url('projects') }}/${editId}` : '{{ route('projects.store') }}'" method="POST">
                @csrf
                <template x-if="editId"><input type="hidden" name="_method" value="PUT"></template>
                <div style="margin-bottom:12px;">
                    <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Nama Project</label>
                    <input type="text" name="name" x-model="name" required maxlength="150"
                           style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:8px 10px; font-size:13px; box-sizing:border-box;">
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Departemen (opsional)</label>
                    <select name="department_id" x-model="departmentId" style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:8px 10px; font-size:13px;">
                        <option value="">— Tidak ada —</option>
                        @foreach($departments as $dept)
                        <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Deskripsi (opsional)</label>
                    <textarea name="description" x-model="description" rows="3" maxlength="2000"
                              style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:8px 10px; font-size:13px; box-sizing:border-box;"></textarea>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px;">
                    <button type="button" @click="modalOpen = false" style="background:#F1F5F9; color:#475569; border:none; padding:8px 16px; border-radius:8px; font-size:12.5px; font-weight:600; cursor:pointer;">Batal</button>
                    <button type="submit" style="background:#7C3AED; color:white; border:none; padding:8px 18px; border-radius:8px; font-size:12.5px; font-weight:600; cursor:pointer;">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
