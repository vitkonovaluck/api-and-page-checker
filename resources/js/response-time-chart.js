const COLOR = '#0f766e';

export function initResponseTimeChart(root, chart) {
    if (!root) return;

    root.replaceChildren();
    delete root.dataset.ready;

    const labels = chart?.labels || [];
    const values = chart?.values || [];
    const counts = chart?.counts || [];

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
}

window.initResponseTimeChart = initResponseTimeChart;
