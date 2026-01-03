<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyUptimeRollup extends Model
{
    /** @use HasFactory<\Database\Factories\DailyUptimeRollupFactory> */
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected static function booted(): void
    {
        static::creating(function (DailyUptimeRollup $rollup) {
            if (empty($rollup->id)) {
                $rollup->id = \Illuminate\Support\Str::uuid7();
            }
        });
    }

    protected $fillable = [
        'monitor_id',
        'date',
        'total_checks',
        'successful_checks',
        'uptime_percentage',
        'avg_response_time_ms',
        'min_response_time_ms',
        'max_response_time_ms',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'total_checks' => 'integer',
            'successful_checks' => 'integer',
            'uptime_percentage' => 'decimal:2',
            'avg_response_time_ms' => 'integer',
            'min_response_time_ms' => 'integer',
            'max_response_time_ms' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Monitor, $this>
     */
    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }
}
