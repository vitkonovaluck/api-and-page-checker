<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Site;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class StoreCheckRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        $site = $this->route('site');

        return $site instanceof Site && ($this->user()?->can('update', $site) ?? false);
    }

    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'address_ids' => ['nullable', 'array'],
            'address_ids.*' => ['integer', 'distinct', 'exists:addresses,id'],
        ];
    }

    /**
     * @return list<int>
     */
    public function addressIds(): array
    {
        $ids = $this->validated('address_ids') ?? [];

        return array_map(intval(...), $ids);
    }
}
