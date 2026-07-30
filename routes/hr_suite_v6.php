<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MyTrainingController;
use App\Http\Controllers\AppraisalController;
use App\Http\Controllers\AppraisalAssignmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\TrainingMaterialController;
use App\Http\Controllers\CandidateController;
use App\Http\Controllers\OfferingController;
use App\Http\Controllers\AppraisalPeriodController;
use App\Http\Controllers\AppraisalCriteriaTemplateController;
use App\Http\Controllers\AppraisalIndicatorController;
use App\Http\Controllers\AppraisalWeightConfigController;
use App\Http\Controllers\HrNotificationController;
use App\Http\Controllers\ApplicationFormController;
use App\Http\Controllers\HrApplicantController;
use App\Http\Controllers\AppSettingController;
use App\Http\Controllers\ApiDocumentationController;
use App\Http\Controllers\ApplicantTestController;
use App\Http\Controllers\ProbationOnboardingController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\EmployeeProfileController;
use App\Http\Controllers\TrainingAssessmentController;
use App\Http\Controllers\Hrd\AttendanceReportController;
use App\Http\Controllers\Hrd\FormBuilderController;
use App\Http\Controllers\Hrd\IqImportController;
use App\Http\Controllers\Hrd\DiscImportController;
use App\Http\Controllers\Hrd\ChoiceImportController;
use App\Http\Controllers\Hrd\PassedCandidateController;
use App\Http\Controllers\Hrd\ContractTemplateController;
use App\Http\Controllers\Hrd\DailyWorkerContractController as HrdDailyWorkerContractController;
use App\Http\Controllers\Hrd\ProbationDataVerificationController;
use App\Http\Controllers\Hrd\TrainingProgramController;
use App\Http\Controllers\Hrd\TrainingEventController;
use App\Http\Controllers\Hrd\TrainingTrainerController;
use App\Http\Controllers\Hrd\RecruitmentRetentionSettingController;
use App\Http\Controllers\Hrd\UserRoleManagementController;
use App\Http\Controllers\Hrd\RolePermissionController;
use App\Http\Controllers\Trainer\TrainingEventController as TrainerTrainingEventController;
use App\Http\Controllers\Master\DepartmentController;
use App\Http\Controllers\Master\PositionController;
use App\Http\Controllers\Master\OutletController;
use App\Http\Controllers\Applicant\DailyWorkerContractController as ApplicantDailyWorkerContractController;
use App\Http\Controllers\ChangelogController;
use App\Http\Controllers\Recruitment\WalkinController as RecruitmentWalkinController;

/*
|--------------------------------------------------------------------------
| HR Suite Routes (v9.1)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/application-form', [ApplicationFormController::class, 'edit'])->name('application-form.edit');
    Route::get('/application-form/session-ping', [ApplicationFormController::class, 'sessionPing'])->name('application-form.session-ping');
    Route::post('/application-form/upload-temp', [ApplicationFormController::class, 'uploadTemporaryDocument'])->name('application-form.upload-temp');
    Route::post('/application-form', [ApplicationFormController::class, 'update'])->name('application-form.update');

    Route::get('/my-tests', [ApplicantTestController::class, 'index'])->name('applicant.tests.index');
    Route::get('/my-tests/{assignment}', [ApplicantTestController::class, 'show'])->name('applicant.tests.show');
    Route::post('/my-tests/{assignment}/start', [ApplicantTestController::class, 'start'])->name('applicant.tests.start');
    Route::post('/my-tests/{assignment}/submit', [ApplicantTestController::class, 'submit'])->name('applicant.tests.submit');

    Route::get('/my-training', [MyTrainingController::class, 'index'])->name('my-training.index');
    Route::get('/my-training/programs/{program}/materials/{material}', [MyTrainingController::class, 'showMaterial'])->name('my-training.materials.show');
    Route::post('/my-training/programs/{program}/materials/{material}/start', [MyTrainingController::class, 'startMaterial'])->name('my-training.materials.start');
    Route::post('/my-training/programs/{program}/materials/{material}/complete', [MyTrainingController::class, 'completeMaterial'])->name('my-training.materials.complete');
    Route::get('/my-training/programs/{program}/materials/{material}/{purpose}', [TrainingAssessmentController::class, 'show'])->whereIn('purpose', ['pretest', 'posttest'])->name('training-assessments.show');
    Route::post('/my-training/programs/{program}/materials/{material}/{purpose}', [TrainingAssessmentController::class, 'submit'])->whereIn('purpose', ['pretest', 'posttest'])->name('training-assessments.submit');
    Route::post('/my-training/events/{event}/register', [MyTrainingController::class, 'registerEvent'])->name('my-training.events.register');
    Route::post('/my-training/events/{event}/check-in', [MyTrainingController::class, 'checkInEvent'])->name('my-training.events.check-in');
    Route::post('/my-training/trainer-application', [TrainingTrainerController::class, 'apply'])->name('my-training.trainer-application.store');

    Route::prefix('trainer')->name('trainer.')->group(function () {
        Route::get('/events', [TrainerTrainingEventController::class, 'index'])->name('events.index');
        Route::get('/events/{event}', [TrainerTrainingEventController::class, 'show'])->name('events.show');
        Route::patch('/events/{event}/participants/{participant}', [TrainerTrainingEventController::class, 'updateParticipant'])->name('events.participants.update');
    });

    Route::get('/my-profile', [EmployeeProfileController::class, 'show'])->name('employee-profile.show');
    Route::get('/my-profile/edit', [EmployeeProfileController::class, 'edit'])->name('employee-profile.edit');
    Route::post('/my-profile', [EmployeeProfileController::class, 'update'])->name('employee-profile.update');

    Route::get('/probation-onboarding', [ProbationOnboardingController::class, 'edit'])->name('probation-onboarding.edit');
    Route::post('/probation-onboarding', [ProbationOnboardingController::class, 'update'])->name('probation-onboarding.update');

    Route::get('/changelog', [ChangelogController::class, 'index'])
        ->name('changelog.index')
        ->middleware('role:admin,manager,finance');

    // Walk-In Interview — Fase 1 (Recruitment module)
    Route::middleware(['role:admin,hrd,manager'])->prefix('recruitment/walkin')->name('walkin.')->group(function () {
        Route::get('/',                                        [RecruitmentWalkinController::class, 'index'])->name('index');
        Route::get('/create',                                  [RecruitmentWalkinController::class, 'create'])->name('create');
        Route::post('/',                                       [RecruitmentWalkinController::class, 'store'])->name('store');
        Route::post('/checkin/{candidateId}',                  [RecruitmentWalkinController::class, 'checkIn'])->name('checkin')->whereNumber('candidateId');
        // Fase 2 — kandidat detail & scoring
        Route::get('/candidate/{candidateId}',                 [RecruitmentWalkinController::class, 'showCandidate'])->name('candidate.show')->whereNumber('candidateId');
        Route::post('/candidate/{candidateId}/interview',      [RecruitmentWalkinController::class, 'updateInterview'])->name('candidate.interview')->whereNumber('candidateId');
        Route::post('/candidate/{candidateId}/test',           [RecruitmentWalkinController::class, 'updateTest'])->name('candidate.test')->whereNumber('candidateId');
        Route::post('/candidate/{candidateId}/plotting',       [RecruitmentWalkinController::class, 'updatePlotting'])->name('candidate.plotting')->whereNumber('candidateId');
        Route::get('/{id}',                                    [RecruitmentWalkinController::class, 'show'])->name('show')->whereNumber('id');
        Route::get('/{id}/dashboard',                          [RecruitmentWalkinController::class, 'dashboard'])->name('dashboard')->whereNumber('id');
        Route::post('/{id}/my-link',                           [RecruitmentWalkinController::class, 'myLink'])->name('my-link')->whereNumber('id');
    });

    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn'])->name('attendance.check-in');
    Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut'])->name('attendance.check-out');

    Route::get('/hr-notifications', [HrNotificationController::class, 'index'])->name('hr-notifications.index');
    Route::post('/hr-notifications/{id}/read', [HrNotificationController::class, 'markRead'])->name('hr-notifications.read');
    Route::post('/hr-notifications/read-all', [HrNotificationController::class, 'markAllRead'])->name('hr-notifications.readAll');

    Route::get('/appraisals/my', [AppraisalController::class, 'my'])->name('appraisals.my');
    Route::get('/appraisals/evaluator', [AppraisalController::class, 'evaluator'])->name('appraisals.evaluator');
    Route::get('/appraisals/panduan-penilaian', [AppraisalController::class, 'guide'])->name('appraisals.guide');
    Route::get('/appraisals/{appraisal}', [AppraisalController::class, 'show'])->whereNumber('appraisal')->name('appraisals.show');
    Route::post('/appraisals/{appraisal}/submit', [AppraisalController::class, 'submit'])->whereNumber('appraisal')->name('appraisals.submit');

    Route::middleware(['role:admin,hrd,manager'])->group(function () {
        Route::get('/settings', [AppSettingController::class, 'index'])->name('settings.index');
        Route::get('/settings/general', [AppSettingController::class, 'general'])->name('settings.general');
        Route::post('/settings/general', [AppSettingController::class, 'updateGeneral'])->name('settings.update');
        Route::get('/settings/email', [AppSettingController::class, 'email'])->name('settings.email');
        Route::post('/settings/email', [AppSettingController::class, 'updateEmail'])->name('settings.email.update');
        Route::post('/settings/email/test', [AppSettingController::class, 'testEmail'])->name('settings.email.test');
        Route::get('/settings/notifications', [AppSettingController::class, 'notifications'])->name('settings.notifications');
        Route::post('/settings/notifications', [AppSettingController::class, 'updateNotifications'])->name('settings.notifications.update');
        Route::get('/settings/api-docs', [ApiDocumentationController::class, 'index'])->middleware('superadmin')->name('settings.api-docs');
        Route::get('/hrd/settings/recruitment-retention', [RecruitmentRetentionSettingController::class, 'edit'])->middleware('role:admin,hrd')->name('settings.recruitment-retention.edit');
        Route::post('/hrd/settings/recruitment-retention', [RecruitmentRetentionSettingController::class, 'update'])->middleware('role:admin,hrd')->name('settings.recruitment-retention.update');
        Route::prefix('hrd')->name('hrd.')->group(function () {
            Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('role:admin,hrd,manager')->name('dashboard');
            Route::get('/users', [UserRoleManagementController::class, 'index'])->middleware('permission:settings.manage,users_roles.manage')->name('users.index');
            Route::patch('/users/{user}/role', [UserRoleManagementController::class, 'updateRole'])->middleware('permission:settings.manage,users_roles.manage')->name('users.role.update');
            Route::get('/roles', [RolePermissionController::class, 'index'])->middleware('permission:settings.manage,users_roles.manage')->name('roles.index');
            Route::get('/roles/create', [RolePermissionController::class, 'create'])->middleware('permission:settings.manage,users_roles.manage')->name('roles.create');
            Route::post('/roles', [RolePermissionController::class, 'store'])->middleware('permission:settings.manage,users_roles.manage')->name('roles.store');
            Route::get('/roles/{role}/edit', [RolePermissionController::class, 'edit'])->middleware('permission:settings.manage,users_roles.manage')->name('roles.edit');
            Route::put('/roles/{role}', [RolePermissionController::class, 'update'])->middleware('permission:settings.manage,users_roles.manage')->name('roles.update');
            Route::delete('/roles/{role}', [RolePermissionController::class, 'destroy'])->middleware('permission:settings.manage,users_roles.manage')->name('roles.destroy');
            Route::get('/roles/{role}/permissions', [RolePermissionController::class, 'permissions'])->middleware('permission:settings.manage,users_roles.manage')->name('roles.permissions');
            Route::put('/roles/{role}/permissions', [RolePermissionController::class, 'updatePermissions'])->middleware('permission:settings.manage,users_roles.manage')->name('roles.permissions.update');

            // Backward-compatible endpoint lama supaya tidak memutus bookmark existing.
            Route::get('/settings/user-roles', [UserRoleManagementController::class, 'index'])->middleware('permission:settings.manage,users_roles.manage')->name('settings.user-roles.index');
            Route::put('/settings/user-roles/{user}', [UserRoleManagementController::class, 'updateRole'])->middleware('permission:settings.manage,users_roles.manage')->name('settings.user-roles.update');
        });

        Route::get('departments/template', [DepartmentController::class, 'template'])->name('departments.template');
        Route::get('departments/export', [DepartmentController::class, 'export'])->name('departments.export');
        Route::post('departments/import', [DepartmentController::class, 'import'])->name('departments.import');
        Route::resource('departments', DepartmentController::class)->except(['show']);
        Route::prefix('positions')->name('positions.')->group(function () {
            Route::get('/', [PositionController::class, 'departmentIndex'])->name('index');
            Route::post('/departments', [PositionController::class, 'storeDepartment'])->name('departments.store');
            Route::put('/departments/{department}', [PositionController::class, 'updateDepartment'])->name('departments.update');
            Route::delete('/departments/{department}', [PositionController::class, 'destroyDepartment'])->name('departments.destroy');
            Route::post('/', [PositionController::class, 'store'])->name('store');
            Route::get('/org-chart', [PositionController::class, 'orgChart'])->name('org-chart');
            Route::get('/{position}/employees', [PositionController::class, 'positionEmployees'])->name('employees');
            Route::put('/{position}', [PositionController::class, 'update'])->name('update');
            Route::delete('/{position}', [PositionController::class, 'destroy'])->name('destroy');
            Route::get('/template', [PositionController::class, 'downloadTemplate'])->name('template');
            Route::post('/import', [PositionController::class, 'import'])->name('import');
        });
        Route::get('outlets/template', [OutletController::class, 'template'])->name('outlets.template');
        Route::get('outlets/export', [OutletController::class, 'export'])->name('outlets.export');
        Route::post('outlets/import', [OutletController::class, 'import'])->name('outlets.import');
        Route::post('outlets/import/confirm', [OutletController::class, 'confirmImport'])->name('outlets.import.confirm');
        Route::post('outlets/import/cancel', [OutletController::class, 'cancelImport'])->name('outlets.import.cancel');
        Route::post('outlets/{outlet}/permits', [OutletController::class, 'storePermit'])->name('outlets.permits.store');
        Route::put('outlets/{outlet}/permits/{permit}', [OutletController::class, 'updatePermit'])->name('outlets.permits.update');
        Route::delete('outlets/{outlet}/permits/{permit}', [OutletController::class, 'destroyPermit'])->name('outlets.permits.destroy');
        Route::delete('outlets/{outlet}/permits/{permit}/attachments/{attachment}', [OutletController::class, 'destroyPermitAttachment'])->name('outlets.permits.attachments.destroy');
        Route::resource('outlets', OutletController::class)->except(['show']);

        Route::get('employees/export', [EmployeeController::class, 'export'])->name('employees.export');
        Route::get('employees/export/hris', [EmployeeController::class, 'exportHris'])->name('employees.export.hris');
        Route::get('employees/import/template', [\App\Http\Controllers\EmployeeImportController::class, 'downloadTemplate'])->name('employees.import.template');
        Route::post('employees/import', [\App\Http\Controllers\EmployeeImportController::class, 'store'])->name('employees.import.store');
        Route::patch('employees/{employee}/promote', [EmployeeController::class, 'promote'])->name('employees.promote');

        // HR XLS Import (Sprint G) — must be BEFORE Route::resource to avoid {employee} wildcard capture
        Route::prefix('employees/hr-import')
            ->name('hr.employee-import.')
            ->group(function () {
                Route::get('/',                       [\App\Http\Controllers\HR\EmployeeImportController::class, 'index'])->name('index');
                Route::get('/create',                 [\App\Http\Controllers\HR\EmployeeImportController::class, 'create'])->name('create');
                Route::post('/store',                 [\App\Http\Controllers\HR\EmployeeImportController::class, 'store'])->name('store');
                Route::get('/preview/{session}',      [\App\Http\Controllers\HR\EmployeeImportController::class, 'preview'])->name('preview');
                Route::post('/confirm/{session}',     [\App\Http\Controllers\HR\EmployeeImportController::class, 'confirm'])->name('confirm');
                Route::get('/status/{session}',       [\App\Http\Controllers\HR\EmployeeImportController::class, 'status'])->name('status');
            });

        // Operational Assignments — harus sebelum Route::resource('employees')
        Route::post('employees/{employee}/assignments',
            [\App\Http\Controllers\EmployeeAssignmentController::class, 'store'])
            ->name('employees.assignments.store');
        Route::delete('employees/{employee}/assignments/{assignment}',
            [\App\Http\Controllers\EmployeeAssignmentController::class, 'destroy'])
            ->name('employees.assignments.destroy');

        // BPJS Assignments — harus sebelum Route::resource('employees') agar {employee} tidak ditangkap wildcard
        Route::resource('employees/{employee}/bpjs-assignments',
            \App\Http\Controllers\Finance\BpjsAssignmentController::class)
            ->parameters(['bpjs-assignments' => 'bpjsAssignment'])
            ->only(['index', 'store', 'update', 'destroy']);

        // Bank Accounts — harus sebelum Route::resource('employees')
        Route::post('employees/{employee}/bank-accounts',
            [\App\Http\Controllers\EmployeeBankAccountController::class, 'store'])
            ->name('employee-bank-accounts.store');
        Route::delete('employees/{employee}/bank-accounts/{bankAccount}',
            [\App\Http\Controllers\EmployeeBankAccountController::class, 'destroy'])
            ->name('employee-bank-accounts.destroy');

        Route::resource('employees', EmployeeController::class);
        Route::resource('training-materials', TrainingMaterialController::class);
        Route::resource('training-programs', TrainingProgramController::class);
        Route::resource('training-events', TrainingEventController::class)->except(['destroy']);
        Route::post('training-events/{training_event}/participants', [TrainingEventController::class, 'inviteParticipants'])->name('training-events.participants.invite');
        Route::patch('training-events/{training_event}/participants/{participant}', [TrainingEventController::class, 'updateParticipant'])->name('training-events.participants.update');
        Route::resource('training-trainers', TrainingTrainerController::class)->only(['index', 'store', 'update']);

        Route::resource('candidates', CandidateController::class);
        Route::post('candidates/{candidate}/accept', [CandidateController::class, 'accept'])->name('candidates.accept');
        Route::post('candidates/{candidate}/reject', [CandidateController::class, 'reject'])->name('candidates.reject');
        Route::post('candidates/{candidate}/block', [CandidateController::class, 'block'])->name('candidates.block');
        Route::post('candidates/{candidate}/restore', [CandidateController::class, 'restore'])->name('candidates.restore');
        Route::get('/hrd/candidates/{candidate}/preview-export', [CandidateController::class, 'previewPage'])->middleware('role:admin,hrd')->name('candidates.export-preview');
        Route::get('/hrd/candidates/{candidate}/preview-pdf', [CandidateController::class, 'preview'])->middleware('role:admin,hrd')->name('candidates.preview-pdf');
        Route::get('/hrd/candidates/{candidate}/export-pdf', [CandidateController::class, 'exportPdf'])->middleware('role:admin,hrd')->name('candidates.export-pdf');
        Route::get('/hrd/candidates/{candidate}/export-cv', [CandidateController::class, 'downloadOriginalCv'])->middleware('role:admin,hrd')->name('candidates.export-cv');
        Route::get('/hrd/candidates/{candidate}/export-package', [CandidateController::class, 'downloadPackage'])->middleware('role:admin,hrd')->name('candidates.export-package');
        Route::post('candidates/{candidate}/assessment', [CandidateController::class, 'updateAssessment'])->middleware('role:admin,hrd')->name('candidates.assessment.update');
        Route::post('candidates/{candidate}/tests/open', [CandidateController::class, 'openTest'])->middleware('role:admin,hrd')->name('candidates.tests.open');
        Route::post('candidates/{candidate}/tests/{assignment}/lock', [CandidateController::class, 'lockTest'])->middleware('role:admin,hrd')->name('candidates.tests.lock');
        Route::post('candidates/{candidate}/tests/{assignment}/reset', [CandidateController::class, 'resetTest'])->middleware('role:admin,hrd')->name('candidates.tests.reset');
        Route::get('candidates/{candidate}/profile', [CandidateController::class, 'applicantProfile'])->middleware('role:admin,hrd')->name('candidates.profile');
        Route::post('/hrd/candidates/bulk-activate-tests', [CandidateController::class, 'bulkActivateTests'])->middleware('role:admin,hrd')->name('candidates.bulk-activate-tests');
        Route::post('/hrd/candidates/bulk-activate', [CandidateController::class, 'bulkActivate'])->middleware('role:admin,hrd')->name('candidates.bulk-activate');
        Route::post('/hrd/candidates/bulk-status', [CandidateController::class, 'bulkStatus'])->middleware('role:admin,hrd')->name('candidates.bulk-status');

        Route::get('/hrd/attendance', [AttendanceReportController::class, 'index'])->middleware('role:admin,hrd')->name('hrd.attendance.index');
        Route::get('/hrd/attendance/export-pdf', [AttendanceReportController::class, 'exportPdf'])->middleware('role:admin,hrd')->name('hrd.attendance.export-pdf');

        Route::get('/hrd/lolos-kandidat', [PassedCandidateController::class, 'index'])->middleware('role:admin,hrd')->name('hrd.passed-candidates.index');

        Route::prefix('hrd/forms')->middleware('role:admin,hrd')->name('hrd.forms.')->group(function () {
            Route::get('/', [FormBuilderController::class, 'index'])->name('index');
            Route::get('/create', [FormBuilderController::class, 'create'])->name('create');
            Route::post('/', [FormBuilderController::class, 'store'])->name('store');
            Route::get('/{form}/edit', [FormBuilderController::class, 'edit'])->name('edit');
            Route::put('/{form}', [FormBuilderController::class, 'update'])->name('update');
            Route::post('/{form}/toggle', [FormBuilderController::class, 'toggle'])->name('toggle');
            Route::post('/{form}/questions', [FormBuilderController::class, 'storeQuestion'])->name('questions.store');
            Route::put('/{form}/questions/{question}', [FormBuilderController::class, 'updateQuestion'])->name('questions.update');
            Route::post('/{form}/questions/{question}/duplicate', [FormBuilderController::class, 'duplicateQuestion'])->name('questions.duplicate');
            Route::post('/{form}/questions/{question}/move', [FormBuilderController::class, 'moveQuestion'])->name('questions.move');
            Route::delete('/{form}/questions/{question}', [FormBuilderController::class, 'destroyQuestion'])->name('questions.destroy');
            Route::post('/{form}/questions/{question}/options', [FormBuilderController::class, 'storeOption'])->name('options.store');
            Route::put('/{form}/questions/{question}/options/{option}', [FormBuilderController::class, 'updateOption'])->name('options.update');
            Route::post('/{form}/questions/{question}/options/{option}/move', [FormBuilderController::class, 'moveOption'])->name('options.move');
            Route::delete('/{form}/questions/{question}/options/{option}', [FormBuilderController::class, 'destroyOption'])->name('options.destroy');
        });

        Route::prefix('hrd/import/iq')->middleware('role:admin,hrd')->name('hrd.import.iq.')->group(function () {
            Route::get('/', [IqImportController::class, 'index'])->name('index');
            Route::get('/template.csv', [IqImportController::class, 'downloadCsv'])->name('template.csv');
            Route::get('/template.xlsx', [IqImportController::class, 'downloadXlsx'])->name('template.xlsx');
            Route::post('/', [IqImportController::class, 'store'])->name('store');
        });

        Route::prefix('hrd/import/disc')->middleware('role:admin,hrd')->name('hrd.import.disc.')->group(function () {
            Route::get('/', [DiscImportController::class, 'index'])->name('index');
            Route::get('/template.csv', [DiscImportController::class, 'downloadCsv'])->name('template.csv');
            Route::get('/template.xlsx', [DiscImportController::class, 'downloadXlsx'])->name('template.xlsx');
            Route::post('/', [DiscImportController::class, 'store'])->name('store');
        });

        Route::prefix('hrd/import/forms/{type}')->middleware('role:admin,hrd')->name('hrd.import.choice.')->group(function () {
            Route::get('/', [ChoiceImportController::class, 'index'])->name('index');
            Route::get('/template.csv', [ChoiceImportController::class, 'downloadCsv'])->name('template.csv');
            Route::get('/template.xlsx', [ChoiceImportController::class, 'downloadXlsx'])->name('template.xlsx');
            Route::post('/', [ChoiceImportController::class, 'store'])->name('store');
        });

        Route::get('offering/{employee}/preview', [OfferingController::class, 'preview'])->name('offering.preview');
        Route::post('offering/{employee}/generate', [OfferingController::class, 'generate'])->name('offering.generate');
        Route::get('documents/{generatedDocument}', [OfferingController::class, 'download'])->name('documents.download');

        Route::resource('appraisal-periods', AppraisalPeriodController::class);
        Route::resource('appraisal-indicators', AppraisalIndicatorController::class);
        Route::resource('appraisal-criteria-templates', AppraisalCriteriaTemplateController::class)->except(['show']);
        Route::post('appraisal-criteria-templates/{appraisal_criteria_template}/duplicate', [AppraisalCriteriaTemplateController::class, 'duplicate'])->name('appraisal-criteria-templates.duplicate');
        Route::get('appraisals', [AppraisalController::class, 'index'])->name('appraisals.index');
        Route::get('appraisals/report', [AppraisalController::class, 'report'])->name('appraisals.report');
        Route::get('appraisals/report/employee/{employeeId}', [AppraisalController::class, 'reportEmployee'])->name('appraisals.report-employee')->whereNumber('employeeId');
        Route::post('appraisals/report/employee/{employeeId}/signer', [AppraisalController::class, 'saveSigner'])->name('appraisals.report-employee.save-signer')->whereNumber('employeeId');
        Route::post('appraisals/report/employee/{employeeId}/sign', [AppraisalController::class, 'saveSignature'])->name('appraisals.report-employee.sign')->whereNumber('employeeId');
        Route::get('appraisals/export-report', [AppraisalController::class, 'exportReport'])->name('appraisals.export-report');
        Route::get('appraisals/export-bulk-pdf', [AppraisalController::class, 'exportBulkPdf'])->name('appraisals.export-bulk-pdf');
        Route::match(['get','post'], 'appraisals/export-employee-pdf/{employeeId}', [AppraisalController::class, 'exportEmployeePdf'])->name('appraisals.export-employee-pdf')->whereNumber('employeeId');
        Route::match(['get','post'], 'appraisals/export-employee-excel/{employeeId}', [AppraisalController::class, 'exportEmployeeReportExcel'])->name('appraisals.export-employee-excel')->whereNumber('employeeId');
        Route::get('appraisals/weight-config', [AppraisalWeightConfigController::class, 'index'])->name('appraisals.weight-config');
        Route::post('appraisals/weight-config/save', [AppraisalWeightConfigController::class, 'save'])->name('appraisals.weight-config.save');
        Route::delete('appraisals/weight-config/{id}', [AppraisalWeightConfigController::class, 'destroy'])->name('appraisals.weight-config.destroy')->whereNumber('id');
        Route::get('appraisals/assignment', [AppraisalAssignmentController::class, 'index'])->name('appraisals.assignment');
        Route::get('appraisals/assignment/search-employees', [AppraisalAssignmentController::class, 'searchEmployees'])->name('appraisals.assignment.search-employees');
        Route::get('appraisals/assignment/search-evaluators', [AppraisalAssignmentController::class, 'searchEvaluators'])->name('appraisals.assignment.search-evaluators');
        Route::post('appraisals/assignment/generate-batch', [AppraisalAssignmentController::class, 'generateBatch'])->name('appraisals.assignment.generate-batch');
        Route::post('appraisals/{appraisal}/approve', [AppraisalController::class, 'approve'])->whereNumber('appraisal')->name('appraisals.approve');
        Route::post('appraisals/{appraisal}/remind', [AppraisalController::class, 'remind'])->whereNumber('appraisal')->name('appraisals.remind');
        Route::post('appraisals/{appraisal}/extend-due-date', [AppraisalController::class, 'extendDueDate'])->whereNumber('appraisal')->name('appraisals.extend-due-date');
        Route::post('appraisals/{appraisal}/assign', [AppraisalController::class, 'assign'])->whereNumber('appraisal')->name('appraisals.assign');
        Route::get('appraisals/{appraisal}/print', [AppraisalController::class, 'print'])->whereNumber('appraisal')->name('appraisals.print');

                Route::get('/hrd/applicants', [HrApplicantController::class, 'index'])->middleware('role:admin,hrd')->name('hrd.applicants.index');
        Route::get('/hrd/applicants/{profile}', [HrApplicantController::class, 'show'])->middleware('role:admin,hrd')->name('hrd.applicants.show');
        Route::get('/hrd/applicants/{profile}/export-pdf', [HrApplicantController::class, 'exportPdf'])->middleware('role:admin,hrd')->name('hrd.applicants.export-pdf');
        Route::post('/hrd/applicants/bulk-action', [HrApplicantController::class, 'bulkAction'])->middleware('permission:any:applicants.manage,candidates.manage')->name('hrd.applicants.bulk-action');
        Route::post('/hrd/applicants/{profile}/govern', [HrApplicantController::class, 'govern'])->middleware('permission:any:applicants.manage,candidates.manage')->name('hrd.applicants.govern');
        Route::post('/hrd/applicants/{profile}/mark-reviewed', [HrApplicantController::class, 'markReviewed'])->middleware('permission:any:applicants.manage,candidates.manage')->name('hrd.applicants.reviewed');

        Route::prefix('/hrd/contract-templates')->middleware('role:admin,hrd')->name('hrd.contract-templates.')->group(function () {
            Route::get('/', [ContractTemplateController::class, 'index'])->name('index');
            Route::get('/create', [ContractTemplateController::class, 'create'])->name('create');
            Route::post('/', [ContractTemplateController::class, 'store'])->name('store');
            Route::get('/{contractTemplate}/edit', [ContractTemplateController::class, 'edit'])->name('edit');
            Route::put('/{contractTemplate}', [ContractTemplateController::class, 'update'])->name('update');
            Route::delete('/{contractTemplate}', [ContractTemplateController::class, 'destroy'])->name('destroy');
            Route::post('/{contractTemplate}/activate', [ContractTemplateController::class, 'activate'])->name('activate');
            Route::post('/{contractTemplate}/deactivate', [ContractTemplateController::class, 'deactivate'])->name('deactivate');
        });

        Route::prefix('/hrd/contracts')->middleware('role:admin,hrd')->name('hrd.contracts.')->group(function () {
            Route::get('/', [HrdDailyWorkerContractController::class, 'index'])->name('index');
            Route::post('/send', [HrdDailyWorkerContractController::class, 'send'])->name('send');
            Route::get('/{contract}', [HrdDailyWorkerContractController::class, 'show'])->name('show');
            Route::post('/{contract}/review', [HrdDailyWorkerContractController::class, 'review'])->name('review');
            Route::get('/{contract}/download', [HrdDailyWorkerContractController::class, 'downloadPdf'])->name('download');
        });

        Route::prefix('/hrd/probation-verifications')->middleware('role:admin,hrd')->name('hrd.probation-verifications.')->group(function () {
            Route::get('/', [ProbationDataVerificationController::class, 'index'])->name('index');
            Route::get('/{changeRequest}', [ProbationDataVerificationController::class, 'show'])->name('show');
            Route::post('/{changeRequest}/approve', [ProbationDataVerificationController::class, 'approve'])->name('approve');
            Route::post('/{changeRequest}/reject', [ProbationDataVerificationController::class, 'reject'])->name('reject');
        });
    });

    Route::get('/my-contracts', [ApplicantDailyWorkerContractController::class, 'index'])->name('applicant.contracts.index');
    Route::get('/my-contracts/{contract}', [ApplicantDailyWorkerContractController::class, 'show'])->name('applicant.contracts.show');
    Route::post('/my-contracts/{contract}/stamp', [ApplicantDailyWorkerContractController::class, 'storeStamp'])->name('applicant.contracts.stamp');
    Route::post('/my-contracts/{contract}/submit', [ApplicantDailyWorkerContractController::class, 'submit'])->name('applicant.contracts.submit');
    Route::get('/my-contracts/{contract}/download', [ApplicantDailyWorkerContractController::class, 'downloadPdf'])->name('applicant.contracts.download');
});
































