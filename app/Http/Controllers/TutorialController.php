<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class TutorialController extends Controller
{
    public function index(): View
    {
        return view('tutorial.index', [
            'sections' => $this->getSections(),
        ]);
    }

    /**
     * Panduan penggunaan untuk HRD/pengguna, dikelompokkan per modul.
     * Berbeda dari Changelog: isinya evergreen (cara pakai fitur yang sedang
     * berjalan sekarang), bukan riwayat perubahan per tanggal. Tambahkan
     * kategori/artikel baru di sini kalau ada fitur baru yang perlu dijelaskan
     * cara pakainya ke HRD.
     */
    private function getSections(): array
    {
        return [
            [
                'category' => 'Appraisal — Monitoring & Periode',
                'icon'     => 'M3 3v18h18M7 15l3-3 3 2 4-5',
                'color'    => '#2563EB',
                'articles' => [
                    [
                        'title' => 'Kenapa jumlah evaluator di Monitoring kadang beda dari yang diundang?',
                        'body'  => 'Halaman <strong>Monitoring Appraisal</strong> dan <strong>Laporan Appraisal</strong> sekarang otomatis menampilkan data <strong>periode yang sedang aktif saja</strong>. Kalau ingin melihat riwayat appraisal dari periode lain (termasuk data lama hasil migrasi "Historis MEO"), gunakan dropdown <strong>"Periode"</strong> di bagian filter, lalu pilih periode tertentu atau "Semua Periode" untuk lihat semuanya sekaligus.',
                    ],
                    [
                        'title' => 'Melihat evaluator yang belum mengisi',
                        'body'  => 'Dari Monitoring, klik <strong>"Detail"</strong> pada karyawan yang ingin dicek. Di bagian atas halaman detail, ada kotak kuning <strong>"Evaluator Belum Mengisi"</strong> yang menampilkan siapa saja yang belum submit penilaian, lengkap kapan terakhir di-reminder dan tombol untuk kirim reminder ulang.',
                    ],
                ],
            ],
            [
                'category' => 'Appraisal — Reminder & Due Date',
                'icon'     => 'M12 8v4l3 3M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20',
                'color'    => '#D97706',
                'articles' => [
                    [
                        'title' => 'Kirim Reminder Evaluator',
                        'body'  => 'Tombol reminder ada di dua tempat: halaman Detail Appraisal (per evaluator), dan halaman Detail Karyawan bagian "Evaluator Belum Mengisi" (bisa untuk beberapa evaluator sekaligus). <strong>Ada jeda 24 jam</strong> antar pengiriman reminder untuk evaluator yang sama — kalau baru dikirim, tombolnya otomatis nonaktif dan menampilkan keterangan jam berapa bisa dikirim lagi. Ini untuk mencegah reminder ter-spam tidak sengaja.',
                    ],
                    [
                        'title' => 'Perpanjang Due Date',
                        'body'  => 'Untuk satu evaluator: buka halaman Detail Appraisal evaluator tersebut, ada form "Perpanjang Due Date". Untuk semua evaluator sekaligus (satu karyawan): buka halaman Detail Karyawan, klik "Perpanjang Due Date — Semua Evaluator". Isi due date baru dan alasan (wajib), lalu simpan — evaluator akan otomatis dapat notifikasi (internal + email).',
                    ],
                ],
            ],
            [
                'category' => 'Appraisal — Kriteria Penilaian',
                'icon'     => 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2',
                'color'    => '#7C3AED',
                'articles' => [
                    [
                        'title' => 'Tab Office / Operational',
                        'body'  => 'Menu <strong>Kelola Kriteria</strong> sekarang punya 2 tab: <strong>Office</strong> dan <strong>Operational</strong> (gabungan outlet & produksi). Setiap kategori karyawan otomatis dinilai pakai kriteria dari tab yang sesuai saat appraisal dibuat — tidak perlu diatur manual per appraisal. Kalau mau mengubah/menambah kriteria untuk satu kategori, buka tab yang sesuai, isinya tidak akan tercampur dengan tab lain.',
                    ],
                    [
                        'title' => 'Komentar evaluator wajib diisi',
                        'body'  => 'Setiap kriteria yang diberi nilai bintang sekarang <strong>wajib</strong> disertai komentar singkat dari evaluator sebelum bisa submit. Ini supaya penilaian bintang selalu punya konteks/alasan, bukan cuma angka.',
                    ],
                ],
            ],
            [
                'category' => 'Appraisal — Nilai & Klasifikasi',
                'icon'     => 'M9 19V6l12-3v13M9 19a3 3 0 1 1-6 0 3 3 0 0 1 6 0zM21 16a3 3 0 1 1-6 0 3 3 0 0 1 6 0z',
                'color'    => '#059669',
                'articles' => [
                    [
                        'title' => 'Satu angka, satu arti, di semua halaman',
                        'body'  => 'Nilai akhir appraisal sekarang selalu ditampilkan dalam skala <strong>1-5</strong> (bukan lagi 0-100) di halaman Laporan, Detail Karyawan, dan semua file export (PDF/Excel) — supaya tidak ada lagi dua angka berbeda untuk hal yang sama. Klasifikasinya (Outstanding/Exceed Expectation/dst) mengikuti tabel yang sama di halaman <a href="/appraisals/panduan-penilaian" class="text-blue-600 underline">Panduan Skor Penilaian</a>.',
                    ],
                ],
            ],
            [
                'category' => 'Appraisal — Tanda Tangan Digital',
                'icon'     => 'M3 17l6 6M21 3l-6.5 6.5M3 21l3-3M9 3H3v6',
                'color'    => '#DC2626',
                'articles' => [
                    [
                        'title' => 'Slot TTD dinamis',
                        'body'  => 'Slot pertama (Karyawan) otomatis terisi nama karyawan yang dinilai, tidak perlu dipilih manual. Slot lain (PIC/HRD/Manager/dst) bisa diganti kategorinya langsung dari dropdown di setiap slot. Klik <strong>"+ Tambah TTD"</strong> kalau butuh slot ke-4 (maksimal 4 slot per appraisal).',
                    ],
                    [
                        'title' => 'Owner In Charge',
                        'body'  => 'Untuk menambahkan tanda tangan pemilik outlet cabang/franchise: isi dulu namanya di <strong>Master Outlet → edit outlet → field "Owner In Charge"</strong>, baru kategori ini bisa dipilih di slot TTD. Slot ini tidak perlu login — cuma menampilkan nama untuk ditandatangani secara fisik di dokumen cetak.',
                    ],
                ],
            ],
            [
                'category' => 'Notifikasi & Email',
                'icon'     => 'M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5M9 17a3 3 0 0 0 6 0',
                'color'    => '#0891B2',
                'articles' => [
                    [
                        'title' => 'Mengatur pesan email notifikasi',
                        'body'  => 'Buka <strong>Settings → Notifikasi → bagian "Template Pesan Ringkas"</strong>. Setiap jenis notifikasi appraisal (undangan evaluator, reminder, due date diperpanjang, permintaan edit) punya judul & isi pesan yang bisa diedit bebas. Placeholder seperti <code>{employee_name}</code> atau <code>{due_date}</code> akan otomatis terisi sesuai data appraisal — daftar placeholder yang berlaku ditampilkan di bawah tiap kolom isi pesan.',
                    ],
                    [
                        'title' => 'Menambahkan lampiran email',
                        'body'  => 'Di halaman yang sama, tiap jenis notifikasi punya kolom <strong>"Lampiran Email"</strong> — upload satu file (PDF/gambar/dokumen, maks 5MB), misalnya panduan pengisian appraisal. File itu akan otomatis ikut terlampir di setiap email jenis tersebut ke depannya, sampai dihapus/diganti.',
                    ],
                ],
            ],
            [
                'category' => 'Master Data — Shift Kerja',
                'icon'     => 'M12 8v4l3 3M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20',
                'color'    => '#0369A1',
                'articles' => [
                    [
                        'title' => 'Menambahkan shift per outlet',
                        'body'  => 'Buka menu <strong>Master Shift Kerja</strong>, pilih outlet operational, lalu tambahkan shift (kode, nama, jam masuk, jam pulang). Karyawan yang scan presensi tanpa jadwal khusus akan otomatis dicocokkan ke shift yang jamnya paling dekat dengan jam scan-nya — tidak perlu assign manual per karyawan per hari.',
                    ],
                ],
            ],
            [
                'category' => 'Task & Project Management',
                'icon'     => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-7 9l2 2 4-4',
                'color'    => '#7C3AED',
                'articles' => [
                    [
                        'title' => 'Papan Kanban Open / In Progress / Done',
                        'body'  => 'Menu <strong>Task & Project</strong> menampilkan semua tugas dalam 3 kolom status. <strong>Drag kartu tugas</strong> antar kolom untuk mengubah statusnya — tidak perlu buka form edit. Klik <strong>"+ Tambah Tugas"</strong> untuk membuat tugas baru, isi judul, project (opsional), siapa yang dikerjakan (1 karyawan tertentu atau seluruh pemegang 1 posisi), deadline, dan prioritas.',
                    ],
                    [
                        'title' => 'Kelola Project',
                        'body'  => 'Klik <strong>"📁 Kelola Project"</strong> di pojok kanan atas papan untuk buka panel tambah/edit/hapus project — tidak perlu pindah halaman. Project dipakai untuk mengelompokkan tugas-tugas yang berkaitan (mis. "Renovasi Outlet Kupang Indah"), dan bisa dipakai sebagai filter di papan utama.',
                    ],
                    [
                        'title' => 'Breakdown tugas tanpa batas (ala ClickUp)',
                        'body'  => 'Klik kartu tugas mana pun untuk buka detailnya, lalu klik <strong>"+ Sub-tugas"</strong> di bagian "Breakdown Tugas" untuk memecahnya jadi langkah-langkah kecil. Sub-tugas ini sendiri juga bisa dipecah lagi jadi sub-sub-tugas, tidak ada batas kedalaman. Gunakan breadcrumb <strong>"← Kembali ke [tugas induk]"</strong> di bagian atas modal untuk naik lagi ke tugas di atasnya. Menghapus 1 tugas otomatis menghapus semua breakdown di bawahnya.',
                    ],
                    [
                        'title' => 'Halaman "Tugas Saya" untuk karyawan',
                        'body'  => 'Karyawan (bukan admin/HRD/manager) punya papan sendiri di menu <strong>"Tugas Saya"</strong> — hanya menampilkan tugas yang ditugaskan langsung ke mereka atau tugas rutin posisi mereka. Karyawan bisa drag kartu untuk ubah status dan memecah tugasnya sendiri jadi breakdown pribadi, tapi tidak bisa membuat tugas baru dari nol atau mengelola project — itu tetap wewenang admin/HRD/manager di papan utama.',
                    ],
                    [
                        'title' => 'Cari karyawan/posisi lebih cepat',
                        'body'  => 'Dropdown "Karyawan" dan "Seluruh Posisi" di form tugas sekarang bisa diketik langsung untuk mencari (live-search), tidak perlu scroll daftar panjang — sama seperti fitur pencarian di Org-chart Builder.',
                    ],
                ],
            ],
            [
                'category' => 'Role & Permission',
                'icon'     => 'M12 3l7 4v5c0 5-3.5 9-7 10-3.5-1-7-5-7-10V7l7-4zM9 12l2 2 4-4',
                'color'    => '#0F766E',
                'articles' => [
                    [
                        'title' => 'Mengatur permission per aksi',
                        'body'  => 'Buka <strong>HRD → Users & Roles</strong>, pilih role yang ingin diatur, klik <strong>"Permission"</strong>. Selain permission umum seperti "Kelola Karyawan", sekarang tersedia juga permission per-aksi yang lebih rinci (mis. "Tambah Karyawan", "Edit Tugas", "Hapus Tugas", "Approve Appraisal", "Terima/Tolak Candidate") — bisa dicentang satu-satu sesuai kebutuhan role tersebut, tidak harus mencentang izin "Kelola" penuh kalau memang cuma butuh sebagian aksi.',
                    ],
                    [
                        'title' => 'Permission yang dikunci untuk Super Admin',
                        'body'  => 'Beberapa permission paling sensitif — <strong>Kelola User & Role</strong>, <strong>Kelola Data Master Lanjutan</strong> (Group Perusahaan/Entitas Legal/Regional/Posisi), dan <strong>Kelola Tarif/Akun BPJS</strong> — hanya bisa dicentang/diberikan oleh akun Super Admin. Kalau bukan Super Admin mencoba mencentang salah satu dari permission ini untuk sebuah role, sistem akan menolak dan menampilkan pesan permission mana saja yang tidak bisa diberikan.',
                    ],
                ],
            ],
        ];
    }
}
