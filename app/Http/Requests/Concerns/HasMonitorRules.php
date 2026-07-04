<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\Monitor;
use App\Rules\SafeUrl;
use Illuminate\Validation\Rule;

trait HasMonitorRules
{
    /**
     * @return array<string, array<mixed>>
     */
    protected function baseMonitorRules(bool $sometimes): array
    {
        $wrap = fn (array $rules) => $sometimes ? ['sometimes', ...$rules] : $rules;

        return [
            'name' => $wrap(['required', 'string', 'max:255']),
            'url' => $wrap(['required', 'url', 'max:2048', new SafeUrl]),
            'method' => $wrap(['required', 'string', Rule::in(Monitor::METHODS)]),
            'interval' => $wrap(['required', 'integer', Rule::in(Monitor::INTERVALS)]),
            'timeout' => $wrap(['required', 'integer', 'min:5', 'max:120']),
            'expected_status_code' => $wrap(['required', 'integer', 'min:100', 'max:599']),
            'failure_confirmation_threshold' => $wrap(['required', 'integer', 'min:1', 'max:10']),
            'recovery_confirmation_threshold' => $wrap(['required', 'integer', 'min:1', 'max:10']),
            'is_active' => $sometimes ? ['sometimes', 'boolean'] : ['boolean'],
            'notifiers' => $sometimes ? ['sometimes', 'array'] : ['array'],
            'notifiers.*' => [
                'string',
                Rule::exists('notifiers', 'id')
                    ->where('user_id', $this->user()->uuid)
                    ->where('is_active', true),
            ],
        ];
    }
}
