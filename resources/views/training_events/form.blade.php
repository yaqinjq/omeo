@extends('layouts.app')
@section('content')
@php
  $dateValue = fn (string $field) => old($field, optional($event->{$field})->format('Y-m-d\TH:i'));
  $selectedType = old('event_type', $event->event_type ?: 'meeting');
  $selectedStatus = old('status', $event->status ?: 'published');
  $selectedEmployeeIds = collect(old('employee_ids', $invitedEmployeeIds ?? []))->map(fn ($id) => (string) $id)->all();
@endphp
<div class="space-y-5">
  <div class="card p-5">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ $isEdit ? 'Edit' : 'Buat' }} Training Event</h1>
        <p class="mt-1 text-sm text-muted">Jadwalkan LMS, live Zoom/Google Meet, atau praktik on-site lengkap dengan undangan dan check-in peserta.</p>
      </div>
      <a href="{{ $isEdit ? route('training-events.show', $event) : route('training-events.index') }}" class="btn-outline">Kembali</a>
    </div>
  </div>

  <form method="POST" action="{{ $isEdit ? route('training-events.update', $event) : route('training-events.store') }}" class="space-y-5">
    @csrf
    @if($isEdit)
      @method('PUT')
    @endif

    <div class="card p-5">
      <h2 class="text-lg font-bold text-slate-900">Informasi Event</h2>
      <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
        <label class="block text-sm font-semibold">Judul Event *
          <input type="text" name="title" value="{{ old('title', $event->title) }}" class="mt-1 w-full rounded-xl border-slate-300" required>
        </label>

        <label class="block text-sm font-semibold">Tipe Event *
          <select name="event_type" class="mt-1 w-full rounded-xl border-slate-300">
            @foreach(['lms' => 'LMS Scheduled', 'meeting' => 'Live Online: Zoom / Google Meet', 'practical' => 'Practical / On-site'] as $value => $label)
              <option value="{{ $value }}" @selected($selectedType === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </label>

        <label class="block text-sm font-semibold">Program Training
          <select name="training_program_id" class="mt-1 w-full rounded-xl border-slate-300">
            <option value="">- Opsional -</option>
            @foreach($programs as $program)
              <option value="{{ $program->id }}" @selected((string) old('training_program_id', $event->training_program_id) === (string) $program->id)>{{ $program->name }}</option>
            @endforeach
          </select>
        </label>

        <label class="block text-sm font-semibold">Materi Terkait
          <select name="training_material_id" class="mt-1 w-full rounded-xl border-slate-300">
            <option value="">- Opsional -</option>
            @foreach($materials as $material)
              <option value="{{ $material->id }}" @selected((string) old('training_material_id', $event->training_material_id) === (string) $material->id)>{{ $material->title }}</option>
            @endforeach
          </select>
        </label>

        <label class="block text-sm font-semibold">Trainer / Pengajar
          <select name="mentor_user_id" class="mt-1 w-full rounded-xl border-slate-300">
            <option value="">- Pilih Trainer -</option>
            @foreach($mentors as $mentor)
              <option value="{{ $mentor->id }}" @selected((string) old('mentor_user_id', $event->mentor_user_id) === (string) $mentor->id)>{{ $mentor->name }} ({{ $mentor->role }})</option>
            @endforeach
          </select>
        </label>

        <label class="block text-sm font-semibold">Status Event *
          <select name="status" class="mt-1 w-full rounded-xl border-slate-300">
            @foreach(['draft' => 'Draft', 'published' => 'Published', 'started' => 'Started', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $value => $label)
              <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </label>
      </div>
    </div>

    <div class="card p-5">
      <h2 class="text-lg font-bold text-slate-900">Jadwal & Akses Online</h2>
      <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
        <label class="block text-sm font-semibold">Platform
          <input type="text" name="platform" value="{{ old('platform', $event->platform) }}" class="mt-1 w-full rounded-xl border-slate-300" placeholder="Zoom / Google Meet / On-site">
        </label>

        <label class="block text-sm font-semibold">Meeting URL
          <input type="text" name="meeting_url" value="{{ old('meeting_url', $event->meeting_url) }}" class="mt-1 w-full rounded-xl border-slate-300" placeholder="https://meet.google.com/...">
        </label>

        <label class="block text-sm font-semibold">Mulai
          <input type="datetime-local" name="starts_at" value="{{ $dateValue('starts_at') }}" class="mt-1 w-full rounded-xl border-slate-300">
        </label>

        <label class="block text-sm font-semibold">Selesai
          <input type="datetime-local" name="ends_at" value="{{ $dateValue('ends_at') }}" class="mt-1 w-full rounded-xl border-slate-300">
        </label>

        <label class="block text-sm font-semibold">Deadline Pendaftaran
          <input type="datetime-local" name="registration_deadline_at" value="{{ $dateValue('registration_deadline_at') }}" class="mt-1 w-full rounded-xl border-slate-300">
        </label>

        <label class="block text-sm font-semibold">Maksimal Peserta
          <input type="number" min="1" name="max_participants" value="{{ old('max_participants', $event->max_participants) }}" class="mt-1 w-full rounded-xl border-slate-300">
        </label>

        <label class="block text-sm font-semibold">Check-in Dibuka
          <input type="datetime-local" name="check_in_opens_at" value="{{ $dateValue('check_in_opens_at') }}" class="mt-1 w-full rounded-xl border-slate-300">
        </label>

        <label class="block text-sm font-semibold">Check-in Ditutup
          <input type="datetime-local" name="check_in_closes_at" value="{{ $dateValue('check_in_closes_at') }}" class="mt-1 w-full rounded-xl border-slate-300">
        </label>
      </div>
    </div>

    <div class="card p-5">
      <h2 class="text-lg font-bold text-slate-900">Lokasi & Validasi Hadir</h2>
      <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
        <label class="block text-sm font-semibold">Nama Lokasi
          <input type="text" name="location_name" value="{{ old('location_name', $event->location_name) }}" class="mt-1 w-full rounded-xl border-slate-300" placeholder="Online / Outlet / Training Center">
        </label>

        <div class="flex flex-wrap items-end gap-4">
          <label class="inline-flex items-center gap-2 text-sm font-semibold">
            <input type="checkbox" name="requires_registration" value="1" @checked(old('requires_registration', $event->exists ? $event->requires_registration : true))>
            Wajib daftar
          </label>
          <label class="inline-flex items-center gap-2 text-sm font-semibold">
            <input type="checkbox" name="requires_photo_validation" value="1" @checked(old('requires_photo_validation', $event->requires_photo_validation))>
            Wajib foto check-in
          </label>
          <label class="inline-flex items-center gap-2 text-sm font-semibold">
            <input type="checkbox" name="requires_geolocation" value="1" @checked(old('requires_geolocation', $event->requires_geolocation))>
            Wajib geotag
          </label>
        </div>
      </div>

      <label class="mt-4 block text-sm font-semibold">Alamat / Detail Lokasi
        <textarea name="location_address" class="mt-1 w-full rounded-xl border-slate-300" rows="2">{{ old('location_address', $event->location_address) }}</textarea>
      </label>

      <label class="mt-4 block text-sm font-semibold">Instruksi Peserta
        <textarea name="participant_instruction" class="mt-1 w-full rounded-xl border-slate-300" rows="3" placeholder="Contoh: daftar dulu, join 10 menit sebelum mulai, gunakan nama asli saat masuk meeting.">{{ old('participant_instruction', $event->participant_instruction) }}</textarea>
      </label>
    </div>

    <div class="card p-5">
      <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
          <h2 class="text-lg font-bold text-slate-900">Undang Peserta</h2>
          <p class="mt-1 text-sm text-muted">Centang karyawan yang perlu diundang. Pada mode edit, peserta yang sudah pernah diundang tetap aman meskipun tidak dicentang ulang.</p>
        </div>
        <div class="text-sm font-semibold text-slate-600">{{ count($selectedEmployeeIds) }} peserta sudah dipilih/diundang</div>
      </div>
      <div class="mt-4 flex flex-col gap-3 md:flex-row">
        <input type="search" class="js-employee-filter w-full rounded-xl border-slate-300" placeholder="Cari nama, jabatan, departemen...">
        <button type="button" class="js-check-visible btn-outline whitespace-nowrap">Centang yang terlihat</button>
        <button type="button" class="js-uncheck-visible btn-outline whitespace-nowrap">Kosongkan yang terlihat</button>
      </div>
      <div class="mt-4 max-h-80 overflow-y-auto rounded-2xl border border-slate-200">
        <div class="grid grid-cols-1 divide-y divide-slate-100">
          @foreach($employees as $employee)
            @php($searchText = strtolower(trim($employee->full_name . ' ' . ($employee->position?->name ?? '') . ' ' . ($employee->department?->name ?? '') . ' ' . ($employee->employee_number ?? ''))))
            <label class="js-employee-row flex cursor-pointer items-start gap-3 px-4 py-3 hover:bg-slate-50" data-search="{{ $searchText }}">
              <input type="checkbox" name="employee_ids[]" value="{{ $employee->id }}" class="mt-1 rounded border-slate-300" @checked(in_array((string) $employee->id, $selectedEmployeeIds, true))>
              <span>
                <span class="block font-semibold text-slate-900">{{ $employee->full_name }}</span>
                <span class="block text-xs text-slate-500">
                  {{ $employee->employee_number ?: '-' }}
                  @if($employee->position) | {{ $employee->position->name }} @endif
                  @if($employee->department) | {{ $employee->department->name }} @endif
                </span>
              </span>
            </label>
          @endforeach
        </div>
      </div>
      <label class="mt-3 inline-flex items-center gap-2 text-sm font-semibold">
        <input type="checkbox" name="auto_invite_program_enrollments" value="1" @checked(old('auto_invite_program_enrollments', true))>
        Undang otomatis semua peserta program terkait
      </label>
    </div>

    <div class="flex justify-end">
      <button class="btn-primary" type="submit">{{ $isEdit ? 'Simpan Perubahan' : 'Simpan Event' }}</button>
    </div>
  </form>
</div>
<script>
(function () {
  const filter = document.querySelector('.js-employee-filter');
  const rows = Array.from(document.querySelectorAll('.js-employee-row'));
  const visibleRows = () => rows.filter((row) => row.style.display !== 'none');

  filter?.addEventListener('input', function () {
    const term = this.value.trim().toLowerCase();
    rows.forEach((row) => {
      row.style.display = !term || row.dataset.search.includes(term) ? '' : 'none';
    });
  });

  document.querySelector('.js-check-visible')?.addEventListener('click', function () {
    visibleRows().forEach((row) => {
      const checkbox = row.querySelector('input[type="checkbox"]');
      if (checkbox) checkbox.checked = true;
    });
  });

  document.querySelector('.js-uncheck-visible')?.addEventListener('click', function () {
    visibleRows().forEach((row) => {
      const checkbox = row.querySelector('input[type="checkbox"]');
      if (checkbox) checkbox.checked = false;
    });
  });
})();
</script>
@endsection
