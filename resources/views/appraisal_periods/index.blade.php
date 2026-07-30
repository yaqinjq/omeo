@extends('layouts.app')
@section('content')
<div class="space-y-6">
  <div class="card p-6">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Grouping Legacy Appraisal</h1>
        <p class="mt-1 text-sm text-slate-600">Grouping ini tetap dipertahankan untuk kebutuhan reminder, pelacakan batch, dan laporan legacy. Namun penjadwalan appraisal operasional sekarang lebih diarahkan dari probation timeline dan percepatan manual HRD.</p>
      </div>
      <a class="btn-primary inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold" href="{{ route('appraisal-periods.create') }}">+ Tambah Grouping</a>
    </div>
  </div>

  <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
    Modul ini bukan lagi pintu utama proses appraisal. Gunakan halaman assignment probation dan monitoring appraisal untuk flow kerja harian HRD, lalu pakai grouping ini sebagai pengelompokan legacy bila diperlukan.
  </div>

  <div class="card overflow-hidden p-0">
    <div class="overflow-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-100 text-slate-700">
          <tr>
            <th class="text-left p-3 font-semibold">Nama Grouping</th>
            <th class="text-left p-3 font-semibold">Window Tanggal</th>
            <th class="text-left p-3 font-semibold">Tipe Grouping</th>
            <th class="text-left p-3 font-semibold">Aktif</th>
            <th class="text-left p-3 font-semibold">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @foreach($periods as $p)
            <tr class="border-t border-slate-200">
              <td class="p-3 text-slate-900">{{ $p->name }}</td>
              <td class="p-3 text-slate-700">{{ $p->start_date->format('d-m-Y') }} s/d {{ $p->end_date->format('d-m-Y') }}</td>
              <td class="p-3 text-slate-700">{{ $p->type }}</td>
              <td class="p-3 text-slate-700">{{ $p->is_active ? 'Ya' : 'Tidak' }}</td>
              <td class="p-3">
                <a class="font-medium text-slate-700 hover:text-slate-900 hover:underline" href="{{ route('appraisal-periods.edit',$p) }}">Edit</a>
                <span class="mx-1 text-slate-300">•</span>
                <form class="inline" method="POST" action="{{ route('appraisal-periods.destroy',$p) }}" onsubmit="return confirm('Hapus grouping ini?')">@csrf @method('DELETE')<button class="font-medium text-rose-700 hover:text-rose-800 hover:underline">Hapus</button></form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  <div>{{ $periods->links() }}</div>
</div>
@endsection
