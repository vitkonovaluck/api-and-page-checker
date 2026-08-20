@if (! empty($timing))
    <div class="{{ $class ?? 'mt-4' }}">
        <h3 class="mb-2 text-sm font-semibold text-zinc-100">Розбивка часу (cURL)</h3>
        <dl class="grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
            <div class="flex justify-between gap-3 rounded-lg border border-white/10 bg-white/5 px-3 py-2">
                <dt class="text-zinc-400">DNS</dt>
                <dd class="font-medium tabular-nums">{{ $timing['dns_ms'] }} ms</dd>
            </div>
            <div class="flex justify-between gap-3 rounded-lg border border-white/10 bg-white/5 px-3 py-2">
                <dt class="text-zinc-400">TCP connect</dt>
                <dd class="font-medium tabular-nums">{{ $timing['connect_ms'] }} ms</dd>
            </div>
            <div class="flex justify-between gap-3 rounded-lg border border-white/10 bg-white/5 px-3 py-2">
                <dt class="text-zinc-400">TLS</dt>
                <dd class="font-medium tabular-nums">{{ $timing['tls_ms'] }} ms</dd>
            </div>
            <div class="flex justify-between gap-3 rounded-lg border border-white/10 bg-white/5 px-3 py-2">
                <dt class="text-zinc-400">TTFB</dt>
                <dd class="font-medium tabular-nums">{{ $timing['ttfb_ms'] }} ms</dd>
            </div>
            <div class="flex justify-between gap-3 rounded-lg border border-white/10 bg-white/5 px-3 py-2">
                <dt class="text-zinc-400">Download</dt>
                <dd class="font-medium tabular-nums">{{ $timing['download_ms'] }} ms</dd>
            </div>
            <div class="flex justify-between gap-3 rounded-lg border border-white/10 bg-white/5 px-3 py-2">
                <dt class="text-zinc-400">cURL total</dt>
                <dd class="font-medium tabular-nums">{{ $timing['total_ms'] }} ms</dd>
            </div>
        </dl>
    </div>
@endif
