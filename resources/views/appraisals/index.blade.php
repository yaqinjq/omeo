@extends('layouts.app')
@section('content')
<div class="space-y-6">
  <div class="card p-6">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
      <div>
        <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Appraisal HRD</div>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">Monitoring Appraisal Probation</h1>
        <p class="mt-2 text-sm text-slate-600">1 baris = 1 karyawan, menggabungkan semua evaluatornya. Klik "Detail" untuk lihat/kelola per evaluator (due date, approve, dsb).</p>
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

  {{-- FILTER --}}
  <form method="GET" class="card p-5">
    <div class="grid grid-cols-1 gap-3 md:grid-cols-3 xl:grid-cols-6">
      <div class="xl:col-span-2">
        <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Cari (nama/jabatan/NIK)</label>
        <input type="text" name="search" value="{{ $search }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Cari karyawan...">
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Departemen</label>
        <select name="department_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
          <option value="">Semua</option>
          @foreach($departments as $d)
            <option value="{{ $d->id }}" @selected((string) $departmentId === (string) $d->id)>{{ $d->name }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Status agregat</label>
        <select name="agg_status" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
          <option value="">Semua</option>
          <option value="complete" @selected($aggStatus === 'complete')>Selesai Semua</option>
          <option value="partial" @selected($aggStatus === 'partial')>Sebagian Submit</option>
          <option value="none" @selected($aggStatus === 'none')>Belum Ada yang Submit</option>
        </select>
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Jenis trigger</label>
        <select name="trigger_source" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
          <option value="">Semua</option>
          <option value="probation_timeline" @selected($triggerSource === 'probation_timeline')>Probation</option>
          <option value="manual_acceleration" @selected($triggerSource === 'manual_acceleration')>Percepatan Manual</option>
        </select>
      </div>
      <div class="flex items-end gap-2">
        <label class="flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm">
          <input type="checkbox" name="overdue_only" value="1" @checked($overdueOnly)>
          Due date terlewat
        </label>
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Periode</label>
        <select name="period_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
          @foreach($periods as $p)
            <option value="{{ $p->id }}" @selected((string) $periodId === (string) $p->id)>{{ $p->name }}{{ $p->is_active ? ' (Aktif)' : '' }}</option>
          @endforeach
          <option value="all" @selected($periodId === null)>Semua Periode (termasuk histori lama)</option>
        </select>
      </div>
    </div>
    @if($periodId !== null)
    <div class="mt-3 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-800">
      Menampilkan periode {{ $periods->firstWhere('id', $periodId)?->name ?? '-' }} saja. Riwayat appraisal lama (termasuk hasil migrasi Historis MEO) tidak ikut dihitung di sini — pilih "Semua Periode" kalau memang perlu lihat semuanya.
    </div>
    @endif
    <div class="mt-3 flex gap-2">
      <button class="btn-primary" type="submit">Terapkan Filter</button>
      <a href="{{ route('appraisals.index') }}" class="btn-outline">Reset</a>
    </div>
  </form>

  <div class="space-y-3">
    @forelse($paginator as $row)
      <div class="card p-5">
        <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
          <div class="min-w-0 flex-1 space-y-2">
            <div class="flex flex-wrap items-center gap-2">
              <div class="text-lg font-semibold text-slate-900">{{ $row->employee_name }}</div>
              <span class="badge">{{ $row->jabatan }}</span>
              <span class="badge">{{ $row->department_name }}</span>
              @if($row->agg_status === 'complete')
                <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Selesai Semua</span>
              @elseif($row->agg_status === 'partial')
                <span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-700">Sebagian Submit</span>
              @else
                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">Belum Ada yang Submit</span>
              @endif
              @if($row->is_overdue)
                <span class="rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700">Due date terlewat</span>
              @endif
            </div>
            <div class="grid grid-cols-1 gap-2 text-sm md:grid-cols-4">
              <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">Progress: <strong>{{ $row->submitted_total }}/{{ $row->total }}</strong> evaluator submit</div>
              <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">Due date terdekat: <strong>{{ $row->nearest_due_date?->format('d-m-Y') ?? '-' }}</strong></div>
              <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">Approved: <strong>{{ $row->approved_count }}/{{ $row->total }}</strong></div>
              <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">Trigger: <strong>{{ $row->trigger_sources->map(fn($t) => strtoupper(str_replace('_',' ',$t)))->implode(', ') ?: '-' }}</strong></div>
            </div>
          </div>
          <div class="flex flex-wrap gap-2 xl:justify-end">
            <a class="btn-primary" href="{{ $row->report_url }}">Detail</a>
          </div>
        </div>
      </div>
    @empty
      <div class="card p-6 text-sm text-slate-600">
        Belum ada data appraisal sesuai filter. Mulai dari <a href="{{ route('appraisals.assignment') }}" class="font-semibold text-slate-900 underline">Assignment Appraisal Probation</a> agar HRD bisa membuat draft berbasis timeline probation atau percepatan manual.
      </div>
    @endforelse
  </div>

  <div>{{ $paginator->links() }}</div>
</div>
@endsection
