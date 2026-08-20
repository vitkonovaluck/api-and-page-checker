@if (in_array(strtoupper($http_method), \App\Models\Address::METHODS_WITH_BODY, true))
    <div>
        <label for="request-body-field" class="mb-1 block text-sm font-medium text-zinc-200">Request body</label>
        <p class="mb-1 text-xs text-zinc-400">Тіло запиту для {{ strtoupper($http_method) }} (наприклад JSON)</p>
        <textarea
            id="request-body-field"
            wire:model="request_body"
            rows="5"
            placeholder='{"key": "value"}'
            class="w-full rounded-lg border border-white/15 bg-zinc-950 px-3 py-2 font-mono text-sm text-zinc-100 shadow-sm focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-400/30"
        ></textarea>
        @error('request_body') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
    </div>
@endif
