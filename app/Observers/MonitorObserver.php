<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\CheckMonitor;
use App\Models\Monitor;

class MonitorObserver
{
    /**
     * Handle the Monitor "creating" event.
     * Sets next_check_at to now() so the monitor is picked up immediately.
     */
    public function creating(Monitor $monitor): void
    {
        if ($monitor->is_active && $monitor->next_check_at === null) {
            $monitor->next_check_at = now();
        }
    }

    /**
     * Handle the Monitor "created" event.
     * Dispatches an immediate check for active monitors.
     */
    public function created(Monitor $monitor): void
    {
        if (config('monitors.test_mode')) {
            return;
        }

        if ($monitor->is_active) {
            CheckMonitor::dispatch($monitor);
        }
    }

    /**
     * Handle the Monitor "updating" event.
     * Recomputes next_check_at when certain fields change.
     */
    public function updating(Monitor $monitor): void
    {
        $wasActive = $monitor->getOriginal('is_active');
        $isActive = $monitor->is_active;

        // Paused: clear next_check_at
        if ($wasActive && ! $isActive) {
            $monitor->next_check_at = null;
        }

        // Resumed: set next_check_at to now
        if (! $wasActive && $isActive) {
            $monitor->next_check_at = now();
        }

        // If interval, timeout, expected_status_code, url, method, or confirmation thresholds changed while active, trigger immediate recheck
        if ($isActive) {
            $triggerFields = [
                'interval',
                'timeout',
                'expected_status_code',
                'url',
                'method',
                'failure_confirmation_threshold',
                'recovery_confirmation_threshold',
            ];
            $changed = false;

            foreach ($triggerFields as $field) {
                if ($monitor->isDirty($field)) {
                    $changed = true;

                    break;
                }
            }

            if ($changed) {
                $monitor->next_check_at = now();
            }
        }
    }

    /**
     * Handle the Monitor "updated" event.
     * Dispatches check when monitor is resumed.
     */
    public function updated(Monitor $monitor): void
    {
        if (config('monitors.test_mode')) {
            return;
        }

        $wasActive = $monitor->getOriginal('is_active');
        $isActive = $monitor->is_active;

        // Just resumed: dispatch immediate check
        if (! $wasActive && $isActive) {
            CheckMonitor::dispatch($monitor);
        }
    }
}
