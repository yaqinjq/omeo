@extends('layouts.app')
@section('content')
@php
  $isEmployee  = $viewerMode === 'employee';
  $isEvaluator = $viewerMode === 'evaluator';
  $isReviewer  = $viewerMode === 'reviewer';
  $fs = is_numeric($appraisal->final_score) ? (float) $appraisal->final_score : null;
  $rb = ['emoji' => '—', 'label' => 'Belum Dinilai', 'color' => '#6b7280', 'bg' => '#f3f4f6'];
  if ($fs !== null) {
      if      ($fs >= 90) { $rb = ['emoji' => '🟢', 'label' => 'Luar Biasa',   'color' => '#065f46', 'bg' => '#d1fae5']; }
      elseif  ($fs >= 70) { $rb = ['emoji' => '🟢', 'label' => 'Sangat Bagus', 'color' => '#166534', 'bg' => '#dcfce7']; }
      elseif  ($fs >= 50) { $rb = ['emoji' => '🟡', 'label' => 'Bagus',        'color' => '#854d0e', 'bg' => '#fef9c3']; }
      elseif  ($fs >= 30) { $rb = ['emoji' => '🟠', 'label' => 'Cukup',        'color' => '#7c2d12', 'bg' => '#ffedd5']; }
      else                { $rb = ['emoji' => '🔴', 'label' => 'Buruk',         'color' => '#7f1d1d', 'bg' => '#fee2e2']; }
  }
@endphp
<div class="space-y-6">
  <div class="card p-6">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ $isEmployee ? 'Detail Appraisal Saya' : ($isEvaluator ? 'Form Penilaian Evaluator' : 'Detail Report Appraisal') }}</h1>
        <p class="mt-1 text-sm text-slate-600">
          Periode: {{ $appraisal->period?->name ?? '-' }}
          | Karyawan: {{ $appraisal->employee?->full_name ?? '-' }}
          @if(!$isEmployee)
            | Evaluator: {{ $appraisal->appraiser?->name ?? '-' }}
          @else
            | {{ $employeeEvaluatorAlias ?? 'Evaluator Anonim' }}
          @endif
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        @if($isEvaluator)
          <a class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50" href="{{ route('appraisals.evaluator') }}">Kembali ke Assignment Saya</a>
        @elseif($isEmployee)
          <a class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50" href="{{ route('appraisals.my') }}">Kembali ke Appraisal Saya</a>
        @else
          <a class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50" href="{{ route('appraisals.index') }}">Kembali ke Semua Appraisal</a>
          <a class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50" href="{{ route('appraisals.print',$appraisal) }}" target="_blank">Print</a>
        @endif
      </div>
    </div>
  </div>

  <div class="grid grid-cols-1 gap-4 xl:grid-cols-4">
    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4"><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</div><div class="mt-2 text-lg font-bold text-slate-900">{{ strtoupper($appraisal->status ?? '-') }}</div></div>
    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4"><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Score akhir</div><div class="mt-2 text-lg font-bold text-slate-900">{{ $appraisal->final_score ?? '-' }}</div></div>
    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
      <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Due date</div>
      <div class="mt-2 text-lg font-bold text-slate-900">{{ $appraisal->due_date?->format('d-m-Y') ?? '-' }}</div>
      @if($isReviewer)
        <details class="mt-2">
          <summary class="cursor-pointer text-xs font-medium text-indigo-600 hover:text-indigo-800">Perpanjang due date</summary>
          <form method="POST" action="{{ route('appraisals.extend-due-date', $appraisal) }}" class="mt-3 space-y-2">
            @csrf
            <div>
              <label class="block text-xs text-slate-500 mb-1">Due date baru</label>
              <input type="date" name="new_due_date" required
                     value="{{ old('new_due_date', $appraisal->due_date?->format('Y-m-d')) }}"
                     class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
            </div>
            <div>
              <label class="block text-xs text-slate-500 mb-1">Alasan perpanjangan</label>
              <textarea name="reason" required rows="2" placeholder="Contoh: evaluator izin sakit, minta tambahan waktu 3 hari"
                        class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">{{ old('reason') }}</textarea>
            </div>
            <button type="submit" class="w-full rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700">
              Simpan Due Date Baru
            </button>
          </form>
        </details>
      @endif
    </div>
    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4"><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Trigger Appraisal</div><div class="mt-2 text-lg font-bold text-slate-900">{{ str_replace('_', ' ', ucfirst((string) ($appraisal->trigger_source ?? 'period_legacy'))) }}</div><div class="mt-1 text-xs text-slate-500">{{ $appraisal->trigger_reason ?: 'Mengikuti assignment appraisal yang berjalan.' }}</div></div>
  </div>

  @if($isEvaluator && $appraisal->status === 'approved')
    @if($approvedUnusedEditRequest)
      <div class="rounded-2xl border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
        ✅ Permintaan edit Anda sudah disetujui HRD. Silakan edit penilaian di bawah, lalu kirim ulang sebelum due date{{ $appraisal->due_date ? ' ('.$appraisal->due_date->format('d-m-Y').')' : '' }}.
      </div>
    @elseif($pendingEditRequest)
      <div class="rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        ⏳ Permintaan edit Anda ({{ $pendingEditRequest->created_at->format('d-m-Y H:i') }}) masih menunggu persetujuan HRD.
      </div>
    @else
      <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <div class="text-sm text-slate-700">Appraisal ini sudah disetujui HRD dan tidak bisa diubah langsung. Salah isi nilai? Ajukan izin edit ke HRD (sisa jatah: {{ max(0, \App\Models\AppraisalEditRequest::MAX_APPROVED_PER_APPRAISAL - $editRequestsApprovedCount) }}x).</div>
        @if($editRequestsApprovedCount < \App\Models\AppraisalEditRequest::MAX_APPROVED_PER_APPRAISAL)
          <details class="mt-2">
            <summary class="cursor-pointer text-xs font-medium text-indigo-600 hover:text-indigo-800">Ajukan Edit Penilaian</summary>
            <form method="POST" action="{{ route('appraisals.request-edit', $appraisal) }}" class="mt-3 max-w-md space-y-2">
              @csrf
              <textarea name="reason" required rows="2" placeholder="Alasan minta edit ulang, contoh: salah pilih skor bintang" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">{{ old('reason') }}</textarea>
              <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700">Kirim Permintaan</button>
            </form>
          </details>
        @endif
      </div>
    @endif
  @endif

  <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
    <div class="space-y-6 xl:col-span-2">
      <div class="card p-6">
        <div class="mb-4 flex items-center justify-between gap-3">
          <h2 class="text-lg font-semibold text-slate-900">Komponen Penilaian</h2>
          <span class="text-sm text-slate-500">Komponen penilaian: KPI (30%), Hasil Training (20%), dan Kriteria Bintang dari evaluator (50%).</span>
        </div>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          @foreach($componentRows as $component)
            <div class="rounded-2xl border border-slate-200 p-4">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <div class="font-semibold text-slate-900">{{ $component['component_label'] }}</div>
                  <div class="mt-1 text-xs uppercase tracking-wide text-slate-500">Bobot {{ rtrim(rtrim(number_format((float) $component['weight'], 2, '.', ''), '0'), '.') }}%</div>
                </div>
                @if(!empty($component['suggested_badge']))
                  <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $component['suggested_badge'] }}</span>
                @endif
              </div>
              <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm">
                  <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Score Normalized</div>
                  <div class="mt-2 text-2xl font-bold text-slate-900">{{ $component['score_normalized'] !== null ? $component['score_normalized'] : '-' }}</div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm">
                  <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Source</div>
                  <div class="mt-2 text-sm font-semibold text-slate-900">{{ $component['source_type'] ?: '-' }}</div>
                </div>
              </div>
              <div class="mt-3 text-sm leading-6 text-slate-600">{{ $component['notes'] ?: 'Belum ada catatan komponen.' }}</div>
            </div>
          @endforeach
        </div>
      </div>

      @if($isEmployee)
        <div class="card p-6">
          <h2 class="text-lg font-semibold text-slate-900">Feedback {{ $employeeEvaluatorAlias ?? 'Evaluator' }}</h2>
          <p class="mt-1 text-sm text-slate-600">Masukan berikut mengikuti pola appraisal lama. Identitas evaluator disamarkan sebagai {{ $employeeEvaluatorAlias ?? 'Evaluator' }} agar karyawan tetap fokus pada hasil dan feedback.</p>
          <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm">
              <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Saran</div>
              <div class="mt-2 leading-6 text-slate-700">{{ $appraisal->feedback_strengths ?: '-' }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm">
              <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kritik / Area Perbaikan</div>
              <div class="mt-2 leading-6 text-slate-700">{{ $appraisal->feedback_improvements ?: '-' }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm">
              <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Catatan Lain</div>
              <div class="mt-2 leading-6 text-slate-700">{{ $appraisal->feedback_notes ?: '-' }}</div>
            </div>
          </div>
        </div>
      @else
        <div class="card p-6">
          <h2 class="text-lg font-semibold text-slate-900">{{ $isEvaluator ? 'Form Pengisian Evaluasi' : 'Hasil Penilaian Evaluator / Report HRD' }}</h2>
          @if($isEvaluator && $canEdit)
            <details class="mt-3 rounded-2xl border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-900">
              <summary class="cursor-pointer font-semibold">📋 Baca dulu: panduan skor penilaian</summary>
              <p class="mt-2 leading-6">Skor 3 adalah standar (sudah memenuhi ekspektasi). Skor 4 untuk kinerja konsisten di atas standar. Skor 5 hanya untuk performa luar biasa yang konsisten sepanjang periode — bukan satu pencapaian sesaat. Skor 1-2 wajib disertai bukti/contoh konkret. Nilai berdasarkan fakta dan perilaku selama periode evaluasi, bukan kesan pribadi atau kejadian terbaru saja.</p>
              <a href="{{ route('appraisals.guide') }}" target="_blank" class="mt-2 inline-block text-xs font-semibold underline">Lihat panduan lengkap &amp; klasifikasi nilai &rarr;</a>
            </details>
          @endif
          @if(!$canEdit)
            <div class="mt-4 rounded-2xl border border-slate-300 bg-slate-50 p-4 text-sm text-slate-700">Form ini sedang dalam mode baca saja. Evaluator tidak dapat mengubah appraisal yang sudah approved.</div>
          @endif

          <form class="mt-5 space-y-4" method="POST" action="{{ route('appraisals.submit',$appraisal) }}">
            @csrf
            @php($currentCat = null)
            @foreach($indicators as $ind)
              @if($currentCat !== $ind->category)
                @php($currentCat = $ind->category)
                <div class="pt-2 text-base font-semibold text-slate-900">{{ $currentCat }}</div>
              @endif
              @php($d = $existing->get($ind->id))
              <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-sm font-medium text-slate-900">{{ $ind->question }}</div>
                @if($ind->description)
                  <div class="mt-1 text-xs text-slate-500">{{ $ind->description }}</div>
                @endif
                <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-6 md:items-start">
                  <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Score 1–5</label>
                    @if($canEdit)
                      <div x-data="{ score: {{ (int) old('scores.'.$ind->id, $d?->score ?? 0) }}, hover: 0, rubric: @json($ind->scale_labels ?? []) }">
                        <div class="flex items-center gap-1">
                          @for($star = 1; $star <= 5; $star++)
                            <button type="button"
                              @mouseover="hover = {{ $star }}"
                              @mouseleave="hover = 0"
                              @click="score = {{ $star }}"
                              :class="(hover || score) >= {{ $star }} ? 'text-amber-400' : 'text-slate-300'"
                              class="text-2xl leading-none transition-colors focus:outline-none">&#9733;</button>
                          @endfor
                          <input type="hidden" name="scores[{{ $ind->id }}]" :value="score || ''">
                          <span class="ml-2 text-sm text-slate-500" x-text="score > 0 ? score + '/5' : '-'"></span>
                        </div>
                        <p class="mt-1.5 text-xs italic text-indigo-600" x-show="rubric[hover || score]" x-text="rubric[hover || score]" x-cloak></p>
                      </div>
                    @else
                      <div>
                        <div class="flex items-center gap-1">
                          @for($star = 1; $star <= 5; $star++)
                            <span class="{{ ($d?->score ?? 0) >= $star ? 'text-amber-400' : 'text-slate-300' }} text-2xl leading-none">&#9733;</span>
                          @endfor
                          <span class="ml-2 text-sm font-semibold text-slate-700">{{ $d?->score ?? '-' }}</span>
                        </div>
                        @if($d?->score && !empty($ind->scale_labels[$d->score]))
                          <p class="mt-1.5 text-xs italic text-slate-500">{{ $ind->scale_labels[$d->score] }}</p>
                        @endif
                      </div>
                    @endif
                  </div>
                  <div class="md:col-span-4">
                    <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Komentar evaluator <span class="text-red-500">*</span></label>
                    <textarea name="comments[{{ $ind->id }}]" rows="3" minlength="3" class="w-full rounded-2xl border px-3 py-2" @disabled(!$canEdit) @required($canEdit)>{{ old('comments.'.$ind->id, $d?->comment) }}</textarea>
                  </div>
                </div>
              </div>
            @endforeach

            <div class="pt-2 text-base font-semibold text-slate-900">Komponen Penilaian — KPI &amp; Training</div>
            <div class="space-y-4">
              @foreach($componentRows as $comp)
                @if(($comp['input_type'] ?? '') === 'percentage')
                  {{-- KPI: input 0-100% --}}
                  <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="mb-3 flex items-start justify-between gap-3">
                      <div>
                        <div class="font-semibold text-slate-900">{{ $comp['component_label'] }}</div>
                        @if(!empty($comp['description']))
                          <p class="mt-1 text-xs text-slate-500">{{ $comp['description'] }}</p>
                        @endif
                      </div>
                      <span class="whitespace-nowrap rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-700">Bobot {{ $comp['weight'] }}%</span>
                    </div>
                    @if($canEdit)
                      <div class="flex flex-wrap items-end gap-3">
                        <div class="flex-1 min-w-[160px]">
                          <label class="mb-1 block text-xs text-slate-500">Pencapaian KPI (%)</label>
                          <input type="number" name="component_kpi_score" min="0" max="100" step="0.1"
                            value="{{ old('component_kpi_score', $comp['score_raw'] ?? '') }}"
                            placeholder="0 – 100"
                            class="w-full rounded-xl border px-3 py-2 text-sm">
                        </div>
                        <div class="w-48">
                          <label class="mb-1 block text-xs text-slate-500">Catatan (opsional)</label>
                          <input type="text" name="component_kpi_notes"
                            value="{{ old('component_kpi_notes', $comp['notes'] ?? '') }}"
                            placeholder="opsional"
                            class="w-full rounded-xl border px-3 py-2 text-sm">
                        </div>
                      </div>
                    @else
                      <p class="text-3xl font-bold text-blue-600">{{ $comp['score_raw'] !== null ? $comp['score_raw'].'%' : '-' }}</p>
                      @if(!empty($comp['notes']))
                        <p class="mt-1 text-sm text-slate-600">{{ $comp['notes'] }}</p>
                      @endif
                    @endif
                  </div>

                @elseif(($comp['input_type'] ?? '') === 'training_multi')
                  {{-- Training: tabel multi-entry --}}
                  <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="mb-3 flex items-start justify-between gap-3">
                      <div class="font-semibold text-slate-900">{{ $comp['component_label'] }}</div>
                      <span class="whitespace-nowrap rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Bobot {{ $comp['weight'] }}%</span>
                    </div>
                    @if($canEdit)
                      <div x-data="{
                        entries: {{ json_encode($comp['entries'] ?? []) }},
                        addEntry() { this.entries.push({ nama_materi: '', nilai: '', source: 'manual' }); },
                        removeEntry(i) { this.entries.splice(i, 1); },
                        avgNilai() {
                          const vals = this.entries.map(e => parseFloat(e.nilai)).filter(v => !isNaN(v) && v >= 0);
                          if (!vals.length) return '-';
                          return (vals.reduce((a,b) => a+b, 0) / vals.length).toFixed(1);
                        }
                      }">
                        <input type="hidden" name="component_training_entries" :value="JSON.stringify(entries)">
                        <table class="mb-3 w-full text-sm">
                          <thead>
                            <tr class="border-b">
                              <th class="w-2/3 py-1 text-left text-xs font-medium text-slate-500">Nama Materi Training</th>
                              <th class="w-1/4 py-1 text-center text-xs font-medium text-slate-500">Nilai (0–100)</th>
                              <th class="w-10"></th>
                            </tr>
                          </thead>
                          <tbody>
                            <template x-for="(entry, i) in entries" :key="i">
                              <tr class="border-b border-slate-50">
                                <td class="py-1.5 pr-2">
                                  <input type="text" x-model="entry.nama_materi"
                                    placeholder="Nama materi..."
                                    :disabled="entry.source === 'system'"
                                    class="w-full rounded border px-2 py-1 text-sm disabled:bg-slate-50 disabled:text-slate-400">
                                </td>
                                <td class="px-2 py-1.5 text-center">
                                  <input type="number" x-model="entry.nilai" min="0" max="100"
                                    placeholder="0-100"
                                    class="w-full rounded border px-2 py-1 text-center text-sm">
                                </td>
                                <td class="py-1.5 text-center">
                                  <button type="button" @click="removeEntry(i)"
                                    class="text-sm leading-none text-rose-400 hover:text-rose-600">&times;</button>
                                </td>
                              </tr>
                            </template>
                            <tr x-show="entries.length === 0">
                              <td colspan="3" class="py-3 text-center text-xs text-slate-400">Belum ada data training. Klik "+ Tambah Materi".</td>
                            </tr>
                          </tbody>
                        </table>
                        <div class="flex items-center justify-between">
                          <button type="button" @click="addEntry()" class="text-sm text-emerald-600 hover:text-emerald-700">+ Tambah Materi</button>
                          <div class="text-right">
                            <span class="text-xs text-slate-500">Rata-rata: </span>
                            <span class="text-lg font-bold text-emerald-600" x-text="avgNilai()"></span>
                          </div>
                        </div>
                      </div>
                    @else
                      @if(!empty($comp['entries']))
                        <table class="w-full text-sm">
                          <thead><tr class="border-b"><th class="py-1 text-left text-xs font-medium text-slate-400">Materi</th><th class="py-1 text-center text-xs font-medium text-slate-400">Nilai</th></tr></thead>
                          <tbody>
                            @foreach($comp['entries'] as $entry)
                              <tr class="border-b border-slate-50">
                                <td class="py-1 text-sm">{{ $entry['nama_materi'] ?? '-' }}</td>
                                <td class="py-1 text-center font-medium">{{ $entry['nilai'] ?? '-' }}</td>
                              </tr>
                            @endforeach
                          </tbody>
                        </table>
                        <div class="mt-2 text-right text-sm">
                          <span class="text-slate-500">Rata-rata: </span>
                          <span class="font-bold text-emerald-600">{{ number_format(collect($comp['entries'])->avg('nilai'), 1) }}</span>
                        </div>
                      @else
                        <p class="text-sm text-slate-400">Belum ada data training.</p>
                      @endif
                    @endif
                  </div>

                @else
                  {{-- Generic manual component (skill / position) --}}
                  <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="mb-3 flex items-start justify-between gap-3">
                      <div class="font-semibold text-slate-900">{{ $comp['component_label'] }}</div>
                      @if(!empty($comp['suggested_badge']))
                        <span class="rounded-full bg-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $comp['suggested_badge'] }}</span>
                      @endif
                    </div>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                      <div>
                        <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Score Raw</label>
                        <input type="number" min="0" max="100" step="0.01"
                          name="components[{{ $comp['component_key'] }}][score_raw]"
                          value="{{ old('components.'.$comp['component_key'].'.score_raw', $comp['score_raw']) }}"
                          class="w-full rounded-xl border px-3 py-2" @disabled(!$canEdit)>
                      </div>
                      <div>
                        <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Score Normalized</label>
                        <input type="number" min="0" max="100" step="0.01"
                          name="components[{{ $comp['component_key'] }}][score_normalized]"
                          value="{{ old('components.'.$comp['component_key'].'.score_normalized', $comp['score_normalized']) }}"
                          class="w-full rounded-xl border px-3 py-2" @disabled(!$canEdit)>
                      </div>
                      <div>
                        <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Bobot</label>
                        <input type="number" min="0" max="100" step="0.01"
                          name="components[{{ $comp['component_key'] }}][weight]"
                          value="{{ old('components.'.$comp['component_key'].'.weight', $comp['weight']) }}"
                          class="w-full rounded-xl border px-3 py-2" @disabled(!$canEdit)>
                      </div>
                    </div>
                    <div class="mt-3">
                      <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Catatan Komponen</label>
                      <textarea name="components[{{ $comp['component_key'] }}][notes]" rows="2"
                        class="w-full rounded-2xl border px-3 py-2" @disabled(!$canEdit)>{{ old('components.'.$comp['component_key'].'.notes', $comp['notes']) }}</textarea>
                    </div>
                  </div>
                @endif
              @endforeach
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
              <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Saran</label>
                <textarea name="feedback_strengths" rows="4" class="w-full rounded-2xl border px-3 py-2" @disabled(!$canEdit)>{{ old('feedback_strengths', $appraisal->feedback_strengths) }}</textarea>
              </div>
              <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Kritik / Area Perbaikan</label>
                <textarea name="feedback_improvements" rows="4" class="w-full rounded-2xl border px-3 py-2" @disabled(!$canEdit)>{{ old('feedback_improvements', $appraisal->feedback_improvements) }}</textarea>
              </div>
              <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Catatan Lain</label>
                <textarea name="feedback_notes" rows="4" class="w-full rounded-2xl border px-3 py-2" @disabled(!$canEdit)>{{ old('feedback_notes', $appraisal->feedback_notes) }}</textarea>
              </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div class="space-y-4">
                {{-- Final Result — read-only badge dari final_score --}}
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Final Result</label>
                  <div class="flex items-center gap-3 rounded-xl border px-4 py-3"
                       style="background-color: {{ $rb['bg'] }}; border-color: {{ $rb['bg'] }};">
                    <span class="text-xl leading-none">{{ $rb['emoji'] }}</span>
                    <span class="text-base font-bold" style="color: {{ $rb['color'] }}">{{ $rb['label'] }}</span>
                    @if($fs !== null)
                      <span class="text-sm" style="color: {{ $rb['color'] }}; opacity: .75">({{ number_format($fs, 1) }})</span>
                    @endif
                  </div>
                </div>
                {{-- Proposed Appraisal — dropdown tersimpan ke kolom proposed_status --}}
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Proposed Appraisal</label>
                  <select name="proposed_status" class="w-full rounded-xl border px-3 py-2" @disabled(!$canEdit)>
                    @php($ps = old('proposed_status', $appraisal->proposed_status))
                    <option value="">- Pilih Proposed Appraisal -</option>
                    <option value="re_contract"     @selected($ps === 're_contract')>RE CONTRACT</option>
                    <option value="discontinue"     @selected($ps === 'discontinue')>DISCONTINUE THE CONTRACT</option>
                    <option value="promoted"        @selected($ps === 'promoted')>PROMOTED</option>
                    <option value="permanent"       @selected($ps === 'permanent')>CONFIRMATION OF PERMANENT WORKER</option>
                    <option value="position_change" @selected($ps === 'position_change')>POSITION CHANGE</option>
                    <option value="pending"         @selected($ps === 'pending')>PENDING</option>
                  </select>
                </div>
              </div>
              <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm">
                <div class="font-semibold text-slate-900">Formula skor akhir (0–100)</div>
                <div class="mt-2 space-y-1 text-slate-600">
                  <div>KPI 30% + Training 20% + Kriteria Bintang 50%</div>
                  <div class="mt-1 font-medium text-slate-700">Threshold hasil:</div>
                  <div>&ge; 90 &rarr; Luar Biasa</div>
                  <div>&ge; 70 &rarr; Sangat Bagus</div>
                  <div>&ge; 50 &rarr; Bagus</div>
                  <div>&ge; 30 &rarr; Cukup</div>
                  <div>&lt; 30 &rarr; Buruk</div>
                </div>
              </div>
            </div>

            @if($canEdit)
              <button class="btn" type="submit">Submit Appraisal</button>
            @endif
          </form>
        </div>
      @endif
    </div>

    <div class="space-y-6">
      <div class="card p-6">
        <h2 class="text-lg font-semibold text-slate-900">Ringkasan per Kategori</h2>
        <div class="mt-4 space-y-3 text-sm">
          @foreach($categorySummary as $category => $summary)
            <div class="rounded-2xl border border-slate-200 p-4">
              <div class="font-semibold text-slate-900">{{ $category }}</div>
              <div class="mt-2 text-slate-600">Terjawab: <strong>{{ $summary['answered'] }}/{{ $summary['count'] }}</strong></div>
              <div class="mt-1 text-slate-600">Rata-rata: <strong>{{ $summary['average'] ?? '-' }}</strong></div>
            </div>
          @endforeach
        </div>
      </div>

      <div class="card p-6">
        <h2 class="text-lg font-semibold text-slate-900">Metadata Appraisal</h2>
        <div class="mt-4 space-y-2 text-sm text-slate-600">
          @if(!$isEmployee)
            <div>Invitation sent: <strong>{{ $appraisal->invited_at?->format('d-m-Y H:i') ?? '-' }}</strong></div>
            <div>Invited by: <strong>{{ $appraisal->invitedBy?->name ?? '-' }}</strong></div>
            <div>Last reminded: <strong>{{ $appraisal->last_reminded_at?->format('d-m-Y H:i') ?? '-' }}</strong></div>
          @endif
          <div>Date appraised: <strong>{{ $appraisal->date_appraised?->format('d-m-Y') ?? '-' }}</strong></div>
          <div>Trigger source: <strong>{{ str_replace('_', ' ', ucfirst((string) ($appraisal->trigger_source ?? 'period_legacy'))) }}</strong></div>
          <div>Trigger reason: <strong>{{ $appraisal->trigger_reason ?: '-' }}</strong></div>
          @if(!$isEmployee)
            <div>Probation snapshot: <strong>{{ $appraisal->probation_end_snapshot?->format('d-m-Y') ?? '-' }}</strong></div>
            <div>Invitation note: <strong>{{ $appraisal->invitation_note ?: '-' }}</strong></div>
          @endif
          <div>Submitted at: <strong>{{ $appraisal->submitted_at?->format('d-m-Y H:i') ?? '-' }}</strong></div>
        </div>

        @if($isReviewer && $appraisal->status !== 'approved')
          <form method="POST" action="{{ route('appraisals.remind', $appraisal) }}" class="mt-4">
            @csrf
            <button class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50" type="submit">Kirim Reminder Evaluator</button>
          </form>
        @endif
      </div>

      @if($isReviewer)
        <div class="card p-6">
          <h2 class="text-lg font-semibold text-slate-900">Audit Trail Invitation</h2>
          <div class="mt-4 space-y-3 text-sm">
            @forelse($appraisal->invitationLogs as $log)
              <div class="rounded-2xl border border-slate-200 p-4">
                <div class="font-semibold text-slate-900">{{ strtoupper($log->action) }}</div>
                <div class="mt-1 text-slate-600">Actor: {{ $log->actor?->name ?? '-' }}</div>
                <div class="mt-1 text-slate-600">Target: {{ $log->target?->name ?? '-' }}</div>
                <div class="mt-1 text-slate-600">Waktu: {{ $log->created_at?->format('d-m-Y H:i') ?? '-' }}</div>
                <div class="mt-1 text-slate-600">Catatan: {{ $log->notes ?: '-' }}</div>
              </div>
            @empty
              <div class="rounded-2xl border border-dashed border-slate-300 p-4 text-slate-500">Belum ada audit trail invitation/reminder.</div>
            @endforelse
          </div>
        </div>
      @endif
    </div>
  </div>
</div>
@endsection
