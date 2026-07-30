# aaPanel Deployment Checklist

Gunakan checklist ini setiap kali update production agar `/employees`, import master, dan API tetap sinkron dengan kode terbaru.

## Server requirements

- PHP `8.2+`
- MySQL dengan dukungan JSON function
- document root mengarah ke folder `public/`
- folder `storage/` dan `bootstrap/cache/` writable oleh web server

## Safe deploy steps

```powershell
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan route:clear
php artisan config:clear
php artisan view:clear
```

Jika semua normal dan route sudah tidak dobel lagi, cache boleh diaktifkan kembali:

```powershell
php artisan config:cache
php artisan route:cache
```

## Jika `/employees` masih 500

1. Cek `storage/logs/laravel.log` segera setelah membuka `/employees`.
2. Jika error `SQLSTATE` kolom/tabel tidak ditemukan, jalankan migrasi yang belum masuk.
3. Jika error terkait route, container, class, atau cache lama, jalankan ulang langkah clear cache di atas.
4. Pastikan upload/deploy ikut membawa folder `vendor/` yang sesuai atau jalankan `composer install` di server.
