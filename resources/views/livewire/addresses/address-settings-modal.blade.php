<dialog
    x-data
    x-effect="$wire.show ? $el.showModal() : ($el.open && $el.close())"
    @click="if ($event.target === $el) $wire.close()"
    class="w-[calc(100%-2rem)] max-w-2xl rounded-xl border border-slate-200 bg-white p-0 shadow-xl backdrop:bg-slate-900/40"
>
    <form wire:submit="save" class="flex max-h-[85vh] flex-col">
        <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Налаштування адреси</h2>
                <p class="mt-0.5 text-sm text-slate-500">Header-параметри запиту</p>
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

        <div class="overflow-y-auto px-5 py-5">
            @include('livewire.partials.headers-editor')
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
                Зберегти
            </button>
        </div>
    </form>
</dialog>
