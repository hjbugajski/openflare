<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Notifier;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Throwable;

class TestNotifierRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(Notifier::TYPES)],

            'config.webhook_url' => [
                'required_if:type,discord',
                'nullable',
                'url',
                'regex:'.Notifier::DISCORD_WEBHOOK_URL_REGEX,
            ],

            'config.email' => [
                'required_if:type,email',
                'nullable',
                'email',
                'max:255',
                $this->ownedEmailRule(),
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

    /**
     * The test endpoint delivers a real, branded email, so the destination must
     * be the account address or one already saved on the user's own notifiers.
     *
     * This is a speed bump, not an ownership guarantee: saving a notifier
     * applies no ownership check of its own, so anyone willing to create an
     * email notifier first can still name any address. It blocks casual misuse
     * of the single-request path and would carry real weight if this ever
     * became multi-user; the daily rate limiter is the actual abuse backstop.
     */
    protected function ownedEmailRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || $value === '' || $this->ownsEmail($value)) {
                return;
            }

            $fail('You can only send a test email to your account email address or to an address already saved on one of your notifiers.');
        };
    }

    protected function ownsEmail(string $email): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $email = mb_strtolower(trim($email));

        if (mb_strtolower((string) $user->email) === $email) {
            return true;
        }

        return Notifier::query()
            ->where('user_id', $user->uuid)
            ->where('type', Notifier::TYPE_EMAIL)
            ->get()
            ->contains(function (Notifier $notifier) use ($email): bool {
                try {
                    // config is encrypted; an unreadable row simply grants nothing
                    return mb_strtolower((string) $notifier->getEmail()) === $email;
                } catch (Throwable) {
                    return false;
                }
            });
    }
}
