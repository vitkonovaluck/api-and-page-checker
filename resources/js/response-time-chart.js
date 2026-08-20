const SERIES_COLORS = {
    total: '#22d3ee',
    ttfb: '#fcd34d',
};

export function initResponseTimeChart(root, chart) {
    if (!root) return;

    root.replaceChildren();
    delete root.dataset.ready;

    const labels = chart?.labels || [];
    const counts = chart?.counts || [];
    const series = normalizeSeries(chart);

    if (!labels.length || !series.length) return;

    const numericValues = series.flatMap((item) => item.values.filter((value) => value != null));
    if (!numericValues.length) return;

    const width = 720;
    const height = 300;
    const pad = { top: 40, right: 20, bottom: 52, left: 52 };
    const plotW = width - pad.left - pad.right;
    const plotH = height - pad.top - pad.bottom;
    const pointCount = Math.max(...series.map((item) => item.values.length), labels.length);

    const maxVal = Math.max(...numericValues, 1);
    const yMax = Math.ceil((maxVal * 1.15) / 50) * 50 || 50;

    const xAt = (i) => pad.left + (pointCount === 1 ? plotW / 2 : (i / (pointCount - 1)) * plotW);
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
        line.setAttribute('stroke', 'rgba(255, 255, 255, 0.08)');
        line.setAttribute('stroke-width', '1');
        svg.appendChild(line);

        const text = document.createElementNS(ns, 'text');
        text.setAttribute('x', String(pad.left - 8));
        text.setAttribute('y', String(y + 4));
        text.setAttribute('text-anchor', 'end');
        text.setAttribute('fill', '#a1a1aa');
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
        text.setAttribute('fill', '#a1a1aa');
        text.setAttribute('font-size', '10');
        text.textContent = label;
        svg.appendChild(text);
    });

    drawLegend(svg, ns, series, pad.left);

    const tooltip = document.createElement('div');
    tooltip.className = 'pointer-events-none absolute z-10 hidden rounded-md border border-white/10 bg-zinc-900 px-2.5 py-1.5 text-xs text-zinc-200 shadow-xl';
    root.style.position = 'relative';
    root.appendChild(tooltip);

    const showTip = (evt, html) => {
        const rect = root.getBoundingClientRect();
        tooltip.innerHTML = html;
        tooltip.classList.remove('hidden');
        const x = evt.clientX - rect.left + 12;
        const y = evt.clientY - rect.top - 10;
        tooltip.style.left = Math.min(x, rect.width - 180) + 'px';
        tooltip.style.top = Math.max(0, y) + 'px';
    };
    const hideTip = () => tooltip.classList.add('hidden');

    series.forEach((item) => {
        drawSeriesLine(svg, ns, item, xAt, yAt);
        drawSeriesPoints(svg, ns, item, labels, counts, xAt, yAt, showTip, hideTip);
    });

    root.appendChild(svg);
    root.dataset.ready = '1';
}

function normalizeSeries(chart) {
    if (Array.isArray(chart?.series) && chart.series.length) {
        return chart.series.map((item) => ({
            key: item.key || 'total',
            label: item.label || 'Час відповіді',
            values: item.values || [],
            color: SERIES_COLORS[item.key] || SERIES_COLORS.total,
        }));
    }

    return [{
        key: 'total',
        label: chart?.series_label || 'Час відповіді',
        values: chart?.values || [],
        color: SERIES_COLORS.total,
    }];
}

function drawLegend(svg, ns, series, left) {
    let x = left;
    series.forEach((item) => {
        const line = document.createElementNS(ns, 'line');
        line.setAttribute('x1', String(x));
        line.setAttribute('x2', String(x + 16));
        line.setAttribute('y1', '16');
        line.setAttribute('y2', '16');
        line.setAttribute('stroke', item.color);
        line.setAttribute('stroke-width', '2.5');
        line.setAttribute('stroke-linecap', 'round');
        svg.appendChild(line);

        const text = document.createElementNS(ns, 'text');
        text.setAttribute('x', String(x + 22));
        text.setAttribute('y', '20');
        text.setAttribute('fill', '#e4e4e7');
        text.setAttribute('font-size', '12');
        text.textContent = item.label;
        svg.appendChild(text);

        x += 28 + item.label.length * 7;
    });
}

function drawSeriesLine(svg, ns, item, xAt, yAt) {
    const segments = contiguousSegments(item.values);
    segments.forEach((segment) => {
        if (segment.length < 2) return;

        const points = segment.map(([index, value]) => `${xAt(index)},${yAt(value)}`).join(' ');
        const path = document.createElementNS(ns, 'polyline');
        path.setAttribute('fill', 'none');
        path.setAttribute('stroke', item.color);
        path.setAttribute('stroke-width', '2.5');
        path.setAttribute('stroke-linejoin', 'round');
        path.setAttribute('stroke-linecap', 'round');
        path.setAttribute('points', points);
        svg.appendChild(path);
    });
}

function drawSeriesPoints(svg, ns, item, labels, counts, xAt, yAt, showTip, hideTip) {
    item.values.forEach((value, i) => {
        if (value == null) return;

        const circle = document.createElementNS(ns, 'circle');
        circle.setAttribute('cx', String(xAt(i)));
        circle.setAttribute('cy', String(yAt(value)));
        circle.setAttribute('r', '4');
        circle.setAttribute('fill', item.color);
        circle.setAttribute('stroke', '#09090b');
        circle.setAttribute('stroke-width', '1.5');
        circle.style.cursor = 'pointer';
        const html = pointTooltipHtml(item, labels[i], value, counts[i]);
        circle.addEventListener('mouseenter', (evt) => showTip(evt, html));
        circle.addEventListener('mousemove', (evt) => showTip(evt, html));
        circle.addEventListener('mouseleave', hideTip);
        svg.appendChild(circle);
    });
}

function contiguousSegments(values) {
    const segments = [];
    let current = [];

    values.forEach((value, index) => {
        if (value == null) {
            if (current.length) {
                segments.push(current);
                current = [];
            }
            return;
        }

        current.push([index, value]);
    });

    if (current.length) {
        segments.push(current);
    }

    return segments;
}

function pointTooltipHtml(item, label, value, count) {
    return `<strong>${item.label}</strong><br>${label}: ${value} ms` +
        (count ? `<br><span class="text-zinc-400">${count} перевірок</span>` : '');
}

window.initResponseTimeChart = initResponseTimeChart;
