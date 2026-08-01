<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasNotifierRules;
use App\Models\Notifier;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNotifierRequest extends FormRequest
{
    use HasNotifierRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->baseNotifierRules(true);
    }

    /**
     * Switching type has no stored value to fall back on — the incoming config
     * must carry the new type's key, or the notifier would end up with a config
     * that CheckMonitor treats as invalid.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $notifier = $this->route('notifier');
            $type = $this->input('type');

            if (! $notifier instanceof Notifier || $type === null || $type === $notifier->type) {
                return;
            }

            [$key, $message] = match ($type) {
                Notifier::TYPE_DISCORD => ['config.webhook_url', 'A Discord webhook URL is required.'],
                Notifier::TYPE_EMAIL => ['config.email', 'An email address is required.'],
                default => [null, null],
            };

            if ($key === null || $validator->errors()->has($key) || filled($this->input($key))) {
                return;
            }

            $validator->errors()->add($key, $message);
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->baseNotifierMessages();
    }
}
