<dialog
    wire:ignore.self
    x-data
    x-effect="$wire.show ? ($el.open || $el.showModal()) : ($el.open && $el.close())"
    @close="$wire.close()"
    @click="if ($event.target === $el) $wire.close()"
    class="w-[calc(100%-2rem)] max-w-2xl rounded-2xl border border-white/10 bg-zinc-900 p-0 text-zinc-100 shadow-2xl backdrop:bg-zinc-950/70"
>
    <form wire:submit="save" class="flex max-h-[85vh] flex-col">
        <div class="flex items-start justify-between gap-4 border-b border-white/10 px-5 py-4">
            <div>
                <h2 class="text-base font-semibold text-white">Додати адресу</h2>
                <p class="mt-0.5 text-sm text-zinc-400">Один або кілька ендпоїнтів (по одному на рядок). Той самий шлях можна додати знову з іншими headers/body</p>
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
            @if ($errors->any())
                <div class="rounded-lg border border-rose-400/20 bg-rose-400/10 px-3 py-2 text-sm text-rose-200" role="alert">
                    <ul class="list-disc space-y-1 pl-4">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <div>
                <label for="create-address-endpoints" class="mb-1 block text-sm font-medium text-zinc-200">Ендпоїнти</label>
                <p class="mb-1 font-mono text-xs text-zinc-400">{{ $site->base_url }}</p>
                <textarea
                    id="create-address-endpoints"
                    wire:model="endpoints"
                    required
                    rows="4"
                    placeholder="/api/users&#10;/api/orders&#10;/api/products"
                    class="w-full rounded-lg border border-white/15 bg-zinc-950 px-3 py-2 font-mono text-sm text-zinc-100 shadow-sm focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-400/30"
                ></textarea>
                @error('endpoints') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="create-address-name" class="mb-1 block text-sm font-medium text-zinc-200">Назва (необовʼязково)</label>
                    <input
                        type="text"
                        id="create-address-name"
                        wire:model="name"
                        placeholder="Лише для однієї адреси"
                        class="w-full rounded-lg border border-white/15 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 shadow-sm focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-400/30"
                    >
                    @error('name') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="create-address-method" class="mb-1 block text-sm font-medium text-zinc-200">Метод</label>
                    <select
                        id="create-address-method"
                        wire:model.live="http_method"
                        class="w-full rounded-lg border border-white/15 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 shadow-sm focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-400/30"
                    >
                        @foreach (\App\Models\Address::METHODS as $method)
                            <option value="{{ $method }}">{{ $method }}</option>
                        @endforeach
                    </select>
                    @error('http_method') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="flex items-center gap-2 text-sm text-zinc-300">
                    <input
                        type="checkbox"
                        wire:model="schedule_enabled"
                        class="rounded border-white/20 bg-zinc-950 text-cyan-400 focus:ring-cyan-400/40"
                    >
                    У розкладі
                </label>
            </div>
            @include('livewire.partials.request-body-editor')
            @include('livewire.partials.site-token-select', ['fieldId' => 'create-address-token'])
            <div class="rounded-lg border border-white/10 bg-white/5 p-4">
                @include('livewire.partials.headers-editor')
            </div>
            <p class="text-xs text-zinc-400">Метод, headers і body застосовуються до всіх доданих ендпоїнтів.</p>
        </div>

        <div class="flex justify-end gap-2 border-t border-white/10 px-5 py-4">
            <button
                type="button"
                wire:click="close"
                class="rounded-lg border border-white/15 px-4 py-2 text-sm font-medium text-zinc-200 transition hover:border-white/30 hover:bg-white/5 hover:text-white"
            >
                Скасувати
            </button>
            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="save"
                class="inline-flex items-center gap-2 rounded-xl bg-cyan-400 px-4 py-2 text-sm font-semibold text-zinc-950 transition hover:bg-cyan-300 disabled:cursor-not-allowed disabled:opacity-50"
            >
                <span wire:loading wire:target="save" class="inline-flex">
                    @include('partials.icons.spinner')
                </span>
                Додати
            </button>
        </div>
    </form>
</dialog>
