<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Incident extends Model
{
    /** @use HasFactory<\Database\Factories\IncidentFactory> */
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected static function booted(): void
    {
        static::creating(function (Incident $incident) {
            if (empty($incident->id)) {
                $incident->id = \Illuminate\Support\Str::uuid7();
            }
        });
    }

    protected $fillable = [
        'monitor_id',
        'started_at',
        'ended_at',
        'cause',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Monitor, $this>
     */
    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    public function isOngoing(): bool
    {
        return $this->ended_at === null;
    }

    public function isResolved(): bool
    {
        return $this->ended_at !== null;
    }
}
