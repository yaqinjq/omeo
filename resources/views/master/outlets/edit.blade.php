@extends('layouts.app')
@section('content')
<div class="max-w-5xl mx-auto space-y-6">
  <div>
    <h1 class="text-2xl font-semibold mb-2">Edit Outlet</h1>
    <p class="text-sm text-slate-500">Lengkapi data geofence dan dokumentasi perizinan outlet di satu tempat agar HRD tidak perlu berpindah flow.</p>
  </div>

  <form method="POST" action="{{ route('outlets.update', $outlet) }}" class="bg-white border rounded-lg p-4 space-y-4" id="outletFormEdit">
    @csrf @method('PUT')

    <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
      HRD bisa cukup perbarui alamat outlet dan tempel link Google Maps atau koordinat. Sistem akan bantu isi latitude dan longitude otomatis agar geofence tetap akurat.
    </div>

    <div class="grid md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm text-gray-600">Brand Name</label>
        <input name="brand_name" value="{{ old('brand_name', $outlet->brand_name) }}" class="border rounded px-3 py-2 w-full">
        @error('brand_name')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="block text-sm text-gray-600">External ID</label>
        <input name="external_id" value="{{ old('external_id', $outlet->external_id) }}" class="border rounded px-3 py-2 w-full">
      </div>
    </div>

    <div>
      <label class="block text-sm text-gray-600">Alamat Outlet</label>
      <input name="location" value="{{ old('location', $outlet->location) }}" class="border rounded px-3 py-2 w-full" placeholder="Contoh: JL. KENCANA SARI TIMUR II BLOK H NO. 33 / 33A SURABAYA">
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
      <input name="name" value="{{ old('name', $outlet->name) }}" class="border rounded px-3 py-2 w-full">
      @error('name')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        Tipe Outlet <span class="text-red-500">*</span>
      </label>
      <select name="outlet_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
        <option value="operational" {{ old('outlet_type', $outlet->outlet_type) === 'operational' ? 'selected' : '' }}>Operasional (customer-facing)</option>
        <option value="payroll"     {{ old('outlet_type', $outlet->outlet_type) === 'payroll'     ? 'selected' : '' }}>Payroll / Legal (internal)</option>
        <option value="production"  {{ old('outlet_type', $outlet->outlet_type) === 'production'  ? 'selected' : '' }}>Unit Produksi (internal)</option>
      </select>
      <p class="text-xs text-gray-400 mt-1">"Payroll" dan "Produksi" tidak muncul di dropdown presensi/HRD.</p>
      @error('outlet_type')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">PT / Badan Hukum (untuk BPJS)</label>
      <select name="legal_entity_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
        <option value="">— Belum ditentukan —</option>
        @foreach($legalEntities as $legalEntity)
          <option value="{{ $legalEntity->id }}" {{ (string) old('legal_entity_id', $outlet->legal_entity_id) === (string) $legalEntity->id ? 'selected' : '' }}>{{ $legalEntity->name }}</option>
        @endforeach
      </select>
      <p class="text-xs text-gray-400 mt-1">PT yang menanggung BPJS outlet ini. Dipakai untuk laporan cross-billing antar outlet.</p>
      @error('legal_entity_id')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
    </div>

    <div class="grid md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm text-gray-600">Latitude Outlet</label>
        <input name="latitude" value="{{ old('latitude', $outlet->latitude) }}" class="border rounded px-3 py-2 w-full" placeholder="-6.2000000">
      </div>
      <div>
        <label class="block text-sm text-gray-600">Longitude Outlet</label>
        <input name="longitude" value="{{ old('longitude', $outlet->longitude) }}" class="border rounded px-3 py-2 w-full" placeholder="106.8166667">
      </div>
    </div>

    <div class="grid md:grid-cols-3 gap-4">
      <div>
        <label class="block text-sm text-gray-600">Radius Geofence (meter)</label>
        <input type="number" min="1" name="radius_meters" value="{{ old('radius_meters', $outlet->radius_meters ?? $outlet->geofence_radius_m ?? 5) }}" class="border rounded px-3 py-2 w-full">
      </div>
      <div>
        <label class="block text-sm text-gray-600">Timezone</label>
        <input name="timezone" value="{{ old('timezone', $outlet->timezone ?? 'Asia/Jakarta') }}" class="border rounded px-3 py-2 w-full" placeholder="Asia/Jakarta">
      </div>
      <div class="text-xs text-gray-500 self-end">Contoh timezone valid: Asia/Jakarta, Asia/Makassar, Asia/Jayapura.</div>
    </div>

    <div class="grid md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm text-gray-600">Jam Masuk Standar (HH:MM)</label>
        <input type="time" name="work_start_time" value="{{ old('work_start_time', $outlet->work_start_time) }}" class="border rounded px-3 py-2 w-full">
      </div>
      <div>
        <label class="block text-sm text-gray-600">Jam Pulang Standar (HH:MM)</label>
        <input type="time" name="work_end_time" value="{{ old('work_end_time', $outlet->work_end_time) }}" class="border rounded px-3 py-2 w-full">
      </div>
    </div>

    <div>
      <label class="block text-sm text-gray-600">Owner In Charge</label>
      <input name="owner_in_charge_name" value="{{ old('owner_in_charge_name', $outlet->owner_in_charge_name) }}" class="border rounded px-3 py-2 w-full" placeholder="Nama pemilik outlet cabang/franchise">
      <p class="text-xs text-gray-400 mt-1">Dipakai sebagai pilihan penanda tangan appraisal "Owner In Charge" — bukan login sistem, cuma nama untuk dicetak di dokumen.</p>
      @error('owner_in_charge_name')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
    </div>

    <div class="flex gap-2">
      <button class="px-4 py-2 rounded bg-gray-900 text-white">Simpan</button>
      <a href="{{ route('outlets.index') }}" class="px-4 py-2 rounded border">Kembali</a>
    </div>
  </form>

  <div class="bg-white border rounded-lg p-4 space-y-4">
    <div>
      <h2 class="text-lg font-semibold">Dokumen Perizinan Outlet</h2>
      <p class="text-sm text-slate-500">Satu outlet dapat memiliki banyak izin dan masing-masing bisa menyimpan beberapa lampiran dokumen/gambar pendukung.</p>
    </div>
    @if ($errors->any())
      <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
        <div class="font-semibold">Data izin belum tersimpan.</div>
        <div class="mt-1">Periksa kembali field izin outlet dan lampiran yang diunggah.</div>
      </div>
    @endif

    <form method="POST" action="{{ route('outlets.permits.store', $outlet) }}" enctype="multipart/form-data" class="border rounded-lg p-4 grid gap-4 md:grid-cols-2">
      @csrf
      <div>
        <label class="block text-sm text-gray-600">Jenis Izin</label>
        <input name="permit_type" class="border rounded px-3 py-2 w-full" placeholder="Contoh: NIB, Izin Usaha, Sertifikat Laik Higiene">
      </div>
      <div>
        <label class="block text-sm text-gray-600">Nomor Dokumen</label>
        <input name="document_number" class="border rounded px-3 py-2 w-full">
      </div>
      <div>
        <label class="block text-sm text-gray-600">Instansi Penerbit</label>
        <input name="issuer_name" class="border rounded px-3 py-2 w-full">
      </div>
      <div>
        <label class="block text-sm text-gray-600">Status</label>
        <select name="status" class="border rounded px-3 py-2 w-full">
          @foreach(['active' => 'Aktif', 'draft' => 'Draft', 'expired' => 'Expired', 'revoked' => 'Revoked'] as $value => $label)
            <option value="{{ $value }}">{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="block text-sm text-gray-600">Tanggal Terbit</label>
        <input type="date" name="issued_at" class="border rounded px-3 py-2 w-full">
      </div>
      <div>
        <label class="block text-sm text-gray-600">Berlaku Sampai</label>
        <input type="date" name="expires_at" class="border rounded px-3 py-2 w-full">
      </div>
      <div class="md:col-span-2">
        <label class="block text-sm text-gray-600">Catatan</label>
        <textarea name="notes" rows="3" class="border rounded px-3 py-2 w-full"></textarea>
      </div>
      <div class="md:col-span-2">
        <label class="block text-sm text-gray-600">Lampiran Dokumen / Gambar</label>
        <input type="file" name="attachments[]" multiple class="border rounded px-3 py-2 w-full" accept=".pdf,.jpg,.jpeg,.png,.webp">
      </div>
      <div class="md:col-span-2">
        <button class="px-4 py-2 rounded bg-blue-600 text-white">Tambah Dokumen Izin</button>
      </div>
    </form>

    <div class="space-y-4">
      @forelse($outlet->permits as $permit)
        <div class="border rounded-lg p-4 space-y-3">
          <form method="POST" action="{{ route('outlets.permits.update', [$outlet, $permit]) }}" enctype="multipart/form-data" class="grid gap-4 md:grid-cols-2">
            @csrf
            @method('PUT')
            <div>
              <label class="block text-sm text-gray-600">Jenis Izin</label>
              <input name="permit_type" value="{{ $permit->permit_type }}" class="border rounded px-3 py-2 w-full">
            </div>
            <div>
              <label class="block text-sm text-gray-600">Nomor Dokumen</label>
              <input name="document_number" value="{{ $permit->document_number }}" class="border rounded px-3 py-2 w-full">
            </div>
            <div>
              <label class="block text-sm text-gray-600">Instansi Penerbit</label>
              <input name="issuer_name" value="{{ $permit->issuer_name }}" class="border rounded px-3 py-2 w-full">
            </div>
            <div>
              <label class="block text-sm text-gray-600">Status</label>
              <select name="status" class="border rounded px-3 py-2 w-full">
                @foreach(['active' => 'Aktif', 'draft' => 'Draft', 'expired' => 'Expired', 'revoked' => 'Revoked'] as $value => $label)
                  <option value="{{ $value }}" @selected($permit->status === $value)>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label class="block text-sm text-gray-600">Tanggal Terbit</label>
              <input type="date" name="issued_at" value="{{ optional($permit->issued_at)->format('Y-m-d') }}" class="border rounded px-3 py-2 w-full">
            </div>
            <div>
              <label class="block text-sm text-gray-600">Berlaku Sampai</label>
              <input type="date" name="expires_at" value="{{ optional($permit->expires_at)->format('Y-m-d') }}" class="border rounded px-3 py-2 w-full">
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm text-gray-600">Catatan</label>
              <textarea name="notes" rows="3" class="border rounded px-3 py-2 w-full">{{ $permit->notes }}</textarea>
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm text-gray-600">Tambah Lampiran Baru</label>
              <input type="file" name="attachments[]" multiple class="border rounded px-3 py-2 w-full" accept=".pdf,.jpg,.jpeg,.png,.webp">
            </div>
            <div class="md:col-span-2 flex flex-wrap gap-2">
              <button class="px-4 py-2 rounded bg-slate-900 text-white">Simpan Perubahan Izin</button>
            </div>
          </form>

          <form method="POST" action="{{ route('outlets.permits.destroy', [$outlet, $permit]) }}" onsubmit="return confirm('Hapus dokumen izin ini?')">
            @csrf @method('DELETE')
            <button class="px-4 py-2 rounded border border-red-300 text-red-600">Hapus Izin</button>
          </form>

          <div class="rounded-lg bg-slate-50 p-3">
            <div class="text-sm font-medium mb-2">Lampiran</div>
            <div class="space-y-2">
              @forelse($permit->attachments as $attachment)
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between border rounded bg-white px-3 py-2 text-sm">
                  <a href="{{ asset('storage/' . ltrim($attachment->file_path, '/')) }}" target="_blank" class="text-blue-600 hover:underline">{{ $attachment->original_name ?: basename($attachment->file_path) }}</a>
                  <form method="POST" action="{{ route('outlets.permits.attachments.destroy', [$outlet, $permit, $attachment]) }}" onsubmit="return confirm('Hapus lampiran ini?')">
                    @csrf @method('DELETE')
                    <button class="text-red-600">Hapus Lampiran</button>
                  </form>
                </div>
              @empty
                <div class="text-sm text-slate-500">Belum ada lampiran untuk dokumen izin ini.</div>
              @endforelse
            </div>
          </div>
        </div>
      @empty
        <div class="text-sm text-slate-500">Belum ada dokumen perizinan outlet yang tercatat.</div>
      @endforelse
    </div>
  </div>
</div>

<script>
(function () {
  const form = document.getElementById('outletFormEdit');
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



