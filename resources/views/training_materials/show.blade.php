@extends('layouts.app')
@section('content')
<div class="bg-white border rounded-lg p-4 space-y-4">
  <div>
    <div class="text-lg font-semibold">{{ $material->title }}</div>
    <div class="text-sm text-slate-600">{{ $material->category }} | Audience: {{ ucfirst($material->audience_scope ?? 'general') }} | {{ $material->duration_minutes }} menit</div>
  </div>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
    <div><span class="text-slate-500">Mentor:</span> {{ $material->mentor?->name ?? '-' }}</div>
    <div><span class="text-slate-500">Departemen:</span> {{ $material->department?->name ?? '-' }}</div>
    <div><span class="text-slate-500">Jabatan:</span> {{ $material->position?->name ?? '-' }}</div>
    <div><span class="text-slate-500">Pass Score:</span> {{ $material->pass_score ?? '-' }}</div>
    <div><span class="text-slate-500">Pretest:</span> {{ $material->pretestForm?->name ?? '-' }}</div>
    <div><span class="text-slate-500">Posttest:</span> {{ $material->posttestForm?->name ?? '-' }}</div>
    <div><span class="text-slate-500">YouTube:</span> {{ $material->youtube_url ?? '-' }}</div>
    <div><span class="text-slate-500">Content URL:</span> {{ $material->content_source_url ?? '-' }}</div>
  </div>
  <div class="whitespace-pre-line text-sm">{{ $material->description ?: '-' }}</div>
  <div>
    <div class="font-medium mb-2">Dipakai di Program</div>
    <div class="space-y-2">@forelse($material->programs as $program)<div class="border rounded p-3 text-sm">{{ $program->name }}</div>@empty<div class="text-sm text-slate-500">Belum dipakai di program mana pun.</div>@endforelse</div>
  </div>
</div>
@endsection
