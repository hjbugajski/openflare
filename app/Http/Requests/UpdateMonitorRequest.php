<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasMonitorRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMonitorRequest extends FormRequest
{
    use HasMonitorRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->baseMonitorRules(true);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->baseMonitorMessages();
    }
}
