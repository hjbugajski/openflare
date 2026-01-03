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

/**
 * Comprehensive test data seeder covering all monitor scenarios for frontend testing.
 *
 * Run with: php artisan db:seed --class=TestDataSeeder
 */
class TestDataSeeder extends Seeder
{
    private User $user;

    /** @var array<Monitor> */
    private array $monitors = [];

    public function run(): void
    {
        $this->user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );

        $this->createHealthyMonitors();
        $this->createDownMonitors();
        $this->createPausedMonitors();
        $this->createPendingMonitors();
        $this->createMonitorsWithVaryingUptime();
        $this->createMonitorsWithDifferentIntervals();
        $this->createMonitorsWithIncidentHistory();
        $this->createEdgeCaseMonitors();
        $this->createNotifiers();
    }

    /**
     * Monitors that are completely healthy - 100% uptime, no incidents.
     */
    private function createHealthyMonitors(): void
    {
        $monitors = [
            ['name' => 'Production API', 'url' => 'https://example.com/api/health', 'method' => 'GET', 'interval' => 60],
            ['name' => 'Main Website', 'url' => 'https://example.com', 'method' => 'HEAD', 'interval' => 300],
            ['name' => 'CDN Endpoint', 'url' => 'https://example.com/cdn/ping', 'method' => 'GET', 'interval' => 60],
        ];

        foreach ($monitors as $data) {
            $monitor = $this->createMonitor($data);
            $this->createChecksHistory($monitor, hours: 24, downPeriods: []);
            $this->createPerfectRollups($monitor, days: 30);
        }
    }

    /**
     * Monitors currently experiencing downtime with active incidents.
     */
    private function createDownMonitors(): void
    {
        // Recently went down (5 minutes ago)
        $monitor1 = $this->createMonitor([
            'name' => 'Payment Gateway',
            'url' => 'https://example.com/payments/status',
            'method' => 'GET',
            'interval' => 60,
        ]);
        $this->createChecksHistory($monitor1, hours: 24, downPeriods: [
            ['start' => now()->subMinutes(5), 'end' => null],
        ]);
        $this->createRollupsWithOutages($monitor1, days: 7, outagePercentage: 0.5);
        Incident::factory()->create([
            'monitor_id' => $monitor1->id,
            'started_at' => now()->subMinutes(5),
            'ended_at' => null,
            'cause' => 'Connection timeout - server not responding',
        ]);

        // Down for several hours
        $monitor2 = $this->createMonitor([
            'name' => 'Email Service',
            'url' => 'https://example.com/mail/health',
            'method' => 'GET',
            'interval' => 300,
        ]);
        $this->createChecksHistory($monitor2, hours: 24, downPeriods: [
            ['start' => now()->subHours(3), 'end' => null],
        ]);
        $this->createRollupsWithOutages($monitor2, days: 7, outagePercentage: 2);
        Incident::factory()->create([
            'monitor_id' => $monitor2->id,
            'started_at' => now()->subHours(3),
            'ended_at' => null,
            'cause' => 'HTTP 503 Service Unavailable',
        ]);

        // Down for over a day (major outage)
        $monitor3 = $this->createMonitor([
            'name' => 'Legacy System',
            'url' => 'https://example.com/legacy/api/ping',
            'method' => 'GET',
            'interval' => 900,
        ]);
        $this->createChecksHistory($monitor3, hours: 48, downPeriods: [
            ['start' => now()->subDay()->subHours(2), 'end' => null],
        ]);
        $this->createRollupsWithOutages($monitor3, days: 7, outagePercentage: 15);
        Incident::factory()->create([
            'monitor_id' => $monitor3->id,
            'started_at' => now()->subDay()->subHours(2),
            'ended_at' => null,
            'cause' => 'DNS resolution failed - domain not resolving',
        ]);
    }

    /**
     * Paused monitors (is_active = false).
     */
    private function createPausedMonitors(): void
    {
        // Paused with historical data
        $monitor1 = $this->createMonitor([
            'name' => 'Staging Server',
            'url' => 'https://example.com/staging',
            'method' => 'GET',
            'interval' => 300,
            'is_active' => false,
        ]);
        $this->createChecksHistory($monitor1, hours: 24, downPeriods: [], stopAt: now()->subDays(3));
        $this->createRollupsWithOutages($monitor1, days: 14, outagePercentage: 0);

        // Paused with no data (never ran)
        $this->createMonitor([
            'name' => 'Development API',
            'url' => 'https://example.com/dev/api/health',
            'method' => 'GET',
            'interval' => 600,
            'is_active' => false,
            'last_checked_at' => null,
            'next_check_at' => null,
        ]);

        // Paused after experiencing issues
        $monitor3 = $this->createMonitor([
            'name' => 'Deprecated Service',
            'url' => 'https://example.com/deprecated/status',
            'method' => 'HEAD',
            'interval' => 3600,
            'is_active' => false,
        ]);
        $this->createChecksHistory($monitor3, hours: 48, downPeriods: [
            ['start' => now()->subDays(10), 'end' => now()->subDays(9)],
        ], stopAt: now()->subDays(2));
        $this->createRollupsWithOutages($monitor3, days: 30, outagePercentage: 5);
    }

    /**
     * Newly created monitors awaiting first check.
     */
    private function createPendingMonitors(): void
    {
        // Just created, waiting for first check
        $this->createMonitor([
            'name' => 'New Microservice',
            'url' => 'https://example.com/services/new/health',
            'method' => 'GET',
            'interval' => 60,
            'is_active' => true,
            'last_checked_at' => null,
            'next_check_at' => now(),
        ]);

        // Created a minute ago, still pending
        $this->createMonitor([
            'name' => 'Beta Feature API',
            'url' => 'https://example.com/api/v2/beta/status',
            'method' => 'GET',
            'interval' => 300,
            'is_active' => true,
            'last_checked_at' => null,
            'next_check_at' => now()->addMinutes(4),
        ]);
    }

    /**
     * Monitors with varying uptime percentages to test sparkline visualization.
     */
    private function createMonitorsWithVaryingUptime(): void
    {
        // 99.9% uptime (near perfect)
        $monitor1 = $this->createMonitor([
            'name' => 'High Availability DB',
            'url' => 'https://example.com/db/health',
            'method' => 'GET',
            'interval' => 60,
        ]);
        $this->createChecksHistory($monitor1, hours: 24, downPeriods: []);
        $this->createCustomRollups($monitor1, [
            ['days_ago' => 0, 'uptime' => 100],
            ['days_ago' => 1, 'uptime' => 100],
            ['days_ago' => 2, 'uptime' => 100],
            ['days_ago' => 3, 'uptime' => 100],
            ['days_ago' => 4, 'uptime' => 100],
            ['days_ago' => 5, 'uptime' => 100],
            ['days_ago' => 6, 'uptime' => 100],
            ['days_ago' => 7, 'uptime' => 100],
            ['days_ago' => 8, 'uptime' => 100],
            ['days_ago' => 9, 'uptime' => 100],
            ['days_ago' => 10, 'uptime' => 100],
            ['days_ago' => 11, 'uptime' => 100],
            ['days_ago' => 12, 'uptime' => 100],
            ['days_ago' => 13, 'uptime' => 100],
            ['days_ago' => 14, 'uptime' => 100],
            ['days_ago' => 15, 'uptime' => 97.92], // Brief outage
            ['days_ago' => 16, 'uptime' => 100],
            ['days_ago' => 17, 'uptime' => 100],
            ['days_ago' => 18, 'uptime' => 100],
            ['days_ago' => 19, 'uptime' => 100],
            ['days_ago' => 20, 'uptime' => 100],
            ['days_ago' => 21, 'uptime' => 100],
            ['days_ago' => 22, 'uptime' => 100],
            ['days_ago' => 23, 'uptime' => 100],
            ['days_ago' => 24, 'uptime' => 100],
            ['days_ago' => 25, 'uptime' => 100],
            ['days_ago' => 26, 'uptime' => 100],
            ['days_ago' => 27, 'uptime' => 100],
            ['days_ago' => 28, 'uptime' => 100],
            ['days_ago' => 29, 'uptime' => 100],
        ]);

        // 95% uptime (occasional issues)
        $monitor2 = $this->createMonitor([
            'name' => 'Third Party Integration',
            'url' => 'https://example.com/integrations/partner/v1/ping',
            'method' => 'GET',
            'interval' => 300,
        ]);
        $this->createChecksHistory($monitor2, hours: 24, downPeriods: []);
        $this->createCustomRollups($monitor2, [
            ['days_ago' => 0, 'uptime' => 100],
            ['days_ago' => 1, 'uptime' => 100],
            ['days_ago' => 2, 'uptime' => 100],
            ['days_ago' => 3, 'uptime' => 95.83],
            ['days_ago' => 4, 'uptime' => 100],
            ['days_ago' => 5, 'uptime' => 100],
            ['days_ago' => 6, 'uptime' => 100],
            ['days_ago' => 7, 'uptime' => 96.88],
            ['days_ago' => 8, 'uptime' => 100],
            ['days_ago' => 9, 'uptime' => 100],
            ['days_ago' => 10, 'uptime' => 100],
            ['days_ago' => 11, 'uptime' => 100],
            ['days_ago' => 12, 'uptime' => 100],
            ['days_ago' => 13, 'uptime' => 100],
            ['days_ago' => 14, 'uptime' => 87.50],
            ['days_ago' => 15, 'uptime' => 100],
            ['days_ago' => 16, 'uptime' => 100],
            ['days_ago' => 17, 'uptime' => 100],
            ['days_ago' => 18, 'uptime' => 100],
            ['days_ago' => 19, 'uptime' => 100],
            ['days_ago' => 20, 'uptime' => 100],
            ['days_ago' => 21, 'uptime' => 95.83],
            ['days_ago' => 22, 'uptime' => 100],
            ['days_ago' => 23, 'uptime' => 100],
            ['days_ago' => 24, 'uptime' => 100],
            ['days_ago' => 25, 'uptime' => 100],
            ['days_ago' => 26, 'uptime' => 100],
            ['days_ago' => 27, 'uptime' => 100],
            ['days_ago' => 28, 'uptime' => 91.67],
            ['days_ago' => 29, 'uptime' => 100],
        ]);

        // 80% uptime (problematic service)
        $monitor3 = $this->createMonitor([
            'name' => 'Unstable External API',
            'url' => 'https://example.com/external/flaky/status',
            'method' => 'GET',
            'interval' => 300,
        ]);
        $this->createChecksHistory($monitor3, hours: 24, downPeriods: []);
        $this->createCustomRollups($monitor3, [
            ['days_ago' => 0, 'uptime' => 100],
            ['days_ago' => 1, 'uptime' => 100],
            ['days_ago' => 2, 'uptime' => 87.50],
            ['days_ago' => 3, 'uptime' => 100],
            ['days_ago' => 4, 'uptime' => 100],
            ['days_ago' => 5, 'uptime' => 79.17],
            ['days_ago' => 6, 'uptime' => 100],
            ['days_ago' => 7, 'uptime' => 100],
            ['days_ago' => 8, 'uptime' => 83.33],
            ['days_ago' => 9, 'uptime' => 100],
            ['days_ago' => 10, 'uptime' => 100],
            ['days_ago' => 11, 'uptime' => 87.50],
            ['days_ago' => 12, 'uptime' => 100],
            ['days_ago' => 13, 'uptime' => 100],
            ['days_ago' => 14, 'uptime' => 79.17],
            ['days_ago' => 15, 'uptime' => 100],
            ['days_ago' => 16, 'uptime' => 100],
            ['days_ago' => 17, 'uptime' => 83.33],
            ['days_ago' => 18, 'uptime' => 100],
            ['days_ago' => 19, 'uptime' => 100],
            ['days_ago' => 20, 'uptime' => 75.00],
            ['days_ago' => 21, 'uptime' => 100],
            ['days_ago' => 22, 'uptime' => 100],
            ['days_ago' => 23, 'uptime' => 87.50],
            ['days_ago' => 24, 'uptime' => 100],
            ['days_ago' => 25, 'uptime' => 100],
            ['days_ago' => 26, 'uptime' => 79.17],
            ['days_ago' => 27, 'uptime' => 100],
            ['days_ago' => 28, 'uptime' => 100],
            ['days_ago' => 29, 'uptime' => 83.33],
        ]);

        // Recently recovered (was down, now up)
        $monitor4 = $this->createMonitor([
            'name' => 'Recovered Service',
            'url' => 'https://example.com/recovered/api',
            'method' => 'GET',
            'interval' => 60,
        ]);
        $this->createChecksHistory($monitor4, hours: 12, downPeriods: [
            ['start' => now()->subHours(6), 'end' => now()->subHours(1)],
        ]);
        $this->createCustomRollups($monitor4, [
            ['days_ago' => 0, 'uptime' => 79.17], // Today had 5 hours of downtime
            ['days_ago' => 1, 'uptime' => 100],
            ['days_ago' => 2, 'uptime' => 100],
            ['days_ago' => 3, 'uptime' => 100],
            ['days_ago' => 4, 'uptime' => 100],
            ['days_ago' => 5, 'uptime' => 100],
            ['days_ago' => 6, 'uptime' => 100],
        ]);
        // Add resolved incident
        Incident::factory()->create([
            'monitor_id' => $monitor4->id,
            'started_at' => now()->subHours(6),
            'ended_at' => now()->subHours(1),
            'cause' => 'Memory exhaustion - service restarted',
        ]);
    }

    /**
     * Monitors with different check intervals.
     */
    private function createMonitorsWithDifferentIntervals(): void
    {
        $intervals = [
            ['interval' => 60, 'name' => '1 Minute Check', 'url' => 'https://example.com/check/fast'],
            ['interval' => 300, 'name' => '5 Minute Check', 'url' => 'https://example.com/check/standard'],
            ['interval' => 900, 'name' => '15 Minute Check', 'url' => 'https://example.com/check/slow'],
            ['interval' => 3600, 'name' => 'Hourly Check', 'url' => 'https://example.com/check/hourly'],
            ['interval' => 86400, 'name' => 'Daily Check', 'url' => 'https://example.com/check/daily'],
        ];

        foreach ($intervals as $data) {
            $monitor = $this->createMonitor([
                'name' => $data['name'],
                'url' => $data['url'],
                'method' => 'GET',
                'interval' => $data['interval'],
            ]);
            $this->createChecksHistory($monitor, hours: 24, downPeriods: []);
            $this->createPerfectRollups($monitor, days: 7);
        }
    }

    /**
     * Monitors with extensive incident history.
     */
    private function createMonitorsWithIncidentHistory(): void
    {
        // Many resolved incidents
        $monitor1 = $this->createMonitor([
            'name' => 'Incident Prone Service',
            'url' => 'https://example.com/incidents/health',
            'method' => 'GET',
            'interval' => 300,
        ]);
        $this->createChecksHistory($monitor1, hours: 24, downPeriods: []);
        $this->createPerfectRollups($monitor1, days: 30);

        $causes = [
            'Connection refused - port not listening',
            'HTTP 500 Internal Server Error',
            'HTTP 502 Bad Gateway',
            'HTTP 503 Service Unavailable',
            'HTTP 504 Gateway Timeout',
            'SSL certificate verification failed',
            'DNS resolution failed',
            'Connection timeout after 30s',
            'Response body mismatch',
            'Unexpected status code 404',
        ];

        for ($i = 0; $i < 15; $i++) {
            $startedAt = now()->subDays(rand(1, 29))->subHours(rand(0, 23));
            $duration = rand(5, 180); // 5 minutes to 3 hours
            Incident::factory()->create([
                'monitor_id' => $monitor1->id,
                'started_at' => $startedAt,
                'ended_at' => $startedAt->copy()->addMinutes($duration),
                'cause' => $causes[array_rand($causes)],
            ]);
        }

        // Single long incident (extended outage)
        $monitor2 = $this->createMonitor([
            'name' => 'Major Outage History',
            'url' => 'https://example.com/outage/status',
            'method' => 'GET',
            'interval' => 300,
        ]);
        $this->createChecksHistory($monitor2, hours: 24, downPeriods: []);
        $this->createRollupsWithOutages($monitor2, days: 30, outagePercentage: 7);
        Incident::factory()->create([
            'monitor_id' => $monitor2->id,
            'started_at' => now()->subDays(20),
            'ended_at' => now()->subDays(18),
            'cause' => 'Database server failure - emergency maintenance',
        ]);
    }

    /**
     * Edge cases for comprehensive testing.
     */
    private function createEdgeCaseMonitors(): void
    {
        // Very long URL
        $monitor1 = $this->createMonitor([
            'name' => 'Long URL Service',
            'url' => 'https://example.com/api/v1/health/check?token=abc123def456ghi789jkl012mno345pqr678stu901vwx234yz&region=us-east-1&cluster=primary&environment=production',
            'method' => 'GET',
            'interval' => 300,
        ]);
        $this->createChecksHistory($monitor1, hours: 6, downPeriods: []);
        $this->createPerfectRollups($monitor1, days: 7);

        // Very long name
        $monitor2 = $this->createMonitor([
            'name' => 'Super Important Production Critical Infrastructure Health Monitoring Endpoint',
            'url' => 'https://example.com/critical/health',
            'method' => 'GET',
            'interval' => 60,
        ]);
        $this->createChecksHistory($monitor2, hours: 6, downPeriods: []);
        $this->createPerfectRollups($monitor2, days: 7);

        // HEAD method
        $monitor3 = $this->createMonitor([
            'name' => 'HEAD Request Monitor',
            'url' => 'https://example.com/head',
            'method' => 'HEAD',
            'interval' => 300,
        ]);
        $this->createChecksHistory($monitor3, hours: 6, downPeriods: []);
        $this->createPerfectRollups($monitor3, days: 7);

        // Custom expected status code (201)
        $monitor4 = $this->createMonitor([
            'name' => 'Custom Status Code',
            'url' => 'https://example.com/custom/create',
            'method' => 'GET',
            'interval' => 600,
            'expected_status_code' => 201,
        ]);
        $this->createChecksHistory($monitor4, hours: 6, downPeriods: []);
        $this->createPerfectRollups($monitor4, days: 7);

        // Custom timeout
        $monitor5 = $this->createMonitor([
            'name' => 'Slow Endpoint',
            'url' => 'https://example.com/slow/heavy-computation',
            'method' => 'GET',
            'interval' => 900,
            'timeout' => 60,
        ]);
        $this->createChecksHistory($monitor5, hours: 12, downPeriods: [], avgResponseTime: 3000, responseTimeVariance: 2000);
        $this->createPerfectRollups($monitor5, days: 7, avgResponseTime: 3000);

        // Monitor with very fast response times
        $monitor6 = $this->createMonitor([
            'name' => 'Edge Cache',
            'url' => 'https://example.com/edge/cached',
            'method' => 'GET',
            'interval' => 60,
        ]);
        $this->createChecksHistory($monitor6, hours: 6, downPeriods: [], avgResponseTime: 15, responseTimeVariance: 10);
        $this->createPerfectRollups($monitor6, days: 7, avgResponseTime: 15);

        // Monitor with only today's data (new monitor)
        $monitor7 = $this->createMonitor([
            'name' => 'Just Started Today',
            'url' => 'https://example.com/new/status',
            'method' => 'GET',
            'interval' => 300,
        ]);
        $this->createChecksHistory($monitor7, hours: 6, downPeriods: []);
        $this->createPerfectRollups($monitor7, days: 1);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createMonitor(array $data): Monitor
    {
        $defaults = [
            'id' => Str::uuid7()->toString(),
            'user_id' => $this->user->uuid,
            'method' => 'GET',
            'interval' => 300,
            'timeout' => 30,
            'expected_status_code' => 200,
            'is_active' => true,
            'last_checked_at' => now(),
            'next_check_at' => now()->addSeconds($data['interval'] ?? 300),
        ];

        // Use withoutEvents to prevent CheckMonitor job from being dispatched
        $monitor = Monitor::withoutEvents(function () use ($defaults, $data) {
            return Monitor::create(array_merge($defaults, $data));
        });

        $this->monitors[] = $monitor;

        return $monitor;
    }

    /**
     * Create check history for a monitor using batch inserts.
     *
     * @param  array<array{start: Carbon, end: Carbon|null}>  $downPeriods
     */
    private function createChecksHistory(
        Monitor $monitor,
        int $hours,
        array $downPeriods,
        ?Carbon $stopAt = null,
        int $avgResponseTime = 150,
        int $responseTimeVariance = 100
    ): void {
        $now = $stopAt ?? now();
        $startTime = $now->copy()->subHours($hours);
        $currentTime = $startTime->copy();

        $checks = [];
        $errorMessages = [
            'Connection timeout',
            'Connection refused',
            'Service unavailable',
            'Internal server error',
        ];
        $downStatusCodes = [0, 500, 502, 503, 504];

        while ($currentTime <= $now) {
            $isDown = false;
            foreach ($downPeriods as $period) {
                if ($currentTime >= $period['start'] && ($period['end'] === null || $currentTime <= $period['end'])) {
                    $isDown = true;
                    break;
                }
            }

            $check = [
                'id' => Str::uuid7()->toString(),
                'monitor_id' => $monitor->id,
                'checked_at' => $currentTime->copy(),
                'created_at' => $currentTime->copy(),
                'updated_at' => $currentTime->copy(),
            ];

            if ($isDown) {
                $check['status'] = 'down';
                $check['status_code'] = $downStatusCodes[array_rand($downStatusCodes)];
                $check['response_time_ms'] = null;
                $check['error_message'] = $errorMessages[array_rand($errorMessages)];
            } else {
                $check['status'] = 'up';
                $check['status_code'] = 200;
                $check['response_time_ms'] = max(10, $avgResponseTime + rand(-$responseTimeVariance, $responseTimeVariance));
                $check['error_message'] = null;
            }

            $checks[] = $check;
            $currentTime->addSeconds($monitor->interval);

            // Batch insert every 500 records
            if (count($checks) >= 500) {
                MonitorCheck::insert($checks);
                $checks = [];
            }
        }

        // Insert remaining checks
        if (count($checks) > 0) {
            MonitorCheck::insert($checks);
        }

        // Update monitor timestamps
        $latestCheck = $monitor->checks()->latest('checked_at')->first();
        if ($latestCheck) {
            $monitor->update([
                'last_checked_at' => $latestCheck->checked_at,
            ]);
        }
    }

    /**
     * Create perfect uptime rollups.
     */
    private function createPerfectRollups(Monitor $monitor, int $days, int $avgResponseTime = 150): void
    {
        $rollups = [];
        for ($i = 0; $i < $days; $i++) {
            $checksPerDay = (int) (86400 / $monitor->interval);
            $rollups[] = [
                'id' => Str::uuid7()->toString(),
                'monitor_id' => $monitor->id,
                'date' => now()->subDays($i)->toDateString(),
                'total_checks' => $checksPerDay,
                'successful_checks' => $checksPerDay,
                'uptime_percentage' => 100.00,
                'avg_response_time_ms' => $avgResponseTime + rand(-20, 20),
                'min_response_time_ms' => max(10, $avgResponseTime - 50),
                'max_response_time_ms' => $avgResponseTime + 100,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DailyUptimeRollup::insert($rollups);
    }

    /**
     * Create rollups with simulated outages.
     */
    private function createRollupsWithOutages(Monitor $monitor, int $days, float $outagePercentage): void
    {
        $rollups = [];
        for ($i = 0; $i < $days; $i++) {
            $checksPerDay = (int) (86400 / $monitor->interval);
            $failedChecks = (int) ($checksPerDay * ($outagePercentage / 100));
            $successfulChecks = $checksPerDay - $failedChecks;
            $uptime = round(($successfulChecks / $checksPerDay) * 100, 2);

            $rollups[] = [
                'id' => Str::uuid7()->toString(),
                'monitor_id' => $monitor->id,
                'date' => now()->subDays($i)->toDateString(),
                'total_checks' => $checksPerDay,
                'successful_checks' => $successfulChecks,
                'uptime_percentage' => $uptime,
                'avg_response_time_ms' => rand(100, 300),
                'min_response_time_ms' => rand(50, 100),
                'max_response_time_ms' => rand(300, 600),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DailyUptimeRollup::insert($rollups);
    }

    /**
     * Create rollups with custom uptime values per day.
     *
     * @param  array<array{days_ago: int, uptime: float}>  $uptimeData
     */
    private function createCustomRollups(Monitor $monitor, array $uptimeData): void
    {
        $rollups = [];
        foreach ($uptimeData as $data) {
            $checksPerDay = (int) (86400 / $monitor->interval);
            $successfulChecks = (int) ($checksPerDay * ($data['uptime'] / 100));

            $rollups[] = [
                'id' => Str::uuid7()->toString(),
                'monitor_id' => $monitor->id,
                'date' => now()->subDays($data['days_ago'])->toDateString(),
                'total_checks' => $checksPerDay,
                'successful_checks' => $successfulChecks,
                'uptime_percentage' => $data['uptime'],
                'avg_response_time_ms' => rand(100, 300),
                'min_response_time_ms' => rand(50, 100),
                'max_response_time_ms' => rand(300, 600),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DailyUptimeRollup::insert($rollups);
    }

    /**
     * Create notifiers covering all scenarios.
     */
    private function createNotifiers(): void
    {
        // Default Discord notifier (apply_to_all)
        Notifier::factory()->discord()->create([
            'user_id' => $this->user->uuid,
            'name' => 'Team Alerts',
            'is_default' => true,
            'apply_to_all' => true,
            'config' => [
                'webhook_url' => 'https://discord.com/api/webhooks/123456789012345678/abcdefghijklmnopqrstuvwxyz',
            ],
        ]);

        // Active Discord notifier attached to specific monitors
        $discordSpecific = Notifier::factory()->discord()->create([
            'user_id' => $this->user->uuid,
            'name' => 'Critical Services',
            'is_default' => false,
            'apply_to_all' => false,
            'config' => [
                'webhook_url' => 'https://discord.com/api/webhooks/987654321098765432/zyxwvutsrqponmlkjihgfedcba',
            ],
        ]);
        // Attach to first 3 monitors
        $discordSpecific->monitors()->attach(array_slice(array_column($this->monitors, 'id'), 0, 3));

        // Inactive Discord notifier
        Notifier::factory()->discord()->inactive()->create([
            'user_id' => $this->user->uuid,
            'name' => 'Old Webhook (Disabled)',
            'is_default' => false,
            'apply_to_all' => false,
            'config' => [
                'webhook_url' => 'https://discord.com/api/webhooks/111111111111111111/oldwebhooktoken',
            ],
        ]);

        // Active email notifier (apply_to_all)
        Notifier::factory()->email()->create([
            'user_id' => $this->user->uuid,
            'name' => 'On-Call Email',
            'is_default' => false,
            'apply_to_all' => true,
            'config' => [
                'email' => 'oncall@example.com',
            ],
        ]);

        // Active email notifier attached to specific monitors
        $emailSpecific = Notifier::factory()->email()->create([
            'user_id' => $this->user->uuid,
            'name' => 'Payment Alerts',
            'is_default' => false,
            'apply_to_all' => false,
            'config' => [
                'email' => 'payments@example.com',
            ],
        ]);
        // Attach to monitors 4-6
        $emailSpecific->monitors()->attach(array_slice(array_column($this->monitors, 'id'), 3, 3));

        // Inactive email notifier
        Notifier::factory()->email()->inactive()->create([
            'user_id' => $this->user->uuid,
            'name' => 'Legacy Email (Disabled)',
            'is_default' => false,
            'apply_to_all' => false,
            'config' => [
                'email' => 'legacy@example.com',
            ],
        ]);

        // Notifier with no monitors attached (not apply_to_all)
        Notifier::factory()->discord()->create([
            'user_id' => $this->user->uuid,
            'name' => 'Unused Webhook',
            'is_default' => false,
            'apply_to_all' => false,
            'config' => [
                'webhook_url' => 'https://discord.com/api/webhooks/222222222222222222/unusedtoken',
            ],
        ]);
    }
}
