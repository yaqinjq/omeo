@extends('layouts.app')
@section('title', 'Tugas Saya')
@section('content')
<div class="p-4">
    <h1 style="font-size:22px; font-weight:800; color:#1e293b; margin:0 0 4px 0;">✅ Tugas Saya</h1>
    <p style="color:#64748B; font-size:13px; margin:0 0 20px 0;">Tugas yang ditugaskan langsung ke Anda, dan tugas rutin posisi Anda</p>

    @if(session('success'))
    <div style="background:#F0FDF4; border:1px solid #BBF7D0; color:#166534; padding:10px 14px; border-radius:10px; font-size:13px; margin-bottom:16px;">✓ {{ session('success') }}</div>
    @endif

    <div style="margin-bottom:28px;">
        <h2 style="font-size:15px; font-weight:700; color:#1e293b; margin:0 0 10px 0;">Tugas Saya</h2>
        <div style="display:flex; flex-direction:column; gap:10px;">
            @forelse($myTasks as $task)
            <div style="background:white; border:1.5px solid #E2E8F0; border-radius:12px; padding:14px 16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <div>
                    <div style="font-weight:700; color:#1e293b; font-size:13.5px;">{{ $task->title }}</div>
                    @if($task->project)<div style="font-size:11px; color:#7C3AED; font-weight:600; margin-top:2px;">📁 {{ $task->project->name }}</div>@endif
                    @if($task->description)<div style="font-size:12px; color:#64748B; margin-top:4px;">{{ $task->description }}</div>@endif
                    @if($task->due_date)
                    <div style="font-size:11.5px; margin-top:6px; color:{{ $task->due_date->isPast() && $task->status !== 'done' ? '#DC2626' : '#94A3B8' }};">
                        Deadline: {{ $task->due_date->format('d M Y') }}
                    </div>
                    @endif
                </div>
                <form method="POST" action="{{ route('tasks.my.status', $task) }}" style="display:flex; align-items:center; gap:6px;">
                    @csrf @method('PATCH')
                    <select name="status" onchange="this.form.submit()" style="border:1.5px solid #E2E8F0; border-radius:8px; padding:6px 10px; font-size:12.5px;">
                        <option value="open" @selected($task->status === 'open')>Open</option>
                        <option value="in_progress" @selected($task->status === 'in_progress')>In Progress</option>
                        <option value="done" @selected($task->status === 'done')>Done</option>
                    </select>
                </form>
            </div>
            @empty
            <div style="text-align:center; color:#94A3B8; padding:24px; background:#FAFAFA; border-radius:12px; font-size:13px;">Belum ada tugas untuk Anda.</div>
            @endforelse
        </div>
    </div>

    <div>
        <h2 style="font-size:15px; font-weight:700; color:#1e293b; margin:0 0 10px 0;">Tugas Rutin Posisi Saya</h2>
        <div style="display:flex; flex-direction:column; gap:10px;">
            @forelse($standingTasks as $task)
            <div style="background:white; border:1.5px solid #E2E8F0; border-radius:12px; padding:14px 16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <div>
                    <div style="font-weight:700; color:#1e293b; font-size:13.5px;">{{ $task->title }}</div>
                    <div style="font-size:11px; color:#B45309; font-weight:600; margin-top:2px;">🧩 Tugas untuk semua pemegang posisi ini</div>
                    @if($task->description)<div style="font-size:12px; color:#64748B; margin-top:4px;">{{ $task->description }}</div>@endif
                </div>
                <form method="POST" action="{{ route('tasks.my.status', $task) }}" style="display:flex; align-items:center; gap:6px;">
                    @csrf @method('PATCH')
                    <select name="status" onchange="this.form.submit()" style="border:1.5px solid #E2E8F0; border-radius:8px; padding:6px 10px; font-size:12.5px;">
                        <option value="open" @selected($task->status === 'open')>Open</option>
                        <option value="in_progress" @selected($task->status === 'in_progress')>In Progress</option>
                        <option value="done" @selected($task->status === 'done')>Done</option>
                    </select>
                </form>
            </div>
            @empty
            <div style="text-align:center; color:#94A3B8; padding:24px; background:#FAFAFA; border-radius:12px; font-size:13px;">Belum ada tugas rutin untuk posisi Anda.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
