<div class="inline-flex rounded-lg border border-white/15 p-0.5" role="group" aria-label="Метрика часу">
    @foreach (\App\Enums\ResponseTimeMetric::cases() as $option)
        <button
            type="button"
            wire:click="setMetric('{{ $option->value }}')"
            wire:key="metric-{{ $option->value }}"
            class="rounded-md px-3 py-1.5 text-sm font-medium transition
                {{ $metric === $option->value
                    ? 'bg-cyan-400 text-zinc-950'
                    : 'text-zinc-300 hover:bg-white/5' }}"
        >
            {{ $option->toggleLabel() }}
        </button>
    @endforeach
</div>
