OMEŌ HR Suite Overlay v6 (Master Data + Appraisal Assignment)

1) Copy folder `overlay/` ke root project Laravel Anda (merge/replace).
2) Tambahkan alias middleware role di bootstrap/app.php (Laravel 12):
   ->withMiddleware(function (Middleware $middleware) {
       $middleware->alias([
           'role' => \App\Http\Middleware\RoleMiddleware::class,
       ]);
   });

3) Tambahkan require routes di routes/web.php (paling bawah):
   require base_path('routes/hr_suite_v6.php');

4) Pastikan model User punya kolom `role` (string). Jika belum, tambahkan manual.
5) Jalankan:
   php artisan optimize:clear

Menu baru:
- /departments /positions /outlets  (Admin/HRD)
- /appraisals/assignment (Admin/HRD) untuk generate appraisal draft multi-penilai

Catatan:
- Periode appraisal memakai tabel `appraisal_periods` (harus sudah ada dari dump).
