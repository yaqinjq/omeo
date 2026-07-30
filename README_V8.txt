OMEŌ HR Suite Overlay v8 (Probation Reminder + HR Notifications)

Tujuan:
- Menambahkan tabel hr_notifications (ringan, tidak bentrok dengan Laravel Notification bawaan)
- Generate reminder appraisal berdasarkan probation_end_date (H-30/H-14/H-7/H-1 default)
- Dashboard menampilkan reminder & jumlah reminder belum dibaca

Cara pasang:
1) Copy semua isi folder overlay/ ke root project (merge/replace).
2) Jalankan: php artisan migrate --path=database/migrations/hr_suite
3) Clear cache: php artisan optimize:clear
4) (Opsional) Generate manual: php artisan hr:generate-reminders

Catatan:
- Tidak mengubah tabel employees.
- Tidak memerlukan tabel notifications bawaan Laravel.
