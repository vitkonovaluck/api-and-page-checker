@php
    /** @var array{
     *   period: string,
     *   period_label: string,
     *   periods: array<string, array{label: string, hours: int}>,
     *   labels: list<string>,
     *   values: list<int>,
     *   counts: list<int>,
     *   avg_response_time_ms: int|null,
     *   points_count: int,
     *   checks_count: int,
     *   has_data: bool,
     *   mode: string,
     *   series_label: string
     * } $chart */
    $chartId = $chartId ?? 'response-time-chart';
    $title = $title ?? 'Історія часу відповіді';
    $selectedPeriod = $chart['period'] ?? \App\Services\CheckStats::DEFAULT_RESPONSE_TIME_PERIOD;
    $periods = $chart['periods'] ?? \App\Services\CheckStats::RESPONSE_TIME_PERIODS;
@endphp

<section class="mb-8 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h2 class="text-base font-semibold text-slate-900">{{ $title }}</h2>
            <p class="mt-0.5 text-sm text-slate-500">
                @if (($chart['mode'] ?? '') === 'site')
                    Середнє значення часу відповіді по всіх адресах за обраний період
                @else
                    Час відповіді адреси за обраний період
                @endif
            </p>
        </div>
        @if (($chart['avg_response_time_ms'] ?? null) !== null)
            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                <div class="text-xs uppercase tracking-wide text-slate-500">Середнє за період</div>
                <div class="font-semibold text-slate-900">{{ $chart['avg_response_time_ms'] }} ms</div>
                <div class="text-xs text-slate-500">{{ $chart['checks_count'] }} перевірок · {{ $chart['period_label'] }}</div>
            </div>
        @endif
    </div>

    <div class="mb-4">
        <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-500">Період вибірки</p>
        <div class="flex flex-wrap gap-1.5">
            @foreach ($periods as $key => $period)
                <a
                    href="{{ request()->fullUrlWithQuery(['period' => $key]) }}"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition
                        {{ $selectedPeriod === $key
                            ? 'bg-slate-900 text-white'
                            : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-100' }}"
                >
                    {{ $period['label'] }}
                </a>
            @endforeach
        </div>
    </div>

    @if (empty($chart['has_data']))
        <p class="py-8 text-center text-sm text-slate-500">
            Немає перевірок за період «{{ $chart['period_label'] ?? $selectedPeriod }}».
        </p>
    @else
        <div class="relative w-full overflow-x-auto">
            <div
                id="{{ $chartId }}"
                class="min-w-[520px]"
                data-response-time-chart
                role="img"
                aria-label="{{ $title }}"
            ></div>
        </div>
        @php
            $chartPayload = [
                'labels' => $chart['labels'],
                'values' => $chart['values'],
                'counts' => $chart['counts'],
                'series_label' => $chart['series_label'] ?? '',
                'period_label' => $chart['period_label'] ?? '',
            ];
        @endphp
        <script type="application/json" id="{{ $chartId }}-data">{!! json_encode($chartPayload, JSON_UNESCAPED_UNICODE) !!}</script>
    @endif
</section>

@once
    <script>
        (() => {
            const COLOR = '#0f766e';

            const initChart = (root) => {
                if (!root || root.dataset.ready === '1') return;

                const dataEl = document.getElementById(root.id + '-data');
                if (!dataEl) return;

                let chart;
                try {
                    chart = JSON.parse(dataEl.textContent || '{}');
                } catch (e) {
                    return;
                }

                const labels = chart.labels || [];
                const values = chart.values || [];
                const counts = chart.counts || [];

                if (!labels.length || !values.length) return;

                const width = 720;
                const height = 280;
                const pad = { top: 24, right: 20, bottom: 52, left: 52 };
                const plotW = width - pad.left - pad.right;
                const plotH = height - pad.top - pad.bottom;

                const maxVal = Math.max(...values, 1);
                const yMax = Math.ceil((maxVal * 1.15) / 50) * 50 || 50;

                const xAt = (i) => pad.left + (values.length === 1 ? plotW / 2 : (i / (values.length - 1)) * plotW);
                const yAt = (v) => pad.top + plotH - (v / yMax) * plotH;

                const ns = 'http://www.w3.org/2000/svg';
                const svg = document.createElementNS(ns, 'svg');
                svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
                svg.setAttribute('class', 'h-64 w-full max-w-full');
                svg.setAttribute('role', 'presentation');

                for (let t = 0; t <= 4; t++) {
                    const value = Math.round((yMax / 4) * t);
                    const y = yAt(value);
                    const line = document.createElementNS(ns, 'line');
                    line.setAttribute('x1', String(pad.left));
                    line.setAttribute('x2', String(width - pad.right));
                    line.setAttribute('y1', String(y));
                    line.setAttribute('y2', String(y));
                    line.setAttribute('stroke', '#e2e8f0');
                    line.setAttribute('stroke-width', '1');
                    svg.appendChild(line);

                    const text = document.createElementNS(ns, 'text');
                    text.setAttribute('x', String(pad.left - 8));
                    text.setAttribute('y', String(y + 4));
                    text.setAttribute('text-anchor', 'end');
                    text.setAttribute('fill', '#64748b');
                    text.setAttribute('font-size', '11');
                    text.textContent = value + ' ms';
                    svg.appendChild(text);
                }

                const labelStep = Math.max(1, Math.ceil(labels.length / 8));
                labels.forEach((label, i) => {
                    if (i % labelStep !== 0 && i !== labels.length - 1) return;
                    const text = document.createElementNS(ns, 'text');
                    text.setAttribute('x', String(xAt(i)));
                    text.setAttribute('y', String(height - 16));
                    text.setAttribute('text-anchor', 'middle');
                    text.setAttribute('fill', '#64748b');
                    text.setAttribute('font-size', '10');
                    text.textContent = label;
                    svg.appendChild(text);
                });

                const tooltip = document.createElement('div');
                tooltip.className = 'pointer-events-none absolute z-10 hidden rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-700 shadow-md';
                root.style.position = 'relative';
                root.appendChild(tooltip);

                const tipHtml = (i) =>
                    `<strong>${chart.series_label || 'Час відповіді'}</strong><br>${labels[i]}: ${values[i]} ms` +
                    (counts[i] ? `<br><span class="text-slate-500">${counts[i]} перевірок</span>` : '');

                const showTip = (evt, html) => {
                    const rect = root.getBoundingClientRect();
                    tooltip.innerHTML = html;
                    tooltip.classList.remove('hidden');
                    const x = evt.clientX - rect.left + 12;
                    const y = evt.clientY - rect.top - 10;
                    tooltip.style.left = Math.min(x, rect.width - 160) + 'px';
                    tooltip.style.top = Math.max(0, y) + 'px';
                };
                const hideTip = () => tooltip.classList.add('hidden');

                if (values.length >= 2) {
                    const points = values.map((value, i) => `${xAt(i)},${yAt(value)}`).join(' ');
                    const path = document.createElementNS(ns, 'polyline');
                    path.setAttribute('fill', 'none');
                    path.setAttribute('stroke', COLOR);
                    path.setAttribute('stroke-width', '2.5');
                    path.setAttribute('stroke-linejoin', 'round');
                    path.setAttribute('stroke-linecap', 'round');
                    path.setAttribute('points', points);
                    svg.appendChild(path);
                }

                values.forEach((value, i) => {
                    const circle = document.createElementNS(ns, 'circle');
                    circle.setAttribute('cx', String(xAt(i)));
                    circle.setAttribute('cy', String(yAt(value)));
                    circle.setAttribute('r', '4');
                    circle.setAttribute('fill', COLOR);
                    circle.setAttribute('stroke', '#fff');
                    circle.setAttribute('stroke-width', '1.5');
                    circle.style.cursor = 'pointer';
                    circle.addEventListener('mouseenter', (evt) => showTip(evt, tipHtml(i)));
                    circle.addEventListener('mousemove', (evt) => showTip(evt, tipHtml(i)));
                    circle.addEventListener('mouseleave', hideTip);
                    svg.appendChild(circle);
                });

                root.appendChild(svg);
                root.dataset.ready = '1';
            };

            const boot = () => {
                document.querySelectorAll('[data-response-time-chart]').forEach(initChart);
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', boot);
            } else {
                boot();
            }
        })();
    </script>
@endonce
