<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Agent;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class StartAgentCheckRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'address_ids' => ['required', 'array', 'min:1'],
            'address_ids.*' => ['integer', 'distinct', 'exists:addresses,id'],
        ];
    }

    /**
     * @return list<int>
     */
    public function addressIds(): array
    {
        /** @var list<int> $ids */
        $ids = array_map(intval(...), $this->validated('address_ids'));

        return $ids;
    }
}
