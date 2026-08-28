<div wire:poll.3s="refreshData">
    <div class="mb-6">
        <nav class="mb-2 text-sm text-zinc-400">
            <a href="{{ route('sites.index') }}" wire:navigate class="text-cyan-300 hover:text-cyan-200 hover:underline">Сайти</a>
            <span class="mx-1">/</span>
            <a href="{{ route('sites.show', $site) }}" wire:navigate class="text-cyan-300 hover:text-cyan-200 hover:underline">{{ $site->name }}</a>
            <span class="mx-1">/</span>
            <span class="text-zinc-300">{{ $address->name ?: 'Адреса' }}</span>
        </nav>
        <div class="mt-3 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-white">{{ $address->name ?: 'Без назви' }}</h1>
                <p class="mt-1 flex flex-wrap items-center gap-2 break-all font-mono text-sm text-zinc-400">
                    <span class="shrink-0 rounded bg-white/10 px-1.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-zinc-300">{{ $address->http_method ?: 'GET' }}</span>
                    <span>{{ $address->endpoint }}</span>
                </p>
                <p class="mt-1 break-all font-mono text-xs text-zinc-500">{{ $address->fullUrl() }}</p>
                <p class="mt-2 text-sm text-zinc-400">
                    Остання перевірка:
                    {{ $address->last_checked_at?->format('d.m.Y H:i:s') ?? 'ще не перевірялася' }}
                    @if ($hasOpenIncident)
                        <span class="ml-2 rounded bg-amber-300/15 px-1.5 py-0.5 text-xs font-medium text-amber-200">{{ __('alerts.open_change') }}</span>
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    wire:click="$dispatch('open-address-settings')"
                    class="inline-flex items-center gap-2 rounded-lg border border-white/15 px-4 py-2 text-sm font-medium text-zinc-200 transition hover:border-white/30 hover:bg-white/5 hover:text-white"
                >
                    @include('partials.icons.cog')
                    Налаштування
                </button>
                @include('partials.response-time-metric-toggle')
                <button
                    type="button"
                    wire:click="$dispatch('open-response-time-chart')"
                    class="inline-flex items-center gap-2 rounded-lg border border-white/15 px-4 py-2 text-sm font-medium text-zinc-200 transition hover:border-white/30 hover:bg-white/5 hover:text-white"
                >
                    Графік
                </button>
                <x-check-button
                    :action="route('addresses.check', [$site, $address])"
                    :site-id="$site->id"
                    :busy="in_array($site->id, $busySiteIds, true)"
                    label="Зробити знімок"
                />
                @if ($diff && $diff['has_changes'] && ! $diff['is_first'])
                    <button
                        type="button"
                        wire:click="acceptBaseline"
                        class="inline-flex items-center gap-2 rounded-lg border border-amber-300/30 bg-amber-300/10 px-4 py-2 text-sm font-medium text-amber-100 transition hover:bg-amber-300/20"
                    >
                        {{ __('alerts.ui.accept_baseline') }}
                    </button>
                @endif
            </div>
        </div>
    </div>

    <livewire:addresses.address-settings-modal :site="$site" :address="$address" :key="'addr-settings-'.$address->id" />
    <livewire:charts.response-time-chart-modal
        mode="address"
        :address-id="$address->id"
        title="Історія часу відповіді та TTFB"
        chart-id="address-response-time-chart"
        :key="'chart-address-'.$address->id"
    />

    @if ($latest && is_array($latest->assertion_results) && $latest->assertion_results !== [])
        <section class="mb-8 rounded-2xl border border-white/10 bg-zinc-900/80 p-5 shadow-xl shadow-cyan-950/20 backdrop-blur-sm">
            <h2 class="mb-3 text-lg font-semibold text-white">{{ __('alerts.ui.assertions') }}</h2>
            <ul class="space-y-2 text-sm">
                @foreach ($latest->assertion_results as $result)
                    <li class="flex flex-wrap items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3 py-2">
                        <span class="{{ ($result['passed'] ?? false) ? 'text-emerald-300' : 'text-rose-300' }}">
                            {{ ($result['passed'] ?? false) ? 'pass' : 'fail' }}
                        </span>
                        <span class="font-mono text-xs text-zinc-300">{{ $result['type'] ?? '' }}</span>
                        <span class="text-xs text-zinc-500">expected {{ is_scalar($result['expected'] ?? null) || $result['expected'] === null ? json_encode($result['expected'] ?? null) : '…' }}</span>
                        <span class="text-xs text-zinc-500">actual {{ is_scalar($result['actual'] ?? null) || $result['actual'] === null ? json_encode($result['actual'] ?? null) : '…' }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($diff && $latest)
        @if ($compareSnapshots->count() > 1)
            <div class="mb-4">
                <label class="mb-1 block text-sm font-medium text-zinc-200">{{ __('alerts.ui.compare_with') }}</label>
                <select wire:model.live="compareFromId" class="rounded-lg border border-white/15 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
                    <option value="">{{ __('alerts.ui.previous_snapshot') }}</option>
                    @foreach ($compareSnapshots as $option)
                        @if ($latest && $option->id !== $latest->id)
                            <option value="{{ $option->id }}">#{{ $option->id }} · {{ $option->created_at?->format('d.m.Y H:i:s') }}</option>
                        @endif
                    @endforeach
                </select>
            </div>
        @endif
        <div class="mb-8">
            @include('partials.diff', ['diff' => $diff])
        </div>

        <section class="mb-8 rounded-2xl border border-white/10 bg-zinc-900/80 p-5 shadow-xl shadow-cyan-950/20 backdrop-blur-sm">
            <h2 class="mb-3 text-lg font-semibold text-white">Останній знімок</h2>
            <dl class="grid gap-3 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-400">Дата</dt>
                    <dd class="mt-1 font-medium">{{ $latest->created_at->format('d.m.Y H:i:s') }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-400">Статус</dt>
                    <dd class="mt-1 font-medium">{{ $latest->status_code ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-400">{{ $metricEnum->snapshotTimeLabel() }}</dt>
                    <dd class="mt-1 font-medium">{{ $latest->formattedTimeMs($metricEnum) }}</dd>
                </div>
            </dl>
            @include('partials.timing', ['timing' => $latest->timing])
            @if ($latest->error_message)
                <p class="mt-4 rounded-lg border border-rose-400/20 bg-rose-400/10 px-3 py-2 text-sm text-rose-200">{{ $latest->error_message }}</p>
            @endif
            <div class="mt-4">
                <h3 class="mb-2 text-sm font-semibold text-zinc-100">Body</h3>
                <pre class="max-h-80 overflow-auto rounded-lg border border-white/10 bg-zinc-950 p-4 text-xs text-zinc-100"><code>{{ $latest->body }}</code></pre>
            </div>
            <div class="mt-4">
                <a href="{{ route('addresses.snapshots.show', [$site, $address, $latest]) }}" wire:navigate class="text-sm text-cyan-300 hover:text-cyan-200 hover:underline">
                    Відкрити деталі знімка →
                </a>
            </div>
        </section>
    @endif

    <section class="rounded-2xl border border-white/10 bg-zinc-900/80 shadow-xl shadow-cyan-950/20 backdrop-blur-sm">
        <div class="border-b border-white/10 px-5 py-4">
            <h2 class="text-base font-semibold text-white">Історія знімків</h2>
            @if (($stats['checks_count'] ?? 0) > 0)
                <dl class="mt-3 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-lg border border-white/10 bg-white/5 px-3 py-2">
                        <dt class="text-xs uppercase tracking-wide text-zinc-400">{{ $metricEnum->historyAverageLabel() }}</dt>
                        <dd class="mt-0.5 text-sm font-semibold text-white">{{ $stats['avg_response_time_ms'] !== null ? $stats['avg_response_time_ms'].' ms' : '—' }}</dd>
                    </div>
                    <div class="rounded-lg border border-white/10 bg-white/5 px-3 py-2">
                        <dt class="text-xs uppercase tracking-wide text-zinc-400">Середня к-сть помилок</dt>
                        <dd class="mt-0.5 text-sm font-semibold text-white">{{ $stats['avg_errors'] }}</dd>
                    </div>
                    <div class="rounded-lg border border-white/10 bg-white/5 px-3 py-2">
                        <dt class="text-xs uppercase tracking-wide text-zinc-400">Помилок / перевірок</dt>
                        <dd class="mt-0.5 text-sm font-semibold text-white">{{ $stats['error_count'] }} / {{ $stats['checks_count'] }}</dd>
                    </div>
                </dl>
            @endif
        </div>

        @if ($snapshots->isEmpty())
            <p class="px-5 py-8 text-sm text-zinc-400">Знімків ще немає. Натисніть «Зробити знімок».</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-white/10 text-sm">
                    <thead class="bg-white/5 text-left text-xs uppercase tracking-wide text-zinc-500">
                        <tr>
                            <th class="px-5 py-3">#</th>
                            <th class="px-5 py-3">Дата</th>
                            <th class="px-5 py-3">Статус</th>
                            <th class="px-5 py-3">{{ $metricEnum->columnLabel() }}</th>
                            <th class="px-5 py-3">Результат</th>
                            <th class="px-5 py-3 text-right">Дії</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach ($snapshots as $snapshot)
                            <tr class="hover:bg-white/5" wire:key="snapshot-{{ $snapshot->id }}">
                                <td class="px-5 py-3 font-mono text-xs text-zinc-400">{{ $snapshot->id }}</td>
                                <td class="px-5 py-3">{{ $snapshot->created_at->format('d.m.Y H:i:s') }}</td>
                                <td class="px-5 py-3">{{ $snapshot->status_code ?? '—' }}</td>
                                <td class="px-5 py-3">{{ $snapshot->formattedTimeMs($metricEnum) }}</td>
                                <td class="px-5 py-3">
                                    @if ($snapshot->error_message)
                                        <span class="text-rose-300">помилка</span>
                                    @else
                                        <span class="text-emerald-300">OK</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex flex-wrap justify-end gap-1.5">
                                        <a
                                            href="{{ route('addresses.snapshots.show', [$site, $address, $snapshot]) }}"
                                            wire:navigate
                                            title="Деталі"
                                            aria-label="Деталі"
                                            class="inline-flex items-center justify-center rounded-lg border border-white/15 p-2 text-zinc-300 transition hover:border-white/30 hover:bg-white/5 hover:text-white"
                                        >
                                            @include('partials.icons.eye')
                                        </a>
                                        <button
                                            type="button"
                                            wire:click="deleteSnapshot({{ $snapshot->id }})"
                                            wire:confirm="Видалити цей знімок?"
                                            title="Видалити"
                                            aria-label="Видалити"
                                            class="inline-flex items-center justify-center rounded-lg border border-rose-400/20 p-2 text-rose-300 transition hover:bg-rose-400/10"
                                        >
                                            @include('partials.icons.trash')
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($snapshots->hasPages())
                <div class="border-t border-white/10 px-5 py-4">
                    {{ $snapshots->links() }}
                </div>
            @endif
        @endif
    </section>
</div>
