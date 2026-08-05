<dialog
    x-data
    x-effect="$wire.show ? $el.showModal() : ($el.open && $el.close())"
    @click="if ($event.target === $el) $wire.close()"
    class="w-[calc(100%-2rem)] max-w-2xl rounded-xl border border-slate-200 bg-white p-0 shadow-xl backdrop:bg-slate-900/40"
>
    <form wire:submit="save" class="flex max-h-[85vh] flex-col">
        <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Додати адресу</h2>
                <p class="mt-0.5 text-sm text-slate-500">Ендпоїнт відносно базового URL сайту</p>
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
            <div>
                <label for="create-address-endpoint" class="mb-1 block text-sm font-medium text-slate-700">Ендпоїнт</label>
                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-2">
                    <span class="shrink-0 font-mono text-xs text-slate-500">{{ $site->base_url }}</span>
                    <input
                        type="text"
                        id="create-address-endpoint"
                        wire:model="endpoint"
                        required
                        placeholder="/api/users"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                </div>
                @error('endpoint') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="create-address-name" class="mb-1 block text-sm font-medium text-slate-700">Назва (необовʼязково)</label>
                    <input
                        type="text"
                        id="create-address-name"
                        wire:model="name"
                        placeholder="Наприклад, Users"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2 pb-2 text-sm text-slate-700">
                        <input
                            type="checkbox"
                            wire:model="schedule_enabled"
                            class="rounded border-slate-300 text-slate-900 focus:ring-slate-200"
                        >
                        У розкладі
                    </label>
                </div>
            </div>
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                @include('livewire.partials.headers-editor')
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
                Додати адресу
            </button>
        </div>
    </form>
</dialog>
