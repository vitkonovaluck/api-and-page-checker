<div @if ($show) wire:poll.10s="refreshChart" @endif>
    <dialog
        wire:ignore.self
        x-data
        x-effect="$wire.show ? ($el.open || $el.showModal()) : ($el.open && $el.close())"
        @close="$wire.close()"
        @click="if ($event.target === $el) $wire.close()"
        class="w-[calc(100%-2rem)] max-w-4xl rounded-2xl border border-white/10 bg-zinc-900 p-0 text-zinc-100 shadow-2xl backdrop:bg-zinc-950/70"
    >
        <div class="flex max-h-[90vh] flex-col">
            <div class="flex items-start justify-between gap-4 border-b border-white/10 px-5 py-4">
                <div>
                    <h2 class="text-base font-semibold text-white">
                        {{ $this->chartHeading() }}
                    </h2>
                    <p class="mt-0.5 text-sm text-zinc-400">
                        {{ $this->chartDescription() }}
                    </p>
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
                @if (($this->chart['avg_response_time_ms'] ?? null) !== null || ($this->chart['avg_ttfb_ms'] ?? null) !== null)
                    <div class="flex flex-wrap gap-3">
                        @if (($this->chart['avg_response_time_ms'] ?? null) !== null)
                            <div class="inline-block rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm">
                                <div class="text-xs uppercase tracking-wide text-zinc-400">{{ $this->totalAverageLabel() }}</div>
                                <div class="font-semibold text-white">{{ $this->chart['avg_response_time_ms'] }} ms</div>
                                <div class="text-xs text-zinc-400">{{ $this->chart['checks_count'] }} перевірок · {{ $this->chart['period_label'] }}</div>
                            </div>
                        @endif
                        @if (($this->chart['avg_ttfb_ms'] ?? null) !== null)
                            <div class="inline-block rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm">
                                <div class="text-xs uppercase tracking-wide text-zinc-400">{{ $this->ttfbAverageLabel() }}</div>
                                <div class="font-semibold text-white">{{ $this->chart['avg_ttfb_ms'] }} ms</div>
                                <div class="text-xs text-zinc-400">{{ $this->chart['checks_count'] }} перевірок · {{ $this->chart['period_label'] }}</div>
                            </div>
                        @endif
                    </div>
                @endif

                <div>
                    <p class="mb-2 text-xs font-medium uppercase tracking-wide text-zinc-400">Період вибірки</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($this->chart['periods'] as $key => $periodMeta)
                            <button
                                type="button"
                                wire:click="setPeriod('{{ $key }}')"
                                class="rounded-lg px-3 py-1.5 text-sm font-medium transition
                                    {{ $period === $key
                                        ? 'bg-cyan-400 text-zinc-950'
                                        : 'border border-white/15 bg-zinc-950 text-zinc-200 hover:bg-white/5' }}"
                            >
                                {{ $periodMeta['label'] }}
                            </button>
                        @endforeach
                    </div>
                </div>

                @if (empty($this->chart['has_data']))
                    <p class="py-8 text-center text-sm text-zinc-400">
                        Немає перевірок за період «{{ $this->chart['period_label'] ?? $period }}».
                    </p>
                @else
                    <div class="relative w-full overflow-x-auto">
                        <div
                            id="{{ $chartId }}"
                            class="min-w-[520px]"
                            data-response-time-chart
                            wire:ignore
                            role="img"
                            aria-label="{{ $this->chartHeading() }}"
                        ></div>
                    </div>
                    <script type="application/json" id="{{ $chartId }}-data" wire:key="chart-data-{{ $period }}-{{ $this->chart['checks_count'] }}-{{ $this->chart['avg_response_time_ms'] }}-{{ $this->chart['avg_ttfb_ms'] }}-{{ implode('-', $this->chart['values'] ?? []) }}">
                        {!! json_encode($this->chartPayload(), JSON_UNESCAPED_UNICODE) !!}
                    </script>
                @endif
            </div>

            <div class="flex justify-end border-t border-white/10 px-5 py-4">
                <button
                    type="button"
                    wire:click="close"
                    class="rounded-lg border border-white/15 px-4 py-2 text-sm font-medium text-zinc-200 transition hover:border-white/30 hover:bg-white/5 hover:text-white"
                >
                    Закрити
                </button>
            </div>
        </div>
    </dialog>

    @script
    <script>
        const renderChart = () => {
            const root = document.getElementById(@js($chartId));
            const dataEl = document.getElementById(@js($chartId) + '-data');
            if (!root || !dataEl || typeof window.initResponseTimeChart !== 'function') {
                return;
            }

            let chart;
            try {
                chart = JSON.parse(dataEl.textContent || '{}');
            } catch (e) {
                return;
            }

            window.initResponseTimeChart(root, chart);
        };

        $wire.on('chart-should-render', () => {
            queueMicrotask(() => requestAnimationFrame(() => {
                if ($wire.show) {
                    const dialog = $wire.$el?.querySelector?.('dialog');
                    if (dialog && !dialog.open) {
                        dialog.showModal();
                    }
                }
                renderChart();
            }));
        });

        $wire.$watch('show', (value) => {
            if (value) {
                queueMicrotask(() => requestAnimationFrame(renderChart));
            }
        });

        $wire.$watch('period', () => {
            if ($wire.show) {
                queueMicrotask(() => requestAnimationFrame(renderChart));
            }
        });
    </script>
    @endscript
</div>
