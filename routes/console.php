<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('applicants:auto-reject-incomplete')
    ->dailyAt('00:30')
    ->withoutOverlapping();

Schedule::command('recruitment:purge')
    ->dailyAt('01:00')
    ->withoutOverlapping();

Schedule::call(function () {
    app(\App\Services\AppraisalProbationReminderService::class)->generate();
})->dailyAt('07:00')->name('appraisal-probation-reminders')->withoutOverlapping();

Artisan::command('appraisals:probation-reminders', function () {
    $created = app(\App\Services\AppraisalProbationReminderService::class)->generate();
    $this->info('Generated ' . $created . ' appraisal probation reminder notifications.');
})->purpose('Generate HRD reminders for probation appraisal invitations');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
