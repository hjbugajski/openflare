<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\DailyUptimeRollup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class BackfillMissingRollups
{
    /**
     * Check for and backfill any missing rollup days.
     */
    public function handle(): void
    {
        if (! Schema::hasTable('daily_uptime_rollups')) {
            return;
        }

        $latestRollup = DailyUptimeRollup::query()
            ->orderBy('date', 'desc')
            ->first();

        if (! $latestRollup) {
            // No rollups exist yet, compute the last 30 days
            $this->runRollupCommand(30);

            return;
        }

        $latestDate = Carbon::parse($latestRollup->date);
        $yesterday = now()->subDay()->startOfDay();

        // Calculate days between latest rollup and yesterday
        $daysMissing = (int) $latestDate->diffInDays($yesterday, false);

        if ($daysMissing > 0) {
            Log::info('Backfilling missing daily rollups', [
                'latest_rollup_date' => $latestDate->toDateString(),
                'days_missing' => $daysMissing,
            ]);

            $this->runRollupCommand($daysMissing);
        }
    }

    /**
     * Run the rollup command for the specified number of days.
     */
    protected function runRollupCommand(int $days): void
    {
        Artisan::call('monitors:compute-rollups', [
            '--days' => $days,
        ]);
    }
}
