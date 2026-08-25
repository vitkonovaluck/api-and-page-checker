<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Agent;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class StartExtensionAgentLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'hostname' => ['nullable', 'string', 'max:255'],
        ];
    }
}
