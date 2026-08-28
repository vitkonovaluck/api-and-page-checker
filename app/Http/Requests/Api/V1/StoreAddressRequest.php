<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\AddressKind;
use App\Models\Address;
use App\Models\Site;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreAddressRequest extends FormRequest
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
            'name' => ['nullable', 'string', 'max:255'],
            'endpoint' => ['required', 'string', 'max:'.(int) config('checking.address_endpoint_max', 766)],
            'http_method' => ['nullable', 'string', Rule::in(Address::METHODS)],
            'kind' => ['nullable', 'string', Rule::enum(AddressKind::class)],
            'request_headers' => ['nullable', 'array'],
            'request_body' => ['nullable', 'string'],
            'ignore_json_paths' => ['nullable', 'array'],
            'ignore_json_paths.*' => ['string', 'max:255'],
            'ignore_headers' => ['nullable', 'array'],
            'ignore_headers.*' => ['string', 'max:255'],
            'watch_json_paths' => ['nullable', 'array'],
            'watch_json_paths.*' => ['string', 'max:255'],
            'assertions' => ['nullable', 'array'],
            'step_order' => ['nullable', 'integer', 'min:1'],
            'extract_json_path' => ['nullable', 'string', 'max:255'],
            'extract_as' => ['nullable', 'string', 'max:64'],
        ];
    }
}
