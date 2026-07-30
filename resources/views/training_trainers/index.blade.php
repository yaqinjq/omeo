@extends('layouts.app')
@section('content')
<div class="space-y-5">
  <div class="card p-5">
    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Trainer Internal</h1>
        <p class="mt-1 text-sm text-muted">Pengajuan dan pengangkatan trainer dari karyawan senior non-HRD maupun role HRD/manager.</p>
      </div>
      <a href="{{ route('training-events.index') }}" class="btn-outline">Training Events</a>
    </div>
  </div>

  <div class="card p-5">
    <h2 class="text-lg font-bold text-slate-900">Angkat Trainer</h2>
    <form method="POST" action="{{ route('training-trainers.store') }}" class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
      @csrf
      <label class="block text-sm font-semibold">Karyawan
        <select name="employee_id" class="mt-1 w-full rounded-xl border-slate-300" required>
          <option value="">- Pilih Karyawan -</option>
          @foreach($employees as $employee)
            <option value="{{ $employee->id }}">
              {{ $employee->full_name }} @if($employee->position) - {{ $employee->position->name }} @endif @if($employee->user) (akun: {{ $employee->user->email }}) @else (belum ada akun login) @endif
            </option>
          @endforeach
        </select>
      </label>

      <label class="block text-sm font-semibold">Status Awal
        <select name="status" class="mt-1 w-full rounded-xl border-slate-300">
          <option value="active">Aktif langsung</option>
          <option value="pending">Pending review</option>
        </select>
      </label>

      <label class="block text-sm font-semibold">Keahlian / Area Training
        <input name="specialty" class="mt-1 w-full rounded-xl border-slate-300" placeholder="Contoh: Barista, Kitchen SOP, Service Excellent">
      </label>

      <label class="block text-sm font-semibold">Catatan
        <input name="notes" class="mt-1 w-full rounded-xl border-slate-300" placeholder="Catatan internal HRD">
      </label>

      <div class="lg:col-span-2">
        <button class="btn-primary" type="submit">Simpan Trainer</button>
      </div>
    </form>
  </div>

  <div class="card overflow-hidden">
    <div class="overflow-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-slate-600">
          <tr>
            <th class="px-4 py-3 text-left font-semibold">Karyawan</th>
            <th class="px-4 py-3 text-left font-semibold">Akun</th>
            <th class="px-4 py-3 text-left font-semibold">Status</th>
            <th class="px-4 py-3 text-left font-semibold">Keahlian</th>
            <th class="px-4 py-3 text-left font-semibold">Tanggal</th>
            <th class="px-4 py-3 text-left font-semibold">Update</th>
          </tr>
        </thead>
        <tbody>
          @forelse($trainers as $trainer)
            <tr class="border-t border-slate-200 align-top">
              <td class="px-4 py-3">
                <div class="font-semibold text-slate-900">{{ $trainer->employee?->full_name ?? '-' }}</div>
                <div class="mt-1 text-xs text-slate-500">{{ $trainer->employee?->department?->name ?? '-' }} | {{ $trainer->employee?->position?->name ?? '-' }}</div>
              </td>
              <td class="px-4 py-3 text-slate-700">
                @if($trainer->user)
                  <div>{{ $trainer->user->name }}</div>
                  <div class="text-xs text-slate-500">{{ $trainer->user->email }}</div>
                @else
                  <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">Belum ada akun login</span>
                @endif
              </td>
              <td class="px-4 py-3">
                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $trainer->status === 'active' ? 'bg-emerald-100 text-emerald-800' : ($trainer->status === 'pending' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700') }}">{{ ucfirst($trainer->status) }}</span>
              </td>
              <td class="px-4 py-3 text-slate-700">
                <div>{{ $trainer->specialty ?: '-' }}</div>
                <div class="mt-1 text-xs text-slate-500">{{ $trainer->notes ?: '-' }}</div>
              </td>
              <td class="px-4 py-3 text-slate-700">
                <div>Request: {{ optional($trainer->requested_at)->format('d/m/Y H:i') ?: '-' }}</div>
                <div>Approve: {{ optional($trainer->approved_at)->format('d/m/Y H:i') ?: '-' }}</div>
                <div class="text-xs text-slate-500">By: {{ $trainer->approvedBy?->name ?? '-' }}</div>
              </td>
              <td class="px-4 py-3">
                <form method="POST" action="{{ route('training-trainers.update', $trainer) }}" class="space-y-2">
                  @csrf
                  @method('PUT')
                  <select name="status" class="w-full rounded-xl border-slate-300 text-sm">
                    @foreach($statuses as $status)
                      <option value="{{ $status }}" @selected($trainer->status === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                  </select>
                  <input name="specialty" value="{{ $trainer->specialty }}" class="w-full rounded-xl border-slate-300 text-xs" placeholder="Keahlian">
                  <input name="notes" value="{{ $trainer->notes }}" class="w-full rounded-xl border-slate-300 text-xs" placeholder="Catatan">
                  <button class="btn-outline text-xs" type="submit">Simpan</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="px-4 py-6 text-center text-sm text-slate-500">Belum ada trainer internal.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div>{{ $trainers->links() }}</div>
</div>
@endsection
