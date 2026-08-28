<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Site;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class ShowSnapshotDiffRequest extends FormRequest
{
    public function authorize(): bool
    {
        $site = $this->route('site');

        return $site instanceof Site && ($this->user()?->can('view', $site) ?? false);
    }

    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'integer', 'exists:snapshots,id'],
            'to' => ['required', 'integer', 'exists:snapshots,id', 'different:from'],
        ];
    }
}
