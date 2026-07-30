<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LinkUsersToEmployees extends Command
{
    protected $signature = 'omeo:link-users-employees
                            {--dry-run : Show matches without saving}';

    protected $description = 'Link users with null employee_id to employees via email or name';

    public function handle(): int
    {
        if (! Schema::hasTable('employees')) {
            $this->error('Table employees does not exist.');

            return self::FAILURE;
        }

        $users = DB::table('users')
            ->whereNull('employee_id')
            ->get(['id', 'name', 'email']);

        if ($users->isEmpty()) {
            $this->info('All users already have an employee_id. Nothing to do.');

            return self::SUCCESS;
        }

        $this->info("Checking {$users->count()} unlinked user(s)...");

        $linked = 0;
        $notFound = 0;
        $dryRun = $this->option('dry-run');

        foreach ($users as $user) {
            $emp = null;
            $method = null;

            // Match by email → email_private
            if ($user->email) {
                $emp = DB::table('employees')
                    ->where('email_private', $user->email)
                    ->whereNull('deleted_at')
                    ->first(['id', 'full_name']);

                if ($emp) {
                    $method = 'email';
                }
            }

            // Fallback: match by name → full_name
            if (! $emp && $user->name) {
                $emp = DB::table('employees')
                    ->where('full_name', $user->name)
                    ->whereNull('deleted_at')
                    ->first(['id', 'full_name']);

                if ($emp) {
                    $method = 'name';
                }
            }

            if ($emp) {
                $label = $dryRun ? '[DRY-RUN] WOULD LINK' : 'LINKED';
                $this->line("  {$label}: user #{$user->id} ({$user->name}) → employee #{$emp->id} ({$emp->full_name}) via {$method}");

                if (! $dryRun) {
                    DB::table('users')->where('id', $user->id)->update(['employee_id' => $emp->id]);
                }

                $linked++;
            } else {
                $this->line("  NOT FOUND: user #{$user->id} ({$user->name} / {$user->email})");
                $notFound++;
            }
        }

        $this->newLine();
        $this->info("Done. Linked: {$linked} | Not found: {$notFound}");

        return self::SUCCESS;
    }
}
