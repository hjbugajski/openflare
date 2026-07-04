<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->closeDuplicateOpenIncidents();

        DB::statement(
            'CREATE UNIQUE INDEX incidents_monitor_id_open_unique ON incidents (monitor_id) WHERE ended_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX incidents_monitor_id_open_unique');
    }

    /**
     * Close all but the earliest open incident per monitor so the new
     * partial unique index below does not fail against pre-existing
     * duplicate-open-incident rows (see plan 013 STOP condition).
     */
    private function closeDuplicateOpenIncidents(): void
    {
        $monitorIds = DB::table('incidents')
            ->whereNull('ended_at')
            ->select('monitor_id')
            ->groupBy('monitor_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('monitor_id');

        foreach ($monitorIds as $monitorId) {
            $openIncidents = DB::table('incidents')
                ->where('monitor_id', $monitorId)
                ->whereNull('ended_at')
                ->orderBy('started_at')
                ->orderBy('id')
                ->get();

            $keep = $openIncidents->first();

            foreach ($openIncidents->skip(1) as $duplicate) {
                DB::table('incidents')
                    ->where('id', $duplicate->id)
                    ->update([
                        'ended_at' => $duplicate->started_at,
                        'cause' => trim(($duplicate->cause ?? '').' [closed by 013 duplicate-incident cleanup]'),
                        'updated_at' => now(),
                    ]);
            }
        }
    }
};
