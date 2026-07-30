<?php

namespace App\Providers;

use App\Models\AppSetting;
use App\Models\Candidate;
use App\Models\CandidateAssessment;
use App\Models\FormAssignment;
use App\Observers\CandidateAssessmentObserver;
use App\Observers\CandidateObserver;
use App\Observers\FormAssignmentObserver;
use App\Models\User;
use App\Support\MailConfiguration;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Bridge Laravel's Gate/can() system to the custom RBAC hasPermission() method.
        // This makes @can('finance.view'), $user->can('finance.view'), and
        // Gate::allows('finance.view') all work correctly in Blade and controllers.
        Gate::before(function (User $user, string $ability): ?bool {
            if ($user->isSuperAdmin()) {
                return true;
            }
            // Only intercept dotted permission codes (e.g. 'finance.view').
            // Return null for Laravel policy abilities like 'viewAny', 'update', etc.
            if (str_contains($ability, '.')) {
                return $user->hasPermission($ability) ?: null;
            }
            return null;
        });

        Candidate::observe(CandidateObserver::class);
        CandidateAssessment::observe(CandidateAssessmentObserver::class);
        FormAssignment::observe(FormAssignmentObserver::class);

        try {
            if (Schema::hasTable('app_settings')) {
                $setting = AppSetting::first();

                if (! $setting) {
                    $setting = new AppSetting([
                        'app_name' => 'OMEO HR',
                        'meta_title' => 'HR System',
                        'app_logo_path' => null,
                        'app_favicon_path' => null,
                        'retention_enabled' => true,
                        'retention_rejected_days' => 30,
                        'retention_blocked_days' => 365,
                    ]);
                }

                MailConfiguration::applyFromSettings($setting);
                View::share('globalSettings', $setting);
            }
        } catch (\Exception $e) {
            // silent fail for migration/bootstrap phase
        }
    }
}
