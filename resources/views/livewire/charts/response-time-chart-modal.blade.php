<div>
    <dialog
        wire:ignore.self
        x-data
        x-effect="$wire.show ? ($el.open || $el.showModal()) : ($el.open && $el.close())"
        @close="$wire.close()"
        @click="if ($event.target === $el) $wire.close()"
        class="w-[calc(100%-2rem)] max-w-4xl rounded-xl border border-slate-200 bg-white p-0 shadow-xl backdrop:bg-slate-900/40"
    >
        <div class="flex max-h-[90vh] flex-col">
            <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">{{ $title }}</h2>
                    <p class="mt-0.5 text-sm text-slate-500">
                        @if ($mode === 'site')
                            Середнє значення часу відповіді по всіх адресах за обраний період
                        @else
                            Час відповіді адреси за обраний період
                        @endif
                    </p>
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
                @if (($this->chart['avg_response_time_ms'] ?? null) !== null)
                    <div class="inline-block rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                        <div class="text-xs uppercase tracking-wide text-slate-500">Середнє за період</div>
                        <div class="font-semibold text-slate-900">{{ $this->chart['avg_response_time_ms'] }} ms</div>
                        <div class="text-xs text-slate-500">{{ $this->chart['checks_count'] }} перевірок · {{ $this->chart['period_label'] }}</div>
                    </div>
                @endif

                <div>
                    <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-500">Період вибірки</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($this->chart['periods'] as $key => $periodMeta)
                            <button
                                type="button"
                                wire:click="setPeriod('{{ $key }}')"
                                class="rounded-lg px-3 py-1.5 text-sm font-medium transition
                                    {{ $period === $key
                                        ? 'bg-slate-900 text-white'
                                        : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-100' }}"
                            >
                                {{ $periodMeta['label'] }}
                            </button>
                        @endforeach
                    </div>
                </div>

                @if (empty($this->chart['has_data']))
                    <p class="py-8 text-center text-sm text-slate-500">
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
                            aria-label="{{ $title }}"
                        ></div>
                    </div>
                    @php
                        $chartPayload = [
                            'labels' => $this->chart['labels'],
                            'values' => $this->chart['values'],
                            'counts' => $this->chart['counts'],
                            'series_label' => $this->chart['series_label'] ?? '',
                            'period_label' => $this->chart['period_label'] ?? '',
                        ];
                    @endphp
                    <script type="application/json" id="{{ $chartId }}-data" wire:key="chart-data-{{ $period }}-{{ $this->chart['points_count'] }}">
                        {!! json_encode($chartPayload, JSON_UNESCAPED_UNICODE) !!}
                    </script>
                @endif
            </div>

            <div class="flex justify-end border-t border-slate-200 px-5 py-4">
                <button
                    type="button"
                    wire:click="close"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
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
