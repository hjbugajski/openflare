<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Notifier extends Model
{
    /** @use HasFactory<\Database\Factories\NotifierFactory> */
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected static function booted(): void
    {
        static::creating(function (Notifier $notifier) {
            if (empty($notifier->id)) {
                $notifier->id = \Illuminate\Support\Str::uuid7();
            }
        });
    }

    public const TYPE_DISCORD = 'discord';

    public const TYPE_EMAIL = 'email';

    public const TYPES = [
        self::TYPE_DISCORD,
        self::TYPE_EMAIL,
    ];

    protected $fillable = [
        'user_id',
        'type',
        'name',
        'config',
        'is_active',
        'is_default',
        'apply_to_all',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'apply_to_all' => 'boolean',
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
     * @return BelongsToMany<Monitor, $this>
     */
    public function monitors(): BelongsToMany
    {
        return $this->belongsToMany(Monitor::class);
    }

    public function getWebhookUrl(): ?string
    {
        return $this->config['webhook_url'] ?? null;
    }

    public function getEmail(): ?string
    {
        return $this->config['email'] ?? null;
    }
}
