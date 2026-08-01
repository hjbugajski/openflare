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
        $targets = $this->getDatesToCompute();

        $this->info('Computing rollups for '.count($targets).' date(s)');

        $processed = 0;
        $created = 0;
        $updated = 0;

        foreach ($targets as $target) {
            $this->output->write('Processing '.$this->describeTarget($target).'... ');

            $stats = $this->computeRollupsForDate($target);
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
     * Relative targets stay relative: an int is a number of days back, resolved
     * against each owner's timezone at rollup time. Resolving them here against
     * the app timezone would hand owners west of it a window that has not
     * elapsed yet — the 00:15 UTC run would write America/Los_Angeles totals
     * missing that day's last 6.75 hours and never revisit them.
     *
     * @return array<int, int|string>
     */
    protected function getDatesToCompute(): array
    {
        $days = (int) $this->option('days');
        if ($days > 0) {
            return range(1, $days);
        }

        $dateStr = $this->option('date');
        if ($dateStr) {
            return [Carbon::parse($dateStr)->toDateString()];
        }

        return [1];
    }

    /**
     * Rollup rows are keyed by (monitor_id, date), and RecomputeUserRollups
     * writes the same rows using each owner's preferred timezone. Both writers
     * must derive the same day window for a given calendar date or they
     * overwrite each other's numbers, so the date is interpreted in the
     * monitor owner's timezone here too.
     *
     * @param  int|string  $target  days back, or an explicit Y-m-d date
     * @return array{created: int, updated: int}
     */
    protected function computeRollupsForDate(int|string $target): array
    {
        $created = 0;
        $updated = 0;

        Monitor::query()->chunkById(100, function ($monitors) use ($target, &$created, &$updated) {
            $timezones = $this->timezoneByUser();
            $groups = $monitors->groupBy(
                fn (Monitor $monitor) => $timezones[$monitor->user_id] ?? $this->defaultTimezone()
            );

            foreach ($groups as $timezone => $group) {
                $localDate = $this->localDate($target, $timezone);
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
     * Mirrors RecomputeUserRollups: now() in the owner's timezone, wound back
     * $target days to the start of that local day.
     *
     * @param  int|string  $target  days back, or an explicit Y-m-d date
     */
    protected function localDate(int|string $target, string $timezone): Carbon
    {
        return is_int($target)
            ? now($timezone)->subDays($target)->startOfDay()
            : Carbon::parse($target, $timezone)->startOfDay();
    }

    /**
     * Progress label only. Relative targets resolve per timezone, so the app
     * timezone's date is shown as a representative value.
     *
     * @param  int|string  $target  days back, or an explicit Y-m-d date
     */
    protected function describeTarget(int|string $target): string
    {
        return is_int($target)
            ? now()->subDays($target)->toDateString()
            : $target;
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
