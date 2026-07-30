@extends('layouts.app')
@section('content')
<div class="space-y-4">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-lg font-semibold">Master Training Materials</h1>
      <p class="text-sm text-slate-600">Repository materi LMS, pretest/posttest, mentor, dan target audience.</p>
    </div>
    <div class="flex gap-2">
      <a class="px-3 py-2 rounded border" href="{{ route('training-programs.index') }}">Program LMS</a>
      <a class="px-3 py-2 rounded bg-slate-900 text-white" href="{{ route('training-materials.create') }}">+ Tambah Materi</a>
    </div>
  </div>
  <div class="overflow-auto border rounded bg-white">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-100"><tr><th class="text-left p-2">Judul</th><th class="text-left p-2">Kategori</th><th class="text-left p-2">Audience</th><th class="text-left p-2">Mentor</th><th class="text-left p-2">Pre/Post</th><th class="text-left p-2">Aksi</th></tr></thead>
      <tbody>
        @foreach($materials as $m)
          <tr class="border-t">
            <td class="p-2"><div class="font-medium">{{ $m->title }}</div><div class="text-xs text-slate-500">{{ $m->duration_minutes }} menit</div></td>
            <td class="p-2">{{ $m->category }}</td>
            <td class="p-2">{{ ucfirst($m->audience_scope ?? 'general') }}</td>
            <td class="p-2">{{ $m->mentor?->name ?? '-' }}</td>
            <td class="p-2 text-xs">{{ $m->pretestForm?->name ?? '-' }} / {{ $m->posttestForm?->name ?? '-' }}</td>
            <td class="p-2"><a class="underline" href="{{ route('training-materials.show',$m) }}">Detail</a> <span class="mx-1">|</span> <a class="underline" href="{{ route('training-materials.edit',$m) }}">Edit</a></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  <div>{{ $materials->links() }}</div>
</div>
@endsection
