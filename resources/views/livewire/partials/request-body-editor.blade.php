@if (in_array(strtoupper($http_method), \App\Models\Address::METHODS_WITH_BODY, true))
    <div>
        <label for="request-body-field" class="mb-1 block text-sm font-medium text-slate-700">Request body</label>
        <p class="mb-1 text-xs text-slate-500">Тіло запиту для {{ strtoupper($http_method) }} (наприклад JSON)</p>
        <textarea
            id="request-body-field"
            wire:model="request_body"
            rows="5"
            placeholder='{"key": "value"}'
            class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
        ></textarea>
        @error('request_body') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>
@endif
