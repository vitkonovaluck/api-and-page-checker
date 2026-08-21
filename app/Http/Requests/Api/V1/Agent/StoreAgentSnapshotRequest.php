<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Agent;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class StoreAgentSnapshotRequest extends FormRequest
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
            'address_id' => ['required', 'integer', 'exists:addresses,id'],
            'status_code' => ['nullable', 'integer', 'min:100', 'max:599'],
            'headers' => ['nullable', 'array'],
            'body' => ['nullable', 'string', 'max:'.$this->maxBodyChars()],
            'response_time_ms' => ['required', 'integer', 'min:0'],
            'timing' => ['nullable', 'array'],
            'error_message' => ['nullable', 'string', 'max:5000'],
        ];
    }

    private function maxBodyChars(): int
    {
        return max(1, (int) config('checking.agent_snapshot_body_max_kb', 1024) * 1024);
    }
}
