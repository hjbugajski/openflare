<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\Monitor;
use App\Models\Notifier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

trait HasNotifierRules
{
    /**
     * @return array<string, array<mixed>>
     */
    protected function baseNotifierRules(bool $sometimes, ?string $type = null): array
    {
        $wrap = fn (array $rules) => $sometimes ? ['sometimes', ...$rules] : $rules;

        return [
            'name' => $wrap(['required', 'string', 'max:255']),
            'type' => $wrap(['required', 'string', Rule::in(Notifier::TYPES)]),
            'is_active' => $sometimes ? ['sometimes', 'boolean'] : ['boolean'],
            'is_default' => $sometimes ? ['sometimes', 'boolean'] : ['boolean'],

            'apply_to_existing' => ['boolean'],
            'monitors' => ['array'],
            'monitors.*' => [
                'string',
                Rule::exists(Monitor::class, 'id')->where('user_id', Auth::user()->uuid),
            ],
            'excluded_monitors' => ['array'],
            'excluded_monitors.*' => [
                'string',
                Rule::exists(Monitor::class, 'id')->where('user_id', Auth::user()->uuid),
            ],

            /*
             * On update the client never receives the stored credential, so an
             * absent key means "keep it". `filled` rejects an explicitly empty
             * one: clearing a Discord webhook would make CheckMonitor skip the
             * notifier silently instead of reporting a failure.
             */
            'config.webhook_url' => $sometimes
                ? ['sometimes', 'filled', 'url', 'regex:'.Notifier::DISCORD_WEBHOOK_URL_REGEX]
                : [
                    Rule::requiredIf($type === Notifier::TYPE_DISCORD),
                    'nullable',
                    'url',
                    'regex:'.Notifier::DISCORD_WEBHOOK_URL_REGEX,
                ],

            'config.email' => $sometimes
                ? ['sometimes', 'filled', 'email', 'max:255']
                : [
                    Rule::requiredIf($type === Notifier::TYPE_EMAIL),
                    'nullable',
                    'email',
                    'max:255',
                ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function baseNotifierMessages(): array
    {
        return [
            'config.webhook_url.required' => 'A Discord webhook URL is required.',
            'config.webhook_url.filled' => 'A Discord webhook URL is required.',
            'config.webhook_url.regex' => 'Please enter a valid Discord webhook URL.',
            'config.email.required' => 'An email address is required.',
            'config.email.filled' => 'An email address is required.',
        ];
    }
}
