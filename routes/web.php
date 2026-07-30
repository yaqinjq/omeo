<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CareerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\TrainingMaterialController;
use App\Http\Controllers\MyTrainingController;
use App\Http\Controllers\CandidateController;
use App\Http\Controllers\OfferingController;
use App\Http\Controllers\AppraisalPeriodController;
use App\Http\Controllers\AppraisalIndicatorController;
use App\Http\Controllers\AppraisalController;
use App\Http\Controllers\HrNotificationController;
use App\Http\Controllers\TrainingAssessmentController;
use App\Http\Controllers\Hrd\TrainingProgramController;
use App\Http\Controllers\Hrd\TrainingEventController;
use App\Http\Controllers\Hrd\TrainingTrainerController;
use App\Http\Controllers\Hrd\CareerPostController as HrdCareerPostController;
use App\Http\Controllers\Hrd\HrTeamMemberController;
use App\Http\Controllers\Hrd\LandingPageController;
use App\Http\Controllers\Hrd\WalkInEventController as HrdWalkInEventController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\Trainer\TrainingEventController as TrainerTrainingEventController;
use App\Http\Controllers\WalkInController;
use App\Http\Controllers\Finance\PayrollImportController;
use App\Http\Controllers\Finance\BpjsDashboardController;
use App\Http\Controllers\Finance\BpjsRateConfigController;
use App\Http\Controllers\Finance\BpjsLegalAccountController;
use App\Http\Controllers\Finance\AnnualSummaryController;
use App\Http\Controllers\Finance\AnnualSummaryExportController;
use App\Http\Controllers\Finance\SlipGajiController;
use App\Http\Controllers\Finance\BpjsReconciliationController;
use App\Http\Controllers\Finance\PayrollBpjsReconciliationController;
use App\Http\Controllers\Finance\DepartmentCategoryMappingController;
use App\Http\Controllers\Finance\FinanceSettingController;
use App\Http\Controllers\Recruitment\WalkinController as RecruitmentWalkinController;
use App\Http\Controllers\Master\CompanyGroupController;
use App\Http\Controllers\Master\LegalEntityController;
use App\Http\Controllers\Master\RegionController;
use App\Http\Controllers\Employee\AttendanceExportController;

/**
 * LANDING (public)
 */
Route::get('/', function () {
    // kalau sudah login -> langsung dashboard
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    // kalau belum login -> landing page
    return app(LandingController::class)->index();
})->name('landing');

Route::get('/karir', [CareerController::class, 'index'])->name('careers.index');
Route::get('/karir/{career}', [CareerController::class, 'show'])->name('careers.show');
// Walk-In registrasi publik via referral code (Fase 1 — HRD recruitment module)
Route::get('/daftar/{code}', [RecruitmentWalkinController::class, 'register'])->name('walkin.register');
Route::post('/daftar/{code}', [RecruitmentWalkinController::class, 'registerStore'])->name('walkin.register.store');
// Walk-In info publik — halaman detail event tanpa perlu referral code
Route::get('/walk-in-event/{id}', [RecruitmentWalkinController::class, 'publicShow'])
    ->name('walkin.public.show')
    ->whereNumber('id');

Route::get('/walk-in', [WalkInController::class, 'index'])->name('walk-ins.index');
Route::get('/walk-in/success/{registration}', [WalkInController::class, 'success'])->name('walk-ins.success');
Route::get('/walk-in/check-in/{token}', [WalkInController::class, 'checkinForm'])->name('walk-ins.checkin.form');
Route::post('/walk-in/check-in/{token}', [WalkInController::class, 'checkin'])->name('walk-ins.checkin.submit');
Route::get('/walk-in/{event}', [WalkInController::class, 'show'])->name('walk-ins.show');
Route::post('/walk-in/{event}/register', [WalkInController::class, 'register'])->name('walk-ins.register');

/**
 * AUTH AREA
 */
Route::middleware(['auth'])->group(function () {

    // dashboard setelah login
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('dashboard')->name('dashboard.')->group(function () {
        Route::middleware('superadmin')->group(function () {
            Route::get('/landing-page', [LandingPageController::class, 'edit'])->name('landing-page.edit');
            Route::put('/landing-page', [LandingPageController::class, 'update'])->name('landing-page.update');
            Route::resource('hr-team', HrTeamMemberController::class)
                ->parameters(['hr-team' => 'hrTeamMember'])
                ->except(['show']);
        });

        Route::middleware('role:admin,hrd')->group(function () {
            Route::resource('careers', HrdCareerPostController::class)->except(['show']);
            Route::resource('walk-ins', HrdWalkInEventController::class)
                ->parameters(['walk-ins' => 'walkIn'])
                ->except(['show']);
            Route::get('walk-ins/{walkIn}/participants', [HrdWalkInEventController::class, 'participants'])->name('walk-ins.participants');
            Route::put('walk-ins/{walkIn}/participants/{registration}', [HrdWalkInEventController::class, 'updateParticipant'])->name('walk-ins.participants.update');
            Route::get('walk-ins/{walkIn}/checkin-qr', [HrdWalkInEventController::class, 'checkinQr'])->name('walk-ins.checkin-qr');
        });
    });

    // Employee self LMS
    Route::get('my-training', [MyTrainingController::class,'index'])->name('my-training.index');
    Route::get('my-training/programs/{program}/materials/{material}', [MyTrainingController::class, 'showMaterial'])->name('my-training.materials.show');
    Route::post('my-training/programs/{program}/materials/{material}/start', [MyTrainingController::class, 'startMaterial'])->name('my-training.materials.start');
    Route::post('my-training/programs/{program}/materials/{material}/complete', [MyTrainingController::class, 'completeMaterial'])->name('my-training.materials.complete');
    Route::get('my-training/programs/{program}/materials/{material}/{purpose}', [TrainingAssessmentController::class, 'show'])->whereIn('purpose', ['pretest', 'posttest'])->name('training-assessments.show');
    Route::post('my-training/programs/{program}/materials/{material}/{purpose}', [TrainingAssessmentController::class, 'submit'])->whereIn('purpose', ['pretest', 'posttest'])->name('training-assessments.submit');
    Route::post('my-training/events/{event}/register', [MyTrainingController::class, 'registerEvent'])->name('my-training.events.register');
    Route::post('my-training/events/{event}/check-in', [MyTrainingController::class, 'checkInEvent'])->name('my-training.events.check-in');
    Route::post('my-training/trainer-application', [TrainingTrainerController::class, 'apply'])->name('my-training.trainer-application.store');

    // Employee self-service: export kehadiran (semua user login)
    Route::prefix('my')->name('employee.')->group(function () {
        Route::get('/attendance-export', [AttendanceExportController::class, 'index'])
            ->name('attendance.export.index');
        Route::post('/attendance-export/download', [AttendanceExportController::class, 'export'])
            ->name('attendance.export.download');
    });

    Route::prefix('trainer')->name('trainer.')->group(function () {
        Route::get('events', [TrainerTrainingEventController::class, 'index'])->name('events.index');
        Route::get('events/{event}', [TrainerTrainingEventController::class, 'show'])->name('events.show');
        Route::patch('events/{event}/participants/{participant}', [TrainerTrainingEventController::class, 'updateParticipant'])->name('events.participants.update');
    });

    // Appraisal (appraiser can fill)
    Route::get('appraisals/my', [AppraisalController::class,'my'])->name('appraisals.my');
    Route::get('appraisals/{appraisal}', [AppraisalController::class,'show'])->name('appraisals.show');
    Route::post('appraisals/{appraisal}/submit', [AppraisalController::class,'submit'])->name('appraisals.submit');

    // Admin/HRD/Manager area
    Route::middleware(['role:admin,hrd,manager'])->group(function () {

        // Master
        Route::resource('training-materials', TrainingMaterialController::class);
        Route::resource('training-programs', TrainingProgramController::class);
        Route::resource('training-events', TrainingEventController::class)->except(['destroy']);
        Route::post('training-events/{training_event}/participants', [TrainingEventController::class, 'inviteParticipants'])->name('training-events.participants.invite');
        Route::patch('training-events/{training_event}/participants/{participant}', [TrainingEventController::class, 'updateParticipant'])->name('training-events.participants.update');
        Route::resource('training-trainers', TrainingTrainerController::class)->only(['index', 'store', 'update']);

        // Recruitment
        Route::resource('candidates', CandidateController::class);
        Route::post('candidates/{candidate}/accept', [CandidateController::class,'accept'])->name('candidates.accept');
        Route::post('candidates/{candidate}/reject', [CandidateController::class,'reject'])->name('candidates.reject');
        Route::post('candidates/{candidate}/block', [CandidateController::class,'block'])->name('candidates.block');
        Route::get('/hrd/candidates/{candidate}/preview-export', [CandidateController::class,'previewPage'])->middleware('role:admin,hrd')->name('candidates.export-preview');
        Route::get('/hrd/candidates/{candidate}/preview-pdf', [CandidateController::class,'preview'])->middleware('role:admin,hrd')->name('candidates.preview-pdf');
        Route::get('/hrd/candidates/{candidate}/export-pdf', [CandidateController::class,'exportPdf'])->middleware('role:admin,hrd')->name('candidates.export-pdf');
        Route::get('/hrd/candidates/{candidate}/export-cv', [CandidateController::class,'downloadOriginalCv'])->middleware('role:admin,hrd')->name('candidates.export-cv');
        Route::get('/hrd/candidates/{candidate}/export-package', [CandidateController::class,'downloadPackage'])->middleware('role:admin,hrd')->name('candidates.export-package');
        Route::post('/hrd/candidates/bulk-activate-tests', [CandidateController::class,'bulkActivateTests'])->middleware('role:admin,hrd')->name('candidates.bulk-activate-tests');
        Route::post('/hrd/candidates/bulk-activate', [CandidateController::class,'bulkActivate'])->middleware('role:admin,hrd')->name('candidates.bulk-activate');

        // Offering
        Route::get('offering/{employee}/preview', [OfferingController::class,'preview'])->name('offering.preview');
        Route::post('offering/{employee}/generate', [OfferingController::class,'generate'])->name('offering.generate');
        Route::get('documents/{generatedDocument}', [OfferingController::class,'download'])->name('documents.download');

        // Appraisal master + approval + print
        Route::resource('appraisal-periods', AppraisalPeriodController::class);
        Route::resource('appraisal-indicators', AppraisalIndicatorController::class);

        Route::get('appraisals', [AppraisalController::class,'index'])->name('appraisals.index');
        Route::post('appraisals/{appraisal}/approve', [AppraisalController::class,'approve'])->name('appraisals.approve');
        Route::post('appraisals/{appraisal}/assign', [AppraisalController::class,'assign'])->name('appraisals.assign');
        Route::get('appraisals/{appraisal}/print', [AppraisalController::class,'print'])->name('appraisals.print');

        // Inbox notifikasi HR (in-app)
        Route::get('/hr-notifications', [HrNotificationController::class, 'index'])->name('hr-notifications.index');
        Route::get('/hr-notifications/preview', [HrNotificationController::class, 'preview'])->name('hr-notifications.preview');
        Route::get('/hr-notifications/unread-count', [HrNotificationController::class, 'unreadCount'])->name('hr-notifications.unread-count');
        Route::post('/hr-notifications/read-all', [HrNotificationController::class, 'markAllRead'])->name('hr-notifications.readAll');
        Route::post('/hr-notifications/{id}/read', [HrNotificationController::class, 'markRead'])->name('hr-notifications.read');
    });

    // ── Finance Module ────────────────────────────────────────────────────────
    Route::middleware(['permission:finance.view'])
        ->prefix('finance')
        ->name('finance.')
        ->group(function () {
            Route::get('/', fn () => redirect()->route('finance.bpjs.index'))->name('home');

            // Mandiri Template Export
            Route::get('/export/mandiri', [\App\Http\Controllers\Finance\PayrollExportController::class, 'exportMandiriTemplate'])
                ->name('export.mandiri');

            // Total Gaji per Brand Export
            Route::get('/export/total-gaji', [\App\Http\Controllers\Finance\PayrollExportController::class, 'exportTotalGaji'])
                ->name('export.total-gaji');

            // Payroll Import
            Route::get('/import', [PayrollImportController::class, 'index'])->name('import.index');
            Route::get('/import/create', [PayrollImportController::class, 'create'])->name('import.create')->middleware('permission:finance.import');
            Route::post('/import', [PayrollImportController::class, 'store'])->name('import.store')->middleware('permission:finance.import');
            Route::delete('/import/{session}', [PayrollImportController::class, 'destroy'])->name('import.destroy')->middleware('permission:finance.import');
            Route::get('/import/{session}', [PayrollImportController::class, 'show'])->name('import.show');
            Route::post('/import/{session}/rows/{row}/action', [PayrollImportController::class, 'confirmRow'])->name('import.row.action')->middleware('permission:finance.import.review');
            Route::post('/import/{session}/confirm-all', [PayrollImportController::class, 'confirmAll'])->name('import.confirm-all')->middleware('permission:finance.import.review');

            // Annual Summary
            Route::get('/annual-summary', [AnnualSummaryController::class, 'index'])
                ->name('annual-summary.index')
                ->middleware('permission:finance.bpjs.view');
            Route::get('/annual-summary/create', [AnnualSummaryController::class, 'create'])
                ->name('annual-summary.create')
                ->middleware('permission:finance.import');
            Route::post('/annual-summary', [AnnualSummaryController::class, 'store'])
                ->name('annual-summary.store')
                ->middleware('permission:finance.import');
            Route::get('/annual-summary/export', [AnnualSummaryExportController::class, 'export'])
                ->name('annual-summary.export')
                ->middleware('permission:finance.bpjs.view');
            Route::get('/annual-summary/export-detail', [AnnualSummaryController::class, 'exportDetail'])
                ->name('annual-summary.export-detail')
                ->middleware('permission:finance.bpjs.view');
            Route::get('/annual-summary/detail-view', [AnnualSummaryController::class, 'detailView'])
                ->name('annual-summary.detail-view')
                ->middleware('permission:finance.bpjs.view');
            Route::get('/annual-summary/detail/{noKomp}/{periode}', [AnnualSummaryController::class, 'detailByOutlet'])
                ->name('annual-summary.detail')
                ->middleware('permission:finance.bpjs.view');

            // Slip Gaji
            Route::get('/slip-gaji', [SlipGajiController::class, 'index'])
                ->name('slip-gaji.index')
                ->middleware('permission:finance.bpjs.view');
            Route::get('/slip-gaji/{periode}/{employeeId}', [SlipGajiController::class, 'pdf'])
                ->name('slip-gaji.pdf')
                ->middleware('permission:finance.bpjs.view')
                ->whereNumber('employeeId');

            // BPJS Dashboard
            Route::get('/bpjs', [BpjsDashboardController::class, 'index'])->name('bpjs.index');
            Route::post('/bpjs/{summary}/payment', [BpjsDashboardController::class, 'updatePaymentStatus'])->name('bpjs.payment.update')->middleware('permission:finance.bpjs.manage');

            // BPJS Tarif per Regional
            Route::middleware(['permission:finance.bpjs.rates.manage'])
                ->prefix('bpjs-rates')
                ->name('bpjs-rates.')
                ->group(function () {
                    Route::get('/', [BpjsRateConfigController::class, 'index'])->name('index');
                    Route::get('/create', [BpjsRateConfigController::class, 'create'])->name('create');
                    Route::post('/', [BpjsRateConfigController::class, 'store'])->name('store');
                    Route::get('/{bpjsRate}/edit', [BpjsRateConfigController::class, 'edit'])->name('edit');
                    Route::put('/{bpjsRate}', [BpjsRateConfigController::class, 'update'])->name('update');
                    Route::delete('/{bpjsRate}', [BpjsRateConfigController::class, 'destroy'])->name('destroy');
                });

            // BPJS Akun Legal
            Route::middleware(['permission:finance.bpjs.legal.manage'])
                ->prefix('bpjs-legal')
                ->name('bpjs-legal.')
                ->group(function () {
                    Route::get('/', [BpjsLegalAccountController::class, 'index'])->name('index');
                    Route::get('/create', [BpjsLegalAccountController::class, 'create'])->name('create');
                    Route::post('/', [BpjsLegalAccountController::class, 'store'])->name('store');
                    Route::get('/{bpjsLegal}/edit', [BpjsLegalAccountController::class, 'edit'])->name('edit');
                    Route::put('/{bpjsLegal}', [BpjsLegalAccountController::class, 'update'])->name('update');
                    Route::delete('/{bpjsLegal}', [BpjsLegalAccountController::class, 'destroy'])->name('destroy');
                });

            // BPJS Assignments (Master — Sprint I)
            Route::middleware(['permission:finance.bpjs.view'])
                ->prefix('bpjs-assignments')
                ->name('bpjs-assignments.')
                ->group(function () {
                    Route::get('/search-employee',
                        [\App\Http\Controllers\MasterBpjs\BpjsAssignmentController::class, 'searchEmployee'])
                        ->name('search-employee');
                    Route::get('/template',
                        [\App\Http\Controllers\MasterBpjs\BpjsAssignmentController::class, 'downloadTemplate'])
                        ->name('template');
                    Route::post('/import',
                        [\App\Http\Controllers\MasterBpjs\BpjsAssignmentController::class, 'import'])
                        ->name('import');
                    Route::get('/cross-billing',
                        [\App\Http\Controllers\MasterBpjs\BpjsAssignmentController::class, 'crossBilling'])
                        ->name('cross-billing');
                    Route::get('/cross-billing-outlet',
                        [\App\Http\Controllers\MasterBpjs\BpjsAssignmentController::class, 'crossBillingByOutlet'])
                        ->name('cross-billing-outlet');
                    Route::get('/',
                        [\App\Http\Controllers\MasterBpjs\BpjsAssignmentController::class, 'index'])
                        ->name('index');
                    Route::post('/',
                        [\App\Http\Controllers\MasterBpjs\BpjsAssignmentController::class, 'store'])
                        ->name('store');
                    Route::put('/{assignment}',
                        [\App\Http\Controllers\MasterBpjs\BpjsAssignmentController::class, 'update'])
                        ->name('update');
                    Route::delete('/{assignment}',
                        [\App\Http\Controllers\MasterBpjs\BpjsAssignmentController::class, 'destroy'])
                        ->name('destroy');
                });

            // Mapping Departemen → Kategori
            Route::prefix('department-mappings')
                ->name('department-mappings.')
                ->group(function () {
                    Route::get('/', [DepartmentCategoryMappingController::class, 'index'])->name('index');
                    Route::get('/create', [DepartmentCategoryMappingController::class, 'create'])->name('create');
                    Route::post('/', [DepartmentCategoryMappingController::class, 'store'])->name('store');
                    Route::get('/{departmentMapping}/edit', [DepartmentCategoryMappingController::class, 'edit'])->name('edit');
                    Route::put('/{departmentMapping}', [DepartmentCategoryMappingController::class, 'update'])->name('update');
                    Route::patch('/{departmentMapping}/toggle', [DepartmentCategoryMappingController::class, 'toggle'])->name('toggle');
                    Route::delete('/{departmentMapping}', [DepartmentCategoryMappingController::class, 'destroy'])->name('destroy');
                });

            // Finance Settings
            Route::get('/settings', [FinanceSettingController::class, 'index'])->name('settings.index');
            Route::post('/settings', [FinanceSettingController::class, 'update'])
                ->name('settings.update')
                ->middleware('permission:finance.import');

            // BPJS Reconciliation
            Route::prefix('bpjs-reconciliation')
                ->name('bpjs-reconciliation.')
                ->group(function () {
                    Route::get('/', [BpjsReconciliationController::class, 'index'])
                        ->name('index')->middleware('permission:finance.bpjs.view');
                    Route::get('/create', [BpjsReconciliationController::class, 'create'])
                        ->name('create')->middleware('permission:finance.import');
                    Route::post('/', [BpjsReconciliationController::class, 'store'])
                        ->name('store')->middleware('permission:finance.import');
                    Route::get('/{bpjsReconciliation}', [BpjsReconciliationController::class, 'show'])
                        ->name('show')->middleware('permission:finance.bpjs.view');
                    Route::post('/{bpjsReconciliation}/reconcile', [BpjsReconciliationController::class, 'reconcile'])
                        ->name('reconcile')->middleware('permission:finance.import');
                    Route::post('/{bpjsReconciliation}/rematch', [BpjsReconciliationController::class, 'rematch'])
                        ->name('rematch')->middleware('permission:finance.import');
                    Route::post('/{id}/cleanup', [BpjsReconciliationController::class, 'cleanupMismatches'])
                        ->name('cleanup')->middleware('permission:finance.import');
                    Route::get('/{bpjsReconciliation}/dashboard', [BpjsReconciliationController::class, 'dashboard'])
                        ->name('dashboard')->middleware('permission:finance.bpjs.view');
                    Route::post('/rows/{row}/confirm', [BpjsReconciliationController::class, 'confirmMatch'])
                        ->name('rows.confirm')->middleware('permission:finance.import');
                    Route::get('/{bill}/review', [BpjsReconciliationController::class, 'review'])
                        ->name('review')->middleware('permission:finance.import');
                    Route::post('/{bill}/confirm-review', [BpjsReconciliationController::class, 'confirmReview'])
                        ->name('confirm-review')->middleware('permission:finance.import');
                    Route::patch('/rows/{row}/update-nama', [BpjsReconciliationController::class, 'updateRowName'])
                        ->name('rows.update-nama')->middleware('permission:finance.import');
                    Route::patch('/rows/{row}/update-nik', [BpjsReconciliationController::class, 'updateRowNik'])
                        ->name('rows.update-nik')->middleware('permission:finance.import');
                    Route::post('/rows/{row}/swap-nik', [BpjsReconciliationController::class, 'swapRowNik'])
                        ->name('rows.swap-nik')->middleware('permission:finance.import');
                    Route::post('/rows/{row}/merge-up', [BpjsReconciliationController::class, 'mergeRowUp'])
                        ->name('rows.merge-up')->middleware('permission:finance.import');
                    Route::post('/{bill}/reparse', [BpjsReconciliationController::class, 'reparse'])
                        ->name('reparse')->middleware('permission:finance.import');
                    Route::get('/{bill}/cross-billing', [BpjsReconciliationController::class, 'crossBilling'])
                        ->name('cross-billing')->middleware('permission:finance.bpjs.view');
                    Route::get('/{bill}/cross-billing/export', [BpjsReconciliationController::class, 'exportCrossBilling'])
                        ->name('cross-billing.export')->middleware('permission:finance.bpjs.view');
                    Route::delete('/{id}', [BpjsReconciliationController::class, 'destroy'])
                        ->name('destroy')->middleware('permission:finance.import');
                });

            // Payroll vs BPJS Reconciliation
            Route::prefix('payroll-bpjs-reconciliation')
                ->name('payroll-bpjs-reconciliation.')
                ->group(function () {
                    Route::get('/', [PayrollBpjsReconciliationController::class, 'index'])
                        ->name('index')->middleware('permission:finance.bpjs.view');
                    Route::get('/export', [PayrollBpjsReconciliationController::class, 'export'])
                        ->name('export')->middleware('permission:finance.bpjs.view');
                });
        });
});

// ── Master Lanjutan (Group Perusahaan / Entitas Legal / Regional) ─────────────
Route::middleware(['auth'])
    ->prefix('master')
    ->name('master.')
    ->group(function () {

        Route::middleware(['permission:master.company_groups.manage'])
            ->prefix('company-groups')
            ->name('company-groups.')
            ->group(function () {
                Route::get('/',        [CompanyGroupController::class, 'index'])->name('index');
                Route::get('/create',  [CompanyGroupController::class, 'create'])->name('create');
                Route::post('/',       [CompanyGroupController::class, 'store'])->name('store');
                // Static slug routes BEFORE /{companyGroup} to avoid param conflict
                Route::get('/import',  [CompanyGroupController::class, 'importForm'])->name('import.form');
                Route::post('/import', [CompanyGroupController::class, 'importStore'])->name('import.store');
                Route::get('/template',[CompanyGroupController::class, 'downloadTemplate'])->name('template');
                // Param routes
                Route::get('/{companyGroup}/edit',    [CompanyGroupController::class, 'edit'])->name('edit');
                Route::put('/{companyGroup}',         [CompanyGroupController::class, 'update'])->name('update');
                Route::patch('/{companyGroup}/toggle',[CompanyGroupController::class, 'toggle'])->name('toggle');
                Route::delete('/{companyGroup}',      [CompanyGroupController::class, 'destroy'])->name('destroy');
            });

        Route::middleware(['permission:master.legal_entities.manage'])
            ->prefix('legal-entities')
            ->name('legal-entities.')
            ->group(function () {
                Route::get('/', [LegalEntityController::class, 'index'])->name('index');
                Route::get('/create', [LegalEntityController::class, 'create'])->name('create');
                Route::post('/', [LegalEntityController::class, 'store'])->name('store');
                Route::get('/{legalEntity}/edit', [LegalEntityController::class, 'edit'])->name('edit');
                Route::put('/{legalEntity}', [LegalEntityController::class, 'update'])->name('update');
                Route::delete('/{legalEntity}', [LegalEntityController::class, 'destroy'])->name('destroy');
            });

        Route::middleware(['permission:master.regions.manage'])
            ->prefix('regions')
            ->name('regions.')
            ->group(function () {
                Route::get('/', [RegionController::class, 'index'])->name('index');
                Route::get('/create', [RegionController::class, 'create'])->name('create');
                Route::post('/', [RegionController::class, 'store'])->name('store');
                Route::get('/{region}/edit', [RegionController::class, 'edit'])->name('edit');
                Route::put('/{region}', [RegionController::class, 'update'])->name('update');
                Route::patch('/{region}/toggle', [RegionController::class, 'toggle'])->name('toggle');
                Route::delete('/{region}', [RegionController::class, 'destroy'])->name('destroy');
            });

        // Outlets live at /outlets (legacy), redirect /master/outlets/* → /outlets/*
        Route::get('/outlets/{any?}', function (string $any = '') {
            return redirect('/outlets' . ($any !== '' ? '/' . $any : ''), 301);
        })->where('any', '.*');
    });

/**
 * PENTING: ini yang membuat route login/register/logout tersedia
 */
require __DIR__.'/auth.php';

/**
 * Modul tambahan master data + appraisal assignment
 */
require base_path('routes/hr_suite_v6.php');
