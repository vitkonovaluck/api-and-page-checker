<div class="inline-flex rounded-lg border border-slate-300 p-0.5" role="group" aria-label="Метрика часу">
    @foreach (\App\Enums\ResponseTimeMetric::cases() as $option)
        <button
            type="button"
            wire:click="setMetric('{{ $option->value }}')"
            wire:key="metric-{{ $option->value }}"
            class="rounded-md px-3 py-1.5 text-sm font-medium transition
                {{ $metric === $option->value
                    ? 'bg-slate-900 text-white'
                    : 'text-slate-700 hover:bg-slate-100' }}"
        >
            {{ $option->toggleLabel() }}
        </button>
    @endforeach
</div>
