@extends('layouts.app')
@section('content')
<div class="space-y-6">
  <div class="card p-6">
    <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Appraisal</div>
    <h1 class="mt-2 text-2xl font-bold text-slate-900">Panduan Skor Penilaian</h1>
    <p class="mt-2 text-sm text-slate-600">Acuan bersama supaya penilaian bintang 1-5 dilakukan berdasarkan fakta, data, dan perilaku selama periode evaluasi — bukan kesan pribadi atau kejadian terbaru saja.</p>
  </div>

  <div class="card p-6">
    @include('appraisals.partials.guide_content', ['bands' => $bands])
  </div>
</div>
@endsection
