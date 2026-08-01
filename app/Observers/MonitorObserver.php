<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\CheckMonitor;
use App\Models\Monitor;

class MonitorObserver
{
    public function creating(Monitor $monitor): void
    {
        if ($monitor->is_active && $monitor->next_check_at === null) {
            $monitor->next_check_at = now();
        }
    }

    public function created(Monitor $monitor): void
    {
        if (config('monitors.test_mode')) {
            return;
        }

        if ($monitor->is_active) {
            CheckMonitor::dispatch($monitor);
        }
    }

    public function updating(Monitor $monitor): void
    {
        $wasActive = $monitor->getOriginal('is_active');
        $isActive = $monitor->is_active;

        if ($wasActive && ! $isActive) {
            $monitor->next_check_at = null;
        }

        if (! $wasActive && $isActive) {
            $monitor->next_check_at = now();
        }

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

    public function updated(Monitor $monitor): void
    {
        if (config('monitors.test_mode')) {
            return;
        }

        $wasActive = $monitor->getOriginal('is_active');
        $isActive = $monitor->is_active;

        if (! $wasActive && $isActive) {
            CheckMonitor::dispatch($monitor);
        }
    }
}
