@extends('layouts.app')
@section('title', 'Setting Finance')
@section('content')
<div class="max-w-2xl mx-auto space-y-4">

  <div>
    <h1 class="text-2xl font-semibold">Setting Finance</h1>
    <p class="text-sm text-slate-500 mt-0.5">Parameter konfigurasi modul Finance.</p>
  </div>

  @if(session('success'))
    <div class="rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</div>
  @endif
  @if($errors->any())
    <div class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">
      <ul class="list-disc list-inside space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  {{-- Info box --}}
  <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
    <strong>Catatan:</strong> Setting ini akan mulai berlaku setelah data HR (hari kerja aktual)
    dan Attd (hari kerja standar) per bulan tersimpan di sistem (Sprint D).
    Saat ini formula "Karyawan Aktif" masih berdasarkan: <strong>gaji bulan &gt; 0</strong>.
  </div>

  @can('finance.import')
  <form method="POST" action="{{ route('finance.settings.update') }}"
        class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    @csrf

    @foreach($settings as $setting)
    <div class="px-6 py-5 border-b border-gray-100 last:border-0">

      @if($setting->key === 'active_employee_min_hr_percent')
        <label class="block text-sm font-semibold text-gray-800 mb-1">
          {{ $setting->label ?? $setting->key }}
        </label>
        <div class="flex items-center gap-2 mt-1">
          <input type="number"
                 name="settings[{{ $setting->key }}]"
                 value="{{ old('settings.' . $setting->key, $setting->value ?? '0') }}"
                 min="0" max="100" step="1"
                 class="border rounded px-3 py-2 w-28 text-sm @error('settings.' . $setting->key) border-red-400 @enderror">
          <span class="text-sm text-gray-500">%</span>
        </div>
        @if($setting->description)
          <p class="text-xs text-gray-400 mt-2">{{ $setting->description }}</p>
        @endif

      @else
        <label class="block text-sm font-semibold text-gray-800 mb-1">
          {{ $setting->label ?? $setting->key }}
        </label>
        <input type="text"
               name="settings[{{ $setting->key }}]"
               value="{{ old('settings.' . $setting->key, $setting->value) }}"
               class="border rounded px-3 py-2 w-full text-sm">
        @if($setting->description)
          <p class="text-xs text-gray-400 mt-2">{{ $setting->description }}</p>
        @endif
      @endif

    </div>
    @endforeach

    <div class="px-6 py-4 bg-gray-50 border-t flex gap-3">
      <button type="submit" class="px-5 py-2 rounded bg-gray-900 text-white text-sm">Simpan Setting</button>
    </div>
  </form>
  @else
    <div class="bg-white rounded-xl border border-gray-200 divide-y">
      @foreach($settings as $setting)
      <div class="px-6 py-4">
        <div class="text-sm font-medium text-gray-700">{{ $setting->label ?? $setting->key }}</div>
        <div class="text-lg font-semibold mt-1">
          {{ $setting->value ?? '—' }}
          @if($setting->key === 'active_employee_min_hr_percent') <span class="text-sm text-gray-400">%</span> @endif
        </div>
        @if($setting->description)
          <p class="text-xs text-gray-400 mt-1">{{ $setting->description }}</p>
        @endif
      </div>
      @endforeach
    </div>
  @endcan

</div>
@endsection
