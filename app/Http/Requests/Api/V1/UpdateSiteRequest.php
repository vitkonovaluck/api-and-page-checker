<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Site;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateSiteRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'base_url' => ['sometimes', 'required', 'url', 'max:2048'],
        ];
    }
}
