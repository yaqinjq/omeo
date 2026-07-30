@extends('layouts.app')

@section('content')
<div class="bg-white border rounded-lg p-4 space-y-4">
  @if(!empty($moduleWarning))
    <div class="rounded border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $moduleWarning }}</div>
  @endif
  <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
    <div>
      <h1 class="text-lg font-semibold">Form Dinamis Assessment</h1>
      <p class="text-sm text-slate-600">Builder scalable untuk IQ, DISC dual-axis, TIU, Diferensial, FAT, dan Custom.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <a href="{{ route('hrd.import.iq.index') }}" class="px-3 py-2 rounded border text-sm hover:bg-slate-50">Import IQ</a>
      <a href="{{ route('hrd.import.disc.index') }}" class="px-3 py-2 rounded border text-sm hover:bg-slate-50">Import DISC</a>
      @foreach(($importableChoiceTypes ?? []) as $type => $label)
        @if($type !== \App\Models\AssessmentForm::TYPE_IQ)
          <a href="{{ route('hrd.import.choice.index', ['type' => $type]) }}" class="px-3 py-2 rounded border text-sm hover:bg-slate-50">Import {{ $label }}</a>
        @endif
      @endforeach
      @if(($schemaReady ?? true))
        <a href="{{ route('hrd.forms.create') }}" class="px-3 py-2 rounded bg-slate-900 text-white">+ Form Baru</a>
      @else
        <span class="px-3 py-2 rounded border text-sm text-slate-500">Schema builder belum siap</span>
      @endif
    </div>
  </div>

  <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
    @foreach(($builderTypes ?? []) as $key => $label)
      <div class="rounded border bg-slate-50 p-3 text-sm">
        <div class="font-semibold">{{ $label }}</div>
        <div class="text-xs text-slate-500">{{ $forms->getCollection()->where('type', $key)->count() }} form di halaman ini</div>
      </div>
    @endforeach
  </div>

  <div class="overflow-auto border rounded">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-100">
        <tr>
          <th class="text-left p-2">Nama</th>
          <th class="text-left p-2">Tipe</th>
          <th class="text-left p-2">Audience</th>
          <th class="text-left p-2">Durasi</th>
          <th class="text-left p-2">Soal</th>
          <th class="text-left p-2">Status</th>
          <th class="text-left p-2">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($forms as $form)
          <tr class="border-t">
            <td class="p-2">
              <div class="font-medium">{{ $form->name }}</div>
              <div class="text-xs text-slate-500">{{ $form->code }}</div>
            </td>
            <td class="p-2 uppercase">{{ \App\Models\AssessmentForm::labelFor($form->type) }}</td>
            <td class="p-2">
              <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $form->audienceDepartmentKey() === 'general' ? 'bg-slate-100 text-slate-700' : 'bg-blue-100 text-blue-700' }}">
                {{ $form->audienceDepartmentName() }}
              </span>
            </td>
            <td class="p-2">{{ $form->duration_minutes ? $form->duration_minutes . ' menit' : '-' }}</td>
            <td class="p-2">{{ $form->questions_count }}</td>
            <td class="p-2">
              <span class="px-2 py-1 rounded text-xs {{ $form->is_active ? 'bg-green-100 text-green-700' : 'bg-slate-200 text-slate-700' }}">
                {{ $form->is_active ? 'Active' : 'Nonaktif' }}
              </span>
            </td>
            <td class="p-2 space-x-2">
              <a href="{{ route('hrd.forms.edit', $form) }}" class="underline">Builder</a>
              <form method="POST" action="{{ route('hrd.forms.toggle', $form) }}" class="inline">
                @csrf
                <button class="underline">{{ $form->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="p-3 text-slate-500">Belum ada form.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div>{{ $forms->links() }}</div>
</div>
@endsection
