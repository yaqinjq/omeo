@extends('layouts.app')
@section('content')
<div class="bg-white border rounded-lg p-4 max-w-3xl">
  <div class="text-lg font-semibold mb-3">Tambah Indikator</div>
  <form method="POST" action="{{ route('appraisal-indicators.store') }}">
    @csrf
    <div class="grid grid-cols-1 gap-3">
  <div>
    <label class="text-sm">Template *</label>
    <select name="template_id" class="w-full border rounded p-2" required>
      @foreach($templates as $t)
        <option value="{{ $t->id }}" @selected((int) old('template_id', $selectedTemplateId) === $t->id)>{{ $t->name }}</option>
      @endforeach
    </select>
  </div>
  <div>
    <label class="text-sm">Category *</label>
    <input name="category" value="{{ old('category', $indicator->category ?? '') }}" class="w-full border rounded p-2" required>
  </div>
  <div>
    <label class="text-sm">Question *</label>
    <textarea name="question" class="w-full border rounded p-2" rows="4" required>{{ old('question', $indicator->question ?? '') }}</textarea>
  </div>
  <div>
    <label class="text-sm">Deskripsi Kriteria (opsional)</label>
    <textarea name="description" class="w-full border rounded p-2" rows="2" placeholder="Penjelasan singkat mengenai kriteria ini">{{ old('description', $indicator->description ?? '') }}</textarea>
  </div>
  <div>
    <label class="text-sm">Weight *</label>
    <input type="number" name="weight" value="{{ old('weight', $indicator->weight ?? 1) }}" class="w-full border rounded p-2" required>
  </div>
</div>

    <div class="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-3">
      <div class="text-sm font-semibold text-slate-700 mb-1">Panduan Skor per Bintang (opsional)</div>
      <p class="text-xs text-slate-500 mb-3">Isi keterangan untuk tiap skor supaya evaluator punya acuan yang jelas saat menilai kriteria ini. Kosongkan jika tidak perlu.</p>
      <div class="space-y-2">
        @for($star = 5; $star >= 1; $star--)
        <div class="flex gap-2 items-start">
          <div class="w-20 flex-shrink-0 pt-2 text-xs font-semibold text-slate-600">Skor {{ $star }}</div>
          <textarea name="rubric[{{ $star }}]" rows="2" class="w-full border rounded p-2 text-sm" placeholder="Contoh: performa sangat tinggi, bekerja mandiri, proaktif...">{{ old("rubric.$star") }}</textarea>
        </div>
        @endfor
      </div>
    </div>

    <div class="mt-4 flex gap-2">
      <button class="px-4 py-2 rounded bg-slate-900 text-white">Simpan</button>
      <a class="px-4 py-2 rounded border" href="{{ route('appraisal-indicators.index') }}">Kembali</a>
    </div>
  </form>
</div>
@endsection
