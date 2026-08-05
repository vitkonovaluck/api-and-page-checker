<dialog
    x-data
    x-effect="$wire.show ? $el.showModal() : ($el.open && $el.close())"
    @click="if ($event.target === $el) $wire.close()"
    class="w-[calc(100%-2rem)] max-w-2xl rounded-xl border border-slate-200 bg-white p-0 shadow-xl backdrop:bg-slate-900/40"
>
    <form wire:submit="save" class="flex max-h-[85vh] flex-col">
        <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Додати сайт</h2>
                <p class="mt-0.5 text-sm text-slate-500">Базовий URL і опційний перший ендпоїнт</p>
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

        <div class="grid gap-4 overflow-y-auto px-5 py-5 sm:grid-cols-2">
            <div>
                <label for="create-site-name" class="mb-1 block text-sm font-medium text-slate-700">Назва сайту</label>
                <input
                    type="text"
                    id="create-site-name"
                    wire:model="name"
                    required
                    placeholder="Наприклад, Demo Shop"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                >
                @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="create-site-base-url" class="mb-1 block text-sm font-medium text-slate-700">Базовий URL</label>
                <input
                    type="url"
                    id="create-site-base-url"
                    wire:model="base_url"
                    required
                    placeholder="http://localhost:8000"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                >
                @error('base_url') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="create-site-address-name" class="mb-1 block text-sm font-medium text-slate-700">Назва адреси (необовʼязково)</label>
                <input
                    type="text"
                    id="create-site-address-name"
                    wire:model="address_name"
                    placeholder="Наприклад, Home API"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                >
                @error('address_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="create-site-endpoint" class="mb-1 block text-sm font-medium text-slate-700">Перший ендпоїнт (необовʼязково)</label>
                <input
                    type="text"
                    id="create-site-endpoint"
                    wire:model="endpoint"
                    placeholder="/api/users"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                >
                @error('endpoint') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-4">
            <button
                type="button"
                wire:click="close"
                class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
            >
                Скасувати
            </button>
            <button type="submit" class="inline-flex rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                Додати сайт
            </button>
        </div>
    </form>
</dialog>
