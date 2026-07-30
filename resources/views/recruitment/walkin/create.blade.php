@extends('layouts.app')
@section('title', 'Buat Event Walk-In')
@section('content')
<div class="p-6 max-w-2xl" x-data="{
    positions: [],
    newPos: '',
    addPos() {
        const v = this.newPos.trim().toUpperCase();
        if (v && !this.positions.includes(v)) this.positions.push(v);
        this.newPos = '';
    },
    removePos(i) { this.positions.splice(i, 1); },
    handleKey(e) { if (e.key === 'Enter') { e.preventDefault(); this.addPos(); } }
}">

    {{-- HEADER --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('walkin.index') }}" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M15 18l-6-6 6-6"/>
            </svg>
        </a>
        <h1 class="text-xl font-bold text-gray-800">Buat Event Walk-In</h1>
    </div>

    @if($errors->any())
    <div class="mb-4 px-4 py-3 rounded-xl text-sm bg-red-50 text-red-700 border border-red-200">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('walkin.store') }}" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-5">
        @csrf

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Judul Event <span class="text-red-500">*</span></label>
            <input type="text" name="title" value="{{ old('title') }}" required
                   class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
                   placeholder="Walk In Interview – Juni 2026">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Mulai Event (WIB) <span class="text-red-500">*</span></label>
                <input type="datetime-local" name="event_start_datetime" value="{{ old('event_start_datetime') }}" required
                       class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Selesai Event (WIB) <span class="text-red-500">*</span></label>
                <input type="datetime-local" name="event_end_datetime" value="{{ old('event_end_datetime') }}" required
                       class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
            </div>
        </div>
        <p class="text-xs text-gray-400 -mt-3">Waktu Indonesia Barat (WIB, UTC+7). Status otomatis berubah: Terbuka → Berlangsung → Selesai berdasarkan waktu.</p>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Status Awal</label>
            <select name="status" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                <option value="draft" @selected(old('status','draft')==='draft')>Draft (belum publish)</option>
                <option value="published" @selected(old('status')==='published')>Published (buka pendaftaran)</option>
            </select>
            <p class="text-xs text-gray-400 mt-1">Status Berlangsung dan Selesai dihitung otomatis dari waktu event</p>
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Lokasi</label>
            <input type="text" name="location" value="{{ old('location') }}"
                   class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
                   placeholder="Jl. Contoh No. 1, Jakarta">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Batas Pendaftaran</label>
            <input type="datetime-local" name="registration_open_until" value="{{ old('registration_open_until') }}"
                   class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
            <p class="text-xs text-gray-400 mt-1">Kosongkan jika pendaftaran dibuka sampai hari H</p>
        </div>

        {{-- Posisi yang Dicari — Tag Input --}}
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Posisi yang Dicari</label>
            <div class="rounded-xl border border-gray-200 p-2 min-h-[3rem] flex flex-wrap gap-2"
                 @click="$refs.posInput.focus()">
                <template x-for="(pos, i) in positions" :key="i">
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold text-white" style="background:#6366f1">
                        <span x-text="pos"></span>
                        <button type="button" @click.stop="removePos(i)" class="hover:opacity-70">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                <path d="M18 6L6 18M6 6l12 12"/>
                            </svg>
                        </button>
                    </span>
                </template>
                <input x-ref="posInput" type="text" x-model="newPos"
                       @keydown="handleKey"
                       @blur="addPos"
                       class="flex-1 min-w-[120px] text-sm outline-none bg-transparent px-1"
                       placeholder="Ketik posisi + Enter (COOK HELPER, KASIR, ...)">
            </div>
            <p class="text-xs text-gray-400 mt-1">Tekan Enter atau pindah kursor untuk menambah posisi</p>

            {{-- Hidden inputs --}}
            <template x-for="(pos, i) in positions" :key="'hidden-'+i">
                <input type="hidden" :name="'target_positions[' + i + ']'" :value="pos">
            </template>
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Deskripsi Event</label>
            <textarea name="description" rows="4"
                      class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
                      placeholder="Detail acara, persyaratan, dan informasi penting lainnya...">{{ old('description') }}</textarea>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit"
                    class="px-6 py-2.5 rounded-xl text-sm font-semibold text-white"
                    style="background:#6366f1">
                Simpan Event
            </button>
            <a href="{{ route('walkin.index') }}"
               class="px-6 py-2.5 rounded-xl text-sm font-semibold text-gray-600 border border-gray-200 hover:bg-gray-50">
                Batal
            </a>
        </div>
    </form>

</div>
@endsection
