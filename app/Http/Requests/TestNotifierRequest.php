<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Notifier;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TestNotifierRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(Notifier::TYPES)],

            // Discord-specific
            'config.webhook_url' => [
                'required_if:type,discord',
                'nullable',
                'url',
                'regex:/^https:\/\/discord\.com\/api\/webhooks\//',
            ],

            // Email-specific
            'config.email' => [
                'required_if:type,email',
                'nullable',
                'email',
                'max:255',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'config.webhook_url.required_if' => 'A Discord webhook URL is required.',
            'config.webhook_url.regex' => 'Please enter a valid Discord webhook URL.',
            'config.email.required_if' => 'An email address is required.',
        ];
    }
}
