# OMEÖ HR Suite Overlay v2 (Auth + CRUD)

Overlay untuk Laravel 12 project Anda. UI pakai Tailwind CDN (tidak butuh npm build).

## Pasang
1. Extract zip
2. Copy isi folder `overlay/` ke root project Laravel Anda (replace)
3. Set .env:
   - SESSION_DRIVER=file
   - CACHE_STORE=file
   - QUEUE_CONNECTION=sync
4. php artisan optimize:clear
5. Import SQL dump Anda (minimal: users, employees, training_materials, training_participations)

## Login
- Jika tabel users kosong, buka /setup untuk create admin pertama.
- Login di /login.

## Role
- Jika kolom users.role ada: admin/hr/employee
- Jika kolom users.is_admin ada: is_admin=1 dianggap admin
