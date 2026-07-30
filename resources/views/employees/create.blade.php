@extends('layouts.app')
@section('content')
<div class="bg-white border rounded-lg p-4">
  <div class="text-lg font-semibold mb-3">Tambah Karyawan</div>
  <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
    Jika email diisi, sistem akan otomatis membuat akun login untuk karyawan dan mengirim link set password awal ke email tersebut.
  </div>

  @if ($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
      {{ $errors->first() }}
    </div>
  @endif

  <form method="POST" action="{{ route('employees.store') }}">
    @csrf

    <div class="mb-3 text-sm font-semibold text-slate-700">Data Utama</div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
      <div>
        <label class="text-sm">NIK *</label>
        <input name="nik" value="{{ old('nik', $employee->nik ?? '') }}" class="w-full border rounded p-2" required>
      </div>
      <div>
        <label class="text-sm">Nama Lengkap *</label>
        <input name="full_name" value="{{ old('full_name', $employee->full_name ?? '') }}" class="w-full border rounded p-2" required>
      </div>
      <div>
        <label class="text-sm">Email (Private)</label>
        <input type="email" name="email_private" value="{{ old('email_private', $employee->email_private ?? '') }}" class="w-full border rounded p-2">
      </div>
      <div>
        <label class="text-sm">No HP</label>
        <input name="phone_number" value="{{ old('phone_number', $employee->phone_number ?? '') }}" class="w-full border rounded p-2">
      </div>
      <div>
        <label class="text-sm">External ID (API)</label>
        <input name="external_id" value="{{ old('external_id', $employee->external_id ?? '') }}" class="w-full border rounded p-2">
      </div>
      <div>
        <label class="text-sm">Status *</label>
        <select name="status_employment" class="w-full border rounded p-2" required>
          @php($val = old('status_employment', $employee->status_employment ?? 'probation'))
          @foreach(['probation','contract','permanent','resigned'] as $opt)
            <option value="{{ $opt }}" @selected($val===$opt)>{{ $opt }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div class="mt-6 mb-3 text-sm font-semibold text-slate-700">Data Kepegawaian</div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
      <div>
        <label class="text-sm">NOKOM (Employee Number)</label>
        <input name="employee_number" value="{{ old('employee_number', $employee->employee_number ?? $employee->nokom ?? '') }}" class="w-full border rounded p-2" placeholder="Contoh: EMP-00123">
      </div>
      <div>
        <label class="text-sm">Join Date</label>
        <input type="date" name="join_date" value="{{ old('join_date', isset($employee) && $employee->join_date ? $employee->join_date->format('Y-m-d') : '') }}" class="w-full border rounded p-2">
      </div>
      <div>
        <label class="text-sm">Jabatan / Posisi @if(($positions ?? collect())->isNotEmpty()) * @endif</label>
        @if(($positions ?? collect())->isNotEmpty())
          <select name="position_id" class="w-full border rounded p-2" @if(($positions ?? collect())->isNotEmpty()) required @endif>
            <option value="">Pilih posisi</option>
            @foreach(($positions ?? collect()) as $position)
              <option value="{{ $position->id }}" @selected((string) old('position_id', $employee->position_id ?? '') === (string) $position->id)>{{ $position->name }}</option>
            @endforeach
          </select>
        @else
          <input name="position_id" value="{{ old('position_id', $employee->position_id ?? '') }}" class="w-full border rounded p-2" placeholder="Isi ID posisi">
        @endif
      </div>
      <div>
        <label class="text-sm">Probation End Date</label>
        <input type="date" name="probation_end_date" value="{{ old('probation_end_date', isset($employee) && $employee->probation_end_date ? $employee->probation_end_date->format('Y-m-d') : '') }}" class="w-full border rounded p-2">
      </div>
      <div>
        <label class="text-sm">Departemen</label>
        @if(($departments ?? collect())->isNotEmpty())
          <select name="department_id" class="w-full border rounded p-2">
            <option value="">Pilih departemen</option>
            @foreach(($departments ?? collect()) as $department)
              <option value="{{ $department->id }}" @selected((string) old('department_id', $employee->department_id ?? '') === (string) $department->id)>{{ $department->name }}</option>
            @endforeach
          </select>
        @else
          <input name="department_id" value="{{ old('department_id', $employee->department_id ?? '') }}" class="w-full border rounded p-2">
        @endif
      </div>
      <div>
        <label class="text-sm">Outlet</label>
        @if(($outlets ?? collect())->isNotEmpty())
          <select name="outlet_id" class="w-full border rounded p-2">
            <option value="">Pilih outlet</option>
            @foreach(($outlets ?? collect()) as $outlet)
              <option value="{{ $outlet->id }}" @selected((string) old('outlet_id', $employee->outlet_id ?? '') === (string) $outlet->id)>{{ $outlet->name }}</option>
            @endforeach
          </select>
        @else
          <input name="outlet_id" value="{{ old('outlet_id', $employee->outlet_id ?? '') }}" class="w-full border rounded p-2">
        @endif
      </div>
    </div>

    <div class="mt-6 mb-3 text-sm font-semibold text-slate-700">Salary Onboarding</div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
      <div>
        <label class="text-sm">Gaji Saat Ini</label>
        <input type="number" min="0" step="0.01" name="current_salary" value="{{ old('current_salary', $employee->current_salary ?? '') }}" class="w-full border rounded p-2" placeholder="Contoh: 5500000">
      </div>
      <div>
        <label class="text-sm">Tanggal Efektif Gaji</label>
        <input type="date" name="salary_effective_date" value="{{ old('salary_effective_date', isset($employee) && $employee->join_date ? $employee->join_date->format('Y-m-d') : '') }}" class="w-full border rounded p-2">
      </div>
      <div class="md:col-span-2">
        <label class="text-sm">Catatan Gaji</label>
        <textarea name="salary_notes" rows="3" class="w-full border rounded p-2" placeholder="Contoh: Gaji awal probation / penyesuaian onboarding">{{ old('salary_notes') }}</textarea>
      </div>
    </div>

    <div class="mt-4 flex gap-2">
      <button class="px-4 py-2 rounded bg-slate-900 text-white">Simpan</button>
      <a class="px-4 py-2 rounded border" href="{{ route('employees.index') }}">Kembali</a>
    </div>
  </form>
</div>
@endsection

