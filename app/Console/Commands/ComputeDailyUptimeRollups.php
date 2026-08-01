<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ComputeRollupStats;
use App\Actions\PersistDailyRollups;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ComputeDailyUptimeRollups extends Command
{
    protected $signature = 'monitors:compute-rollups
        {--date= : Specific date to compute (Y-m-d format, defaults to yesterday)}
        {--days= : Number of past days to compute (overrides --date)}';

    protected $description = 'Compute daily uptime rollups from monitor checks';

    /** @var array<string, string>|null */
    private ?array $timezones = null;

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
     * Rollup rows are keyed by (monitor_id, date), and RecomputeUserRollups
     * writes the same rows using each owner's preferred timezone. Both writers
     * must derive the same day window for a given calendar date or they
     * overwrite each other's numbers, so the date is interpreted in the
     * monitor owner's timezone here too.
     *
     * @return array{created: int, updated: int}
     */
    protected function computeRollupsForDate(Carbon $date): array
    {
        $created = 0;
        $updated = 0;
        $dateString = $date->toDateString();

        Monitor::query()->chunkById(100, function ($monitors) use ($dateString, &$created, &$updated) {
            $timezones = $this->timezoneByUser();
            $groups = $monitors->groupBy(
                fn (Monitor $monitor) => $timezones[$monitor->user_id] ?? $this->defaultTimezone()
            );

            foreach ($groups as $timezone => $group) {
                $localDate = Carbon::parse($dateString, $timezone)->startOfDay();
                $monitorIds = $group->pluck('id');

                $stats = $this->computeRollupStats->handle(
                    $monitorIds,
                    $localDate->copy()->utc(),
                    $localDate->copy()->endOfDay()->utc(),
                );

                $result = $this->persistDailyRollups->handle($monitorIds, $localDate, $stats);

                $created += $result['created'];
                $updated += $result['updated'];
            }
        });

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * Owner uuid => preferred timezone, resolved once per command run. Users
     * with no preference fall back to the app timezone, which is what
     * RecomputeAllUserRollups leaves them on.
     *
     * @return array<string, string>
     */
    protected function timezoneByUser(): array
    {
        return $this->timezones ??= User::query()
            ->select(['uuid', 'preferences'])
            ->get()
            ->mapWithKeys(fn (User $user) => [
                $user->uuid => $user->getPreference('timezone') ?: $this->defaultTimezone(),
            ])
            ->all();
    }

    protected function defaultTimezone(): string
    {
        return (string) config('app.timezone', 'UTC');
    }
}
