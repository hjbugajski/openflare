<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\CheckMonitor;
use App\Models\Monitor;
use Illuminate\Console\Command;

class DispatchMonitorChecks extends Command
{
    protected $signature = 'monitors:dispatch-checks
        {--limit= : Maximum number of monitors to dispatch (default from config)}';

    protected $description = 'Dispatch monitor checks for monitors due for checking';

    public function handle(): int
    {
        if (config('monitors.test_mode')) {
            $this->info('Test mode enabled - skipping monitor checks');

            return Command::SUCCESS;
        }

        $maxPerRun = (int) ($this->option('limit') ?? config('monitors.dispatch_limit', 500));
        $dispatched = 0;
        $skipped = 0;

        Monitor::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('next_check_at')
                    ->orWhere('next_check_at', '<=', now());
            })
            ->select([
                'id',
                'user_id',
                'name',
                'url',
                'method',
                'interval',
                'timeout',
                'expected_status_code',
                'failure_confirmation_threshold',
                'recovery_confirmation_threshold',
                'is_active',
                'last_checked_at',
                'next_check_at',
            ])
            ->chunkById(100, function ($monitors) use ($maxPerRun, &$dispatched, &$skipped) {
                foreach ($monitors as $monitor) {
                    if ($dispatched >= $maxPerRun) {
                        $skipped++;

                        continue;
                    }

                    CheckMonitor::dispatch($monitor);
                    $dispatched++;
                }

                return $dispatched < $maxPerRun;
            });

        $this->info("Dispatched {$dispatched} monitor checks".($skipped > 0 ? " (skipped {$skipped})" : ''));

        return Command::SUCCESS;
    }
}
