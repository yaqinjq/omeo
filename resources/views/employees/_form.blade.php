@csrf
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
  <div>
    <label class="block text-sm font-medium">NIK *</label>
    <input name="nik" value="{{ old('nik', $employee->nik) }}" class="mt-1 w-full rounded-lg border px-3 py-2" required>
  </div>
  <div>
    <label class="block text-sm font-medium">External ID</label>
    <input name="external_id" value="{{ old('external_id', $employee->external_id) }}" class="mt-1 w-full rounded-lg border px-3 py-2">
  </div>
  <div>
    <label class="block text-sm font-medium">Nama lengkap *</label>
    <input name="full_name" value="{{ old('full_name', $employee->full_name) }}" class="mt-1 w-full rounded-lg border px-3 py-2" required>
  </div>
  <div>
    <label class="block text-sm font-medium">Email</label>
    <input name="email" type="email" value="{{ old('email', $employee->email) }}" class="mt-1 w-full rounded-lg border px-3 py-2">
  </div>
  <div>
    <label class="block text-sm font-medium">Phone</label>
    <input name="phone" value="{{ old('phone', $employee->phone) }}" class="mt-1 w-full rounded-lg border px-3 py-2">
  </div>
  <div>
    <label class="block text-sm font-medium">Join date *</label>
    <input name="join_date" type="date" value="{{ old('join_date', optional($employee->join_date)->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border px-3 py-2" required>
  </div>
  <div>
    <label class="block text-sm font-medium">Probation end date</label>
    <input name="probation_end_date" type="date" value="{{ old('probation_end_date', optional($employee->probation_end_date)->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border px-3 py-2">
    <p class="text-xs text-slate-500 mt-1">Jika kosong & status probation, otomatis +3 bulan dari join date.</p>
  </div>
  <div>
    <label class="block text-sm font-medium">Status *</label>
    @php($val = old('status', $employee->status ?: 'probation'))
    <select name="status" class="mt-1 w-full rounded-lg border px-3 py-2" required>
      @foreach (['probation','contract','permanent','resigned'] as $s)
        <option value="{{ $s }}" @selected($val===$s)>{{ $s }}</option>
      @endforeach
    </select>
  </div>
  <div>
    <label class="block text-sm font-medium">Department ID</label>
    <input name="department_id" value="{{ old('department_id', $employee->department_id) }}" class="mt-1 w-full rounded-lg border px-3 py-2">
  </div>
  <div>
    <label class="block text-sm font-medium">Position ID</label>
    <input name="position_id" value="{{ old('position_id', $employee->position_id) }}" class="mt-1 w-full rounded-lg border px-3 py-2">
  </div>
  <div>
    <label class="block text-sm font-medium">Outlet ID</label>
    <input name="outlet_id" value="{{ old('outlet_id', $employee->outlet_id) }}" class="mt-1 w-full rounded-lg border px-3 py-2">
  </div>
</div>

<div class="mt-6 flex items-center gap-2">
  <button class="rounded-lg bg-slate-900 text-white px-4 py-2 hover:bg-slate-800" type="submit">Simpan</button>
  <a class="rounded-lg border px-4 py-2 hover:bg-slate-50" href="{{ route('employees.index') }}">Batal</a>
</div>
