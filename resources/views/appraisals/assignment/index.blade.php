@extends('layouts.app')

@section('content')
<div class="p-6 space-y-6" x-data="batchBuilder()">

  {{-- ── Header ───────────────────────────────────────────────────────────── --}}
  <div class="card p-6">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
      <div>
        <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Appraisal HRD</div>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">Assignment Appraisal</h1>
        <p class="mt-2 max-w-2xl text-sm text-slate-600">Pilih mode, cari karyawan, tentukan evaluator berbeda per karyawan, lalu generate semua draft sekaligus.</p>
      </div>
      <a href="{{ route('appraisals.index') }}" class="btn-outline shrink-0 self-start">Lanjut ke Monitoring</a>
    </div>
  </div>

  {{-- ── Flash ───────────────────────────────────────────────────────────── --}}
  @if(session('success'))
    <div class="rounded-2xl border border-emerald-300 bg-emerald-50 px-5 py-4 text-sm text-emerald-900">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="rounded-2xl border border-red-300 bg-red-50 px-5 py-4 text-sm text-red-900">{{ session('error') }}</div>
  @endif
  @if(!empty($moduleWarning))
    <div class="rounded-2xl border border-amber-300 bg-amber-50 px-5 py-4 text-sm text-amber-900">{{ $moduleWarning }}</div>
  @endif

  <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
    ℹ️ <strong>Catatan:</strong> Status probation dihitung otomatis dari Tanggal Bergabung (Join Date) + 3 bulan,
    karena data status karyawan hasil import lama belum sepenuhnya akurat.
    Jika ada karyawan dengan masa probation yang berbeda, silakan update manual di profil karyawan.
  </div>

  {{-- ── 2 Tab ───────────────────────────────────────────────────────────── --}}
  <div class="flex gap-1 rounded-2xl border border-slate-200 bg-slate-100 p-1 w-fit">
    <button type="button" @click="setMode('probation')"
      :class="mode === 'probation' ? 'bg-white shadow text-slate-900 font-semibold' : 'text-slate-600 hover:text-slate-900'"
      class="rounded-xl px-5 py-2.5 text-sm transition-colors">
      ⏰ Probation (Window)
    </button>
    <button type="button" @click="setMode('manual')"
      :class="mode === 'manual' ? 'bg-white shadow text-slate-900 font-semibold' : 'text-slate-600 hover:text-slate-900'"
      class="rounded-xl px-5 py-2.5 text-sm transition-colors">
      🔍 Manual (Cari Karyawan)
    </button>
  </div>

  {{-- ── Window filter (probation only) ────────────────────────────────── --}}
  <div x-show="mode === 'probation'" x-cloak class="flex items-end gap-3">
    <div>
      <label class="mb-1 block text-xs text-slate-500">Window kandidat (hari)</label>
      <input type="number" x-model="windowDays" @change="probationCandidates = []; refreshProbation()"
             min="1" max="90" class="w-28 rounded-xl border border-slate-200 px-3 py-2 text-sm">
    </div>
    <button type="button" @click="refreshProbation()"
            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
      Refresh kandidat
    </button>
    <span class="text-xs text-slate-500 self-center">
      <span x-text="probationCandidates.length"></span> kandidat ditemukan
    </span>
  </div>

  {{-- ── Settings card ───────────────────────────────────────────────────── --}}
  <div class="card p-6">
    <h2 class="mb-4 text-base font-semibold text-slate-900">Pengaturan Batch</h2>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
      <div>
        <label class="mb-1 block text-xs text-slate-500">Periode Appraisal *</label>
        <select x-model="periodId" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
          <option value="">-- Pilih periode --</option>
          @foreach($periods as $p)
            <option value="{{ $p->id }}"
              @if($p->id == ($defaultPeriodId ?? 0)) selected @endif>
              {{ $p->name }}
              @if($p->start_date && $p->end_date)
                ({{ \Illuminate\Support\Carbon::parse($p->start_date)->format('d/m/Y') }} – {{ \Illuminate\Support\Carbon::parse($p->end_date)->format('d/m/Y') }})
              @endif
            </option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="mb-1 block text-xs text-slate-500">Tanggal Appraisal *</label>
        <input type="date" x-model="dateAppraised" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
      </div>
      <div>
        <label class="mb-1 block text-xs text-slate-500">Due Date Evaluator</label>
        <input type="date" x-model="dueDate" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
      </div>
    </div>
    <div class="mt-4">
      <label class="mb-1 block text-xs text-slate-500">Catatan Invitation Evaluator</label>
      <textarea x-model="invitationNote" rows="2"
        placeholder="Contoh: Mohon fokus pada KPI, hasil training, kesiapan role, dan kedisiplinan kerja."
        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></textarea>
    </div>
    <div class="mt-4">
      <label class="mb-2 block text-xs text-slate-500">Komponen Penilaian Aktif</label>
      <div class="flex flex-wrap gap-4 text-sm">
        <label class="inline-flex items-center gap-2">
          <input type="checkbox" x-model="enableKpi" class="rounded"> KPI (aktif by default)
        </label>
        <label class="inline-flex items-center gap-2">
          <input type="checkbox" x-model="enableSkill" class="rounded"> Kompetensi Keahlian
        </label>
        <label class="inline-flex items-center gap-2">
          <input type="checkbox" x-model="enablePosition" class="rounded"> Kompetensi Posisi/Jabatan
        </label>
      </div>
    </div>
  </div>

  {{-- ── Builder card ────────────────────────────────────────────────────── --}}
  <div class="card p-6">
    <h2 class="mb-1 text-base font-semibold text-slate-900">➕ Tambah Karyawan ke Batch</h2>
    <p class="mb-5 text-xs text-slate-500">Pilih karyawan, tambah 1 atau lebih evaluator, lalu klik "+ Tambahkan ke Daftar".</p>

    {{-- Probation quick-select ─────────────────────────────────────── --}}
    @if($candidateEmployees->count() > 0)
    <div x-show="mode === 'probation'" x-cloak class="mb-5">
      <div class="mb-3 flex items-center justify-between">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
          Kandidat Probation Tersedia — Klik untuk Pilih
        </p>
        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">
          {{ $candidateEmployees->count() }} kandidat total
        </span>
      </div>

      <div class="grid grid-cols-1 gap-3 md:grid-cols-3">

        {{-- KOLOM 1: MASIH AMAN --}}
        <div class="overflow-hidden rounded-xl border border-emerald-100">
          <div class="flex items-center justify-between border-b border-emerald-100 bg-emerald-50 px-3 py-2">
            <span class="flex items-center gap-1.5 text-xs font-semibold text-emerald-700">
              <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
              Masih Aman
            </span>
            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-700">
              {{ $grouped['safe']->count() }}
            </span>
          </div>
          <div class="max-h-80 space-y-1.5 overflow-y-auto p-2">
            @forelse($grouped['safe'] as $emp)
              @include('appraisals.assignment._candidate_card', ['emp' => $emp, 'color' => 'emerald'])
            @empty
              <p class="py-4 text-center text-xs text-slate-400">Tidak ada kandidat</p>
            @endforelse
          </div>
        </div>

        {{-- KOLOM 2: SEGERA --}}
        <div class="overflow-hidden rounded-xl border border-amber-100">
          <div class="flex items-center justify-between border-b border-amber-100 bg-amber-50 px-3 py-2">
            <span class="flex items-center gap-1.5 text-xs font-semibold text-amber-700">
              <span class="h-2 w-2 rounded-full bg-amber-500"></span>
              Segera (≤ 7 hari)
            </span>
            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-700">
              {{ $grouped['urgent']->count() }}
            </span>
          </div>
          <div class="max-h-80 space-y-1.5 overflow-y-auto p-2">
            @forelse($grouped['urgent'] as $emp)
              @include('appraisals.assignment._candidate_card', ['emp' => $emp, 'color' => 'amber'])
            @empty
              <p class="py-4 text-center text-xs text-slate-400">Tidak ada kandidat</p>
            @endforelse
          </div>
        </div>

        {{-- KOLOM 3: SUDAH LEWAT --}}
        <div class="overflow-hidden rounded-xl border border-red-100">
          <div class="flex items-center justify-between border-b border-red-100 bg-red-50 px-3 py-2">
            <span class="flex items-center gap-1.5 text-xs font-semibold text-red-700">
              <span class="h-2 w-2 rounded-full bg-red-500"></span>
              Sudah Lewat
            </span>
            <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-bold text-red-700">
              {{ $grouped['overdue']->count() }}
            </span>
          </div>
          <div class="max-h-80 space-y-1.5 overflow-y-auto p-2">
            @forelse($grouped['overdue'] as $emp)
              @include('appraisals.assignment._candidate_card', ['emp' => $emp, 'color' => 'red'])
            @empty
              <p class="py-4 text-center text-xs text-slate-400">Tidak ada kandidat</p>
            @endforelse
          </div>
        </div>

      </div>
    </div>
    @endif

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
      {{-- Employee search ──────────────────────────────────────── --}}
      <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">
          Cari Karyawan
          <span x-show="mode === 'probation'" class="ml-1 text-xs font-normal text-slate-400">(ter-filter ke probation)</span>
        </label>
        <div class="relative" @click.outside="empResults = []">
          <input type="text" x-model="empSearch"
            @input.debounce.400ms="searchEmployee()"
            placeholder="Ketik nama atau No. Komp (min. 2 karakter)..."
            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none">
          <div x-show="empLoading" class="absolute right-3 top-2.5 text-xs text-slate-400">Mencari…</div>
          <div x-show="empResults.length > 0"
               class="absolute z-30 mt-1 w-full overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-lg">
            <div class="max-h-56 overflow-auto">
              <template x-for="emp in empResults" :key="emp.id">
                <button type="button" @click="selectEmployee(emp)"
                  :class="isInBatch(emp.id) ? 'opacity-50' : 'hover:bg-slate-50'"
                  class="flex w-full items-center justify-between px-4 py-2.5 text-left text-sm border-b border-slate-100 last:border-0">
                  <div>
                    <div class="font-medium text-slate-900" x-text="emp.full_name"></div>
                    <div class="text-xs text-slate-400" x-text="emp.employee_number || '-'"></div>
                  </div>
                  <div class="text-right">
                    <div x-show="emp.display_probation_end" class="text-xs text-amber-600"
                         x-text="emp.display_probation_end ? 'Probation: ' + formatDate(emp.display_probation_end) : ''"></div>
                    <div x-show="isInBatch(emp.id)" class="text-xs text-emerald-600">Sudah di batch</div>
                  </div>
                </button>
              </template>
            </div>
          </div>
        </div>

        {{-- Selected employee chip --}}
        <div x-show="currentEmployee" x-cloak class="mt-2">
          <div class="inline-flex items-center gap-2 rounded-xl bg-blue-50 px-3 py-2 text-sm text-blue-900">
            <span class="font-medium" x-text="currentEmployee?.full_name"></span>
            <span class="text-xs text-blue-500" x-text="currentEmployee?.employee_number ? '(' + currentEmployee.employee_number + ')' : ''"></span>
            <button type="button" @click="currentEmployee = null; empSearch = ''" class="ml-1 text-blue-400 hover:text-blue-700">✕</button>
          </div>
        </div>

        <div x-show="empSearch.length >= 2 && empResults.length === 0 && !empLoading && !currentEmployee" x-cloak
             class="mt-2 rounded-xl border border-dashed border-slate-300 px-3 py-2 text-xs text-slate-500">
          Tidak ada karyawan yang cocok.
        </div>
      </div>

      {{-- Evaluator search ─────────────────────────────────────── --}}
      <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">
          Cari &amp; Pilih Evaluator
          <span class="ml-1 text-xs font-normal text-slate-400">(bisa lebih dari 1)</span>
        </label>
        <div class="relative" @click.outside="evalResults = []">
          <input type="text" x-model="evalSearch"
            @input.debounce.400ms="searchEvaluator()"
            placeholder="Ketik nama evaluator..."
            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-purple-400 focus:outline-none">
          <div x-show="evalLoading" class="absolute right-3 top-2.5 text-xs text-slate-400">Mencari…</div>
          <div x-show="evalResults.length > 0"
               class="absolute z-30 mt-1 w-full overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-lg">
            <div class="max-h-48 overflow-auto">
              <template x-for="user in evalResults" :key="user.id">
                <button type="button" @click="toggleCurrentEval(user)"
                  :class="isCurrentEvalSelected(user.id) ? 'bg-purple-50' : 'hover:bg-slate-50'"
                  class="flex w-full items-center justify-between px-4 py-2.5 text-left text-sm border-b border-slate-100 last:border-0">
                  <span>
                    <span class="font-medium text-slate-900" x-text="user.name"></span>
                    <span class="ml-1.5 text-xs text-slate-400 uppercase" x-text="user.role || '-'"></span>
                  </span>
                  <span x-show="isCurrentEvalSelected(user.id)" class="text-xs text-purple-600 font-medium">✓ dipilih</span>
                </button>
              </template>
            </div>
          </div>
        </div>

        {{-- Current evaluator chips --}}
        <div class="mt-2 flex min-h-[32px] flex-wrap gap-2">
          <template x-for="ev in currentEvaluators" :key="ev.id">
            <span class="inline-flex items-center gap-1 rounded-full bg-purple-100 px-3 py-1 text-xs font-medium text-purple-900">
              <span x-text="ev.name"></span>
              <button type="button" @click="removeCurrentEval(ev.id)" class="ml-0.5 text-purple-400 hover:text-purple-700">✕</button>
            </span>
          </template>
          <span x-show="currentEvaluators.length === 0" class="text-xs text-slate-400 italic self-center">Belum ada evaluator dipilih</span>
        </div>
      </div>
    </div>

    <div class="mt-5">
      <button type="button" @click="addToBatch()"
        :disabled="!currentEmployee || currentEvaluators.length === 0"
        :class="(!currentEmployee || currentEvaluators.length === 0) ? 'opacity-40 cursor-not-allowed' : 'hover:bg-indigo-700'"
        class="btn-primary">
        + Tambahkan ke Daftar Batch
      </button>
      <span x-show="!currentEmployee && currentEvaluators.length > 0" x-cloak
            class="ml-3 text-xs text-amber-600">Pilih karyawan dulu.</span>
      <span x-show="currentEmployee && currentEvaluators.length === 0" x-cloak
            class="ml-3 text-xs text-amber-600">Pilih minimal 1 evaluator.</span>
    </div>
  </div>

  {{-- ── Daftar Batch ────────────────────────────────────────────────────── --}}
  <div x-show="batch.length > 0" x-cloak class="card p-6">
    <div class="mb-4 flex items-center justify-between">
      <div>
        <h2 class="text-base font-semibold text-slate-900">📋 Daftar Batch</h2>
        <p class="mt-0.5 text-xs text-slate-500">
          <span x-text="batch.length" class="font-medium"></span> karyawan ·
          <span x-text="totalEvaluatorCount()" class="font-medium"></span> total penugasan evaluator
        </p>
      </div>
      <button type="button" @click="clearBatch()"
              class="rounded-xl border border-red-200 px-3 py-1.5 text-xs text-red-600 hover:bg-red-50">
        Kosongkan semua
      </button>
    </div>

    <div class="space-y-2">
      <template x-for="(item, idx) in batch" :key="idx">
        <div class="overflow-hidden rounded-2xl border border-slate-200">
          {{-- Row header --}}
          <div class="flex items-center justify-between bg-slate-50 px-4 py-3">
            <div class="min-w-0">
              <span class="font-semibold text-slate-900" x-text="item.employee.full_name"></span>
              <span class="ml-2 text-xs text-slate-400"
                    x-text="item.evaluators.length + ' evaluator'"></span>
              {{-- Evaluator name previews --}}
              <div class="mt-1 flex flex-wrap gap-1">
                <template x-for="ev in item.evaluators" :key="ev.id">
                  <span class="rounded-full bg-purple-100 px-2 py-0.5 text-xs text-purple-800"
                        x-text="ev.name"></span>
                </template>
              </div>
            </div>
            <div class="ml-4 flex shrink-0 gap-3">
              <button type="button" @click="toggleEdit(idx)"
                      class="text-xs text-blue-600 hover:text-blue-800">
                ✏️ Edit
              </button>
              <button type="button" @click="removeFromBatch(idx)"
                      class="text-xs text-red-500 hover:text-red-700">
                ✕ Hapus
              </button>
            </div>
          </div>

          {{-- Inline edit expand --}}
          <div x-show="editingIndex === idx" x-cloak class="border-t border-slate-200 bg-white p-4">
            <p class="mb-2 text-xs text-slate-500">Evaluator saat ini:</p>
            <div class="mb-3 flex flex-wrap gap-2">
              <template x-for="ev in item.evaluators" :key="ev.id">
                <span class="inline-flex items-center gap-1 rounded-full bg-purple-100 px-2.5 py-1 text-xs font-medium text-purple-900">
                  <span x-text="ev.name"></span>
                  <button type="button" @click="removeEvalFromItem(idx, ev.id)"
                          class="ml-0.5 text-purple-400 hover:text-purple-700">✕</button>
                </span>
              </template>
              <span x-show="item.evaluators.length === 0" class="text-xs italic text-red-400">Tidak ada evaluator — baris ini tidak akan di-generate.</span>
            </div>

            <div class="relative max-w-sm" @click.outside="editResults = []">
              <input type="text" x-model="editSearch"
                @input.debounce.400ms="searchEditEval()"
                placeholder="Tambah evaluator lain..."
                class="w-full rounded-xl border border-slate-200 px-3 py-1.5 text-sm focus:border-purple-400 focus:outline-none">
              <div x-show="editResults.length > 0"
                   class="absolute z-30 mt-0.5 w-full overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-lg">
                <div class="max-h-40 overflow-auto">
                  <template x-for="user in editResults" :key="user.id">
                    <button type="button"
                      @click="addEvalToItem(idx, user)"
                      :class="item.evaluators.some(e => e.id === user.id) ? 'opacity-40 cursor-default' : 'hover:bg-slate-50'"
                      class="flex w-full items-center justify-between px-3 py-2 text-left text-sm border-b border-slate-100 last:border-0">
                      <span x-text="user.name"></span>
                      <span class="text-xs uppercase text-slate-400" x-text="user.role || '-'"></span>
                    </button>
                  </template>
                </div>
              </div>
            </div>

            <button type="button" @click="editingIndex = null; editSearch = ''; editResults = []"
                    class="mt-3 rounded-xl bg-slate-800 px-4 py-1.5 text-xs font-medium text-white hover:bg-slate-700">
              Selesai
            </button>
          </div>
        </div>
      </template>
    </div>
  </div>

  {{-- ── Generate button ─────────────────────────────────────────────────── --}}
  <div x-show="batch.length > 0" x-cloak>
    <form action="{{ route('appraisals.assignment.generate-batch') }}" method="POST"
          @submit.prevent="prepareAndSubmit($event)">
      @csrf
      <input type="hidden" name="mode" :value="mode">
      <input type="hidden" name="period_id" :value="periodId">
      <input type="hidden" name="date_appraised" :value="dateAppraised">
      <input type="hidden" name="due_date" :value="dueDate">
      <input type="hidden" name="invitation_note" :value="invitationNote">
      <input type="hidden" name="enable_kpi_component" :value="enableKpi ? '1' : '0'">
      <input type="hidden" name="enable_skill_component" :value="enableSkill ? '1' : '0'">
      <input type="hidden" name="enable_position_component" :value="enablePosition ? '1' : '0'">
      {{-- batch[] fields injected dynamically by prepareAndSubmit() --}}

      <div class="flex flex-wrap items-center gap-4">
        <button type="submit"
          :disabled="!periodId || !dateAppraised || batch.length === 0"
          :class="(!periodId || !dateAppraised || batch.length === 0) ? 'opacity-40 cursor-not-allowed' : 'hover:bg-indigo-700'"
          class="btn-primary text-base px-6 py-3">
          🚀 Generate Semua Draft Appraisal
          (<span x-text="batch.length"></span> karyawan ·
          <span x-text="totalEvaluatorCount()"></span> total baris)
        </button>
        <span x-show="!periodId" x-cloak class="text-xs text-amber-600">Pilih periode terlebih dahulu.</span>
      </div>
    </form>
  </div>

  <div x-show="batch.length === 0" x-cloak
       class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">
    Daftar batch kosong. Cari karyawan, pilih evaluator, lalu klik "+ Tambahkan ke Daftar Batch".
  </div>

</div>

<script>
function batchBuilder() {
    return {
        mode:            '{{ $mode ?? 'probation' }}',
        windowDays:      {{ $windowDays ?? 30 }},
        periodId:        '{{ $defaultPeriodId ?? '' }}',
        dateAppraised:   new Date().toISOString().slice(0, 10),
        dueDate:         '',
        invitationNote:  '',
        enableKpi:       true,
        enableSkill:     false,
        enablePosition:  false,

        // Employee search
        empSearch:       '',
        empResults:      [],
        empLoading:      false,
        currentEmployee: null,

        // Evaluator search
        evalSearch:      '',
        evalResults:     [],
        evalLoading:     false,
        currentEvaluators: [],

        // Batch list
        batch:           [],

        // Inline edit state
        editingIndex:    null,
        editSearch:      '',
        editResults:     [],

        // Race-condition guards
        empSearchToken:      0,
        evalSearchToken:     0,
        editSearchToken:     0,
        empAbortController:  null,
        evalAbortController: null,
        editAbortController: null,

        // Probation quick-select candidates (from server)
        probationCandidates: @json($candidateEmployees ?? []),

        // ── Mode ──────────────────────────────────────────────────────────
        setMode(m) {
            this.mode = m;
            this.empSearch = '';
            this.empResults = [];
            this.currentEmployee = null;
            if (m === 'probation') {
                this.refreshProbation();
            } else {
                this.probationCandidates = [];
            }
        },

        async refreshProbation() {
            try {
                const params = new URLSearchParams({ q: ' ', mode: 'probation', days: this.windowDays });
                const res = await fetch('{{ route('appraisals.assignment.search-employees') }}?' + params);
                // Use a dummy query since endpoint requires min 2 chars — fetch all via window
                // Actually, rely on server-rendered candidateEmployees + window change reloads page
            } catch {}
            // Reload page with new windowDays to get fresh candidateEmployees
            const url = new URL(window.location.href);
            url.searchParams.set('mode', 'probation');
            url.searchParams.set('days', this.windowDays);
            window.location.href = url.toString();
        },

        // ── Employee search ────────────────────────────────────────────────
        async searchEmployee() {
            if (this.empSearch.length < 2) { this.empResults = []; return; }

            if (this.empAbortController) this.empAbortController.abort();
            this.empAbortController = new AbortController();
            const myToken = ++this.empSearchToken;
            this.empLoading = true;

            try {
                const params = new URLSearchParams({
                    q:    this.empSearch,
                    mode: this.mode,
                    days: this.windowDays,
                });
                const res  = await fetch(
                    '{{ route('appraisals.assignment.search-employees') }}?' + params,
                    { signal: this.empAbortController.signal }
                );
                const data = await res.json();
                if (myToken === this.empSearchToken) this.empResults = data;
            } catch (err) {
                if (err.name !== 'AbortError') console.error('Search employee error:', err);
            } finally {
                if (myToken === this.empSearchToken) this.empLoading = false;
            }
        },

        selectEmployee(emp) {
            this.currentEmployee = { ...emp };
            this.empSearch  = '';
            this.empResults = [];
        },

        isInBatch(empId) {
            return this.batch.some(b => b.employee.id === empId);
        },

        // ── Evaluator search ───────────────────────────────────────────────
        async searchEvaluator() {
            if (this.evalSearch.length < 2) { this.evalResults = []; return; }

            if (this.evalAbortController) this.evalAbortController.abort();
            this.evalAbortController = new AbortController();
            const myToken = ++this.evalSearchToken;
            this.evalLoading = true;

            try {
                const res  = await fetch(
                    '{{ route('appraisals.assignment.search-evaluators') }}?q='
                    + encodeURIComponent(this.evalSearch),
                    { signal: this.evalAbortController.signal }
                );
                const data = await res.json();
                if (myToken === this.evalSearchToken) this.evalResults = data;
            } catch (err) {
                if (err.name !== 'AbortError') console.error('Search evaluator error:', err);
            } finally {
                if (myToken === this.evalSearchToken) this.evalLoading = false;
            }
        },

        isCurrentEvalSelected(id) {
            return this.currentEvaluators.some(e => e.id === id);
        },

        toggleCurrentEval(user) {
            const idx = this.currentEvaluators.findIndex(e => e.id === user.id);
            if (idx >= 0) this.currentEvaluators.splice(idx, 1);
            else          this.currentEvaluators.push({ ...user });
        },

        removeCurrentEval(id) {
            this.currentEvaluators = this.currentEvaluators.filter(e => e.id !== id);
        },

        // ── Batch operations ───────────────────────────────────────────────
        addToBatch() {
            if (!this.currentEmployee || !this.currentEvaluators.length) return;

            const existIdx = this.batch.findIndex(b => b.employee.id === this.currentEmployee.id);
            if (existIdx >= 0) {
                // Merge evaluators (no duplicate)
                const existIds = this.batch[existIdx].evaluators.map(e => e.id);
                this.currentEvaluators.forEach(ev => {
                    if (!existIds.includes(ev.id)) {
                        this.batch[existIdx].evaluators.push({ ...ev });
                    }
                });
            } else {
                this.batch.push({
                    employee:   { ...this.currentEmployee },
                    evaluators: this.currentEvaluators.map(ev => ({ ...ev })),
                });
            }

            this.currentEmployee   = null;
            this.currentEvaluators = [];
            this.empSearch   = '';
            this.evalSearch  = '';
            this.evalResults = [];
        },

        removeFromBatch(idx) {
            this.batch.splice(idx, 1);
            if (this.editingIndex === idx)       this.editingIndex = null;
            else if (this.editingIndex > idx)    this.editingIndex--;
        },

        clearBatch() {
            if (!confirm('Kosongkan semua daftar batch?')) return;
            this.batch = [];
            this.editingIndex = null;
        },

        totalEvaluatorCount() {
            return this.batch.reduce((sum, b) => sum + b.evaluators.length, 0);
        },

        // ── Inline edit ────────────────────────────────────────────────────
        toggleEdit(idx) {
            this.editingIndex = (this.editingIndex === idx) ? null : idx;
            this.editSearch  = '';
            this.editResults = [];
        },

        async searchEditEval() {
            if (this.editSearch.length < 2) { this.editResults = []; return; }

            if (this.editAbortController) this.editAbortController.abort();
            this.editAbortController = new AbortController();
            const myToken = ++this.editSearchToken;

            try {
                const res  = await fetch(
                    '{{ route('appraisals.assignment.search-evaluators') }}?q='
                    + encodeURIComponent(this.editSearch),
                    { signal: this.editAbortController.signal }
                );
                const data = await res.json();
                if (myToken === this.editSearchToken) this.editResults = data;
            } catch (err) {
                if (err.name !== 'AbortError') console.error('Search edit evaluator error:', err);
            }
        },

        addEvalToItem(idx, user) {
            if (this.batch[idx].evaluators.some(e => e.id === user.id)) return;
            this.batch[idx].evaluators.push({ ...user });
            this.editSearch  = '';
            this.editResults = [];
        },

        removeEvalFromItem(idx, evalId) {
            this.batch[idx].evaluators = this.batch[idx].evaluators.filter(e => e.id !== evalId);
        },

        // ── Submit ─────────────────────────────────────────────────────────
        prepareAndSubmit(e) {
            const form = e.target;

            // Inject batch[] fields
            this.batch.forEach((item, i) => {
                const add = (name, value) => {
                    const el = document.createElement('input');
                    el.type  = 'hidden';
                    el.name  = name;
                    el.value = value;
                    form.appendChild(el);
                };
                add(`batch[${i}][employee_id]`, item.employee.id);
                item.evaluators.forEach((ev, j) => {
                    add(`batch[${i}][evaluator_ids][${j}]`, ev.id);
                });
            });

            form.submit();
        },

        // ── Helpers ────────────────────────────────────────────────────────
        formatDate(dateStr) {
            if (!dateStr) return '-';
            const datePart = dateStr.split('T')[0];
            const d = new Date(datePart + 'T00:00:00');
            if (isNaN(d.getTime())) return '-';
            return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
        },
    };
}
</script>
@endsection
