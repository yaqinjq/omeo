@extends('layouts.app')
@section('content')
@php($selectedMaterials = old('materials', $linkedMaterials->map(fn ($material) => ['training_material_id' => $material->id, 'sequence_order' => $material->pivot->sequence_order, 'is_required' => $material->pivot->is_required, 'unlock_after_previous_completed' => $material->pivot->unlock_after_previous_completed])->values()->all()))
<div class="space-y-4 bg-white border rounded-lg p-4">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-lg font-semibold">{{ $isEdit ? 'Edit' : 'Buat' }} Program Training</h1>
      <p class="text-sm text-slate-600">Susun kurikulum berurutan seperti LMS untuk probation dan karyawan.</p>
    </div>
    <a href="{{ route('training-programs.index') }}" class="px-3 py-2 rounded border">Kembali</a>
  </div>

  <form method="POST" action="{{ $isEdit ? route('training-programs.update', $program) : route('training-programs.store') }}" class="space-y-4">
    @csrf
    @if($isEdit)
      @method('PUT')
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
      <div>
        <label class="text-sm">Nama Program</label>
        <input type="text" name="name" value="{{ old('name', $program->name) }}" class="w-full border rounded p-2" required>
      </div>
      <div>
        <label class="text-sm">Mentor</label>
        <select name="mentor_user_id" class="w-full border rounded p-2">
          <option value="">- Pilih Mentor -</option>
          @foreach($mentors as $mentor)
            <option value="{{ $mentor->id }}" @selected((string) old('mentor_user_id', $program->mentor_user_id) === (string) $mentor->id)>{{ $mentor->name }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="text-sm">Audience</label>
        <select name="audience_scope" class="w-full border rounded p-2">
          @foreach(['general' => 'General', 'department' => 'Khusus Departemen', 'position' => 'Khusus Jabatan'] as $value => $label)
            <option value="{{ $value }}" @selected(old('audience_scope', $program->audience_scope ?: 'general') === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="text-sm">Departemen Target</label>
        <select name="department_id" class="w-full border rounded p-2">
          <option value="">- Semua / Tidak Dipakai -</option>
          @foreach($departments as $department)
            <option value="{{ $department->id }}" @selected((string) old('department_id', $program->department_id) === (string) $department->id)>{{ $department->name }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="text-sm">Jabatan Target</label>
        <select name="position_id" class="w-full border rounded p-2">
          <option value="">- Semua / Tidak Dipakai -</option>
          @foreach($positions as $position)
            <option value="{{ $position->id }}" @selected((string) old('position_id', $program->position_id) === (string) $position->id)>{{ $position->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="flex items-end gap-4">
        <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="is_sequential" value="1" @checked(old('is_sequential', $program->is_sequential ?? true))> Wajib urut satu per satu</label>
        <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $program->is_active ?? true))> Aktif</label>
      </div>
    </div>

    <div>
      <label class="text-sm">Deskripsi</label>
      <textarea name="description" class="w-full border rounded p-2" rows="3">{{ old('description', $program->description) }}</textarea>
    </div>

    <div class="space-y-2">
      <div class="font-medium">Susunan Materi</div>
      <div class="text-xs text-slate-500">Tentukan urutan materi. Program sequential akan membuka materi berikut hanya setelah materi sebelumnya selesai.</div>
      <div id="materialRows" class="space-y-2">
        @foreach($selectedMaterials as $index => $row)
          <div class="grid grid-cols-1 md:grid-cols-12 gap-2 border rounded p-3 material-row">
            <div class="md:col-span-6">
              <label class="text-xs text-slate-500">Materi</label>
              <select name="materials[{{ $index }}][training_material_id]" class="w-full border rounded p-2">
                <option value="">- Pilih Materi -</option>
                @foreach($materials as $materialOption)
                  <option value="{{ $materialOption->id }}" @selected((string) ($row['training_material_id'] ?? '') === (string) $materialOption->id)>{{ $materialOption->title }} ({{ $materialOption->category }})</option>
                @endforeach
              </select>
            </div>
            <div class="md:col-span-2">
              <label class="text-xs text-slate-500">Urutan</label>
              <input type="number" min="1" name="materials[{{ $index }}][sequence_order]" value="{{ $row['sequence_order'] ?? ($index + 1) }}" class="w-full border rounded p-2">
            </div>
            <div class="md:col-span-2 flex items-end"><label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="materials[{{ $index }}][is_required]" value="1" @checked($row['is_required'] ?? true)> Wajib</label></div>
            <div class="md:col-span-2 flex items-end"><label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="materials[{{ $index }}][unlock_after_previous_completed]" value="1" @checked($row['unlock_after_previous_completed'] ?? true)> Kunci urutan</label></div>
          </div>
        @endforeach
      </div>
      <button type="button" id="addMaterialRow" class="px-3 py-2 rounded border">+ Tambah Materi</button>
    </div>

    <button class="px-4 py-2 rounded bg-slate-900 text-white">Simpan Program</button>
  </form>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const rows = document.getElementById('materialRows');
    const addBtn = document.getElementById('addMaterialRow');
    if (!rows || !addBtn) return;
    let index = rows.querySelectorAll('.material-row').length;
    const options = `@foreach($materials as $materialOption)<option value="{{ $materialOption->id }}">{{ $materialOption->title }} ({{ $materialOption->category }})</option>@endforeach`;
    addBtn.addEventListener('click', function () {
      rows.insertAdjacentHTML('beforeend', `<div class="grid grid-cols-1 md:grid-cols-12 gap-2 border rounded p-3 material-row"><div class="md:col-span-6"><label class="text-xs text-slate-500">Materi</label><select name="materials[${index}][training_material_id]" class="w-full border rounded p-2"><option value="">- Pilih Materi -</option>${options}</select></div><div class="md:col-span-2"><label class="text-xs text-slate-500">Urutan</label><input type="number" min="1" name="materials[${index}][sequence_order]" value="${index + 1}" class="w-full border rounded p-2"></div><div class="md:col-span-2 flex items-end"><label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="materials[${index}][is_required]" value="1" checked> Wajib</label></div><div class="md:col-span-2 flex items-end"><label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="materials[${index}][unlock_after_previous_completed]" value="1" checked> Kunci urutan</label></div></div>`);
      index++;
    });
  });
</script>
@endsection
