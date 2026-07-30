@extends('layouts.app')
@section('content')
@php
  $collection = collect($appraisals->items());
  $draftCount = $collection->where('status', 'draft')->count();
  $submittedCount = $collection->where('status', 'submitted')->count();
  $approvedCount = $collection->where('status', 'approved')->count();
  $overdueCount = $collection->filter(fn ($item) => $item->status !== 'approved' && $item->due_date && $item->due_date->isPast())->count();
@endphp
<div class="space-y-6">
  <div class="card p-6">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
      <div>
        <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Appraisal HRD</div>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">Monitoring Appraisal Probation</h1>
        <p class="mt-2 text-sm text-slate-600">Halaman monitoring ini difokuskan untuk appraisal probation dan percepatan manual. Period tetap dipakai sebagai grouping/reporting lama, tetapi pengambilan keputusan HRD diarahkan dari due date probation, trigger appraisal, dan hasil evaluasi nyata.</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <a href="{{ route('appraisals.assignment') }}" class="btn-primary">Buka Assignment Probation</a>
        <a href="{{ route('appraisal-periods.index') }}" class="btn-outline">Grouping Legacy</a>
      </div>
    </div>
  </div>

  @if(!empty($moduleWarning))
    <div class="rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $moduleWarning }}</div>
  @endif

  <div class="grid grid-cols-1 gap-4 xl:grid-cols-5">
    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4"><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total halaman ini</div><div class="mt-2 text-2xl font-bold text-slate-900">{{ $collection->count() }}</div></div>
    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4"><div class="text-xs font-semibold uppercase tracking-wide text-amber-700">Draft</div><div class="mt-2 text-2xl font-bold text-amber-900">{{ $draftCount }}</div></div>
    <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4"><div class="text-xs font-semibold uppercase tracking-wide text-blue-700">Submitted</div><div class="mt-2 text-2xl font-bold text-blue-900">{{ $submittedCount }}</div></div>
    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4"><div class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Approved</div><div class="mt-2 text-2xl font-bold text-emerald-900">{{ $approvedCount }}</div></div>
    <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4"><div class="text-xs font-semibold uppercase tracking-wide text-rose-700">Perlu follow up</div><div class="mt-2 text-2xl font-bold text-rose-900">{{ $overdueCount }}</div><div class="mt-1 text-xs text-rose-700">Draft/submitted yang due date evaluator-nya sudah lewat.</div></div>
  </div>

  <div class="space-y-4">
    @forelse($appraisals as $a)
      <div class="card p-5">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
          <div class="min-w-0 flex-1 space-y-3">
            <div class="flex flex-wrap items-center gap-2">
              <div class="text-lg font-semibold text-slate-900">{{ $a->employee?->full_name ?? '-' }}</div>
              <span class="badge">{{ $a->period?->name ?? 'Grouping legacy' }}</span>
              <span class="badge">{{ strtoupper(str_replace('_', ' ', $a->trigger_source ?? 'legacy')) }}</span>
              @if($a->status === 'draft')
                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">Draft</span>
              @elseif($a->status === 'submitted')
                <span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-700">Submitted</span>
              @else
                <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Approved</span>
              @endif
              @if($a->is_feedback_private)
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Evaluator anonim di sisi employee</span>
              @endif
            </div>
            <div class="grid grid-cols-1 gap-2 text-sm md:grid-cols-5">
              <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">Evaluator: <strong>{{ $a->appraiser?->name ?? '-' }}</strong></div>
              <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">Probation snapshot: <strong>{{ $a->probation_end_snapshot?->format('d-m-Y') ?? '-' }}</strong></div>
              <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">Due evaluator: <strong>{{ $a->due_date?->format('d-m-Y') ?? '-' }}</strong></div>
              <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">Nilai akhir: <strong>{{ $a->final_score ?? '-' }}</strong></div>
              <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">Hasil: <strong>{{ $a->final_result ?? '-' }}</strong></div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-600">Alasan trigger: <strong>{{ $a->trigger_reason ?: 'Belum ada catatan trigger khusus.' }}</strong></div>
          </div>
          <div class="flex flex-wrap gap-2 xl:justify-end">
            <a class="btn-outline" href="{{ route('appraisals.show',$a) }}">Buka</a>
            @if($a->status === 'submitted')
              <form method="POST" action="{{ route('appraisals.approve',$a) }}">@csrf<button class="btn-primary" type="submit">Approve</button></form>
            @endif
            @if($a->status !== 'approved')
              <form method="POST" action="{{ route('appraisals.remind',$a) }}">@csrf<button class="btn-outline" type="submit">Kirim Reminder</button></form>
            @endif
            <a class="btn-outline" href="{{ route('appraisals.print',$a) }}" target="_blank">Print</a>
          </div>
        </div>
      </div>
    @empty
      <div class="card p-6 text-sm text-slate-600">
        Belum ada data appraisal. Mulai dari <a href="{{ route('appraisals.assignment') }}" class="font-semibold text-slate-900 underline">Assignment Appraisal Probation</a> agar HRD bisa membuat draft berbasis timeline probation atau percepatan manual.
      </div>
    @endforelse
  </div>

  <div>{{ $appraisals->links() }}</div>
</div>
@endsection
