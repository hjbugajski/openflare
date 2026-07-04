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
    protected function baseNotifierRules(bool $sometimes, ?string $type): array
    {
        $wrap = fn (array $rules) => $sometimes ? ['sometimes', ...$rules] : $rules;

        return [
            'name' => $wrap(['required', 'string', 'max:255']),
            'type' => $wrap(['required', 'string', Rule::in(Notifier::TYPES)]),
            'is_active' => $sometimes ? ['sometimes', 'boolean'] : ['boolean'],
            'is_default' => $sometimes ? ['sometimes', 'boolean'] : ['boolean'],

            // Monitor associations
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

            // Discord-specific
            'config.webhook_url' => [
                Rule::requiredIf($type === Notifier::TYPE_DISCORD),
                'nullable',
                'url',
                'regex:'.Notifier::DISCORD_WEBHOOK_URL_REGEX,
            ],

            // Email-specific
            'config.email' => [
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
            'config.webhook_url.regex' => 'Please enter a valid Discord webhook URL.',
            'config.email.required' => 'An email address is required.',
        ];
    }
}
