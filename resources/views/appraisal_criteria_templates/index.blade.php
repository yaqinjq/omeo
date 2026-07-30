@extends('layouts.app')
@section('content')
<div class="space-y-6">
  <div class="card p-6">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
      <div>
        <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Appraisal</div>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">Template Kriteria Appraisal</h1>
        <p class="mt-2 text-sm text-slate-600">Setiap kategori karyawan (Office, Operational Outlet, Production) bisa punya set kriteria penilaian sendiri. Saat appraisal dibuat, sistem otomatis memilih template sesuai kategori karyawan yang dinilai — jika tidak ada yang cocok, template <em>Default</em> dipakai.</p>
      </div>
      <a href="{{ route('appraisal-criteria-templates.create') }}" class="btn-primary whitespace-nowrap">+ Template Baru</a>
    </div>
  </div>

  @if(session('success'))
    <div class="rounded-2xl border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="rounded-2xl border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ session('error') }}</div>
  @endif

  <div class="card overflow-hidden p-0">
    <table class="w-full text-sm">
      <thead>
        <tr class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
          <th class="px-4 py-3">Nama Template</th>
          <th class="px-4 py-3">Kategori Karyawan</th>
          <th class="px-4 py-3 text-center">Jumlah Kriteria</th>
          <th class="px-4 py-3 text-center">Status</th>
          <th class="px-4 py-3 text-right">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($templates as $template)
        <tr class="border-t border-slate-100">
          <td class="px-4 py-3">
            <div class="font-semibold text-slate-800">{{ $template->name }}</div>
            @if($template->is_default)
              <span class="mt-1 inline-flex rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold text-indigo-600">Default / Fallback</span>
            @endif
            @if($template->description)
              <div class="mt-1 text-xs text-slate-500">{{ $template->description }}</div>
            @endif
          </td>
          <td class="px-4 py-3 text-slate-600">
            {{ $template->lokasi_kerja ? (\App\Models\AppraisalCriteriaTemplate::LOKASI_KERJA_OPTIONS[$template->lokasi_kerja] ?? $template->lokasi_kerja) : '—' }}
          </td>
          <td class="px-4 py-3 text-center text-slate-600">{{ $template->indicators_count }}</td>
          <td class="px-4 py-3 text-center">
            @if($template->is_active)
              <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Aktif</span>
            @else
              <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500">Nonaktif</span>
            @endif
          </td>
          <td class="px-4 py-3">
            <div class="flex justify-end gap-2">
              <a href="{{ route('appraisal-criteria-templates.edit', $template) }}" class="rounded-lg border px-3 py-1.5 text-xs font-medium hover:bg-slate-50">Kelola Kriteria</a>
              <form method="POST" action="{{ route('appraisal-criteria-templates.duplicate', $template) }}" onsubmit="return promptDuplicateName(event, this)">
                @csrf
                <input type="hidden" name="name" value="">
                <button type="submit" class="rounded-lg border px-3 py-1.5 text-xs font-medium hover:bg-slate-50">Duplikat</button>
              </form>
              @if(!$template->is_default)
              <form method="POST" action="{{ route('appraisal-criteria-templates.destroy', $template) }}" onsubmit="return confirm('Hapus template ini?')">
                @csrf @method('DELETE')
                <button type="submit" class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-medium text-rose-600 hover:bg-rose-50">Hapus</button>
              </form>
              @endif
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-slate-400">Belum ada template.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<script>
function promptDuplicateName(event, form) {
  const name = prompt('Nama template baru:');
  if (!name) { event.preventDefault(); return false; }
  form.querySelector('input[name="name"]').value = name;
  return true;
}
</script>
@endsection
