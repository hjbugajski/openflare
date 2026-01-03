<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DailyUptimeRollup;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\MonitorStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ComputeDailyUptimeRollups extends Command
{
    protected $signature = 'monitors:compute-rollups
        {--date= : Specific date to compute (Y-m-d format, defaults to yesterday)}
        {--days= : Number of past days to compute (overrides --date)}';

    protected $description = 'Compute daily uptime rollups from monitor checks';

    public function handle(): int
    {
        $dates = $this->getDatesToCompute();

        $this->info('Computing rollups for '.count($dates).' date(s)');

        $processed = 0;
        $created = 0;
        $updated = 0;

        foreach ($dates as $date) {
            $this->output->write("Processing {$date->format('Y-m-d')}... ");

            $stats = $this->computeRollupsForDate($date);
            $processed++;
            $created += $stats['created'];
            $updated += $stats['updated'];

            $this->output->writeln("<info>Created: {$stats['created']}, Updated: {$stats['updated']}</info>");
        }

        Log::info('Daily uptime rollups computed', [
            'dates_processed' => $processed,
            'rollups_created' => $created,
            'rollups_updated' => $updated,
        ]);

        $this->info("Done! Processed {$processed} date(s), created {$created}, updated {$updated} rollups.");

        return Command::SUCCESS;
    }

    /**
     * @return array<Carbon>
     */
    protected function getDatesToCompute(): array
    {
        if ($days = $this->option('days')) {
            $dates = [];
            for ($i = 1; $i <= (int) $days; $i++) {
                $dates[] = now()->subDays($i)->startOfDay();
            }

            return $dates;
        }

        $dateStr = $this->option('date');
        if ($dateStr) {
            return [Carbon::parse($dateStr)->startOfDay()];
        }

        return [now()->subDay()->startOfDay()];
    }

    /**
     * @return array{created: int, updated: int}
     */
    protected function computeRollupsForDate(Carbon $date): array
    {
        $created = 0;
        $updated = 0;

        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        // Get all monitors
        Monitor::query()->chunkById(100, function ($monitors) use ($startOfDay, $endOfDay, &$created, &$updated) {
            foreach ($monitors as $monitor) {
                $stats = MonitorCheck::query()
                    ->where('monitor_id', $monitor->id)
                    ->whereBetween('checked_at', [$startOfDay, $endOfDay])
                    ->selectRaw('
                        COUNT(*) as total_checks,
                        SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as successful_checks,
                        AVG(response_time_ms) as avg_response_time_ms,
                        MIN(response_time_ms) as min_response_time_ms,
                        MAX(response_time_ms) as max_response_time_ms
                    ', [MonitorStatus::Up->value])
                    ->first();

                if (! $stats || $stats->total_checks === 0) {
                    continue;
                }

                $uptimePercentage = $stats->total_checks > 0
                    ? round(($stats->successful_checks / $stats->total_checks) * 100, 2)
                    : 100.00;

                $rollup = DailyUptimeRollup::updateOrCreate(
                    [
                        'monitor_id' => $monitor->id,
                        'date' => $startOfDay->toDateString(),
                    ],
                    [
                        'total_checks' => $stats->total_checks,
                        'successful_checks' => $stats->successful_checks,
                        'uptime_percentage' => $uptimePercentage,
                        'avg_response_time_ms' => $stats->avg_response_time_ms ? (int) round($stats->avg_response_time_ms) : null,
                        'min_response_time_ms' => $stats->min_response_time_ms,
                        'max_response_time_ms' => $stats->max_response_time_ms,
                    ]
                );

                if ($rollup->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }
            }
        });

        return ['created' => $created, 'updated' => $updated];
    }
}
