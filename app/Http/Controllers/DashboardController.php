<?php

namespace App\Http\Controllers;

use App\Models\ApplicantProfile;
use App\Models\AssessmentForm;
use App\Models\Candidate;
use App\Models\FormAssignment;
use App\Models\HrNotification;
use App\Models\ProfileChangeRequest;
use App\Services\EmployeeProfileCompletenessService;
use App\Services\PayrollProfileTargetService;
use App\Services\ProbationOfficialProfileReminderService;
use App\Services\ProbationReminderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index(
        Request $request,
        ProbationReminderService $reminderService,
        PayrollProfileTargetService $targetService,
        ProbationOfficialProfileReminderService $officialProfileReminderService,
        EmployeeProfileCompletenessService $completenessService
    ) {
        $user = Auth::user();

        if (in_array($user->role, ['admin', 'hrd', 'manager'])) {
            return $this->hrDashboard($request, $reminderService, $officialProfileReminderService, $completenessService);
        }

        $applicant = ApplicantProfile::where('user_id', $user->id)->first();
        $candidate = $user->resolveCandidate();
        $pendingStartAssignment = $candidate ? $this->resolvePendingStartAssignmentForCandidate($candidate) : null;

        $isProbationUser = $this->isProbationUser($user);
        $currentPayroll = $targetService->getCurrent((int) $user->id, $user->employee_id ? (int) $user->employee_id : null);

        $pendingPayrollChange = ProfileChangeRequest::query()
            ->where('user_id', $user->id)
            ->where('entity_type', ProfileChangeRequest::ENTITY_EMPLOYEE_PROFILE)
            ->where('status', ProfileChangeRequest::STATUS_PENDING)
            ->latest('id')
            ->first();

        $isComplete = $targetService->isRequiredComplete($currentPayroll);
        $isVerified = !empty($currentPayroll['payroll_verified_at']);
        $showPayrollReminderPopup = $isProbationUser && (!$isComplete || !$isVerified);

        return view('dashboard', [
            'user' => $user,
            'applicant' => $applicant,
            'pendingStartAssignment' => $pendingStartAssignment,
            'showPendingStartPopup' => $pendingStartAssignment !== null,
            'pendingStartUrl' => $pendingStartAssignment ? route('applicant.tests.show', $pendingStartAssignment) : null,
            'isProbationUser' => $isProbationUser,
            'pendingPayrollChange' => $pendingPayrollChange,
            'showPayrollReminderPopup' => $showPayrollReminderPopup,
            'probationOnboardingUrl' => route('probation-onboarding.edit'),
            'payrollIsComplete' => $isComplete,
            'payrollIsVerified' => $isVerified,
        ]);
    }

    private function hrDashboard(
        Request $request,
        ProbationReminderService $reminderService,
        ProbationOfficialProfileReminderService $officialProfileReminderService,
        EmployeeProfileCompletenessService $completenessService
    ) {
        try {
            $reminderService->generate([30, 14, 7, 1]);
            $officialProfileReminderService->generate();
        } catch (\Throwable $e) {
            Log::warning('Dashboard reminder generation skipped', [
                'message' => $e->getMessage(),
                'employees_table' => Schema::hasTable('employees'),
                'hr_notifications_table' => Schema::hasTable('hr_notifications'),
            ]);
        }

        $today = now()->toDateString();
        $end = now()->addDays(14)->toDateString();
        $employeeColumns = $this->tableColumns('employees');
        $notificationColumns = $this->tableColumns('hr_notifications');

        $upcomingProbation = collect();
        $totalEmployees = 0;
        $totalUpcomingProbation = 0;

        if ($employeeColumns !== []) {
            $employeeQuery = DB::table('employees');
            if (in_array('deleted_at', $employeeColumns, true)) {
                $employeeQuery->whereNull('deleted_at');
            }
            $totalEmployees = (clone $employeeQuery)->count();

            if (in_array('probation_end_date', $employeeColumns, true)) {
                $select = array_values(array_intersect(
                    ['id', 'full_name', 'nik', 'probation_end_date'],
                    $employeeColumns
                ));

                $probationQuery = DB::table('employees')
                    ->select($select === [] ? ['id'] : $select)
                    ->whereNotNull('probation_end_date')
                    ->whereDate('probation_end_date', '>=', $today)
                    ->whereDate('probation_end_date', '<=', $end)
                    ->orderBy('probation_end_date')
                    ->limit(10);

                if (in_array('deleted_at', $employeeColumns, true)) {
                    $probationQuery->whereNull('deleted_at');
                }

                $upcomingProbation = $probationQuery->get();

                $probationCountQuery = DB::table('employees')
                    ->whereNotNull('probation_end_date')
                    ->whereDate('probation_end_date', '>=', $today)
                    ->whereDate('probation_end_date', '<=', $end);

                if (in_array('deleted_at', $employeeColumns, true)) {
                    $probationCountQuery->whereNull('deleted_at');
                }

                $totalUpcomingProbation = $probationCountQuery->count();
            } else {
                Log::warning('Dashboard probation widgets skipped because employees.probation_end_date is missing');
            }
        } else {
            Log::warning('Dashboard employees widgets skipped because employees table is missing');
        }

        $candidatesApplied = 0;
        $candidatesAccepted = 0;

        if (Schema::hasTable('candidates') && Schema::hasColumn('candidates', 'status')) {
            $qApplied = DB::table('candidates')->where('status', 'applied');
            $qAccepted = DB::table('candidates')->where('status', 'accepted');

            if (Schema::hasColumn('candidates', 'deleted_at')) {
                $qApplied->whereNull('deleted_at');
                $qAccepted->whereNull('deleted_at');
            }

            $candidatesApplied = $qApplied->count();
            $candidatesAccepted = $qAccepted->count();
        }

        $hrReminders = collect();
        $hrUnreadCount = 0;
        if ($notificationColumns !== []) {
            $canReadReminderList = in_array('type', $notificationColumns, true)
                && in_array('id', $notificationColumns, true);

            if ($canReadReminderList) {
                $reminderQuery = HrNotification::query()
                    ->whereIn('type', ['probation_reminder', 'probation_official_profile'])
                    ->orderByDesc('id')
                    ->limit(10);

                if (in_array('user_id', $notificationColumns, true)) {
                    $reminderQuery->where(function ($query) use ($request) {
                        $query->whereNull('user_id')
                            ->orWhere('user_id', $request->user()->id);
                    });
                }

                $hrReminders = $reminderQuery->get();
            }

            if (in_array('type', $notificationColumns, true) && in_array('is_read', $notificationColumns, true)) {
                $unreadQuery = HrNotification::query()
                    ->whereIn('type', ['probation_reminder', 'probation_official_profile'])
                    ->where('is_read', false);

                if (in_array('user_id', $notificationColumns, true)) {
                    $unreadQuery->where(function ($query) use ($request) {
                        $query->whereNull('user_id')
                            ->orWhere('user_id', $request->user()->id);
                    });
                }

                $hrUnreadCount = $unreadQuery->count();
            }
        }

        try {
            $nokomSetupEmployees = $completenessService->listEmployeesNeedsNokomSetup(20);
        } catch (\Throwable $e) {
            Log::warning('Dashboard NOKOM setup widget skipped', [
                'message' => $e->getMessage(),
                'users_table' => Schema::hasTable('users'),
                'employees_table' => Schema::hasTable('employees'),
                'profile_change_requests_table' => Schema::hasTable('profile_change_requests'),
            ]);
            $nokomSetupEmployees = [];
        }

        return view('dashboard.index', [
            'upcomingProbation' => $upcomingProbation,
            'totalEmployees' => $totalEmployees,
            'totalUpcomingProbation' => $totalUpcomingProbation,
            'candidatesApplied' => $candidatesApplied,
            'candidatesAccepted' => $candidatesAccepted,
            'hrReminders' => $hrReminders,
            'hrUnreadCount' => $hrUnreadCount,
            'probationDue' => $upcomingProbation,
            'nokomSetupEmployees' => $nokomSetupEmployees,
        ]);
    }

    private function resolvePendingStartAssignmentForCandidate(Candidate $candidate): ?FormAssignment
    {
        $assignments = FormAssignment::query()
            ->with(['attempt', 'form:id,name,type,duration_minutes,is_active'])
            ->where('candidate_id', $candidate->id)
            ->whereHas('form', function ($query) {
                $query->whereIn('type', [AssessmentForm::TYPE_IQ, AssessmentForm::TYPE_DISC]);
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        return $assignments->first(fn (FormAssignment $assignment) => $this->isOpenedPendingStartAssignment($assignment));
    }

    private function isOpenedPendingStartAssignment(FormAssignment $assignment): bool
    {
        if ($assignment->status !== FormAssignment::STATUS_OPENED) {
            return false;
        }

        if ($assignment->attempt && $assignment->attempt->started_at) {
            return false;
        }

        return !$assignment->expires_at || now()->lessThanOrEqualTo($assignment->expires_at);
    }

    private function isProbationUser($user): bool
    {
        if (($user->role ?? null) === 'probation') {
            return true;
        }

        if (Schema::hasColumn('users', 'employee_status') && ($user->employee_status ?? null) === 'probation') {
            return true;
        }

        if (!Schema::hasTable('employees') || !Schema::hasColumn('employees', 'status_employment')) {
            return false;
        }

        if ((int) ($user->employee_id ?? 0) <= 0) {
            return false;
        }

        return DB::table('employees')
            ->where('id', (int) $user->employee_id)
            ->where('status_employment', 'probation')
            ->exists();
    }

    /**
     * @return array<int,string>
     */
    private function tableColumns(string $table): array
    {
        if (!Schema::hasTable($table)) {
            return [];
        }

        return Schema::getColumnListing($table);
    }
}

