<div class="space-y-3">
    <div class="flex items-center justify-between gap-2">
        <div>
            <h3 class="text-sm font-semibold text-white">Request headers</h3>
            <p class="mt-0.5 text-xs text-zinc-400">Параметри header запиту (ключ → значення)</p>
        </div>
        <button
            type="button"
            wire:click="addHeaderRow"
            class="rounded-lg border border-white/15 px-2.5 py-1.5 text-xs font-medium text-zinc-200 transition hover:border-white/30 hover:bg-white/5 hover:text-white"
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
                    class="w-full rounded-lg border border-white/15 bg-zinc-950 px-3 py-2 font-mono text-sm text-zinc-100 shadow-sm focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-400/30 sm:w-2/5"
                >
                <input
                    type="text"
                    wire:model="headers.{{ $index }}.value"
                    placeholder="Значення"
                    class="w-full rounded-lg border border-white/15 bg-zinc-950 px-3 py-2 font-mono text-sm text-zinc-100 shadow-sm focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-400/30 sm:flex-1"
                >
                <button
                    type="button"
                    wire:click="removeHeaderRow({{ $index }})"
                    title="Видалити"
                    aria-label="Видалити"
                    class="inline-flex shrink-0 items-center justify-center rounded-lg border border-rose-400/20 p-2 text-rose-300 transition hover:bg-rose-400/10"
                >
                    @include('partials.icons.trash')
                </button>
            </div>
        @endforeach
    </div>
</div>
