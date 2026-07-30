@extends('layouts.app')

@section('content')
@if(!empty($moduleWarning))
  <div class="mb-4 rounded border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $moduleWarning }}</div>
@endif
<div class="bg-white border rounded-lg p-4 space-y-6">
  <div>
    <h1 class="text-lg font-semibold">Import Soal DISC</h1>
    <p class="text-sm text-slate-600">Import bank soal DISC dual-axis dari file CSV/XLSX ke Form Dinamis recruitment.</p>
  </div>

  @if(session('success'))
    <div class="rounded border border-green-200 bg-green-50 p-3 text-sm text-green-800">
      {{ session('success') }}
    </div>
  @endif

  @if(session('error'))
    <div class="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-800">
      {{ session('error') }}
    </div>
  @endif

  @if($errors->any())
    <div class="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-800">
      {{ $errors->first() }}
    </div>
  @endif

  @if(session('import_summary'))
    @php($summary = session('import_summary'))
    <div class="rounded border border-blue-200 bg-blue-50 p-4 space-y-2">
      <h2 class="text-sm font-semibold text-blue-900">Ringkasan Import</h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-2 text-sm text-blue-900">
        <div>Pertanyaan diimport: <b>{{ $summary['imported_questions'] ?? 0 }}</b></div>
        <div>Opsi diimport: <b>{{ $summary['imported_options'] ?? 0 }}</b></div>
        <div>Baris diskip: <b>{{ $summary['skipped_rows'] ?? 0 }}</b></div>
      </div>
      @if(!empty($summary['form_id']))
        <a href="{{ route('hrd.forms.edit', $summary['form_id']) }}" class="inline-block text-sm underline text-blue-800">Buka Form Builder: {{ $summary['form_name'] ?? 'Form DISC' }}</a>
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
          <ul class="mt-1 text-xs text-blue-900 list-disc ml-5 space-y-1 max-h-48 overflow-auto">
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
      <p>Gunakan header kolom persis seperti template resmi berikut. Template baru DISC memakai pasangan axis <b>Most</b> dan <b>Least</b> untuk setiap opsi:</p>
      <code class="block rounded bg-slate-900 text-slate-100 p-2 text-xs break-all">{{ implode(', ', $templateHeaders) }}</code>
      <ul class="list-disc ml-5 space-y-1 text-xs">
        <li><b>Tipe jawaban</b> hanya boleh <b>radio</b> atau <b>dropdown</b>.</li>
        <li>Minimal isi <b>Opsi 1</b> dan <b>Opsi 2</b>.</li>
        <li>Setiap opsi terisi wajib punya <b>Sumbu Most</b> dan <b>Sumbu Least</b> valid: <b>D / I / S / C</b>.</li>
        <li>Kolom <b>Wajib diisi</b> gunakan nilai: <b>Wajib</b> atau <b>Tidak Wajib</b>.</li>
      </ul>
    </div>
  </section>

  <section class="space-y-3">
    <h2 class="text-sm font-semibold">B. Download Template</h2>
    <div class="flex flex-wrap gap-2">
      <a href="{{ route('hrd.import.disc.template.csv') }}" class="px-3 py-2 rounded border text-sm hover:bg-slate-50">Download Template CSV</a>
      <a href="{{ route('hrd.import.disc.template.xlsx') }}" class="px-3 py-2 rounded bg-slate-900 text-white text-sm">Download Template XLSX</a>
    </div>
  </section>

  <section class="space-y-3">
    <h2 class="text-sm font-semibold">C. Upload & Import</h2>
    <form method="POST" action="{{ route('hrd.import.disc.store') }}" enctype="multipart/form-data" class="space-y-4 border rounded p-4">
      @csrf

      <div>
        <label class="block text-sm font-medium mb-1">File Import <span class="text-red-600">*</span></label>
        <input type="file" name="file" accept=".csv,.xlsx" class="block w-full text-sm border rounded px-3 py-2" required>
        <p class="text-xs text-slate-500 mt-1">Format didukung: CSV, XLSX.</p>
      </div>

      <div class="space-y-2">
        <label class="block text-sm font-medium">Target Import <span class="text-red-600">*</span></label>
        <label class="flex items-start gap-2 text-sm">
          <input type="radio" name="target_mode" value="create" {{ old('target_mode', 'create') === 'create' ? 'checked' : '' }}>
          <span>Create new form DISC</span>
        </label>
        <label class="flex items-start gap-2 text-sm">
          <input type="radio" name="target_mode" value="append" {{ old('target_mode') === 'append' ? 'checked' : '' }}>
          <span>Append ke form DISC existing</span>
        </label>
      </div>

      <div id="createFields" class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-medium mb-1">Nama Form DISC Baru</label>
          <input type="text" name="form_name" value="{{ old('form_name') }}" class="block w-full text-sm border rounded px-3 py-2" placeholder="Contoh: DISC Batch Maret 2026">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Durasi (menit)</label>
          <input type="number" name="duration_minutes" value="{{ old('duration_minutes', 20) }}" min="1" max="300" class="block w-full text-sm border rounded px-3 py-2">
        </div>
      </div>

      <div id="appendFields" class="hidden">
        <label class="block text-sm font-medium mb-1">Pilih Form DISC Existing</label>
        <select name="form_id" class="block w-full text-sm border rounded px-3 py-2">
          <option value="">-- Pilih Form DISC --</option>
          @foreach($discForms as $form)
            <option value="{{ $form->id }}" {{ (string) old('form_id') === (string) $form->id ? 'selected' : '' }}>
              {{ $form->name }} ({{ $form->duration_minutes ?? '-' }} menit)
            </option>
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



