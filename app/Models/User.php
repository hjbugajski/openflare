<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->uuid)) {
                $user->uuid = \Illuminate\Support\Str::uuid7();
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'preferences',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'preferences' => 'array',
        ];
    }

    /**
     * Get a user preference value.
     */
    public function getPreference(string $key, mixed $default = null): mixed
    {
        return data_get($this->preferences, $key, $default);
    }

    /**
     * Set a user preference value.
     */
    public function setPreference(string $key, mixed $value): void
    {
        $preferences = $this->preferences ?? [];
        data_set($preferences, $key, $value);
        $this->preferences = $preferences;
    }

    /**
     * @return HasMany<Monitor, $this>
     */
    public function monitors(): HasMany
    {
        return $this->hasMany(Monitor::class, 'user_id', 'uuid');
    }

    /**
     * @return HasMany<Notifier, $this>
     */
    public function notifiers(): HasMany
    {
        return $this->hasMany(Notifier::class, 'user_id', 'uuid');
    }
}
