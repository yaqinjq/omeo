@extends('layouts.app')
@section('content')
<div class="max-w-5xl mx-auto space-y-4">
  <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
    <div>
      <h1 class="text-2xl font-semibold">Master Posisi</h1>
      <p class="text-sm text-slate-500">Kelola referensi posisi kerja dan dukung import/export master untuk operasional HRD.</p>
    </div>
    <div class="flex flex-wrap gap-2">
      <a href="{{ route('positions.template') }}" class="px-4 py-2 rounded border">Download Template Posisi</a>
      <a href="{{ route('positions.export') }}" class="px-4 py-2 rounded border border-emerald-300 bg-emerald-50 text-emerald-700">Export Data Existing</a>
      <a href="{{ route('positions.create') }}" class="px-4 py-2 rounded bg-gray-900 text-white">+ Tambah</a>
    </div>
  </div>

  <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
    <div class="font-semibold text-slate-900">Panduan Import Posisi</div>
    <div class="mt-2">Gunakan template khusus posisi. Kolom wajib: <span class="font-semibold">name</span>. Kolom <span class="font-semibold">level</span> bersifat opsional.</div>
    <div class="mt-1">Jika nama posisi yang sama sudah ada, sistem akan update data existing secara case-insensitive agar tidak membuat duplikasi posisi dengan huruf besar/kecil berbeda.</div>
  </div>

  <form method="POST" action="{{ route('positions.import') }}" enctype="multipart/form-data" class="bg-white border rounded-lg p-4 grid gap-3 md:grid-cols-[1fr_auto] md:items-end">
    @csrf
    <div>
      <label class="block text-sm text-gray-600 mb-1">Import Posisi</label>
      <input type="file" name="file" class="border rounded px-3 py-2 w-full" accept=".csv,.txt,.xlsx" required>
      <div class="mt-1 text-xs text-slate-500">Template: name, level</div>
      @error('file')<div class="text-red-600 text-sm mt-1">{{ $message }}</div>@enderror
    </div>
    <button class="px-4 py-2 rounded bg-blue-600 text-white">Upload Import</button>
  </form>

  @if(session('import_summary'))
    <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900 space-y-2">
      <div>Dibuat: {{ data_get(session('import_summary'), 'created', 0) }}</div>
      <div>Diupdate: {{ data_get(session('import_summary'), 'updated', 0) }}</div>
      <div>Gagal: {{ data_get(session('import_summary'), 'failed', 0) }}</div>
      @php($positionImportWarnings = collect(data_get(session('import_summary'), 'warnings', []))->take(3))
      @if($positionImportWarnings->isNotEmpty())
        <div class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900">
          @foreach($positionImportWarnings as $warning)
            <div>{{ $warning }}</div>
          @endforeach
        </div>
      @endif
      @php($positionImportErrors = collect(data_get(session('import_summary'), 'row_errors', []))->take(5))
      @if($positionImportErrors->isNotEmpty())
        <div class="rounded-lg border border-blue-300 bg-white/70 p-3 text-xs text-slate-700">
          @foreach($positionImportErrors as $rowError)
            <div>Baris {{ data_get($rowError, 'row', '?') }}: {{ data_get($rowError, 'message', '-') }}</div>
          @endforeach
        </div>
      @endif
    </div>
  @endif

  <div class="bg-white rounded-lg border overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-gray-50">
        <tr class="text-left">
          <th class="p-3 w-20">ID</th>
          <th class="p-3">Nama</th>
          <th class="p-3 w-28">Level</th>
          <th class="p-3 w-64">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @foreach($items as $it)
          <tr class="border-t">
            <td class="p-3">{{ $it->id }}</td>
            <td class="p-3">{{ $it->name }}</td>
            <td class="p-3">{{ $it->level ?: '-' }}</td>
            <td class="p-3">
              <a class="text-indigo-600" href="{{ route('positions.edit',$it) }}">Edit</a>
              <form method="POST" action="{{ route('positions.destroy',$it) }}" class="inline" onsubmit="return confirm('Hapus?')">
                @csrf @method('DELETE')
                <button class="text-red-600 ml-3">Hapus</button>
              </form>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <div>{{ $items->links() }}</div>
</div>
@endsection

