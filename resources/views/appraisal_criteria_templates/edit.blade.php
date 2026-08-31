@extends('layouts.app')
@section('content')
<div class="space-y-6">
  <div class="bg-white border rounded-lg p-4 max-w-2xl">
    <div class="text-lg font-semibold mb-3">Edit Template: {{ $template->name }}</div>
    <form method="POST" action="{{ route('appraisal-criteria-templates.update', $template) }}">
      @csrf @method('PUT')
      <div class="grid grid-cols-1 gap-3">
        <div>
          <label class="text-sm">Nama Template *</label>
          <input name="name" value="{{ old('name', $template->name) }}" class="w-full border rounded p-2" required>
        </div>
        <div>
          <label class="text-sm">Deskripsi (opsional)</label>
          <textarea name="description" rows="2" class="w-full border rounded p-2">{{ old('description', $template->description) }}</textarea>
        </div>
        <div>
          <label class="text-sm">Kategori Karyawan</label>
          <select name="lokasi_kerja" class="w-full border rounded p-2" @disabled($template->is_default)>
            <option value="">— Tidak otomatis (pilih manual saja) —</option>
            @foreach(\App\Models\AppraisalCriteriaTemplate::LOKASI_KERJA_OPTIONS as $val => $label)
              <option value="{{ $val }}" @selected(old('lokasi_kerja', $template->lokasi_kerja) === $val)>{{ $label }}</option>
            @endforeach
          </select>
          @if($template->is_default)
            <p class="mt-1 text-xs text-slate-500">Template default tidak terikat kategori — otomatis dipakai sebagai fallback.</p>
          @endif
        </div>
        <div class="flex items-center gap-2">
          <input type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $template->is_active)) class="rounded border-slate-300">
          <label for="is_active" class="text-sm">Aktif</label>
        </div>
      </div>
      <div class="mt-4 flex gap-2">
        <button class="px-4 py-2 rounded bg-slate-900 text-white">Update</button>
        <a class="px-4 py-2 rounded border" href="{{ route('appraisal-criteria-templates.index') }}">Kembali ke Daftar Template</a>
      </div>
    </form>
  </div>

  <div class="bg-white border rounded-lg p-4 max-w-4xl">
    <div class="flex items-center justify-between mb-3">
      <div class="text-lg font-semibold">Kriteria Penilaian ({{ $template->indicators->count() }})</div>
      <a href="{{ route('appraisal-indicators.create', ['template_id' => $template->id]) }}" class="px-3 py-1.5 rounded bg-slate-900 text-white text-sm">+ Tambah Kriteria</a>
    </div>

    @if($template->indicators->isEmpty())
      <p class="text-sm text-slate-400 py-6 text-center">Belum ada kriteria di template ini.</p>
    @else
      <div class="overflow-hidden rounded-lg border border-slate-200">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
              <th class="px-3 py-2">Kategori</th>
              <th class="px-3 py-2">Pertanyaan</th>
              <th class="px-3 py-2 text-center">Bobot</th>
              <th class="px-3 py-2 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody>
            @foreach($template->indicators as $ind)
            <tr class="border-t border-slate-100">
              <td class="px-3 py-2 text-slate-600">{{ $ind->category }}</td>
              <td class="px-3 py-2 text-slate-800">{{ \Illuminate\Support\Str::limit($ind->question, 80) }}</td>
              <td class="px-3 py-2 text-center text-slate-600">{{ $ind->weight }}</td>
              <td class="px-3 py-2 text-right">
                <a href="{{ route('appraisal-indicators.edit', $ind) }}" class="text-xs font-medium text-indigo-600 hover:underline">Edit</a>
                <form method="POST" action="{{ route('appraisal-indicators.destroy', $ind) }}" class="inline"
                      onsubmit="return confirm('Hapus kriteria \'{{ addslashes(\Illuminate\Support\Str::limit($ind->question, 60)) }}\'? Kalau kriteria ini sudah pernah dipakai di penilaian appraisal, penghapusan akan ditolak otomatis.');">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="ml-3 text-xs font-medium text-red-600 hover:underline">Hapus</button>
                </form>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
</div>
@endsection
