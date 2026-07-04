<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasNotifierRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreNotifierRequest extends FormRequest
{
    use HasNotifierRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->baseNotifierRules(false, $this->input('type'));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->baseNotifierMessages();
    }
}
