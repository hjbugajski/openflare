<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Monitor extends Model
{
    /** @use HasFactory<\Database\Factories\MonitorFactory> */
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected static function booted(): void
    {
        static::observe(\App\Observers\MonitorObserver::class);

        static::creating(function (Monitor $monitor) {
            if (empty($monitor->id)) {
                $monitor->id = \Illuminate\Support\Str::uuid7();
            }
        });
    }

    public const INTERVALS = [
        60,     // 1 minute
        300,    // 5 minutes
        900,    // 15 minutes
        1800,   // 30 minutes
        3600,   // 1 hour
        10800,  // 3 hours
        21600,  // 6 hours
        43200,  // 12 hours
        86400,  // 24 hours
    ];

    public const METHODS = ['GET', 'HEAD'];

    protected $fillable = [
        'user_id',
        'name',
        'url',
        'method',
        'interval',
        'timeout',
        'expected_status_code',
        'is_active',
        'last_checked_at',
        'next_check_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'interval' => 'integer',
            'timeout' => 'integer',
            'expected_status_code' => 'integer',
            'is_active' => 'boolean',
            'last_checked_at' => 'datetime',
            'next_check_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
    }

    /**
     * @return HasMany<MonitorCheck, $this>
     */
    public function checks(): HasMany
    {
        return $this->hasMany(MonitorCheck::class);
    }

    /**
     * @return HasOne<MonitorCheck, $this>
     */
    public function latestCheck(): HasOne
    {
        return $this->hasOne(MonitorCheck::class)->latestOfMany('checked_at');
    }

    /**
     * @return HasMany<Incident, $this>
     */
    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    /**
     * @return HasOne<Incident, $this>
     */
    public function currentIncident(): HasOne
    {
        return $this->hasOne(Incident::class)->whereNull('ended_at')->latestOfMany('started_at');
    }

    /**
     * @return BelongsToMany<Notifier, $this>
     */
    public function notifiers(): BelongsToMany
    {
        return $this->belongsToMany(Notifier::class)->withPivot('is_excluded');
    }

    /**
     * Get notifiers that should receive notifications for this monitor.
     * Includes: explicitly attached (not excluded) + apply_to_all (not excluded).
     *
     * @return \Illuminate\Support\Collection<int, Notifier>
     */
    public function getEffectiveNotifiers(): \Illuminate\Support\Collection
    {
        $pivotData = $this->notifiers()->pluck('is_excluded', 'notifiers.id');
        $excludedIds = $pivotData->filter(fn (bool $excluded) => $excluded)->keys();
        $explicitIds = $pivotData->filter(fn (bool $excluded) => ! $excluded)->keys();

        return Notifier::query()
            ->where('user_id', $this->user_id)
            ->where('is_active', true)
            ->where(function ($query) use ($explicitIds) {
                $query->whereIn('id', $explicitIds)
                    ->orWhere('apply_to_all', true);
            })
            ->whereNotIn('id', $excludedIds)
            ->get();
    }

    /**
     * @return HasMany<DailyUptimeRollup, $this>
     */
    public function dailyUptimeRollups(): HasMany
    {
        return $this->hasMany(DailyUptimeRollup::class);
    }
}
