@php
    /** @var array{labels: list<string>, keys: list<string>, series: list<array{id: int|string, label: string, values: list<int|null>, counts: list<int>}>, has_data: bool} $chart */
    $chartId = $chartId ?? 'response-time-chart-'.substr(md5(json_encode($chart['series'] ?? []).uniqid('', true)), 0, 10);
    $title = $title ?? 'Історія часу відповіді';
    $subtitle = $subtitle ?? 'Середнє значення за періоди: останній час, 6 / 12 / 24 / 48 / 96 год, 1 тиждень';
@endphp

<section class="mb-8 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-base font-semibold text-slate-900">{{ $title }}</h2>
            <p class="mt-0.5 text-sm text-slate-500">{{ $subtitle }}</p>
        </div>
        @if (! empty($chart['series']) && count($chart['series']) > 1)
            <p class="text-xs text-slate-400">Наведіть на точку, щоб побачити значення</p>
        @endif
    </div>

    @if (empty($chart['has_data']))
        <p class="py-8 text-center text-sm text-slate-500">Немає даних для графіка. Зробіть кілька перевірок.</p>
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
        <script type="application/json" id="{{ $chartId }}-data">@json($chart)</script>
        @if (count($chart['series']) > 1)
            <ul id="{{ $chartId }}-legend" class="mt-4 flex flex-wrap gap-x-4 gap-y-2 text-xs text-slate-600"></ul>
        @endif
    @endif
</section>

@once
    <script>
        (() => {
            const COLORS = [
                '#0f766e', // teal
                '#0369a1', // sky
                '#b45309', // amber
                '#be123c', // rose
                '#4338ca', // indigo
                '#15803d', // green
                '#c2410c', // orange
                '#6d28d9', // violet
            ];

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
                const series = (chart.series || []).filter((s) =>
                    (s.values || []).some((v) => v !== null && v !== undefined)
                );

                if (!labels.length || !series.length) return;

                const width = 720;
                const height = 280;
                const pad = { top: 24, right: 20, bottom: 44, left: 52 };
                const plotW = width - pad.left - pad.right;
                const plotH = height - pad.top - pad.bottom;

                const allValues = series.flatMap((s) => s.values.filter((v) => v !== null && v !== undefined));
                const maxVal = Math.max(...allValues, 1);
                const yMax = Math.ceil(maxVal * 1.15 / 50) * 50 || 50;

                const xAt = (i) => pad.left + (labels.length === 1 ? plotW / 2 : (i / (labels.length - 1)) * plotW);
                const yAt = (v) => pad.top + plotH - (v / yMax) * plotH;

                const ns = 'http://www.w3.org/2000/svg';
                const svg = document.createElementNS(ns, 'svg');
                svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
                svg.setAttribute('class', 'h-64 w-full max-w-full');
                svg.setAttribute('role', 'presentation');

                const gridGroup = document.createElementNS(ns, 'g');
                const ticks = 4;
                for (let t = 0; t <= ticks; t++) {
                    const value = Math.round((yMax / ticks) * t);
                    const y = yAt(value);
                    const line = document.createElementNS(ns, 'line');
                    line.setAttribute('x1', String(pad.left));
                    line.setAttribute('x2', String(width - pad.right));
                    line.setAttribute('y1', String(y));
                    line.setAttribute('y2', String(y));
                    line.setAttribute('stroke', '#e2e8f0');
                    line.setAttribute('stroke-width', '1');
                    gridGroup.appendChild(line);

                    const text = document.createElementNS(ns, 'text');
                    text.setAttribute('x', String(pad.left - 8));
                    text.setAttribute('y', String(y + 4));
                    text.setAttribute('text-anchor', 'end');
                    text.setAttribute('fill', '#64748b');
                    text.setAttribute('font-size', '11');
                    text.textContent = value + ' ms';
                    gridGroup.appendChild(text);
                }
                svg.appendChild(gridGroup);

                labels.forEach((label, i) => {
                    const text = document.createElementNS(ns, 'text');
                    text.setAttribute('x', String(xAt(i)));
                    text.setAttribute('y', String(height - 14));
                    text.setAttribute('text-anchor', 'middle');
                    text.setAttribute('fill', '#64748b');
                    text.setAttribute('font-size', '11');
                    text.textContent = label;
                    svg.appendChild(text);
                });

                const tooltip = document.createElement('div');
                tooltip.className = 'pointer-events-none absolute z-10 hidden rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-700 shadow-md';
                root.style.position = 'relative';
                root.appendChild(tooltip);

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

                const legend = document.getElementById(root.id + '-legend');
                if (legend) legend.innerHTML = '';

                series.forEach((serie, sIndex) => {
                    const color = COLORS[sIndex % COLORS.length];
                    const points = [];

                    serie.values.forEach((value, i) => {
                        if (value === null || value === undefined) return;
                        points.push(`${xAt(i)},${yAt(value)}`);
                    });

                    if (points.length >= 2) {
                        const path = document.createElementNS(ns, 'polyline');
                        path.setAttribute('fill', 'none');
                        path.setAttribute('stroke', color);
                        path.setAttribute('stroke-width', serie.id === 'overall' ? '2.5' : '2');
                        path.setAttribute('stroke-linejoin', 'round');
                        path.setAttribute('stroke-linecap', 'round');
                        if (serie.id === 'overall') {
                            path.setAttribute('stroke-dasharray', '5 4');
                        }
                        path.setAttribute('points', points.join(' '));
                        svg.appendChild(path);
                    }

                    serie.values.forEach((value, i) => {
                        if (value === null || value === undefined) return;
                        const circle = document.createElementNS(ns, 'circle');
                        circle.setAttribute('cx', String(xAt(i)));
                        circle.setAttribute('cy', String(yAt(value)));
                        circle.setAttribute('r', '4');
                        circle.setAttribute('fill', color);
                        circle.setAttribute('stroke', '#fff');
                        circle.setAttribute('stroke-width', '1.5');
                        circle.style.cursor = 'pointer';

                        const count = (serie.counts && serie.counts[i]) || 0;
                        circle.addEventListener('mouseenter', (evt) => {
                            showTip(
                                evt,
                                `<strong>${serie.label}</strong><br>${labels[i]}: ${value} ms` +
                                    (count ? `<br><span class="text-slate-500">${count} перевірок</span>` : '')
                            );
                        });
                        circle.addEventListener('mousemove', (evt) => {
                            showTip(
                                evt,
                                `<strong>${serie.label}</strong><br>${labels[i]}: ${value} ms` +
                                    (count ? `<br><span class="text-slate-500">${count} перевірок</span>` : '')
                            );
                        });
                        circle.addEventListener('mouseleave', hideTip);
                        svg.appendChild(circle);
                    });

                    if (legend) {
                        const item = document.createElement('li');
                        item.className = 'inline-flex items-center gap-1.5';
                        item.innerHTML =
                            `<span class="inline-block h-2.5 w-2.5 rounded-full" style="background:${color}"></span>` +
                            `<span>${serie.label}</span>`;
                        legend.appendChild(item);
                    }
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
