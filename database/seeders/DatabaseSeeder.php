<?php

namespace Database\Seeders;

use App\Models\Incident;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\Notifier;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );

        // Create notifiers
        $discordNotifier = Notifier::factory()->discord()->create([
            'user_id' => $user->id,
            'name' => 'Discord Alerts',
        ]);

        $emailNotifier = Notifier::factory()->email()->create([
            'user_id' => $user->id,
            'name' => 'Email Alerts',
            'config' => ['email' => $user->email],
        ]);

        // Create monitors with various states
        $monitors = [
            ['name' => 'Google', 'url' => 'https://google.com', 'interval' => 300],
            ['name' => 'GitHub', 'url' => 'https://github.com', 'interval' => 300],
            ['name' => 'Laravel', 'url' => 'https://laravel.com', 'interval' => 600],
            ['name' => 'Example Down', 'url' => 'https://httpstat.us/503', 'interval' => 300],
        ];

        foreach ($monitors as $monitorData) {
            $monitor = Monitor::factory()->create([
                'user_id' => $user->id,
                ...$monitorData,
            ]);

            $monitor->notifiers()->attach([$discordNotifier->id, $emailNotifier->id]);

            // Create check history for last 24 hours
            $checksCount = (int) (86400 / $monitor->interval);
            $checksCount = min($checksCount, 100); // Cap at 100 checks

            for ($i = $checksCount; $i >= 0; $i--) {
                $checkedAt = now()->subSeconds($i * $monitor->interval);
                $isDown = $monitorData['name'] === 'Example Down' && $i < 3;

                MonitorCheck::factory()
                    ->{$isDown ? 'down' : 'up'}()
                    ->create([
                        'monitor_id' => $monitor->id,
                        'checked_at' => $checkedAt,
                    ]);
            }

            // Create incident for the "down" monitor
            if ($monitorData['name'] === 'Example Down') {
                Incident::factory()->ongoing()->create([
                    'monitor_id' => $monitor->id,
                    'cause' => 'Service returned 503 status code',
                ]);
            }

            // Update monitor timestamps
            $monitor->update([
                'last_checked_at' => now(),
                'next_check_at' => now()->addSeconds($monitor->interval),
            ]);
        }
    }
}
