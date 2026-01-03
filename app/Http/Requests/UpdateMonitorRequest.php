<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Monitor;
use App\Rules\SafeUrl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMonitorRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'url' => ['sometimes', 'required', 'url', 'max:2048', new SafeUrl],
            'method' => ['sometimes', 'required', 'string', Rule::in(Monitor::METHODS)],
            'interval' => ['sometimes', 'required', 'integer', Rule::in(Monitor::INTERVALS)],
            'timeout' => ['sometimes', 'required', 'integer', 'min:5', 'max:120'],
            'expected_status_code' => ['sometimes', 'required', 'integer', 'min:100', 'max:599'],
            'is_active' => ['sometimes', 'boolean'],
            'notifiers' => ['sometimes', 'array'],
            'notifiers.*' => [
                'string',
                Rule::exists('notifiers', 'id')
                    ->where('user_id', $this->user()->uuid)
                    ->where('is_active', true),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'url.url' => 'Please enter a valid URL including the protocol (e.g., https://example.com).',
            'interval.in' => 'Please select a valid check interval.',
            'method.in' => 'Please select a valid HTTP method.',
        ];
    }
}
