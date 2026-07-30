@extends('layouts.app')
@section('content')
<div class="space-y-6">
  <div class="card p-6">
    <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Appraisal</div>
    <h1 class="mt-2 text-2xl font-bold text-slate-900">Panduan Skor Penilaian</h1>
    <p class="mt-2 text-sm text-slate-600">Acuan bersama supaya penilaian bintang 1-5 dilakukan berdasarkan fakta, data, dan perilaku selama periode evaluasi — bukan kesan pribadi atau kejadian terbaru saja.</p>
  </div>

  <div class="card p-6">
    <h2 class="text-base font-bold text-slate-900">Prinsip Penilaian bagi Evaluator</h2>
    <ol class="mt-3 space-y-2 text-sm text-slate-700 list-decimal list-inside">
      <li><strong>Skor 3</strong> adalah standar karyawan yang telah memenuhi ekspektasi jabatan, bukan berarti kurang baik.</li>
      <li><strong>Skor 4</strong> diberikan apabila karyawan secara konsisten menunjukkan kinerja di atas standar.</li>
      <li><strong>Skor 5</strong> hanya diberikan untuk performa yang benar-benar luar biasa dan konsisten sepanjang periode penilaian, bukan karena satu pencapaian sesaat.</li>
      <li><strong>Skor 2 dan 1</strong> harus disertai bukti atau contoh perilaku/kinerja yang menunjukkan belum terpenuhinya standar.</li>
      <li>Penilaian harus didasarkan pada <strong>fakta, data, dan perilaku selama periode evaluasi</strong>, bukan berdasarkan kesan pribadi atau kejadian terbaru saja.</li>
    </ol>
  </div>

  <div class="card p-6">
    <h2 class="text-base font-bold text-slate-900">Klasifikasi Penilaian — Overall Performance Rating</h2>
    <p class="mt-1 text-sm text-slate-500">Nilai akhir (rata-rata tertimbang skala bintang 1-5) dikelompokkan menjadi 6 tingkat berikut.</p>
    <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
            <th class="px-4 py-2.5">Nilai</th>
            <th class="px-4 py-2.5">Grade</th>
            <th class="px-4 py-2.5">Arti dalam Bahasa Indonesia</th>
          </tr>
        </thead>
        <tbody>
          @php
            $descriptions = [
              'Outstanding'        => 'Kinerja jauh melampaui ekspektasi. Memberikan hasil luar biasa secara konsisten, menjadi teladan, serta memberikan dampak signifikan bagi perusahaan.',
              'Exceed Expectation' => 'Kinerja melampaui target dan harapan. Sering menunjukkan inisiatif, kualitas kerja tinggi, dan kontribusi di atas standar.',
              'Above Expectation'  => 'Kinerja lebih baik dari yang diharapkan. Mencapai target dengan hasil yang baik dan sesekali memberikan nilai tambah.',
              'Meet Expectation'   => 'Kinerja memenuhi target dan standar yang ditetapkan. Menjalankan tugas dengan baik sesuai tanggung jawab.',
              'Need Improvement'   => 'Kinerja belum konsisten memenuhi harapan. Masih terdapat beberapa area yang perlu ditingkatkan melalui pembinaan atau pelatihan.',
              'Unsatisfactory'     => 'Kinerja berada di bawah standar minimum. Memerlukan perbaikan yang signifikan dan evaluasi lebih lanjut.',
            ];
            $rangeLabels = [
              'Outstanding'        => '4.51 – 5.00',
              'Exceed Expectation' => '4.01 – 4.50',
              'Above Expectation'  => '3.51 – 4.00',
              'Meet Expectation'   => '3.00 – 3.50',
              'Need Improvement'   => '2.50 – 2.99',
              'Unsatisfactory'     => '< 2.50',
            ];
          @endphp
          @foreach($bands as $band)
          <tr class="border-t border-slate-100">
            <td class="px-4 py-3 font-semibold text-slate-800">{{ $rangeLabels[$band['label']] }}</td>
            <td class="px-4 py-3">
              <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" style="background:{{ $band['bg'] }};color:{{ $band['color'] }}">{{ $band['label'] }}</span>
              <span class="ml-1 text-xs text-slate-500">({{ $band['label_id'] }})</span>
            </td>
            <td class="px-4 py-3 text-slate-600">{{ $descriptions[$band['label']] ?? '-' }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  <div class="card p-6">
    <h2 class="text-base font-bold text-slate-900">Panduan Skor Penilaian (1-5)</h2>
    <p class="mt-1 text-sm text-slate-500">Digunakan sebagai acuan umum saat memilih skor per kriteria. Sebagian kriteria juga punya keterangan spesifiknya sendiri — akan muncul otomatis saat Anda mengarahkan kursor ke bintang pada form penilaian.</p>
    <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
            <th class="px-4 py-2.5 w-24">Skor</th>
            <th class="px-4 py-2.5 w-56">Definisi Umum</th>
            <th class="px-4 py-2.5">Deskripsi</th>
          </tr>
        </thead>
        <tbody>
          @php
            $scoreGuide = [
              5 => ['def' => 'Melampaui ekspektasi', 'title' => 'Sangat Baik (Outstanding)', 'desc' => 'Secara konsisten menunjukkan performa yang sangat tinggi, bekerja mandiri, proaktif, menjadi contoh bagi rekan kerja, serta hampir tidak pernah memerlukan arahan atau perbaikan.'],
              4 => ['def' => 'Di atas ekspektasi', 'title' => 'Baik (Exceeds Expectations)', 'desc' => 'Mampu memenuhi seluruh target dengan hasil yang baik, sesekali memberikan nilai tambah, membutuhkan arahan yang minimal, dan hanya terdapat kekurangan kecil yang tidak memengaruhi hasil kerja.'],
              3 => ['def' => 'Sesuai ekspektasi', 'title' => 'Cukup (Meets Expectations)', 'desc' => 'Memenuhi standar pekerjaan yang ditetapkan. Target dan tanggung jawab tercapai, namun masih memerlukan arahan atau pengawasan pada beberapa aspek serta terdapat ruang untuk pengembangan.'],
              2 => ['def' => 'Di bawah ekspektasi', 'title' => 'Kurang (Below Expectations)', 'desc' => 'Belum mampu memenuhi sebagian target atau standar kerja. Kesalahan masih cukup sering terjadi, memerlukan pengawasan intensif, serta perlu perbaikan melalui pembinaan atau pelatihan.'],
              1 => ['def' => 'Jauh di bawah ekspektasi', 'title' => 'Sangat Kurang (Unsatisfactory)', 'desc' => 'Tidak memenuhi standar pekerjaan, sering melakukan kesalahan yang berdampak pada pekerjaan, tidak menunjukkan perbaikan meskipun telah diberikan arahan, sehingga memerlukan tindakan pembinaan khusus.'],
            ];
          @endphp
          @foreach($scoreGuide as $score => $g)
          <tr class="border-t border-slate-100">
            <td class="px-4 py-3">
              <span class="text-lg font-bold text-amber-500">{{ $score }}</span>
              <span class="ml-1 text-xs font-semibold text-slate-500">{{ $g['title'] }}</span>
            </td>
            <td class="px-4 py-3 text-slate-600">{{ $g['def'] }}</td>
            <td class="px-4 py-3 text-slate-600">{{ $g['desc'] }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
