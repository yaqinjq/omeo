@extends('layouts.app')
@section('content')
<div class="max-w-3xl mx-auto">
  <h1 class="text-2xl font-semibold mb-4">Tambah Outlet</h1>
  <form method="POST" action="{{ route('outlets.store') }}" class="bg-white border rounded-lg p-4 space-y-4" id="outletFormCreate">
    @csrf

    <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
      HRD cukup isi alamat outlet dan tempel link Google Maps atau koordinat dari tombol <strong>Bagikan lokasi ini</strong>. Sistem akan bantu isi latitude dan longitude otomatis.
    </div>

    <div class="grid md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm text-gray-600">Brand Name</label>
        <input name="brand_name" value="{{ old('brand_name') }}" class="border rounded px-3 py-2 w-full">
        @error('brand_name')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="block text-sm text-gray-600">External ID</label>
        <input name="external_id" value="{{ old('external_id') }}" class="border rounded px-3 py-2 w-full">
      </div>
    </div>

    <div>
      <label class="block text-sm text-gray-600">Alamat Outlet</label>
      <input name="location" value="{{ old('location') }}" class="border rounded px-3 py-2 w-full" placeholder="Contoh: JL. KENCANA SARI TIMUR II BLOK H NO. 33 / 33A SURABAYA">
    </div>

    <div>
      <label class="block text-sm text-gray-600">Link Google Maps / Koordinat</label>
      <textarea name="maps_reference" rows="3" class="border rounded px-3 py-2 w-full" placeholder="Tempel link Google Maps, share location, atau koordinat seperti -7.29699,112.71980">{{ old('maps_reference') }}</textarea>
      <div class="mt-2 flex flex-wrap gap-2">
        <button type="button" class="px-4 py-2 rounded border border-slate-300 text-sm font-medium text-slate-700 hover:bg-slate-50" data-parse-coordinates>Isi Koordinat Otomatis</button>
        <span class="text-xs text-gray-500 self-center">Tip: di Google Maps buka menu titik lokasi lalu copy angka koordinat atau link share.</span>
      </div>
      <div class="mt-2 hidden rounded border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800" data-parse-success></div>
      <div class="mt-2 hidden rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800" data-parse-error></div>
    </div>

    <div>
      <label class="block text-sm text-gray-600">Nama</label>
      <input name="name" value="{{ old('name') }}" class="border rounded px-3 py-2 w-full">
      @error('name')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        Tipe Outlet <span class="text-red-500">*</span>
      </label>
      <select name="outlet_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
        <option value="operational" {{ old('outlet_type', 'operational') === 'operational' ? 'selected' : '' }}>Operasional (customer-facing)</option>
        <option value="payroll"     {{ old('outlet_type') === 'payroll'     ? 'selected' : '' }}>Payroll / Legal (internal)</option>
        <option value="production"  {{ old('outlet_type') === 'production'  ? 'selected' : '' }}>Unit Produksi (internal)</option>
      </select>
      <p class="text-xs text-gray-400 mt-1">"Payroll" dan "Produksi" tidak muncul di dropdown presensi/HRD.</p>
      @error('outlet_type')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">PT / Badan Hukum (untuk BPJS)</label>
      <select name="legal_entity_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
        <option value="">— Belum ditentukan —</option>
        @foreach($legalEntities as $legalEntity)
          <option value="{{ $legalEntity->id }}" {{ (string) old('legal_entity_id') === (string) $legalEntity->id ? 'selected' : '' }}>{{ $legalEntity->name }}</option>
        @endforeach
      </select>
      <p class="text-xs text-gray-400 mt-1">PT yang menanggung BPJS outlet ini. Dipakai untuk laporan cross-billing antar outlet.</p>
      @error('legal_entity_id')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
    </div>

    <div class="grid md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm text-gray-600">Latitude Outlet</label>
        <input name="latitude" value="{{ old('latitude') }}" class="border rounded px-3 py-2 w-full" placeholder="-6.2000000">
      </div>
      <div>
        <label class="block text-sm text-gray-600">Longitude Outlet</label>
        <input name="longitude" value="{{ old('longitude') }}" class="border rounded px-3 py-2 w-full" placeholder="106.8166667">
      </div>
    </div>

    <div class="grid md:grid-cols-3 gap-4">
      <div>
        <label class="block text-sm text-gray-600">Radius Geofence (meter)</label>
        <input type="number" min="1" name="radius_meters" value="{{ old('radius_meters', 5) }}" class="border rounded px-3 py-2 w-full">
      </div>
      <div>
        <label class="block text-sm text-gray-600">Timezone</label>
        <input name="timezone" value="{{ old('timezone', 'Asia/Jakarta') }}" class="border rounded px-3 py-2 w-full" placeholder="Asia/Jakarta">
      </div>
      <div class="text-xs text-gray-500 self-end">Contoh timezone valid: Asia/Jakarta, Asia/Makassar, Asia/Jayapura.</div>
    </div>

    <div class="grid md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm text-gray-600">Jam Masuk Standar (HH:MM)</label>
        <input type="time" name="work_start_time" value="{{ old('work_start_time') }}" class="border rounded px-3 py-2 w-full">
      </div>
      <div>
        <label class="block text-sm text-gray-600">Jam Pulang Standar (HH:MM)</label>
        <input type="time" name="work_end_time" value="{{ old('work_end_time') }}" class="border rounded px-3 py-2 w-full">
      </div>
    </div>

    <div>
      <label class="block text-sm text-gray-600">Owner In Charge</label>
      <input name="owner_in_charge_name" value="{{ old('owner_in_charge_name') }}" class="border rounded px-3 py-2 w-full" placeholder="Nama pemilik outlet cabang/franchise">
      <p class="text-xs text-gray-400 mt-1">Dipakai sebagai pilihan penanda tangan appraisal "Owner In Charge" — bukan login sistem, cuma nama untuk dicetak di dokumen.</p>
      @error('owner_in_charge_name')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
    </div>

    <div class="flex gap-2">
      <button class="px-4 py-2 rounded bg-gray-900 text-white">Simpan</button>
      <a href="{{ route('outlets.index') }}" class="px-4 py-2 rounded border">Kembali</a>
    </div>
  </form>
</div>

<script>
(function () {
  const form = document.getElementById('outletFormCreate');
  if (!form) return;

  const trigger = form.querySelector('[data-parse-coordinates]');
  const source = form.querySelector('textarea[name="maps_reference"]');
  const locationField = form.querySelector('input[name="location"]');
  const latField = form.querySelector('input[name="latitude"]');
  const lngField = form.querySelector('input[name="longitude"]');
  const successBox = form.querySelector('[data-parse-success]');
  const errorBox = form.querySelector('[data-parse-error]');

  function setMessage(target, text) {
    if (!target) return;
    target.textContent = text;
    target.classList.remove('hidden');
  }

  function clearMessages() {
    successBox?.classList.add('hidden');
    errorBox?.classList.add('hidden');
    if (successBox) successBox.textContent = '';
    if (errorBox) errorBox.textContent = '';
  }

  function extractCoordinates(text) {
    const patterns = [
      /@(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/,
      /!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/,
      /[?&]q=(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/,
      /\b(-?\d{1,2}\.\d{4,})\s*,\s*(-?\d{1,3}\.\d{4,})\b/
    ];

    for (const pattern of patterns) {
      const match = String(text || '').match(pattern);
      if (!match) continue;
      const lat = Number(match[1]);
      const lng = Number(match[2]);
      if (Number.isNaN(lat) || Number.isNaN(lng)) continue;
      if (lat < -90 || lat > 90 || lng < -180 || lng > 180) continue;
      return { lat, lng };
    }

    return null;
  }

  trigger?.addEventListener('click', function () {
    clearMessages();
    const combined = [source?.value || '', locationField?.value || ''].filter(Boolean).join('\n');
    const result = extractCoordinates(combined);

    if (!result) {
      setMessage(errorBox, 'Koordinat belum ditemukan. Tempel link Google Maps, share link, atau koordinat seperti -7.29699,112.71980.');
      return;
    }

    if (latField) latField.value = result.lat.toFixed(7);
    if (lngField) lngField.value = result.lng.toFixed(7);
    setMessage(successBox, `Koordinat berhasil diisi otomatis: ${result.lat.toFixed(7)}, ${result.lng.toFixed(7)}`);
  });
})();
</script>
@endsection