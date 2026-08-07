@if (! empty($timing))
    <div class="{{ $class ?? 'mt-4' }}">
        <h3 class="mb-2 text-sm font-semibold text-slate-800">Розбивка часу (cURL)</h3>
        <dl class="grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
            <div class="flex justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                <dt class="text-slate-500">DNS</dt>
                <dd class="font-medium tabular-nums">{{ $timing['dns_ms'] }} ms</dd>
            </div>
            <div class="flex justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                <dt class="text-slate-500">TCP connect</dt>
                <dd class="font-medium tabular-nums">{{ $timing['connect_ms'] }} ms</dd>
            </div>
            <div class="flex justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                <dt class="text-slate-500">TLS</dt>
                <dd class="font-medium tabular-nums">{{ $timing['tls_ms'] }} ms</dd>
            </div>
            <div class="flex justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                <dt class="text-slate-500">TTFB</dt>
                <dd class="font-medium tabular-nums">{{ $timing['ttfb_ms'] }} ms</dd>
            </div>
            <div class="flex justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                <dt class="text-slate-500">Download</dt>
                <dd class="font-medium tabular-nums">{{ $timing['download_ms'] }} ms</dd>
            </div>
            <div class="flex justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                <dt class="text-slate-500">cURL total</dt>
                <dd class="font-medium tabular-nums">{{ $timing['total_ms'] }} ms</dd>
            </div>
        </dl>
    </div>
@endif
