<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ComputeRollupStats;
use App\Actions\PersistDailyRollups;
use App\Models\Monitor;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ComputeDailyUptimeRollups extends Command
{
    protected $signature = 'monitors:compute-rollups
        {--date= : Specific date to compute (Y-m-d format, defaults to yesterday)}
        {--days= : Number of past days to compute (overrides --date)}';

    protected $description = 'Compute daily uptime rollups from monitor checks';

    public function __construct(
        private readonly ComputeRollupStats $computeRollupStats,
        private readonly PersistDailyRollups $persistDailyRollups,
    ) {
        parent::__construct();
    }

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

        Monitor::query()->chunkById(100, function ($monitors) use ($startOfDay, $endOfDay, $date, &$created, &$updated) {
            $monitorIds = $monitors->pluck('id');

            $stats = $this->computeRollupStats->handle($monitorIds, $startOfDay, $endOfDay);

            $result = $this->persistDailyRollups->handle($monitorIds, $date, $stats);

            $created += $result['created'];
            $updated += $result['updated'];
        });

        return ['created' => $created, 'updated' => $updated];
    }
}
