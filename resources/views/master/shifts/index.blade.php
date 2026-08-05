@extends('layouts.app')
@section('content')
<div class="max-w-5xl mx-auto space-y-4">
  <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
    <div>
      <h1 class="text-2xl font-semibold">Master Shift Kerja</h1>
      <p class="text-sm text-slate-500">Definisikan jam shift per outlet operational. Saat karyawan scan presensi tanpa jadwal khusus, sistem otomatis memilih shift yang jamnya paling dekat dengan jam scan.</p>
    </div>
    <a href="{{ route('master-shifts.create') }}{{ $outletId ? '?outlet_id='.$outletId : '' }}" class="px-4 py-2 rounded bg-gray-900 text-white whitespace-nowrap">+ Tambah Shift</a>
  </div>

  @if(session('success'))
    <div class="rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="rounded-lg border border-rose-300 bg-rose-50 p-3 text-sm text-rose-800">{{ session('error') }}</div>
  @endif

  <form method="GET" class="flex items-center gap-2">
    <label class="text-sm text-slate-600">Filter outlet:</label>
    <select name="outlet_id" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm" onchange="this.form.submit()">
      <option value="">Semua Outlet Operational</option>
      @foreach($outlets as $o)
        <option value="{{ $o->id }}" @selected($outletId == $o->id)>{{ $o->name }}</option>
      @endforeach
    </select>
  </form>

  <div class="bg-white border rounded-lg overflow-hidden">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-100 text-slate-700">
        <tr>
          <th class="text-left p-3 font-semibold">Outlet</th>
          <th class="text-left p-3 font-semibold">Kode</th>
          <th class="text-left p-3 font-semibold">Nama Shift</th>
          <th class="text-left p-3 font-semibold">Jam Masuk</th>
          <th class="text-left p-3 font-semibold">Jam Pulang</th>
          <th class="text-left p-3 font-semibold">Status</th>
          <th class="text-left p-3 font-semibold">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($shifts as $s)
        <tr class="border-t border-slate-200">
          <td class="p-3 text-slate-700">{{ $s->outlet?->name ?? '—' }}</td>
          <td class="p-3 text-slate-600 font-mono text-xs">{{ $s->code }}</td>
          <td class="p-3 text-slate-900">{{ $s->name }}</td>
          <td class="p-3 text-slate-700">{{ \Illuminate\Support\Str::of($s->in_time)->substr(0,5) }}</td>
          <td class="p-3 text-slate-700">{{ \Illuminate\Support\Str::of($s->out_time)->substr(0,5) }}</td>
          <td class="p-3">
            @if($s->is_active)
              <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Aktif</span>
            @else
              <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500">Nonaktif</span>
            @endif
          </td>
          <td class="p-3">
            <a class="font-medium text-slate-700 hover:text-slate-900 hover:underline" href="{{ route('master-shifts.edit', $s) }}">Edit</a>
            <span class="mx-1 text-slate-300">·</span>
            <form class="inline" method="POST" action="{{ route('master-shifts.destroy', $s) }}" onsubmit="return confirm('Hapus shift ini?')">@csrf @method('DELETE')<button class="font-medium text-rose-700 hover:text-rose-800 hover:underline">Hapus</button></form>
          </td>
        </tr>
        @empty
        <tr><td colspan="7" class="p-8 text-center text-sm text-slate-400">Belum ada shift terdaftar{{ $outletId ? ' untuk outlet ini' : '' }}.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
