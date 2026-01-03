<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Monitor;
use Illuminate\Http\JsonResponse;

class MonitorController extends Controller
{
    public function show(Monitor $monitor): JsonResponse
    {
        $this->authorize('view', $monitor);

        $monitor->load(['latestCheck', 'currentIncident']);

        return response()->json([
            'id' => $monitor->id,
            'name' => $monitor->name,
            'url' => $monitor->url,
            'method' => $monitor->method,
            'interval' => $monitor->interval,
            'is_active' => $monitor->is_active,
            'status' => $monitor->latestCheck?->status ?? 'pending',
            'latest_check' => $monitor->latestCheck ? [
                'id' => $monitor->latestCheck->id,
                'status' => $monitor->latestCheck->status,
                'status_code' => $monitor->latestCheck->status_code,
                'response_time_ms' => $monitor->latestCheck->response_time_ms,
                'error_message' => $monitor->latestCheck->error_message,
                'checked_at' => $monitor->latestCheck->checked_at->toIso8601String(),
            ] : null,
            'current_incident' => $monitor->currentIncident ? [
                'id' => $monitor->currentIncident->id,
                'started_at' => $monitor->currentIncident->started_at->toIso8601String(),
                'cause' => $monitor->currentIncident->cause,
            ] : null,
        ]);
    }

    public function checks(Monitor $monitor): JsonResponse
    {
        $this->authorize('view', $monitor);

        $checks = $monitor->checks()
            ->latest('checked_at')
            ->limit(100)
            ->get()
            ->map(fn ($check) => [
                'id' => $check->id,
                'status' => $check->status,
                'status_code' => $check->status_code,
                'response_time_ms' => $check->response_time_ms,
                'error_message' => $check->error_message,
                'checked_at' => $check->checked_at->toIso8601String(),
            ]);

        return response()->json(['checks' => $checks]);
    }

    public function rollups(Monitor $monitor): JsonResponse
    {
        $this->authorize('view', $monitor);

        $thirtyDaysAgo = now()->subDays(30)->toDateString();

        $rollups = $monitor->dailyUptimeRollups()
            ->where('date', '>=', $thirtyDaysAgo)
            ->orderBy('date')
            ->get()
            ->map(fn ($rollup) => [
                'date' => $rollup->date->toDateString(),
                'total_checks' => $rollup->total_checks,
                'successful_checks' => $rollup->successful_checks,
                'uptime_percentage' => $rollup->uptime_percentage,
                'avg_response_time_ms' => $rollup->avg_response_time_ms,
            ]);

        // Calculate overall stats using weighted average for response time
        $totalChecks = $rollups->sum('total_checks');
        $successfulChecks = $rollups->sum('successful_checks');
        $overallUptime = $totalChecks > 0
            ? round(($successfulChecks / $totalChecks) * 100, 2)
            : null;

        // Weighted average: sum(avg_response_time * total_checks) / total_checks
        $weightedResponseTimeSum = $rollups->sum(function ($rollup) {
            return ($rollup['avg_response_time_ms'] ?? 0) * $rollup['total_checks'];
        });
        $avgResponseTime = $totalChecks > 0
            ? round($weightedResponseTimeSum / $totalChecks)
            : null;

        return response()->json([
            'rollups' => $rollups,
            'summary' => [
                'total_checks' => $totalChecks,
                'successful_checks' => $successfulChecks,
                'uptime_percentage' => $overallUptime,
                'avg_response_time_ms' => $avgResponseTime,
            ],
        ]);
    }
}
