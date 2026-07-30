@extends('layouts.app')

@php
  use App\Models\AssessmentForm;
  use App\Models\FormQuestion;
  use Illuminate\Support\Facades\Storage;

  $isDisc = $form->type === AssessmentForm::TYPE_DISC;
  $isWeightedType = AssessmentForm::isObjectiveScoreType($form->type);

  $allTypes = [
    FormQuestion::TYPE_SHORT_TEXT => 'Jawaban Singkat',
    FormQuestion::TYPE_PARAGRAPH => 'Paragraf',
    FormQuestion::TYPE_RADIO => 'Pilihan Ganda',
    FormQuestion::TYPE_CHECKBOX => 'Kotak Centang',
    FormQuestion::TYPE_DROPDOWN => 'Dropdown',
    FormQuestion::TYPE_RATING => 'Rating',
    FormQuestion::TYPE_LINEAR_SCALE => 'Linear Scale',
    FormQuestion::TYPE_IMAGE_UPLOAD => 'Upload Foto',
    FormQuestion::TYPE_FILE_UPLOAD => 'Upload File',
  ];

  $allowedTypes = $isDisc
    ? [
      FormQuestion::TYPE_RADIO => $allTypes[FormQuestion::TYPE_RADIO],
      FormQuestion::TYPE_DROPDOWN => $allTypes[FormQuestion::TYPE_DROPDOWN],
    ]
    : $allTypes;

  $discAxisJson = json_encode($discAxisOptions ?? ['D', 'I', 'S', 'C']);
@endphp

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
  <div class="rounded-2xl border border-indigo-200 bg-gradient-to-r from-indigo-50 to-white p-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 class="text-xl font-semibold text-slate-900">Builder Form: {{ $form->name }}</h1>
        <p class="mt-1 text-sm text-slate-600">Kode: {{ $form->code }} | Tipe: <span class="font-medium">{{ AssessmentForm::labelFor($form->type) }}</span></p>
      </div>
      <div class="flex items-center gap-2">
        <a href="#add-question" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">+ Tambah Pertanyaan</a>
        <a href="{{ route('hrd.forms.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Kembali</a>
      </div>
    </div>
  </div>

  <div class="rounded-2xl border border-slate-200 bg-white p-5">
    <form method="POST" action="{{ route('hrd.forms.update', $form) }}" class="space-y-4">
      @csrf
      @method('PUT')
      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Nama Form</label>
        <input type="text" name="name" value="{{ old('name', $form->name) }}" class="w-full rounded border px-3 py-2" required>
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Deskripsi</label>
        <textarea name="description" rows="2" class="w-full rounded border px-3 py-2">{{ old('description', $form->description) }}</textarea>
      </div>
      <div class="grid gap-3 md:grid-cols-2">
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700">Tipe Form</label>
          <select name="type" class="w-full rounded border px-3 py-2">
            @foreach(($builderTypes ?? []) as $key => $label)
              <option value="{{ $key }}" @selected(old('type', $form->type) === $key)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700">Durasi (menit)</label>
          <input type="number" min="1" max="240" name="duration_minutes" value="{{ old('duration_minutes', $form->duration_minutes) }}" class="w-full rounded border px-3 py-2">
        </div>
      </div>
      <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
        <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
          <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $form->is_active))>
          Form aktif
        </label>
        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white">Simpan Pengaturan</button>
      </div>
    </form>
  </div>

  <div class="space-y-4">
    @forelse($form->questions as $question)
      @php
        $isChoice = in_array($question->question_type, [FormQuestion::TYPE_RADIO, FormQuestion::TYPE_CHECKBOX, FormQuestion::TYPE_DROPDOWN], true);
        $isScale = in_array($question->question_type, [FormQuestion::TYPE_RATING, FormQuestion::TYPE_LINEAR_SCALE], true);
        $isUpload = in_array($question->question_type, FormQuestion::uploadTypes(), true);
      @endphp
      <div class="rounded-2xl border border-slate-200 bg-white p-4 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div>
            <div class="text-xs uppercase tracking-wide text-slate-500">Pertanyaan #{{ $question->position }}</div>
            <div class="font-semibold text-slate-900">{{ $question->question_text }}</div>
          </div>
          <div class="flex items-center gap-1">
            <form method="POST" action="{{ route('hrd.forms.questions.move', [$form, $question]) }}">@csrf<input type="hidden" name="direction" value="up"><button class="rounded border px-2 py-1 text-xs">Up</button></form>
            <form method="POST" action="{{ route('hrd.forms.questions.move', [$form, $question]) }}">@csrf<input type="hidden" name="direction" value="down"><button class="rounded border px-2 py-1 text-xs">Down</button></form>
            <form method="POST" action="{{ route('hrd.forms.questions.duplicate', [$form, $question]) }}">@csrf<button class="rounded border px-2 py-1 text-xs">Copy</button></form>
            <form method="POST" action="{{ route('hrd.forms.questions.destroy', [$form, $question]) }}" onsubmit="return confirm('Hapus pertanyaan ini?')">@csrf @method('DELETE')<button class="rounded border border-rose-300 px-2 py-1 text-xs text-rose-600">Hapus</button></form>
          </div>
        </div>

        <form method="POST" action="{{ route('hrd.forms.questions.update', [$form, $question]) }}" enctype="multipart/form-data" class="space-y-4 border-t pt-4">
          @csrf
          @method('PUT')

          <div class="grid gap-3 md:grid-cols-[1fr_220px]">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Judul Pertanyaan</label>
              <input type="text" name="question_text" value="{{ $question->question_text }}" class="w-full rounded border px-3 py-2" required>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Tipe Pertanyaan</label>
              <select name="question_type" class="w-full rounded border px-3 py-2">
                @foreach($allowedTypes as $value => $label)
                  <option value="{{ $value }}" @selected($question->question_type === $value)>{{ $label }}</option>
                @endforeach
              </select>
            </div>
          </div>

          @if($isDisc)
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Mode DISC</label>
              <select name="settings[disc_mode]" class="w-full rounded border px-3 py-2 md:max-w-xs">
                <option value="dual_axis" @selected(data_get($question->settings, 'disc_mode', 'dual_axis') === 'dual_axis')>Dual Axis (Most + Least)</option>
                <option value="single_axis" @selected(data_get($question->settings, 'disc_mode') === 'single_axis')>Legacy Single Axis</option>
              </select>
            </div>
          @endif

          @if($isScale)
            <div class="grid gap-2 md:grid-cols-2">
              <input type="number" name="settings[min]" value="{{ data_get($question->settings, 'min') }}" class="w-full rounded border px-3 py-2" placeholder="Skala min">
              <input type="number" name="settings[max]" value="{{ data_get($question->settings, 'max') }}" class="w-full rounded border px-3 py-2" placeholder="Skala max">
              <input type="text" name="settings[min_label]" value="{{ data_get($question->settings, 'min_label') }}" class="w-full rounded border px-3 py-2" placeholder="Label min">
              <input type="text" name="settings[max_label]" value="{{ data_get($question->settings, 'max_label') }}" class="w-full rounded border px-3 py-2" placeholder="Label max">
            </div>
          @endif

          <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-3">
            <div class="text-sm font-semibold text-slate-800">Media Pendukung Soal</div>
            <div class="grid gap-3 md:grid-cols-2">
              <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Judul Media</label>
                <input type="text" name="settings[media_title]" value="{{ data_get($question->settings, 'media_title') }}" class="w-full rounded border px-3 py-2" placeholder="Contoh: Observasi Produk / Foto Layout">
              </div>
              <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Link Media</label>
                <input type="url" name="settings[media_url]" value="{{ data_get($question->settings, 'media_url') }}" class="w-full rounded border px-3 py-2" placeholder="https://...">
              </div>
              <div class="md:col-span-2">
                <label class="mb-1 block text-xs font-medium text-slate-600">Link YouTube</label>
                <input type="url" name="settings[youtube_url]" value="{{ data_get($question->settings, 'youtube_url') }}" class="w-full rounded border px-3 py-2" placeholder="https://www.youtube.com/watch?v=...">
              </div>
            </div>
            @if(data_get($question->settings, 'media_url') || data_get($question->settings, 'youtube_url'))
              <div class="text-xs text-slate-500">Media ini akan tampil di halaman peserta sebagai bahan soal training.</div>
            @endif
          </div>

          @if($isUpload)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 space-y-3">
              <div class="text-sm font-semibold text-amber-900">Aturan Upload Jawaban</div>
              <div class="grid gap-3 md:grid-cols-2">
                <div>
                  <label class="mb-1 block text-xs font-medium text-amber-800">Format yang Diizinkan</label>
                  <input type="text" name="settings[answer_accept]" value="{{ data_get($question->settings, 'answer_accept', $question->question_type === FormQuestion::TYPE_IMAGE_UPLOAD ? 'image/*' : '.pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.mp4') }}" class="w-full rounded border px-3 py-2" placeholder="image/* atau .pdf,.jpg,.png">
                </div>
                <div>
                  <label class="mb-1 block text-xs font-medium text-amber-800">Maksimum Ukuran (KB)</label>
                  <input type="number" min="64" max="10240" name="settings[answer_max_kb]" value="{{ data_get($question->settings, 'answer_max_kb', 3072) }}" class="w-full rounded border px-3 py-2">
                </div>
              </div>
            </div>
          @endif

          <div class="space-y-2">
            <label class="block text-xs font-medium text-slate-600">Gambar Soal (opsional)</label>
            @if($question->question_image_path)
              <div class="rounded-lg border border-slate-300 bg-slate-50 p-2">
                <img src="{{ Storage::disk('public')->url($question->question_image_path) }}" alt="Soal {{ $question->id }}" class="max-h-64 rounded">
                <label class="mt-2 inline-flex items-center gap-2 text-xs text-rose-600">
                  <input type="checkbox" name="remove_question_image" value="1">
                  Hapus gambar ini
                </label>
              </div>
            @endif
            <input type="file" name="question_image" accept="image/png,image/jpeg,image/jpg,image/webp" class="block w-full text-xs">
          </div>

          @if($isChoice)
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 space-y-3">
              <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-slate-700">Opsi Jawaban</h3>
                <button type="button" class="rounded border px-2 py-1 text-xs" data-add-option="question-{{ $question->id }}">+ Opsi</button>
              </div>

              @if($isDisc)
                <div class="text-xs text-slate-500">Setiap opsi DISC harus punya axis <b>Most</b> dan <b>Least</b>. Field legacy tetap disimpan melalui <b>Most</b> agar backward compatible.</div>
              @elseif($isWeightedType)
                <div class="text-xs text-slate-500">Jenis {{ AssessmentForm::labelFor($form->type) }} memakai bobot per opsi. Anda juga bisa menandai jawaban kunci bila diperlukan.</div>
              @endif

              <div class="space-y-2" id="option-list-question-{{ $question->id }}">
                @foreach($question->options as $idx => $option)
                  @php
                    $discAxisMost = data_get($option->meta, 'disc_axis_most') ?: data_get($option->meta, 'disc_axis');
                    $discAxisLeast = data_get($option->meta, 'disc_axis_least') ?: data_get($option->meta, 'disc_axis');
                  @endphp
                  <div class="grid gap-2 md:grid-cols-12 items-end rounded-lg border border-slate-200 bg-white p-3 option-row">
                    <input type="hidden" name="options[{{ $idx }}][id]" value="{{ $option->id }}">
                    <div class="md:col-span-{{ $isDisc ? '4' : '6' }}">
                      <label class="text-xs text-slate-500">Teks Opsi</label>
                      <input type="text" name="options[{{ $idx }}][option_text]" value="{{ $option->option_text }}" class="w-full rounded border px-3 py-2" required>
                    </div>

                    @if($isDisc)
                      <div class="md:col-span-3">
                        <label class="text-xs text-slate-500">Axis Most</label>
                        <select name="options[{{ $idx }}][meta][disc_axis_most]" class="w-full rounded border px-3 py-2" required>
                          <option value="">Pilih</option>
                          @foreach(($discAxisOptions ?? ['D','I','S','C']) as $axis)
                            <option value="{{ $axis }}" @selected($discAxisMost === $axis)>{{ $axis }}</option>
                          @endforeach
                        </select>
                      </div>
                      <div class="md:col-span-3">
                        <label class="text-xs text-slate-500">Axis Least</label>
                        <select name="options[{{ $idx }}][meta][disc_axis_least]" class="w-full rounded border px-3 py-2" required>
                          <option value="">Pilih</option>
                          @foreach(($discAxisOptions ?? ['D','I','S','C']) as $axis)
                            <option value="{{ $axis }}" @selected($discAxisLeast === $axis)>{{ $axis }}</option>
                          @endforeach
                        </select>
                      </div>
                    @elseif($isWeightedType)
                      <div class="md:col-span-2">
                        <label class="text-xs text-slate-500">Bobot</label>
                        <input type="number" min="0" name="options[{{ $idx }}][weight]" value="{{ $option->weight }}" class="w-full rounded border px-3 py-2">
                      </div>
                      <label class="md:col-span-2 inline-flex items-center gap-2 text-xs text-slate-700 h-10">
                        <input type="hidden" name="options[{{ $idx }}][meta][is_correct]" value="0">
                        <input type="checkbox" name="options[{{ $idx }}][meta][is_correct]" value="1" @checked((bool) data_get($option->meta, 'is_correct'))>
                        Kunci
                      </label>
                    @endif

                    <div class="md:col-span-2 flex items-center justify-end gap-1">
                      <button type="button" class="rounded border px-2 py-1 text-xs" data-move="up">Up</button>
                      <button type="button" class="rounded border px-2 py-1 text-xs" data-move="down">Down</button>
                      <button type="button" class="rounded border border-rose-300 px-2 py-1 text-xs text-rose-600" data-delete-option>Hapus</button>
                    </div>
                  </div>
                @endforeach
              </div>
            </div>
          @endif

          <div class="flex items-center justify-between border-t pt-3">
            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
              <input type="checkbox" name="is_required" value="1" @checked($question->is_required)>
              Wajib diisi
            </label>
            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white">Simpan Pertanyaan</button>
          </div>
        </form>
      </div>
    @empty
      <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
        Belum ada pertanyaan. Klik <span class="font-medium">Tambah Pertanyaan</span> untuk mulai menyusun form.
      </div>
    @endforelse
  </div>

  <div id="add-question" class="rounded-2xl border border-indigo-200 bg-white p-5 shadow-sm">
    <h2 class="mb-4 text-lg font-semibold text-slate-900">+ Tambah Pertanyaan Baru</h2>

    <form id="new-question-form" method="POST" action="{{ route('hrd.forms.questions.store', $form) }}" enctype="multipart/form-data" class="space-y-4" data-form-type="{{ $form->type }}">
      @csrf

      <div class="grid gap-3 md:grid-cols-[1fr_220px]">
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700">Judul Pertanyaan</label>
          <input type="text" name="question_text" class="w-full rounded border px-3 py-2" required>
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700">Tipe Pertanyaan</label>
          <select name="question_type" class="w-full rounded border px-3 py-2" id="new-question-type">
            @foreach($allowedTypes as $value => $label)
              <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
      </div>

      @if($isDisc)
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700">Mode DISC</label>
          <select name="settings[disc_mode]" class="w-full rounded border px-3 py-2 md:max-w-xs">
            <option value="dual_axis" selected>Dual Axis (Most + Least)</option>
            <option value="single_axis">Legacy Single Axis</option>
          </select>
        </div>
      @endif

      <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-3">
        <div class="text-sm font-semibold text-slate-800">Media Pendukung Soal</div>
        <div class="grid gap-3 md:grid-cols-2">
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Judul Media</label>
            <input type="text" name="settings[media_title]" class="w-full rounded border px-3 py-2" placeholder="Contoh: Foto SOP / Link demonstrasi">
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Link Media</label>
            <input type="url" name="settings[media_url]" class="w-full rounded border px-3 py-2" placeholder="https://...">
          </div>
          <div class="md:col-span-2">
            <label class="mb-1 block text-xs font-medium text-slate-600">Link YouTube</label>
            <input type="url" name="settings[youtube_url]" class="w-full rounded border px-3 py-2" placeholder="https://www.youtube.com/watch?v=...">
          </div>
        </div>
      </div>

      <div>
        <label class="mb-1 block text-xs font-medium text-slate-600">Gambar Soal (opsional)</label>
        <input type="file" name="question_image" accept="image/png,image/jpeg,image/jpg,image/webp" class="block w-full text-xs">
      </div>

      <div class="grid gap-2 md:grid-cols-2 hidden" id="new-scale-section">
        <input type="number" name="settings[min]" class="w-full rounded border px-3 py-2" placeholder="Skala min">
        <input type="number" name="settings[max]" class="w-full rounded border px-3 py-2" placeholder="Skala max">
        <input type="text" name="settings[min_label]" class="w-full rounded border px-3 py-2" placeholder="Label min">
        <input type="text" name="settings[max_label]" class="w-full rounded border px-3 py-2" placeholder="Label max">
      </div>

      <div class="hidden rounded-xl border border-amber-200 bg-amber-50 p-4 space-y-3" id="new-upload-section">
        <div class="text-sm font-semibold text-amber-900">Aturan Upload Jawaban</div>
        <div class="grid gap-3 md:grid-cols-2">
          <div>
            <label class="mb-1 block text-xs font-medium text-amber-800">Format yang Diizinkan</label>
            <input type="text" name="settings[answer_accept]" class="w-full rounded border px-3 py-2" placeholder="image/* atau .pdf,.jpg,.png">
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-amber-800">Maksimum Ukuran (KB)</label>
            <input type="number" min="64" max="10240" name="settings[answer_max_kb]" class="w-full rounded border px-3 py-2" value="3072">
          </div>
        </div>
      </div>

      <div class="hidden rounded-lg border border-slate-200 bg-slate-50 p-3" id="new-choice-section">
        <div class="mb-2 flex items-center justify-between">
          <div class="text-sm font-medium text-slate-700">Opsi Jawaban (minimal 2)</div>
          <button type="button" class="rounded border px-2 py-1 text-xs" id="add-new-option-row">+ Opsi</button>
        </div>
        <div class="space-y-2" id="new-option-rows"></div>
      </div>

      <div class="flex items-center justify-between border-t pt-3">
        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
          <input type="checkbox" name="is_required" value="1">
          Wajib diisi
        </label>
        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white" type="submit">Tambah Pertanyaan</button>
      </div>
    </form>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const choiceTypes = ['radio', 'checkbox', 'dropdown'];
    const scaleTypes = ['rating', 'linear_scale'];
    const uploadTypes = ['image_upload', 'file_upload'];
    const formType = document.getElementById('new-question-form')?.dataset.formType || '';
    const discAxes = {!! $discAxisJson !!};

    document.querySelectorAll('[data-add-option]').forEach((button) => {
      button.addEventListener('click', function () {
        const key = this.getAttribute('data-add-option');
        const list = document.getElementById('option-list-' + key);
        if (!list) return;

        const index = list.querySelectorAll('.option-row').length;
        list.insertAdjacentHTML('beforeend', buildOptionRow(index, formType));
      });
    });

    document.addEventListener('click', function (event) {
      const deleteButton = event.target.closest('[data-delete-option]');
      if (deleteButton) {
        const row = deleteButton.closest('.option-row');
        const list = row?.parentElement;
        if (!row || !list) return;
        if (list.querySelectorAll('.option-row').length <= 2) return;
        row.remove();
        renumberList(list, formType);
        return;
      }

      const moveButton = event.target.closest('[data-move]');
      if (!moveButton) return;
      const row = moveButton.closest('.option-row');
      const list = row?.parentElement;
      if (!row || !list) return;
      const direction = moveButton.getAttribute('data-move');
      if (direction === 'up' && row.previousElementSibling) {
        list.insertBefore(row, row.previousElementSibling);
      }
      if (direction === 'down' && row.nextElementSibling) {
        list.insertBefore(row.nextElementSibling, row);
      }
      renumberList(list, formType);
    });

    const newQuestionType = document.getElementById('new-question-type');
    const newChoiceSection = document.getElementById('new-choice-section');
    const newScaleSection = document.getElementById('new-scale-section');
    const newUploadSection = document.getElementById('new-upload-section');
    const newRows = document.getElementById('new-option-rows');
    const addNewRowButton = document.getElementById('add-new-option-row');

    function ensureNewRows() {
      if (!newRows || !newQuestionType) return;
      if (!choiceTypes.includes(newQuestionType.value)) {
        newRows.innerHTML = '';
        return;
      }
      while (newRows.querySelectorAll('.option-row').length < 2) {
        const index = newRows.querySelectorAll('.option-row').length;
        newRows.insertAdjacentHTML('beforeend', buildOptionRow(index, formType));
      }
    }

    function toggleNewSections() {
      if (!newQuestionType) return;
      newChoiceSection?.classList.toggle('hidden', !choiceTypes.includes(newQuestionType.value));
      newScaleSection?.classList.toggle('hidden', !scaleTypes.includes(newQuestionType.value));
      newUploadSection?.classList.toggle('hidden', !uploadTypes.includes(newQuestionType.value));

      const acceptInput = newUploadSection?.querySelector('input[name="settings[answer_accept]"]');
      if (uploadTypes.includes(newQuestionType.value) && acceptInput && !acceptInput.value) {
        acceptInput.value = newQuestionType.value === 'image_upload' ? 'image/*' : '.pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.mp4';
      }

      ensureNewRows();
    }

    addNewRowButton?.addEventListener('click', function () {
      const index = newRows.querySelectorAll('.option-row').length;
      newRows.insertAdjacentHTML('beforeend', buildOptionRow(index, formType));
    });

    newQuestionType?.addEventListener('change', toggleNewSections);
    toggleNewSections();

    function renumberList(list, currentFormType) {
      list.querySelectorAll('.option-row').forEach((row, index) => {
        row.querySelectorAll('[data-name-pattern]').forEach((field) => {
          field.name = field.dataset.namePattern.replaceAll('__INDEX__', String(index));
        });
      });
    }

    function buildOptionRow(index, currentFormType) {
      if (currentFormType === 'disc') {
        return `
          <div class="grid gap-2 md:grid-cols-12 items-end rounded-lg border border-slate-200 bg-white p-3 option-row">
            <div class="md:col-span-4">
              <label class="text-xs text-slate-500">Teks Opsi</label>
              <input type="text" data-name-pattern="options[__INDEX__][option_text]" name="options[${index}][option_text]" class="w-full rounded border px-3 py-2" required>
            </div>
            <div class="md:col-span-3">
              <label class="text-xs text-slate-500">Axis Most</label>
              <select data-name-pattern="options[__INDEX__][meta][disc_axis_most]" name="options[${index}][meta][disc_axis_most]" class="w-full rounded border px-3 py-2" required>
                <option value="">Pilih</option>
                ${discAxes.map(axis => `<option value="${axis}">${axis}</option>`).join('')}
              </select>
            </div>
            <div class="md:col-span-3">
              <label class="text-xs text-slate-500">Axis Least</label>
              <select data-name-pattern="options[__INDEX__][meta][disc_axis_least]" name="options[${index}][meta][disc_axis_least]" class="w-full rounded border px-3 py-2" required>
                <option value="">Pilih</option>
                ${discAxes.map(axis => `<option value="${axis}">${axis}</option>`).join('')}
              </select>
            </div>
            <div class="md:col-span-2 flex items-center justify-end gap-1">
              <button type="button" class="rounded border border-rose-300 px-2 py-1 text-xs text-rose-600" data-delete-option>Hapus</button>
            </div>
          </div>`;
      }

      if (['iq', 'tiu', 'diferensial', 'fat'].includes(currentFormType)) {
        return `
          <div class="grid gap-2 md:grid-cols-12 items-end rounded-lg border border-slate-200 bg-white p-3 option-row">
            <div class="md:col-span-6">
              <label class="text-xs text-slate-500">Teks Opsi</label>
              <input type="text" data-name-pattern="options[__INDEX__][option_text]" name="options[${index}][option_text]" class="w-full rounded border px-3 py-2" required>
            </div>
            <div class="md:col-span-2">
              <label class="text-xs text-slate-500">Bobot</label>
              <input type="number" min="0" data-name-pattern="options[__INDEX__][weight]" name="options[${index}][weight]" class="w-full rounded border px-3 py-2">
            </div>
            <label class="md:col-span-2 inline-flex items-center gap-2 text-xs text-slate-700 h-10">
              <input type="hidden" data-name-pattern="options[__INDEX__][meta][is_correct]" name="options[${index}][meta][is_correct]" value="0">
              <input type="checkbox" data-name-pattern="options[__INDEX__][meta][is_correct]" name="options[${index}][meta][is_correct]" value="1">Kunci
            </label>
            <div class="md:col-span-2 flex items-center justify-end gap-1">
              <button type="button" class="rounded border border-rose-300 px-2 py-1 text-xs text-rose-600" data-delete-option>Hapus</button>
            </div>
          </div>`;
      }

      return `
        <div class="grid gap-2 md:grid-cols-12 items-end rounded-lg border border-slate-200 bg-white p-3 option-row">
          <div class="md:col-span-10">
            <label class="text-xs text-slate-500">Teks Opsi</label>
            <input type="text" data-name-pattern="options[__INDEX__][option_text]" name="options[${index}][option_text]" class="w-full rounded border px-3 py-2" required>
          </div>
          <div class="md:col-span-2 flex items-center justify-end gap-1">
            <button type="button" class="rounded border border-rose-300 px-2 py-1 text-xs text-rose-600" data-delete-option>Hapus</button>
          </div>
        </div>`;
    }
  });
</script>
@endsection




