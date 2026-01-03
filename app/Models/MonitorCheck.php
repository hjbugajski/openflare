<?php

declare(strict_types=1);

namespace App\Models;

use App\MonitorStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitorCheck extends Model
{
    /** @use HasFactory<\Database\Factories\MonitorCheckFactory> */
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected static function booted(): void
    {
        static::creating(function (MonitorCheck $check) {
            if (empty($check->id)) {
                $check->id = \Illuminate\Support\Str::uuid7();
            }
        });
    }

    protected $fillable = [
        'monitor_id',
        'status',
        'status_code',
        'response_time_ms',
        'error_message',
        'checked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'response_time_ms' => 'integer',
            'checked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Monitor, $this>
     */
    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    public function isUp(): bool
    {
        return $this->status === MonitorStatus::Up->value;
    }

    public function isDown(): bool
    {
        return $this->status === MonitorStatus::Down->value;
    }
}
