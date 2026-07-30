@extends('layouts.app')

@section('content')
@if(!empty($moduleWarning))
  <div class="mb-4 rounded border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $moduleWarning }}</div>
@endif
<div class="bg-white border rounded-lg p-4 space-y-6">
  <div>
    <h1 class="text-lg font-semibold">Import Soal {{ $typeLabel }}</h1>
    <p class="text-sm text-slate-600">Import bank soal {{ $typeLabel }} dari file CSV/XLSX ke Form Dinamis recruitment.</p>
  </div>

  @if(session('success'))
    <div class="rounded border border-green-200 bg-green-50 p-3 text-sm text-green-800">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>
  @endif
  @if($errors->any())
    <div class="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</div>
  @endif

  @if(session('import_summary'))
    @php($summary = session('import_summary'))
    <div class="rounded border border-blue-200 bg-blue-50 p-4 space-y-2">
      <h2 class="text-sm font-semibold text-blue-900">Ringkasan Import</h2>
      <div class="grid grid-cols-1 gap-2 text-sm text-blue-900 md:grid-cols-3">
        <div>Pertanyaan diimport: <b>{{ $summary['imported_questions'] ?? 0 }}</b></div>
        <div>Opsi diimport: <b>{{ $summary['imported_options'] ?? 0 }}</b></div>
        <div>Baris diskip: <b>{{ $summary['skipped_rows'] ?? 0 }}</b></div>
      </div>
      @if(!empty($summary['form_id']))
        <a href="{{ route('hrd.forms.edit', $summary['form_id']) }}" class="inline-block text-sm text-blue-800 underline">Buka Form Builder: {{ $summary['form_name'] ?? ('Form ' . $typeLabel) }}</a>
      @endif
      @if(!empty($summary['warnings']))
        <div class="mt-2 border-t border-amber-200 pt-2">
          <p class="text-sm font-semibold text-amber-900">Warning Header</p>
          <ul class="mt-1 text-xs text-amber-900 list-disc ml-5 space-y-1">
            @foreach($summary['warnings'] as $item)
              <li>{{ $item }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      @if(!empty($summary['row_errors']))
        <div class="mt-2 border-t border-blue-200 pt-2">
          <p class="text-sm font-semibold text-blue-900">Detail Error Baris</p>
          <ul class="ml-5 mt-1 max-h-48 list-disc space-y-1 overflow-auto text-xs text-blue-900">
            @foreach($summary['row_errors'] as $item)
              <li>Baris {{ $item['row'] ?? '-' }}: {{ $item['message'] ?? '-' }}</li>
            @endforeach
          </ul>
        </div>
      @endif
    </div>
  @endif

  <section class="space-y-3">
    <h2 class="text-sm font-semibold">A. Panduan Format Template</h2>
    <div class="rounded border bg-slate-50 p-3 text-sm text-slate-700 space-y-2">
      <p>Gunakan header kolom persis seperti template resmi berikut:</p>
      <code class="block rounded bg-slate-900 p-2 text-xs text-slate-100 break-all">{{ implode(', ', $templateHeaders) }}</code>
      <ul class="ml-5 list-disc space-y-1 text-xs">
        <li><b>question_text</b> dan <b>question_type</b> wajib diisi.</li>
        <li>Untuk <b>radio/dropdown/checkbox</b>, minimal isi <b>option_1</b> dan <b>option_2</b>.</li>
        <li><b>weight_1..weight_5</b> boleh kosong, default 0.</li>
        <li><b>correct_index</b> (1..5) dipakai untuk kunci single choice.</li>
        <li>Untuk short_text/paragraph, kolom option/weight/correct_index boleh kosong.</li>
      </ul>
    </div>
  </section>

  <section class="space-y-3">
    <h2 class="text-sm font-semibold">B. Download Template</h2>
    <div class="flex flex-wrap gap-2">
      <a href="{{ route('hrd.import.choice.template.csv', ['type' => $type]) }}" class="px-3 py-2 rounded border text-sm hover:bg-slate-50">Download Template CSV</a>
      <a href="{{ route('hrd.import.choice.template.xlsx', ['type' => $type]) }}" class="px-3 py-2 rounded bg-slate-900 text-white text-sm">Download Template XLSX</a>
    </div>
  </section>

  <section class="space-y-3">
    <h2 class="text-sm font-semibold">C. Upload & Import</h2>
    <form method="POST" action="{{ route('hrd.import.choice.store', ['type' => $type]) }}" enctype="multipart/form-data" class="space-y-4 border rounded p-4">
      @csrf

      <div>
        <label class="block text-sm font-medium mb-1">File Import <span class="text-red-600">*</span></label>
        <input type="file" name="file" accept=".csv,.xlsx" class="block w-full text-sm border rounded px-3 py-2" required>
      </div>

      <div class="space-y-2">
        <label class="block text-sm font-medium">Target Import <span class="text-red-600">*</span></label>
        <label class="flex items-start gap-2 text-sm">
          <input type="radio" name="target_mode" value="create" {{ old('target_mode', 'create') === 'create' ? 'checked' : '' }}>
          <span>Create new form {{ $typeLabel }}</span>
        </label>
        <label class="flex items-start gap-2 text-sm">
          <input type="radio" name="target_mode" value="append" {{ old('target_mode') === 'append' ? 'checked' : '' }}>
          <span>Append ke form {{ $typeLabel }} existing</span>
        </label>
      </div>

      <div id="createFields" class="grid grid-cols-1 gap-3 md:grid-cols-2">
        <div>
          <label class="block text-sm font-medium mb-1">Nama Form {{ $typeLabel }} Baru</label>
          <input type="text" name="form_name" value="{{ old('form_name') }}" class="block w-full text-sm border rounded px-3 py-2">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Durasi (menit)</label>
          <input type="number" name="duration_minutes" value="{{ old('duration_minutes', 30) }}" min="1" max="300" class="block w-full text-sm border rounded px-3 py-2">
        </div>
      </div>

      <div id="appendFields" class="hidden">
        <label class="block text-sm font-medium mb-1">Pilih Form {{ $typeLabel }} Existing</label>
        <select name="form_id" class="block w-full text-sm border rounded px-3 py-2">
          <option value="">-- Pilih Form {{ $typeLabel }} --</option>
          @foreach($forms as $form)
            <option value="{{ $form->id }}" {{ (string) old('form_id') === (string) $form->id ? 'selected' : '' }}>{{ $form->name }} ({{ $form->duration_minutes ?? '-' }} menit)</option>
          @endforeach
        </select>
      </div>

      <div>
        <button type="submit" class="px-4 py-2 rounded bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">Import</button>
      </div>
    </form>
  </section>
</div>

<script>
  (function () {
    const radios = document.querySelectorAll('input[name="target_mode"]');
    const createFields = document.getElementById('createFields');
    const appendFields = document.getElementById('appendFields');

    function toggleMode() {
      const selected = document.querySelector('input[name="target_mode"]:checked')?.value || 'create';
      if (selected === 'append') {
        createFields.classList.add('hidden');
        appendFields.classList.remove('hidden');
      } else {
        appendFields.classList.add('hidden');
        createFields.classList.remove('hidden');
      }
    }

    radios.forEach((radio) => radio.addEventListener('change', toggleMode));
    toggleMode();
  })();
</script>
@endsection


