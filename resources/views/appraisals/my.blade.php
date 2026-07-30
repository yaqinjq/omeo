@extends('layouts.app')
@section('content')
<div class="space-y-6">
  <div class="card p-6">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Appraisal Saya</h1>
        <p class="mt-1 text-sm text-slate-600">Karyawan hanya dapat melihat hasil appraisal dirinya sendiri. Feedback evaluator ditampilkan anonim sebagai Evaluator 1, Evaluator 2, dan seterusnya tanpa identitas penilai.</p>
      </div>
      <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm md:max-w-sm">
        <div class="font-semibold text-slate-900">Catatan Privacy</div>
        <p class="mt-2 text-slate-600">Nama evaluator tidak ditampilkan di halaman ini. Jika ada lebih dari satu evaluator pada satu periode, setiap hasil akan ditandai sebagai Evaluator 1, Evaluator 2, dan seterusnya.</p>
      </div>
    </div>
  </div>

  @if(!empty($moduleWarning))
    <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">{{ $moduleWarning }}</div>
  @endif

  <div class="space-y-4">
    @forelse($appraisalGroups as $group)
      @php
        $averageScore = $group['average_score'] !== null ? number_format((float) $group['average_score'], 2) : '-';
      @endphp
      <div class="card p-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <div class="text-lg font-semibold text-slate-900">{{ $group['period_name'] }}</div>
              <span class="badge">{{ $group['submitted_count'] }} feedback tampil</span>
              @if($group['draft_count'] > 0)
                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">{{ $group['draft_count'] }} evaluator belum submit</span>
              @endif
            </div>
            <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3">
              <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Rata-rata Score</div>
                <div class="mt-2 text-2xl font-bold text-slate-900">{{ $averageScore }}</div>
              </div>
              <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Final Result</div>
                <div class="mt-2 text-base font-semibold text-slate-900">{{ $group['final_results']->isNotEmpty() ? $group['final_results']->implode(' / ') : '-' }}</div>
              </div>
              <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Periode Evaluator</div>
                <div class="mt-2 text-base font-semibold text-slate-900">{{ $group['rows']->count() }} assignment</div>
              </div>
            </div>
          </div>
        </div>

        <div class="mt-5 space-y-4">
          @foreach($group['rows'] as $index => $row)
            <div class="rounded-3xl border border-slate-200 p-5 {{ in_array($row->status, ['submitted', 'approved'], true) ? 'bg-white' : 'bg-slate-50' }}">
              <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                  <div class="flex flex-wrap items-center gap-2">
                    <div class="font-semibold text-slate-900">Evaluator {{ $index + 1 }}</div>
                    @if($row->status === 'approved')
                      <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Approved</span>
                    @elseif($row->status === 'submitted')
                      <span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-700">Submitted</span>
                    @else
                      <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">Belum tersedia</span>
                    @endif
                  </div>
                  <div class="mt-2 text-sm text-slate-500">Due date: {{ $row->due_date?->format('d-m-Y') ?? '-' }} | Tanggal dinilai: {{ $row->submitted_at?->format('d-m-Y H:i') ?? '-' }}</div>
                </div>
                <a href="{{ route('appraisals.show', $row) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">Lihat Detail</a>
              </div>

              <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm">
                  <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Score</div>
                  <div class="mt-2 text-2xl font-bold text-slate-900">{{ $row->final_score ?? '-' }}</div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm md:col-span-2">
                  <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Hasil Akhir</div>
                  <div class="mt-2 text-base font-semibold text-slate-900">{{ $row->final_result ?: '-' }}</div>
                </div>
              </div>

              <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm">
                  <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Saran</div>
                  <div class="mt-2 leading-6 text-slate-700">{{ $row->feedback_strengths ?: '-' }}</div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm">
                  <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kritik / Area Perbaikan</div>
                  <div class="mt-2 leading-6 text-slate-700">{{ $row->feedback_improvements ?: '-' }}</div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm">
                  <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Catatan Lain</div>
                  <div class="mt-2 leading-6 text-slate-700">{{ $row->feedback_notes ?: '-' }}</div>
                </div>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    @empty
      <div class="card p-6 text-sm text-slate-600">Belum ada appraisal untuk profil Anda saat ini.</div>
    @endforelse
  </div>
</div>
@endsection
