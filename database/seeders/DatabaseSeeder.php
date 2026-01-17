<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DailyUptimeRollup;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\Notifier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
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

        $discordNotifier = Notifier::factory()->discord()->create([
            'user_id' => $user->uuid,
            'name' => 'Team Alerts',
            'is_default' => true,
            'apply_to_all' => true,
            'config' => [
                'webhook_url' => 'https://discord.com/api/webhooks/123456789012345678/promotionaltoken',
            ],
        ]);

        $emailNotifier = Notifier::factory()->email()->create([
            'user_id' => $user->uuid,
            'name' => 'On-Call Email',
            'apply_to_all' => true,
            'config' => [
                'email' => 'alerts@example.com',
            ],
        ]);

        $monitorSetups = [
            [
                'data' => [
                    'name' => 'Marketing Site',
                    'url' => 'https://example.com',
                    'method' => 'GET',
                    'interval' => 300,
                ],
                'history' => [
                    'hours' => 24,
                    'down_periods' => [],
                    'avg_response_time' => 140,
                ],
                'rollups' => [
                    'base_uptime' => 99.98,
                    'variance' => 0.1,
                    'avg_response_time' => 140,
                    'special' => [0 => 99.92],
                ],
                'incidents' => [],
            ],
            [
                'data' => [
                    'name' => 'API Gateway',
                    'url' => 'https://example.com/api/health',
                    'method' => 'GET',
                    'interval' => 60,
                ],
                'history' => [
                    'hours' => 24,
                    'down_periods' => [
                        ['start' => now()->subHours(4), 'end' => now()->subHours(3)->subMinutes(40)],
                    ],
                    'avg_response_time' => 180,
                ],
                'rollups' => [
                    'base_uptime' => 99.1,
                    'variance' => 0.4,
                    'avg_response_time' => 180,
                    'special' => [0 => 98.4, 3 => 97.9],
                ],
                'incidents' => [
                    [
                        'started_at' => now()->subHours(4),
                        'ended_at' => now()->subHours(3)->subMinutes(40),
                        'cause' => 'Regional load balancer reset',
                    ],
                ],
            ],
            [
                'data' => [
                    'name' => 'Billing Service',
                    'url' => 'https://example.com/billing/status',
                    'method' => 'GET',
                    'interval' => 300,
                ],
                'history' => [
                    'hours' => 24,
                    'down_periods' => [
                        ['start' => now()->subMinutes(30), 'end' => null],
                    ],
                    'avg_response_time' => 220,
                ],
                'rollups' => [
                    'base_uptime' => 98.4,
                    'variance' => 0.6,
                    'avg_response_time' => 220,
                    'special' => [0 => 92.3, 1 => 97.8],
                ],
                'incidents' => [
                    [
                        'started_at' => now()->subMinutes(30),
                        'ended_at' => null,
                        'cause' => 'Database failover in progress',
                    ],
                ],
            ],
            [
                'data' => [
                    'name' => 'Auth Service',
                    'url' => 'https://example.com/auth/health',
                    'method' => 'GET',
                    'interval' => 300,
                ],
                'history' => [
                    'hours' => 24,
                    'down_periods' => [
                        ['start' => now()->subHours(10), 'end' => now()->subHours(9)->subMinutes(15)],
                    ],
                    'avg_response_time' => 190,
                ],
                'rollups' => [
                    'base_uptime' => 97.6,
                    'variance' => 0.7,
                    'avg_response_time' => 190,
                    'special' => [0 => 96.2, 6 => 94.8],
                ],
                'incidents' => [
                    [
                        'started_at' => now()->subHours(10),
                        'ended_at' => now()->subHours(9)->subMinutes(15),
                        'cause' => 'Token signing service restarted',
                    ],
                ],
            ],
            [
                'data' => [
                    'name' => 'Edge Cache',
                    'url' => 'https://example.com/cache/ping',
                    'method' => 'HEAD',
                    'interval' => 60,
                ],
                'history' => [
                    'hours' => 24,
                    'down_periods' => [],
                    'avg_response_time' => 35,
                    'response_time_variance' => 15,
                ],
                'rollups' => [
                    'base_uptime' => 99.99,
                    'variance' => 0.05,
                    'avg_response_time' => 35,
                    'special' => [12 => 99.2],
                ],
                'incidents' => [],
            ],
            [
                'data' => [
                    'name' => 'Status Page',
                    'url' => 'https://example.com/status',
                    'method' => 'GET',
                    'interval' => 900,
                ],
                'history' => [
                    'hours' => 24,
                    'down_periods' => [],
                    'avg_response_time' => 160,
                ],
                'rollups' => [
                    'base_uptime' => 99.6,
                    'variance' => 0.3,
                    'avg_response_time' => 160,
                    'special' => [2 => 98.5, 14 => 97.3],
                ],
                'incidents' => [],
            ],
        ];

        foreach ($monitorSetups as $setup) {
            $monitor = $this->createMonitor($user, $setup['data']);
            $monitor->notifiers()->attach([$discordNotifier->id, $emailNotifier->id]);

            $this->createChecksHistory(
                $monitor,
                $setup['history']['hours'],
                $setup['history']['down_periods'],
                $setup['history']['avg_response_time'],
                $setup['history']['response_time_variance'] ?? 80
            );

            $specialUptimeDays = $setup['rollups']['special'];
            $specialUptimeDays[0] = $this->calculateUptimeForWindow(
                $monitor,
                now()->subHours(24),
                now()
            );


            $this->createRollups(
                $monitor,
                30,
                $setup['rollups']['base_uptime'],
                $setup['rollups']['variance'],
                $setup['rollups']['avg_response_time'],
                $specialUptimeDays
            );

            foreach ($setup['incidents'] as $incident) {
                Incident::factory()->create([
                    'monitor_id' => $monitor->id,
                    'started_at' => $incident['started_at'],
                    'ended_at' => $incident['ended_at'],
                    'cause' => $incident['cause'],
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createMonitor(User $user, array $data): Monitor
    {
        $defaults = [
            'id' => Str::uuid7()->toString(),
            'user_id' => $user->uuid,
            'method' => 'GET',
            'interval' => 300,
            'timeout' => 30,
            'expected_status_code' => 200,
            'is_active' => true,
            'last_checked_at' => now(),
            'next_check_at' => now()->addSeconds($data['interval'] ?? 300),
        ];

        return Monitor::withoutEvents(function () use ($defaults, $data) {
            return Monitor::create(array_merge($defaults, $data));
        });
    }

    /**
     * @param  array<array{start: Carbon, end: Carbon|null}>  $downPeriods
     */
    private function createChecksHistory(
        Monitor $monitor,
        int $hours,
        array $downPeriods,
        int $avgResponseTime,
        int $responseTimeVariance
    ): void {
        $now = now();
        $startTime = $now->copy()->subHours($hours);
        $currentTime = $startTime->copy();

        $checks = [];
        $errorMessages = [
            'Connection timeout',
            'Service unavailable',
            'Bad gateway response',
            'Upstream connection reset',
        ];
        $downStatusCodes = [500, 502, 503, 504];

        while ($currentTime <= $now) {
            $isDown = $this->isWithinDownPeriod($currentTime, $downPeriods);

            $checks[] = [
                'id' => Str::uuid7()->toString(),
                'monitor_id' => $monitor->id,
                'status' => $isDown ? 'down' : 'up',
                'status_code' => $isDown ? $downStatusCodes[array_rand($downStatusCodes)] : $monitor->expected_status_code,
                'response_time_ms' => $isDown ? null : max(20, $avgResponseTime + random_int(-$responseTimeVariance, $responseTimeVariance)),
                'error_message' => $isDown ? $errorMessages[array_rand($errorMessages)] : null,
                'checked_at' => $currentTime->copy(),
                'created_at' => $currentTime->copy(),
                'updated_at' => $currentTime->copy(),
            ];

            $currentTime->addSeconds($monitor->interval);

            if (count($checks) >= 500) {
                MonitorCheck::insert($checks);
                $checks = [];
            }
        }

        if (count($checks) > 0) {
            MonitorCheck::insert($checks);
        }

        $monitor->update([
            'last_checked_at' => $now,
            'next_check_at' => $now->copy()->addSeconds($monitor->interval),
        ]);
    }

    /**
     * @param  array<int, float>  $specialUptimeDays
     */
    private function createRollups(
        Monitor $monitor,
        int $days,
        float $baseUptime,
        float $variance,
        int $avgResponseTime,
        array $specialUptimeDays
    ): void {
        $rollups = [];

        for ($i = 0; $i < $days; $i++) {
            $targetUptime = $specialUptimeDays[$i] ?? $this->clampUptime(
                $baseUptime + (random_int((int) round(-$variance * 100), (int) round($variance * 100)) / 100)
            );

            $checksPerDay = (int) (86400 / $monitor->interval);

            if ($checksPerDay === 0) {
                $successfulChecks = 0;
                $uptime = 0.0;
            } elseif ($targetUptime >= 100) {
                $successfulChecks = $checksPerDay;
                $uptime = 100.00;
            } else {
                $successfulChecks = (int) floor($checksPerDay * ($targetUptime / 100));
                $uptime = round(($successfulChecks / $checksPerDay) * 100, 2);
            }

            $avgTime = max(20, $avgResponseTime + random_int(-20, 20));

            $rollups[] = [
                'id' => Str::uuid7()->toString(),
                'monitor_id' => $monitor->id,
                'date' => now()->subDays($i)->toDateString(),
                'total_checks' => $checksPerDay,
                'successful_checks' => $successfulChecks,
                'uptime_percentage' => $uptime,
                'avg_response_time_ms' => $avgTime,
                'min_response_time_ms' => max(10, $avgTime - 30),
                'max_response_time_ms' => $avgTime + 120,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DailyUptimeRollup::insert($rollups);
    }

    private function calculateUptimeForWindow(Monitor $monitor, Carbon $start, Carbon $end): float
    {
        $totalChecks = MonitorCheck::query()
            ->where('monitor_id', $monitor->id)
            ->whereBetween('checked_at', [$start, $end])
            ->count();

        if ($totalChecks === 0) {
            return 0.0;
        }

        $successfulChecks = MonitorCheck::query()
            ->where('monitor_id', $monitor->id)
            ->whereBetween('checked_at', [$start, $end])
            ->where('status', 'up')
            ->count();

        return round(($successfulChecks / $totalChecks) * 100, 2);
    }

    /**
     * @param  array<array{start: Carbon, end: Carbon|null}>  $downPeriods
     */
    private function isWithinDownPeriod(Carbon $time, array $downPeriods): bool
    {
        foreach ($downPeriods as $period) {
            if ($time >= $period['start'] && ($period['end'] === null || $time <= $period['end'])) {
                return true;
            }
        }

        return false;
    }

    private function clampUptime(float $uptime): float
    {
        return max(80.0, min(100.0, $uptime));
    }
}
