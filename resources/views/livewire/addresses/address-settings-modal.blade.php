<dialog
    x-data
    x-effect="$wire.show ? $el.showModal() : ($el.open && $el.close())"
    @click="if ($event.target === $el) $wire.close()"
    class="w-[calc(100%-2rem)] max-w-2xl rounded-2xl border border-white/10 bg-zinc-900 p-0 text-zinc-100 shadow-2xl backdrop:bg-zinc-950/70"
>
    <form wire:submit="save" class="flex max-h-[85vh] flex-col">
        <div class="flex items-start justify-between gap-4 border-b border-white/10 px-5 py-4">
            <div>
                <h2 class="text-base font-semibold text-white">Налаштування адреси</h2>
                <p class="mt-0.5 text-sm text-zinc-400">Параметри запиту</p>
            </div>
            <button
                type="button"
                wire:click="close"
                class="rounded-lg p-2 text-zinc-400 transition hover:bg-white/5 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-400"
                title="Закрити"
                aria-label="Закрити"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="space-y-4 overflow-y-auto px-5 py-5">
            <div>
                <label for="address-settings-method" class="mb-1 block text-sm font-medium text-zinc-200">Метод</label>
                <select
                    id="address-settings-method"
                    wire:model.live="http_method"
                    class="w-full rounded-lg border border-white/15 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 shadow-sm focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-400/30 sm:max-w-xs"
                >
                    @foreach (\App\Models\Address::METHODS as $method)
                        <option value="{{ $method }}">{{ $method }}</option>
                    @endforeach
                </select>
                @error('http_method') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
            </div>
            @include('livewire.partials.request-body-editor')
            @include('livewire.partials.site-token-select', ['fieldId' => 'address-settings-token'])
            <div class="rounded-lg border border-white/10 bg-white/5 p-4">
                @include('livewire.partials.headers-editor')
            </div>
        </div>

        <div class="flex justify-end gap-2 border-t border-white/10 px-5 py-4">
            <button
                type="button"
                wire:click="close"
                class="rounded-lg border border-white/15 px-4 py-2 text-sm font-medium text-zinc-200 transition hover:border-white/30 hover:bg-white/5 hover:text-white"
            >
                Скасувати
            </button>
            <button type="submit" class="inline-flex rounded-xl bg-cyan-400 px-4 py-2 text-sm font-semibold text-zinc-950 transition hover:bg-cyan-300">
                Зберегти
            </button>
        </div>
    </form>
</dialog>
