PATCH V9 - Perbaikan Error & Menu Kandidat

File yang perlu Anda REPLACE di project Laravel Anda sesuai path berikut:

1) routes/web.php
   -> routes/web.php

2) Layout utama
   -> resources/views/layouts/app.blade.php

3) Menu sidebar
   -> resources/views/partials/hr_suite_menu.blade.php

4) Application Form
   -> app/Http/Controllers/ApplicationFormController.php
   -> resources/views/application-form/edit.blade.php
   -> resources/views/application/partials/repeatable.blade.php

5) HRD/ADMIN - List Applicants
   -> app/Http/Controllers/HrApplicantController.php
   -> resources/views/hr/applicants/index.blade.php

Catatan:
- Partial repeatable HARUS berada pada: resources/views/application/partials/repeatable.blade.php
  karena view memanggil: @include('application.partials.repeatable', ...)

- Jika Anda masih melihat RouteNotFoundException:
  jalankan: php artisan route:clear && php artisan optimize:clear

- Pastikan model ApplicantProfile memiliki relasi:
    public function user() { return $this->belongsTo(User::class); }
