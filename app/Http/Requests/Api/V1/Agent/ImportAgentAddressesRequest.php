<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Agent;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class ImportAgentAddressesRequest extends FormRequest
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
        $maxItems = max(1, (int) config('checking.agent_import_addresses_max', 500));
        $maxRaw = max(1, (int) config('checking.agent_import_endpoint_raw_max', 2048));

        return [
            'endpoints' => ['required', 'array', 'min:1', 'max:'.$maxItems],
            'endpoints.*' => ['required', 'string', 'max:'.$maxRaw],
            'schedule_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
