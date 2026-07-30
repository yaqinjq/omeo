@extends('layouts.app')
@section('content')
<div class="max-w-2xl mx-auto space-y-4">
  <div>
    <h1 class="text-2xl font-semibold">Upload Tagihan Resmi BPJS</h1>
    <p class="text-sm text-slate-500 mt-1">Formulir 2a PU — BPJS Ketenagakerjaan</p>
  </div>

  @if($errors->any())
    <div class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">
      <ul class="list-disc list-inside space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('finance.bpjs-reconciliation.store') }}"
        enctype="multipart/form-data"
        class="bg-white rounded-lg border p-6 space-y-5">
    @csrf

    <div class="grid gap-4 md:grid-cols-2">
      <div>
        <label class="block text-sm font-medium mb-1">Periode <span class="text-red-500">*</span></label>
        <input type="text" name="periode" value="{{ old('periode', now()->format('Y-m')) }}" required
               class="border rounded px-3 py-2 w-full font-mono @error('periode') border-red-400 @enderror"
               placeholder="2025-01" pattern="\d{4}-\d{2}">
        @error('periode')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Entitas Legal</label>
        <select name="legal_entity_id" class="border rounded px-3 py-2 w-full">
          <option value="">-- Otomatis dari NPP --</option>
          @foreach($legalEntities as $le)
            <option value="{{ $le->id }}" @selected(old('legal_entity_id') == $le->id)>
              {{ $le->short_name ?? $le->name }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">Akun BPJS Ketenagakerjaan</label>
        <select name="bpjs_legal_account_id" class="border rounded px-3 py-2 w-full">
          <option value="">-- Tidak dikaitkan --</option>
          @foreach($bpjsAccounts as $acc)
            <option value="{{ $acc->id }}" @selected(old('bpjs_legal_account_id') == $acc->id)>
              {{ $acc->account_name ?? $acc->account_number }}
              @if($acc->npp) — NPP: {{ $acc->npp }}@endif
              @if($acc->legalEntity) ({{ $acc->legalEntity->short_name ?? $acc->legalEntity->name }})@endif
            </option>
          @endforeach
        </select>
      </div>

      <div class="md:col-span-2"
           x-data="{
             files: [],
             isDragging: false,
             updateFiles(e) {
               this.files = Array.from(e.target.files).map(f => ({
                 name: f.name,
                 size: (f.size / 1024 / 1024).toFixed(2)
               }));
             },
             handleDrop(e) {
               this.isDragging = false;
               const dt = e.dataTransfer;
               if (dt && dt.files.length) {
                 this.$refs.fileInput.files = dt.files;
                 this.updateFiles({ target: this.$refs.fileInput });
               }
             }
           }">
        <label class="block text-sm font-medium mb-2">
          File Tagihan <span class="text-red-500">*</span>
          <span class="ml-1 text-xs text-gray-400 font-normal">(pilih satu atau beberapa file — maks. 10)</span>
        </label>

        <div class="border-2 border-dashed rounded-lg p-6 text-center transition-colors cursor-pointer"
             :class="isDragging ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300 hover:border-indigo-400'"
             @dragover.prevent="isDragging = true"
             @dragleave.prevent="isDragging = false"
             @drop.prevent="handleDrop($event)"
             @click="$refs.fileInput.click()">

          <input type="file"
                 name="pdf_files[]"
                 id="fileInput"
                 x-ref="fileInput"
                 accept=".pdf,.xlsx,.xls,.csv"
                 multiple
                 class="hidden"
                 @change="updateFiles($event)">

          <svg class="mx-auto w-10 h-10 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>

          <p class="text-sm text-gray-600">
            Klik atau drag &amp; drop file PDF / Excel / CSV
          </p>
          <p class="text-xs text-gray-400 mt-1">Maks. 10 file, 20 MB per file</p>
        </div>

        {{-- Alpine.js file preview list --}}
        <template x-if="files.length > 0">
          <div class="mt-3 space-y-1">
            <p class="text-xs text-gray-500 font-medium">
              File yang dipilih (<span x-text="files.length"></span>):
            </p>
            <template x-for="f in files" :key="f.name">
              <div class="flex items-center gap-2 text-xs text-gray-600 bg-gray-50 rounded px-3 py-1.5 border border-gray-200">
                <span>📄</span>
                <span x-text="f.name" class="flex-1 truncate"></span>
                <span class="text-gray-400 shrink-0" x-text="'(' + f.size + ' MB)'"></span>
              </div>
            </template>
          </div>
        </template>

        @error('pdf_files')
          <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
        @enderror
        @error('pdf_files.*')
          <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
        @enderror
      </div>
    </div>

    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-800">
      <p class="font-medium mb-1">Format yang didukung:</p>
      <ul class="list-disc list-inside space-y-1 text-xs">
        <li><strong>PDF digital</strong> — PDF dengan teks yang bisa diseleksi (bukan scan) — mendukung multi-upload</li>
        <li><strong>Excel / CSV</strong> — file Formulir 2a PU dari BPJSTK Online, kolom: NIK, Nama, JKK, JKM, JHT, JP</li>
        <li>PDF scan belum didukung (memerlukan OCR)</li>
        <li>Upload beberapa file sekaligus: pilih multiple atau drag &amp; drop bersama</li>
      </ul>
    </div>

    <div class="flex gap-3 pt-2">
      <button type="submit" class="px-5 py-2 rounded bg-gray-900 text-white text-sm">
        Upload &amp; Parse
      </button>
      <a href="{{ route('finance.bpjs-reconciliation.index') }}" class="px-5 py-2 rounded border text-sm">Batal</a>
    </div>
  </form>
</div>

@endsection
