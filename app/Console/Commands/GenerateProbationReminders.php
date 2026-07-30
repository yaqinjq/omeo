<?php

namespace App\Console\Commands;

use App\Services\ProbationReminderService;
use Illuminate\Console\Command;

class GenerateProbationReminders extends Command
{
    protected $signature = 'hr:generate-reminders {--days=30,14,7,1}';
    protected $description = 'Generate HR reminders for upcoming probation end dates';

    public function handle(ProbationReminderService $service): int
    {
        $days = collect(explode(',', (string)$this->option('days')))
            ->map(fn($v) => (int)trim($v))
            ->filter(fn($v) => $v > 0)
            ->values()
            ->all();

        $created = $service->generate($days);
        $this->info('Created reminders: ' . $created);

        return self::SUCCESS;
    }
}
