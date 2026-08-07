<?php

namespace App\Livewire\Concerns;

use App\Models\Address;
use Illuminate\Validation\Rule;

trait HandlesHttpMethodAndBody
{
    public string $http_method = 'GET';

    public string $request_body = '';

    /**
     * @return array<string, mixed>
     */
    protected function methodAndBodyRules(): array
    {
        return [
            'http_method' => ['required', 'string', Rule::in(Address::METHODS)],
            'request_body' => ['nullable', 'string', 'max:65535'],
        ];
    }

    protected function resolvedRequestBody(): ?string
    {
        if (! in_array(strtoupper($this->http_method), Address::METHODS_WITH_BODY, true)) {
            return null;
        }

        $body = $this->request_body;

        return $body === '' ? null : $body;
    }

    public function updatedHttpMethod(string $value): void
    {
        if (! in_array(strtoupper($value), Address::METHODS_WITH_BODY, true)) {
            $this->request_body = '';
        }
    }
}
