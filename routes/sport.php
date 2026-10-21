<?php

use App\Http\Controllers\Sport\Admin\BackdateController;
use App\Http\Controllers\Sport\Admin\ImportController;
use App\Http\Controllers\Sport\Admin\NonMkoAttendanceController;
use App\Http\Controllers\Sport\Admin\ParticipantController;
use App\Http\Controllers\Sport\Admin\ReportController;
use App\Http\Controllers\Sport\Admin\ReportExportController;
use App\Http\Controllers\Sport\Admin\RewardConfigController;
use App\Http\Controllers\Sport\EventController;
use App\Http\Controllers\Sport\SportController;
use Illuminate\Support\Facades\Route;

/**
 * OMEO Sport — MVP slice 1. Sengaja di file terpisah (bukan ditambahkan ke
 * routes/hr_suite_v6.php atau routes/web.php) supaya modul ini benar-benar
 * terisolasi dan mudah di-review/dinonaktifkan tanpa menyentuh routing
 * HRD/Finance yang sudah ada.
 */
Route::middleware(['auth'])->group(function () {
    // Sisi karyawan (MKO) — lihat event, daftar, ajukan pembatalan, konfirmasi hadir.
    // Tidak ada permission khusus (sama seperti pola tasks.my) — tapi tetap
    // dibatasi server-side ke employee_id milik user yang login (lihat
    // SportController::requireEmployeeId / authorizeOwnRegistration).
    Route::prefix('sport')->name('sport.')->group(function () {
        Route::get('/', [SportController::class, 'index'])->name('index');
        Route::get('/{event}', [SportController::class, 'show'])->name('show');
        Route::post('/{event}/register', [SportController::class, 'register'])->name('register');
        Route::post('/registrations/{registration}/request-cancellation', [SportController::class, 'requestCancellation'])->name('registrations.request-cancellation');
        Route::post('/registrations/{registration}/confirm-attendance', [SportController::class, 'confirmAttendance'])->name('registrations.confirm-attendance');
    });

    // Sisi Koordinator — kelola event miliknya + keputusan pembatalan.
    Route::middleware(['role:koordinator,admin,finance'])->prefix('sport-coordinator')->name('sport.coordinator.')->group(function () {
        Route::get('/', [EventController::class, 'index'])->name('index');
        Route::get('/create', [EventController::class, 'create'])->name('create');
        Route::post('/', [EventController::class, 'store'])->name('store');
        Route::get('/{event}', [EventController::class, 'show'])->name('show');
        Route::get('/{event}/edit', [EventController::class, 'edit'])->name('edit');
        Route::put('/{event}', [EventController::class, 'update'])->name('update');
        Route::post('/{event}/publish', [EventController::class, 'publish'])->name('publish');
        Route::post('/{event}/cancel', [EventController::class, 'cancel'])->name('cancel');
        Route::post('/cancellations/{cancellationRequest}/approve', [EventController::class, 'approveCancellation'])->name('cancellations.decide');
        Route::post('/cancellations/{cancellationRequest}/reject', [EventController::class, 'rejectCancellation'])->name('cancellations.reject');
    });

    /**
     * OMEO Sport — MVP slice 2 (v1.1): report/export, Non-MKO, import
     * historis, draft reward, backdate. HANYA Admin Finance (role:admin,
     * finance) — Koordinator TIDAK diberi akses di sini, sesuai Spesifikasi
     * v1.1 Section 2 & 11 (jangan beri Finance semua permission HRD
     * implisit, dan jangan beri Koordinator akses report/import/reward/
     * backdate yang bukan tanggung jawabnya).
     */
    Route::middleware(['role:admin,finance'])->prefix('sport-admin')->name('sport.admin.')->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [ReportExportController::class, 'export'])->name('reports.export');
        Route::get('/reports/fiscal-settings', [ReportController::class, 'editFiscalSettings'])->name('reports.fiscal-settings');
        Route::post('/reports/fiscal-settings', [ReportController::class, 'updateFiscalSettings'])->name('reports.fiscal-settings.update');

        Route::get('/participants', [ParticipantController::class, 'index'])->name('participants.index');
        Route::post('/participants', [ParticipantController::class, 'store'])->name('participants.store');

        Route::get('/events/{event}/non-mko-attendances', [NonMkoAttendanceController::class, 'forEvent'])->name('events.non-mko-attendances.index');
        Route::post('/events/{event}/non-mko-attendances', [NonMkoAttendanceController::class, 'store'])->name('events.non-mko-attendances.store');
        // Sengaja TIDAK ADA route destroy/delete — lihat NonMkoAttendanceController (retensi riwayat).

        Route::get('/reward', [RewardConfigController::class, 'index'])->name('reward.index');
        Route::post('/reward', [RewardConfigController::class, 'store'])->name('reward.store');

        Route::get('/backdate/settings', [BackdateController::class, 'editSettings'])->name('backdate.settings');
        Route::post('/backdate/settings', [BackdateController::class, 'updateSettings'])->name('backdate.settings.update');
        Route::post('/registrations/{registration}/backdate', [BackdateController::class, 'store'])->name('backdate.store');

        Route::get('/import', [ImportController::class, 'create'])->name('import.create');
        Route::post('/import', [ImportController::class, 'store'])->name('import.store');
        Route::get('/import/{batch}/map', [ImportController::class, 'editMapping'])->name('import.map');
        Route::post('/import/{batch}/map', [ImportController::class, 'saveMapping'])->name('import.map.save');
        Route::get('/import/{batch}/preview', [ImportController::class, 'preview'])->name('import.preview');
        Route::post('/import/{batch}/commit', [ImportController::class, 'commit'])->name('import.commit');
    });
});
