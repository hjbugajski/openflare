<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MonitorCheck;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PruneMonitorChecks extends Command
{
    private const int BATCH_SIZE = 1000;

    protected $signature = 'monitors:prune-checks';

    protected $description = 'Delete monitor checks older than the configured retention period';

    public function handle(): int
    {
        $retentionDays = (int) config('monitors.retention_days', 30);
        $cutoff = now()->subDays($retentionDays);
        $deleted = 0;

        while (true) {
            $ids = MonitorCheck::query()
                ->where('checked_at', '<', $cutoff)
                ->limit(self::BATCH_SIZE)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted += MonitorCheck::query()->whereIn('id', $ids)->delete();
        }

        if ($deleted > 0) {
            Log::info('Pruned old monitor checks', ['deleted' => $deleted]);
        }

        return Command::SUCCESS;
    }
}
