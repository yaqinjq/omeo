@extends('layouts.app')
@section('content')
<div class="max-w-6xl mx-auto space-y-4">
  <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
    <div>
      <h1 class="text-2xl font-semibold">Master Outlet</h1>
      <p class="text-sm text-slate-500">Kelola outlet, geofence, import/export master, dan dokumentasi perizinan outlet.</p>
    </div>
    <div class="flex flex-wrap gap-2">
      <a href="{{ route('outlets.template') }}" class="px-4 py-2 rounded border">Download Template Outlet</a>
      <a href="{{ route('outlets.export') }}" class="px-4 py-2 rounded border border-emerald-300 bg-emerald-50 text-emerald-700">Export Data Existing</a>
      <a href="{{ route('outlets.create') }}" class="px-4 py-2 rounded bg-gray-900 text-white">+ Tambah</a>
    </div>
  </div>

  <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
    <div class="font-semibold text-slate-900">Panduan Import Outlet</div>
    <div class="mt-2">Kolom wajib: <span class="font-semibold">name</span>, <span class="font-semibold">brand_name</span>, <span class="font-semibold">timezone</span>. Disarankan isi <span class="font-semibold">external_id</span> agar update outlet existing lebih akurat.</div>
    <div class="mt-1">Jika external_id tidak diisi, sistem akan mencocokkan berdasarkan nama outlet. Koordinat bisa diisi langsung atau diambil dari kolom maps_reference. Field opsional yang dikosongkan tidak akan menghapus koordinat/jam kerja existing saat update.</div>
  </div>

  <form method="POST" action="{{ route('outlets.import') }}" enctype="multipart/form-data" class="bg-white border rounded-lg p-4 grid gap-3 md:grid-cols-[1fr_auto] md:items-end">
    @csrf
    <div>
      <label class="block text-sm text-gray-600 mb-1">Import Outlet</label>
      <input type="file" name="file" class="border rounded px-3 py-2 w-full" accept=".csv,.txt,.xlsx" required>
      <div class="mt-1 text-xs text-slate-500">Template: name, brand_name, external_id, location, maps_reference, latitude, longitude, radius_meters, timezone, work_start_time, work_end_time</div>
      <div class="mt-1 text-xs text-slate-500">Flow import sekarang: upload file untuk preview dulu, lalu konfirmasi apakah data existing boleh diupdate atau tidak.</div>
      @error('file')<div class="text-red-600 text-sm mt-1">{{ $message }}</div>@enderror
    </div>
    <button class="px-4 py-2 rounded bg-blue-600 text-white">Upload Import</button>
  </form>

  @if(session('import_summary'))
    <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900 space-y-2">
      <div>Dibuat: {{ data_get(session('import_summary'), 'created', 0) }}</div>
      <div>Diupdate: {{ data_get(session('import_summary'), 'updated', 0) }}</div>
      <div>Gagal: {{ data_get(session('import_summary'), 'failed', 0) }}</div>
      @php($outletImportWarnings = collect(data_get(session('import_summary'), 'warnings', []))->take(3))
      @if($outletImportWarnings->isNotEmpty())
        <div class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900">
          @foreach($outletImportWarnings as $warning)
            <div>{{ $warning }}</div>
          @endforeach
        </div>
      @endif
      @php($outletImportErrors = collect(data_get(session('import_summary'), 'row_errors', []))->take(5))
      @if($outletImportErrors->isNotEmpty())
        <div class="rounded-lg border border-blue-300 bg-white/70 p-3 text-xs text-slate-700">
          @foreach($outletImportErrors as $rowError)
            <div>Baris {{ data_get($rowError, 'row', '?') }}: {{ data_get($rowError, 'message', '-') }}</div>
          @endforeach
        </div>
      @endif
    </div>
  @endif

  @if(is_array($importPreview ?? null))
    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 space-y-4">
      <div>
        <div class="text-sm font-semibold text-amber-900">Preview Import Outlet</div>
        <div class="text-xs text-amber-800 mt-1">Periksa dulu data yang akan dibuat atau diupdate. Duplicate existing tidak akan ditambah baru, tetapi diupdate setelah Anda konfirmasi.</div>
      </div>

      <div class="grid gap-2 md:grid-cols-3 text-sm text-amber-950">
        <div>Akan dibuat: {{ data_get($importPreview, 'summary.creates', 0) }}</div>
        <div>Akan diupdate: {{ data_get($importPreview, 'summary.updates', 0) }}</div>
        <div>Gagal: {{ data_get($importPreview, 'summary.failed', 0) }}</div>
      </div>

      @php($previewWarnings = collect(data_get($importPreview, 'warnings', []))->take(5))
      @if($previewWarnings->isNotEmpty())
        <div class="rounded-lg border border-amber-300 bg-white/60 p-3 text-xs text-amber-900">
          @foreach($previewWarnings as $warning)
            <div>{{ $warning }}</div>
          @endforeach
        </div>
      @endif

      <div class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-lg border bg-white p-3">
          <div class="font-semibold text-sm text-emerald-700">Akan Dibuat</div>
          <div class="mt-2 space-y-2 text-xs text-slate-700">
            @forelse(data_get($importPreview, 'creates', []) as $item)
              <div class="rounded border p-2">
                <div class="font-medium">Baris {{ data_get($item, 'row') }} - {{ data_get($item, 'name') }}</div>
                <div>{{ data_get($item, 'brand_name') }} | {{ data_get($item, 'external_id') ?: '-' }}</div>
                <div>Jam: {{ data_get($item, 'work_start_time') ?: '-' }} - {{ data_get($item, 'work_end_time') ?: '-' }}</div>
              </div>
            @empty
              <div class="text-slate-500">Tidak ada data baru pada preview ini.</div>
            @endforelse
          </div>
        </div>

        <div class="rounded-lg border bg-white p-3">
          <div class="font-semibold text-sm text-blue-700">Akan Diupdate</div>
          <div class="mt-2 space-y-2 text-xs text-slate-700">
            @forelse(data_get($importPreview, 'updates', []) as $item)
              <div class="rounded border p-2">
                <div class="font-medium">Baris {{ data_get($item, 'row') }} - {{ data_get($item, 'name') }}</div>
                <div>{{ data_get($item, 'brand_name') }} | {{ data_get($item, 'external_id') ?: '-' }}</div>
                <div>Sumber match: {{ data_get($item, 'match_source') === 'external_id' ? 'match by external_id' : 'match by name' }}</div>
                <div>Outlet existing: {{ data_get($item, 'existing_name') ?: '-' }}</div>
                <div>Jam: {{ data_get($item, 'work_start_time') ?: '-' }} - {{ data_get($item, 'work_end_time') ?: '-' }}</div>
              </div>
            @empty
              <div class="text-slate-500">Tidak ada data existing yang akan diupdate.</div>
            @endforelse
          </div>
        </div>

        <div class="rounded-lg border bg-white p-3">
          <div class="font-semibold text-sm text-rose-700">Gagal Dibaca</div>
          <div class="mt-2 space-y-2 text-xs text-slate-700">
            @forelse(data_get($importPreview, 'errors', []) as $rowError)
              <div class="rounded border p-2">
                <div class="font-medium">Baris {{ data_get($rowError, 'row', '?') }}</div>
                <div>{{ data_get($rowError, 'message', '-') }}</div>
              </div>
            @empty
              <div class="text-slate-500">Tidak ada error pada preview ini.</div>
            @endforelse
          </div>
        </div>
      </div>

      <div class="flex flex-wrap gap-2">
        <form method="POST" action="{{ route('outlets.import.confirm') }}">
          @csrf
          <input type="hidden" name="preview_token" value="{{ data_get($importPreview, 'token') }}">
          <button class="px-4 py-2 rounded bg-emerald-600 text-white">Ya, Update & Simpan</button>
        </form>
        <form method="POST" action="{{ route('outlets.import.cancel') }}">
          @csrf
          <button class="px-4 py-2 rounded border border-slate-300 bg-white text-slate-700">Batal</button>
        </form>
      </div>
    </div>
  @endif

  {{-- Filter tipe outlet --}}
  <div class="flex flex-wrap items-center gap-2 mb-4">
    <span class="text-sm font-medium" style="color:#6b7280">Filter:</span>

    <a href="{{ route('outlets.index') }}"
       style="{{ !request('type')
         ? 'background:#1f2937;color:#fff;border-color:#1f2937'
         : 'background:#fff;color:#4b5563;border-color:#d1d5db' }}"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium transition-colors border">
      Semua
      <span style="{{ !request('type') ? 'background:#fff;color:#1f2937' : 'background:#f3f4f6;color:#4b5563' }}"
            class="inline-flex items-center justify-center w-5 h-5 text-xs rounded-full">
        {{ \App\Models\Outlet::count() }}
      </span>
    </a>

    <a href="{{ route('outlets.index', ['type' => 'operational']) }}"
       style="{{ request('type') === 'operational'
         ? 'background:#16a34a;color:#fff;border-color:#16a34a'
         : 'background:#fff;color:#15803d;border-color:#86efac' }}"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium transition-colors border">
      <span class="w-2 h-2 rounded-full"
            style="background:{{ request('type') === 'operational' ? '#fff' : '#16a34a' }}"></span>
      Operasional
      <span style="background:#dcfce7;color:#15803d"
            class="inline-flex items-center justify-center w-5 h-5 text-xs rounded-full">
        {{ \App\Models\Outlet::where('outlet_type','operational')->count() }}
      </span>
    </a>

    <a href="{{ route('outlets.index', ['type' => 'payroll']) }}"
       style="{{ request('type') === 'payroll'
         ? 'background:#1d4ed8;color:#fff;border-color:#1d4ed8'
         : 'background:#fff;color:#1d4ed8;border-color:#93c5fd' }}"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium transition-colors border">
      <span class="w-2 h-2 rounded-full"
            style="background:{{ request('type') === 'payroll' ? '#fff' : '#1d4ed8' }}"></span>
      Payroll / Legal
      <span style="background:#dbeafe;color:#1d4ed8"
            class="inline-flex items-center justify-center w-5 h-5 text-xs rounded-full">
        {{ \App\Models\Outlet::where('outlet_type','payroll')->count() }}
      </span>
    </a>

    <a href="{{ route('outlets.index', ['type' => 'production']) }}"
       style="{{ request('type') === 'production'
         ? 'background:#ea580c;color:#fff;border-color:#ea580c'
         : 'background:#fff;color:#c2410c;border-color:#fdba74' }}"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium transition-colors border">
      <span class="w-2 h-2 rounded-full"
            style="background:{{ request('type') === 'production' ? '#fff' : '#ea580c' }}"></span>
      Unit Produksi
      <span style="background:#ffedd5;color:#c2410c"
            class="inline-flex items-center justify-center w-5 h-5 text-xs rounded-full">
        {{ \App\Models\Outlet::where('outlet_type','production')->count() }}
      </span>
    </a>
  </div>

  <div class="bg-white rounded-lg border overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-gray-50">
        <tr class="text-left">
          <th class="p-3 w-20">ID</th>
          <th class="p-3">Nama</th>
          <th class="p-3">Tipe</th>
          <th class="p-3">Region</th>
          <th class="p-3">Entitas Legal</th>
          <th class="p-3">Timezone</th>
          <th class="p-3">Koordinat</th>
          <th class="p-3">Radius</th>
          <th class="p-3">Perizinan</th>
          <th class="p-3 w-64">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @foreach($items as $it)
          <tr class="border-t align-top">
            <td class="p-3">{{ $it->id }}</td>
            <td class="p-3">
              <div class="font-medium">{{ $it->name }}</div>
              <div class="text-xs text-slate-500">{{ $it->brand_name ?: '-' }} | {{ $it->external_id ?: '-' }}</div>
            </td>
            <td class="p-3">
              <span style="{{ $it->outlet_type_badge_color }}">{{ $it->outlet_type_label }}</span>
            </td>
            <td class="p-3">
              @if($it->region)
                <a href="{{ route('master.regions.edit', $it->region_id) }}"
                   class="inline-block px-2 py-0.5 rounded text-xs bg-indigo-100 text-indigo-700 hover:bg-indigo-200">
                  {{ $it->region->name }}
                </a>
              @else
                <a href="{{ route('master.regions.index') }}"
                   class="inline-block px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-500 hover:bg-gray-200">
                  Belum diatur
                </a>
              @endif
            </td>
            <td class="p-3">
              @if($it->legalEntity)
                <a href="{{ route('master.legal-entities.edit', $it->legal_entity_id) }}"
                   class="inline-block px-2 py-0.5 rounded text-xs bg-emerald-100 text-emerald-700 hover:bg-emerald-200">
                  {{ $it->legalEntity->short_name ?? $it->legalEntity->name }}
                </a>
              @else
                <a href="{{ route('master.legal-entities.index') }}"
                   class="inline-block px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-500 hover:bg-gray-200">
                  Belum diatur
                </a>
              @endif
            </td>
            <td class="p-3">{{ $it->timezone ?? 'Asia/Jakarta' }}</td>
            <td class="p-3">{{ $it->latitude ?? '-' }}, {{ $it->longitude ?? '-' }}</td>
            <td class="p-3">{{ (int) ($it->radius_meters ?? $it->geofence_radius_m ?? 5) }} m</td>
            <td class="p-3">
              <div>{{ $it->permits_count }} dokumen</div>
              <div class="text-xs text-slate-500">Kelola dari halaman edit outlet</div>
            </td>
            <td class="p-3">
              <a class="text-indigo-600" href="{{ route('outlets.edit',$it) }}">Edit</a>
              <form method="POST" action="{{ route('outlets.destroy',$it) }}" class="inline" onsubmit="return confirm('Hapus outlet ini?')">
                @csrf @method('DELETE')
                <button class="text-red-600 ml-3">Hapus</button>
              </form>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mt-2">
    <div class="flex flex-wrap items-center gap-4">
      <span class="text-sm text-gray-500">
        Menampilkan {{ $items->firstItem() ?? 0 }}–{{ $items->lastItem() ?? 0 }}
        dari {{ $items->total() }} outlet
      </span>
      <x-per-page-selector :per-page="(int) request('per_page', 15)" />
    </div>
    <div>{{ $items->appends(request()->query())->links() }}</div>
  </div>
</div>
@endsection
