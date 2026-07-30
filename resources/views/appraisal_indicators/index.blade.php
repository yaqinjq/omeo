@extends('layouts.app')
@section('content')
<div class="space-y-6">
  <div class="card p-6">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Kriteria Penilaian HRD</h1>
        <p class="mt-1 text-sm text-slate-600">Modul ini dipakai untuk menyusun butir penilaian evaluator yang mendukung 5 kelompok appraisal HRD: KPI, training + KKM, kompetensi keahlian, kompetensi posisi, dan kriteria penilaian.</p>
      </div>
      <div class="flex gap-2">
        <a class="btn-outline inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold" href="{{ route('appraisal-criteria-templates.index') }}">Kelola Template</a>
        <a class="btn-primary inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold" href="{{ route('appraisal-indicators.create') }}">+ Tambah Kriteria</a>
      </div>
    </div>
  </div>

  <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
    Kriteria di halaman ini bukan lagi dimaksudkan sebagai modul teknis yang berdiri sendiri. Fungsinya adalah menjadi bank butir evaluasi agar form evaluator tetap familiar dan mudah dibaca HRD. Kriteria dikelompokkan per <strong>Template</strong> — karyawan Office dan Operational Outlet bisa punya butir penilaian yang berbeda.
  </div>

  <form method="GET" class="flex items-center gap-2">
    <label class="text-sm text-slate-600">Filter template:</label>
    <select name="template_id" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm" onchange="this.form.submit()">
      <option value="">Semua Template</option>
      @foreach($templates as $t)
        <option value="{{ $t->id }}" @selected(request('template_id') == $t->id)>{{ $t->name }}</option>
      @endforeach
    </select>
  </form>

  <div class="card overflow-hidden p-0">
    <div class="overflow-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-100 text-slate-700">
          <tr>
            <th class="text-left p-3 font-semibold">Template</th>
            <th class="text-left p-3 font-semibold">Kelompok Kriteria</th>
            <th class="text-left p-3 font-semibold">Butir Penilaian</th>
            <th class="text-left p-3 font-semibold">Bobot</th>
            <th class="text-left p-3 font-semibold">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @foreach($indicators as $i)
            <tr class="border-t border-slate-200">
              <td class="p-3 text-slate-600 text-xs">{{ $i->template?->name ?? '—' }}</td>
              <td class="p-3 text-slate-700">{{ $i->category }}</td>
              <td class="p-3 text-slate-900">{{ \Illuminate\Support\Str::limit($i->question, 80) }}</td>
              <td class="p-3 text-slate-700">{{ $i->weight }}</td>
              <td class="p-3">
                <a class="font-medium text-slate-700 hover:text-slate-900 hover:underline" href="{{ route('appraisal-indicators.edit',$i) }}">Edit</a>
                <span class="mx-1 text-slate-300">�</span>
                <form class="inline" method="POST" action="{{ route('appraisal-indicators.destroy',$i) }}" onsubmit="return confirm('Hapus kriteria ini?')">@csrf @method('DELETE')<button class="font-medium text-rose-700 hover:text-rose-800 hover:underline">Hapus</button></form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  <div>{{ $indicators->links() }}</div>
</div>
@endsection
