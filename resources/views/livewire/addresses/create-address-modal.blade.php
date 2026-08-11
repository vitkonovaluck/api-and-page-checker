<dialog
    wire:ignore.self
    x-data
    x-effect="$wire.show ? ($el.open || $el.showModal()) : ($el.open && $el.close())"
    @close="$wire.close()"
    @click="if ($event.target === $el) $wire.close()"
    class="w-[calc(100%-2rem)] max-w-2xl rounded-xl border border-slate-200 bg-white p-0 shadow-xl backdrop:bg-slate-900/40"
>
    <form wire:submit="save" class="flex max-h-[85vh] flex-col">
        <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Додати адресу</h2>
                <p class="mt-0.5 text-sm text-slate-500">Один або кілька ендпоїнтів (по одному на рядок) відносно базового URL</p>
            </div>
            <button
                type="button"
                wire:click="close"
                class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-800"
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
                <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">
                    <ul class="list-disc space-y-1 pl-4">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <div>
                <label for="create-address-endpoints" class="mb-1 block text-sm font-medium text-slate-700">Ендпоїнти</label>
                <p class="mb-1 font-mono text-xs text-slate-500">{{ $site->base_url }}</p>
                <textarea
                    id="create-address-endpoints"
                    wire:model="endpoints"
                    required
                    rows="4"
                    placeholder="/api/users&#10;/api/orders&#10;/api/products"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                ></textarea>
                @error('endpoints') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="create-address-name" class="mb-1 block text-sm font-medium text-slate-700">Назва (необовʼязково)</label>
                    <input
                        type="text"
                        id="create-address-name"
                        wire:model="name"
                        placeholder="Лише для однієї адреси"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="create-address-method" class="mb-1 block text-sm font-medium text-slate-700">Метод</label>
                    <select
                        id="create-address-method"
                        wire:model.live="http_method"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                        @foreach (\App\Models\Address::METHODS as $method)
                            <option value="{{ $method }}">{{ $method }}</option>
                        @endforeach
                    </select>
                    @error('http_method') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input
                        type="checkbox"
                        wire:model="schedule_enabled"
                        class="rounded border-slate-300 text-slate-900 focus:ring-slate-200"
                    >
                    У розкладі
                </label>
            </div>
            @include('livewire.partials.request-body-editor')
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                @include('livewire.partials.headers-editor')
            </div>
            <p class="text-xs text-slate-500">Метод, headers і body застосовуються до всіх доданих ендпоїнтів.</p>
        </div>

        <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-4">
            <button
                type="button"
                wire:click="close"
                class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
            >
                Скасувати
            </button>
            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="save"
                class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
            >
                <span wire:loading wire:target="save" class="inline-flex">
                    @include('partials.icons.spinner')
                </span>
                Додати
            </button>
        </div>
    </form>
</dialog>
