<div class="grid grid-cols-1 md:grid-cols-2 gap-3">
  <div>
    <label class="text-sm">Judul *</label>
    <input name="title" value="{{ old('title', $material->title ?? '') }}" class="w-full border rounded p-2" required>
  </div>
  <div>
    <label class="text-sm">Kategori *</label>
    <input name="category" value="{{ old('category', $material->category ?? '') }}" class="w-full border rounded p-2" required>
  </div>
  <div>
    <label class="text-sm">Audience</label>
    <select name="audience_scope" class="w-full border rounded p-2">
      @foreach(['general' => 'General', 'department' => 'Khusus Departemen', 'position' => 'Khusus Jabatan'] as $value => $label)
        <option value="{{ $value }}" @selected(old('audience_scope', $material->audience_scope ?? 'general') === $value)>{{ $label }}</option>
      @endforeach
    </select>
  </div>
  <div>
    <label class="text-sm">Mentor</label>
    <select name="mentor_user_id" class="w-full border rounded p-2">
      <option value="">- Pilih Mentor -</option>
      @foreach($mentors as $mentor)
        <option value="{{ $mentor->id }}" @selected((string) old('mentor_user_id', $material->mentor_user_id ?? '') === (string) $mentor->id)>{{ $mentor->name }}</option>
      @endforeach
    </select>
  </div>
  <div>
    <label class="text-sm">Departemen Target</label>
    <select name="department_id" class="w-full border rounded p-2">
      <option value="">- Opsional -</option>
      @foreach($departments as $department)
        <option value="{{ $department->id }}" @selected((string) old('department_id', $material->department_id ?? '') === (string) $department->id)>{{ $department->name }}</option>
      @endforeach
    </select>
  </div>
  <div>
    <label class="text-sm">Jabatan Target</label>
    <select name="position_id" class="w-full border rounded p-2">
      <option value="">- Opsional -</option>
      @foreach($positions as $position)
        <option value="{{ $position->id }}" @selected((string) old('position_id', $material->position_id ?? '') === (string) $position->id)>{{ $position->name }}</option>
      @endforeach
    </select>
  </div>
  <div>
    <label class="text-sm">Link YouTube</label>
    <input name="youtube_url" value="{{ old('youtube_url', $material->youtube_url ?? '') }}" class="w-full border rounded p-2">
  </div>
  <div>
    <label class="text-sm">Content Source Type</label>
    <select name="content_source_type" class="w-full border rounded p-2">
      <option value="">- Opsional -</option>
      @foreach(['youtube' => 'YouTube', 'video' => 'Video', 'link' => 'Link', 'document' => 'Document'] as $value => $label)
        <option value="{{ $value }}" @selected(old('content_source_type', $material->content_source_type ?? '') === $value)>{{ $label }}</option>
      @endforeach
    </select>
  </div>
  <div>
    <label class="text-sm">Content Source URL</label>
    <input name="content_source_url" value="{{ old('content_source_url', $material->content_source_url ?? '') }}" class="w-full border rounded p-2">
  </div>
  <div>
    <label class="text-sm">Durasi (menit)</label>
    <input type="number" name="duration_minutes" value="{{ old('duration_minutes', $material->duration_minutes ?? '') }}" class="w-full border rounded p-2">
  </div>
  <div>
    <label class="text-sm">Passing Score</label>
    <input type="number" name="pass_score" min="0" max="100" value="{{ old('pass_score', $material->pass_score ?? '') }}" class="w-full border rounded p-2">
  </div>
  <div>
    <label class="text-sm">Pretest Form</label>
    <select name="pretest_form_id" class="w-full border rounded p-2">
      <option value="">- Tidak Ada -</option>
      @foreach($forms as $form)
        <option value="{{ $form->id }}" @selected((string) old('pretest_form_id', $material->pretest_form_id ?? '') === (string) $form->id)>{{ $form->name }} ({{ strtoupper($form->type) }})</option>
      @endforeach
    </select>
  </div>
  <div>
    <label class="text-sm">Posttest Form</label>
    <select name="posttest_form_id" class="w-full border rounded p-2">
      <option value="">- Tidak Ada -</option>
      @foreach($forms as $form)
        <option value="{{ $form->id }}" @selected((string) old('posttest_form_id', $material->posttest_form_id ?? '') === (string) $form->id)>{{ $form->name }} ({{ strtoupper($form->type) }})</option>
      @endforeach
    </select>
  </div>
</div>
<div>
  <label class="text-sm">Deskripsi</label>
  <textarea name="description" class="w-full border rounded p-2" rows="4">{{ old('description', $material->description ?? '') }}</textarea>
</div>
<label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $material->is_active ?? true))> Materi aktif</label>
