<div class="space-y-3">
    <div class="flex items-center justify-between gap-2">
        <div>
            <h3 class="text-sm font-semibold text-slate-900">Request headers</h3>
            <p class="mt-0.5 text-xs text-slate-500">Параметри header запиту (ключ → значення)</p>
        </div>
        <button
            type="button"
            wire:click="addHeaderRow"
            class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100"
        >
            Додати
        </button>
    </div>

    <div class="space-y-2">
        @foreach ($headers as $index => $row)
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center" wire:key="header-row-{{ $index }}">
                <input
                    type="text"
                    wire:model="headers.{{ $index }}.name"
                    placeholder="Параметр"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200 sm:w-2/5"
                >
                <input
                    type="text"
                    wire:model="headers.{{ $index }}.value"
                    placeholder="Значення"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200 sm:flex-1"
                >
                <button
                    type="button"
                    wire:click="removeHeaderRow({{ $index }})"
                    title="Видалити"
                    aria-label="Видалити"
                    class="inline-flex shrink-0 items-center justify-center rounded-lg border border-red-200 p-2 text-red-700 hover:bg-red-50"
                >
                    @include('partials.icons.trash')
                </button>
            </div>
        @endforeach
    </div>
</div>
