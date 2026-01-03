<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Monitor;
use App\Models\Notifier;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreNotifierRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(Notifier::TYPES)],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],

            // Monitor associations
            'apply_to_existing' => ['boolean'],
            'monitors' => ['array'],
            'monitors.*' => [
                'string',
                Rule::exists(Monitor::class, 'id')->where('user_id', Auth::user()->uuid),
            ],

            // Discord-specific
            'config.webhook_url' => [
                'required_if:type,discord',
                'nullable',
                'url',
                'regex:/^https:\/\/discord\.com\/api\/webhooks\/\d+\/[\w-]+$/',
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
