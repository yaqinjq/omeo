OMEŌ HR Suite Overlay v7 (patch)
================================

Tujuan patch v7:
1) Mengatasi error candidates.deleted_at (kolom tidak ada).
2) Menambahkan partial menu link untuk master & appraisal assignment.
3) Membuat DashboardController lebih aman: count candidates tanpa asumsi deleted_at.
4) Menyediakan migration opsional untuk menambah deleted_at pada candidates (jika Anda ingin soft delete).

PENTING tentang migration:
- Karena Anda import database dari file SQL, menjalankan `php artisan migrate` penuh akan gagal (table users sudah ada).
- Jalankan hanya migration overlay ini dengan PATH khusus:

  php artisan migrate --path=database/migrations/hr_suite

Langkah install:
1) Extract ZIP.
2) Copy isi folder `overlay/` ke root project Laravel Anda (merge/replace).
3) Tambahkan include partial menu ke layout Anda (contoh):
   - buka: resources/views/layouts/app.blade.php
   - di area sidebar menu, tambahkan:
       @include('partials.hr_suite_menu')
4) Clear cache:
   php artisan optimize:clear

Jika ingin menambah kolom deleted_at pada candidates:
- jalankan migration khusus:
   php artisan migrate --path=database/migrations/hr_suite

Catatan:
- Route /appraisals/assignment sudah ada di v6. Jika masih 404, cek Herd domain pointing ke folder project yang benar (public path).
