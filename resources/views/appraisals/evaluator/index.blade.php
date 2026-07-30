@extends('layouts.app')
@section('content')
@php
  $collection = collect($appraisals->items());
  $draftCount = $collection->where('status', 'draft')->count();
  $submittedCount = $collection->where('status', 'submitted')->count();
  $approvedCount = $collection->where('status', 'approved')->count();
@endphp
<div class="space-y-6">
  <div class="card p-6">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Assignment Evaluator Saya</h1>
        <p class="mt-1 text-sm text-slate-600">Halaman ini hanya menampilkan appraisal yang memang ditugaskan kepada Anda. Anda tidak dapat melihat appraisal karyawan lain yang bukan tanggung jawab Anda.</p>
      </div>
      <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm md:max-w-sm">
        <div class="font-semibold text-slate-900">Panduan singkat</div>
        <p class="mt-2 text-slate-600">Prioritaskan assignment draft dengan due date terdekat. Setelah dikirim, hasil akan masuk ke HRD dan employee hanya melihat feedback anonim.</p>
      </div>
    </div>
  </div>

  @if(!empty($moduleWarning))
    <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">{{ $moduleWarning }}</div>
  @endif

  <div class="grid grid-cols-1 gap-4 xl:grid-cols-4">
    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4"><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total</div><div class="mt-2 text-2xl font-bold text-slate-900">{{ $collection->count() }}</div></div>
    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4"><div class="text-xs font-semibold uppercase tracking-wide text-amber-700">Belum dinilai</div><div class="mt-2 text-2xl font-bold text-amber-900">{{ $draftCount }}</div></div>
    <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4"><div class="text-xs font-semibold uppercase tracking-wide text-blue-700">Sudah dikirim</div><div class="mt-2 text-2xl font-bold text-blue-900">{{ $submittedCount }}</div></div>
    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4"><div class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Approved</div><div class="mt-2 text-2xl font-bold text-emerald-900">{{ $approvedCount }}</div></div>
  </div>

  <div class="space-y-4">
    @forelse($appraisals as $a)
      <div class="card p-5">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <div class="text-lg font-semibold text-slate-900">{{ $a->employee?->full_name ?? '-' }}</div>
              <span class="badge">{{ $a->period?->name ?? 'Periode appraisal' }}</span>
              @if($a->status === 'draft')
                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">Menunggu Anda isi</span>
              @elseif($a->status === 'submitted')
                <span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-700">Sudah dikirim</span>
              @else
                <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Disetujui</span>
              @endif
            </div>
            <div class="mt-2 grid grid-cols-1 gap-2 text-sm md:grid-cols-4">
              <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">Due date: <strong>{{ $a->due_date?->format('d-m-Y') ?? '-' }}</strong></div>
              <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">Invitation: <strong>{{ $a->invited_at?->format('d-m-Y H:i') ?? '-' }}</strong></div>
              <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">Score: <strong>{{ $a->final_score ?? '-' }}</strong></div>
              <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">Result: <strong>{{ $a->final_result ?? '-' }}</strong></div>
            </div>
          </div>
          <div class="flex flex-wrap gap-2 xl:justify-end">
            <a class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50" href="{{ route('appraisals.show', $a) }}">{{ $a->status === 'draft' ? 'Isi Penilaian' : 'Buka Detail' }}</a>
            @if($a->status !== 'approved')
              <span class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-500">Akses hanya untuk assignment Anda</span>
            @endif
          </div>
        </div>
      </div>
    @empty
      <div class="card p-6 text-sm text-slate-600">Belum ada assignment appraisal untuk akun Anda.</div>
    @endforelse
  </div>

  <div>{{ $appraisals->links() }}</div>
</div>
@endsection
