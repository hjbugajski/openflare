<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\RecomputeUserRollups;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecomputeUserRollupsJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    /**
     * Only spans the queue wait: the lock is released as the job starts, so a
     * preference change landing mid-run queues a fresh recompute rather than
     * being dropped as a duplicate.
     */
    public int $uniqueFor = 600;

    public function __construct(
        public User $user
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->user->uuid;
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['user:'.$this->user->uuid];
    }

    /**
     * The timezone is read here rather than carried as a constructor argument:
     * queued runs coalesce, so several rapid toggles must converge on whatever
     * the preference finally settled on instead of replaying stale values.
     * The user is re-read because a sync or direct dispatch skips model
     * serialization and would otherwise hand back the dispatch-time instance.
     */
    public function handle(RecomputeUserRollups $recomputeUserRollups): void
    {
        $user = $this->user->fresh();

        if (! $user) {
            return;
        }

        $recomputeUserRollups->handle($user, $user->getPreference('timezone', config('app.timezone')));
    }
}
