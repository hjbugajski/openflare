<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasNotifierRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNotifierRequest extends FormRequest
{
    use HasNotifierRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $notifier = $this->route('notifier');
        $type = $this->input('type', $notifier?->type);

        return $this->baseNotifierRules(true, $type);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->baseNotifierMessages();
    }
}
