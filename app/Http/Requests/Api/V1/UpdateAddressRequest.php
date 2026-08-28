<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\AddressKind;
use App\Models\Address;
use App\Models\Site;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateAddressRequest extends FormRequest
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
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'endpoint' => ['sometimes', 'required', 'string', 'max:'.(int) config('checking.address_endpoint_max', 766)],
            'http_method' => ['sometimes', 'string', Rule::in(Address::METHODS)],
            'kind' => ['sometimes', 'string', Rule::enum(AddressKind::class)],
            'ignore_json_paths' => ['sometimes', 'array'],
            'ignore_json_paths.*' => ['string', 'max:255'],
            'ignore_headers' => ['sometimes', 'array'],
            'ignore_headers.*' => ['string', 'max:255'],
            'watch_json_paths' => ['sometimes', 'array'],
            'watch_json_paths.*' => ['string', 'max:255'],
            'assertions' => ['sometimes', 'array'],
            'step_order' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'extract_json_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'extract_as' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }
}
