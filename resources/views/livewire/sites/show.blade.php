<div>
<div wire:poll.3s="refreshData" class="flex min-h-0 flex-col gap-3 lg:h-[calc(100dvh-8rem)]">
    <div class="mb-0 shrink-0">
        <div class="flex flex-col gap-2 xl:flex-row xl:items-center xl:justify-between">
            <div class="min-w-0">
                <a href="{{ route('sites.index') }}" wire:navigate class="text-xs text-cyan-300 hover:text-cyan-200 hover:underline">← До списку сайтів</a>
                <div class="mt-1 flex flex-wrap items-baseline gap-x-3 gap-y-0.5">
                    <h1 class="text-lg font-semibold tracking-tight text-white">{{ $site->name }}</h1>
                    @if (in_array($site->id, $busySiteIds, true))
                        <p class="inline-flex items-center gap-1 text-xs font-medium text-emerald-300">
                            @include('partials.icons.spinner', ['class' => 'h-3 w-3 animate-spin'])
                            Перевіряється…
                        </p>
                    @endif
                    <p class="break-all font-mono text-xs text-zinc-400">{{ $site->base_url }}</p>
                    <p class="text-xs text-zinc-400">
                        Адреси цього сайту ({{ $site->addresses->count() }})
                        @if ($site->schedule_enabled)
                            <span class="text-emerald-300">
                                · розклад: {{ \App\Models\Site::SCHEDULE_INTERVAL_LABELS[$site->schedule_interval] ?? $site->schedule_interval }}
                            </span>
                        @endif
                        @if ($site->checksPerMinute() > 0)
                            <span class="text-zinc-400">· {{ $site->checksPerMinute() }} перевірок/хв</span>
                        @endif
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap gap-1.5">
                <button
                    type="button"
                    wire:click="$dispatch('open-site-settings')"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-white/15 px-3 py-1.5 text-sm font-medium text-zinc-200 transition hover:border-white/30 hover:bg-white/5 hover:text-white"
                >
                    @include('partials.icons.cog')
                    Налаштування
                </button>
                <button
                    type="button"
                    wire:click="$dispatch('open-address-list')"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-white/15 px-3 py-1.5 text-sm font-medium text-zinc-200 transition hover:border-white/30 hover:bg-white/5 hover:text-white"
                >
                    Список адрес
                </button>
                @include('partials.response-time-metric-toggle')
                <button
                    type="button"
                    wire:click="$dispatch('open-response-time-chart')"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-white/15 px-3 py-1.5 text-sm font-medium text-zinc-200 transition hover:border-white/30 hover:bg-white/5 hover:text-white"
                >
                    Графік
                </button>
                <button
                    type="button"
                    wire:click="$dispatch('open-create-address')"
                    class="inline-flex rounded-lg border border-white/15 px-3 py-1.5 text-sm font-medium text-zinc-200 transition hover:border-white/30 hover:bg-white/5 hover:text-white"
                >
                    Додати адресу
                </button>
                <x-check-button
                    :action="route('sites.check', $site)"
                    :site-id="$site->id"
                    :busy="in_array($site->id, $busySiteIds, true)"
                    :disabled="$site->addresses->isEmpty()"
                    label="Перевірити всі адреси"
                    class="px-3 py-1.5"
                />
                @if ($canDeleteLastManualRun && ! $checksBusy)
                    <button
                        type="button"
                        wire:click="deleteLastManualCheckRun"
                        wire:confirm="Видалити всі знімки останнього ручного проходу? Цю дію неможливо скасувати."
                        wire:loading.attr="disabled"
                        wire:target="deleteLastManualCheckRun"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-rose-400/20 px-3 py-1.5 text-sm font-medium text-rose-200 transition hover:bg-rose-400/10 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="deleteLastManualCheckRun" class="inline-flex">
                            @include('partials.icons.trash')
                        </span>
                        <span wire:loading wire:target="deleteLastManualCheckRun" class="inline-flex">
                            @include('partials.icons.spinner')
                        </span>
                        Видалити останній прохід
                    </button>
                @endif
            </div>
        </div>
    </div>

    @if (($scheduleStats && $scheduleStats['checks_count'] > 0) || ($siteStats['checks_count'] ?? 0) > 0)
        <section class="shrink-0 rounded-2xl border border-white/10 bg-zinc-900/80 px-4 py-3 shadow-xl shadow-cyan-950/20 backdrop-blur-sm">
            <div class="mb-2 flex flex-wrap items-baseline gap-x-3 gap-y-0.5">
                <h2 class="text-sm font-semibold text-white">Середні показники перевірок</h2>
                <p class="text-xs text-zinc-400">
                    Середньоарифметичні значення за історією знімків
                    @if ($site->schedule_enabled)
                        (для розкладу — окремо по запусках)
                    @endif
                </p>
            </div>
            <dl @class([
                'grid grid-cols-1 gap-2 sm:grid-cols-2',
                'xl:grid-cols-5' => $site->schedule_enabled && $scheduleStats && $scheduleStats['checks_count'] > 0,
                'xl:grid-cols-3' => ! ($site->schedule_enabled && $scheduleStats && $scheduleStats['checks_count'] > 0),
            ])>
                @if ($site->schedule_enabled && $scheduleStats && $scheduleStats['checks_count'] > 0)
                    <div class="rounded-lg border border-emerald-400/20 bg-emerald-400/10 px-3 py-2">
                        <dt class="text-[11px] uppercase tracking-wide text-emerald-300/80">{{ $metricEnum->scheduleAverageLabel() }}</dt>
                        <dd class="mt-0.5 text-base font-semibold text-emerald-100">
                            {{ $scheduleStats['avg_response_time_ms'] !== null ? $scheduleStats['avg_response_time_ms'].' ms' : '—' }}
                        </dd>
                        <p class="mt-0.5 text-[11px] text-emerald-300/80">
                            {{ $scheduleStats['checks_count'] }} перевірок у розкладі
                        </p>
                    </div>
                    <div class="rounded-lg border border-emerald-400/20 bg-emerald-400/10 px-3 py-2">
                        <dt class="text-[11px] uppercase tracking-wide text-emerald-300/80">Сер. помилок / запуск</dt>
                        <dd class="mt-0.5 text-base font-semibold text-emerald-100">
                            {{ $scheduleStats['avg_errors_per_run'] !== null ? $scheduleStats['avg_errors_per_run'] : '—' }}
                        </dd>
                        <p class="mt-0.5 text-[11px] text-emerald-300/80">
                            {{ $scheduleStats['error_count'] }} помилок за {{ $scheduleStats['runs_count'] }} запусків
                        </p>
                        @if (($scheduleStats['error_count'] ?? 0) > 0)
                            <button
                                type="button"
                                wire:click="$dispatch('open-error-snapshots')"
                                class="mt-1 inline-flex items-center gap-1 rounded-md border border-emerald-400/30 bg-zinc-950/60 px-2 py-0.5 text-[11px] font-medium text-emerald-100 hover:bg-zinc-900"
                            >
                                @include('partials.icons.eye', ['class' => 'h-3 w-3'])
                                Переглянути помилки
                            </button>
                        @endif
                    </div>
                @endif
                <div class="rounded-lg border border-white/10 bg-white/5 px-3 py-2">
                    <dt class="text-[11px] uppercase tracking-wide text-zinc-400">{{ $metricEnum->latestAverageLabel() }}</dt>
                    <dd class="mt-0.5 text-base font-semibold text-white">
                        {{ $siteStats['avg_latest_response_time_ms'] !== null ? $siteStats['avg_latest_response_time_ms'].' ms' : '—' }}
                    </dd>
                    <p class="mt-0.5 text-[11px] text-zinc-400">
                        середнє за останній прохід · {{ $siteStats['latest_checks_count'] }} адрес
                    </p>
                </div>
                <div class="rounded-lg border border-white/10 bg-white/5 px-3 py-2">
                    <dt class="text-[11px] uppercase tracking-wide text-zinc-400">{{ $metricEnum->allAverageLabel() }}</dt>
                    <dd class="mt-0.5 text-base font-semibold text-white">
                        {{ $siteStats['avg_response_time_ms'] !== null ? $siteStats['avg_response_time_ms'].' ms' : '—' }}
                    </dd>
                    <p class="mt-0.5 text-[11px] text-zinc-400">
                        {{ $siteStats['checks_count'] }} перевірок загалом
                    </p>
                </div>
                <div class="rounded-lg border border-white/10 bg-white/5 px-3 py-2">
                    <dt class="text-[11px] uppercase tracking-wide text-zinc-400">Сер. помилок / перевірка</dt>
                    <dd class="mt-0.5 text-base font-semibold text-white">
                        {{ $siteStats['avg_errors'] !== null ? $siteStats['avg_errors'] : '—' }}
                    </dd>
                    <p class="mt-0.5 text-[11px] text-zinc-400">
                        {{ $siteStats['error_count'] }} помилок загалом
                    </p>
                </div>
            </dl>
        </section>
    @endif

    <section class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-2xl border border-white/10 bg-zinc-900/80 shadow-xl shadow-cyan-950/20 backdrop-blur-sm">
        <div class="flex shrink-0 items-center justify-between gap-3 border-b border-white/10 px-4 py-2">
            <h2 class="text-sm font-semibold text-white">Адреси</h2>
            <button
                type="button"
                wire:click="$dispatch('open-create-address')"
                class="inline-flex rounded-xl bg-cyan-400 px-3 py-1 text-sm font-semibold text-zinc-950 transition hover:bg-cyan-300"
            >
                Додати адресу
            </button>
        </div>

        @if ($site->addresses->isEmpty())
            <p class="px-4 py-6 text-sm text-zinc-400">Ще немає адрес. Додайте ендпоїнт кнопкою вище.</p>
        @else
            <div wire:key="site-addresses-scroll" class="min-h-0 w-full max-w-full flex-1 overflow-auto overscroll-contain">
                <table class="w-full table-fixed divide-y divide-white/10 text-sm">
                    <thead class="sticky top-0 z-10 bg-zinc-900 text-left text-xs uppercase tracking-wide text-zinc-500">
                        <tr>
                            <th class="w-10 px-3 py-2" title="Розклад">
                                <span class="sr-only">Розклад</span>
                                @include('partials.icons.clock', ['class' => 'h-3.5 w-3.5'])
                            </th>
                            <th class="px-3 py-2">Ендпоїнт</th>
                            <th class="w-40 px-3 py-2">Остання перевірка</th>
                            <th class="w-24 px-3 py-2">Статус</th>
                            <th class="w-28 px-3 py-2">{{ $metricEnum->columnLabel() }}</th>
                            <th class="w-24 px-3 py-2">Body</th>
                            <th class="w-24 px-3 py-2">{{ $metricEnum->averageLabel() }}</th>
                            <th class="w-28 px-3 py-2">Сер. помилок</th>
                            <th class="w-36 px-3 py-2 text-right">Дії</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach ($site->addresses as $address)
                            @php
                                $address->setRelation('site', $site);
                                $latest = $address->latestSnapshot;
                                $previous = $address->previousSnapshot;
                                $statusChanged = $latest && $previous && $previous->status_code !== $latest->status_code;
                                $responseTimeDelta = $latest?->timeDeltaMs($previous, $metricEnum);
                                $bodyChanged = $latest && $previous
                                    ? $latest->body_hash !== $previous->body_hash
                                    : null;
                                $stats = $addressStats[$address->id] ?? ['checks_count' => 0, 'avg_response_time_ms' => null, 'error_count' => 0, 'avg_errors' => null];
                                $headerCount = is_array($address->request_headers) ? count($address->request_headers) : 0;
                                $hasBody = filled($address->request_body);
                            @endphp
                            <tr class="hover:bg-white/5" wire:key="address-{{ $address->id }}">
                                <td class="px-3 py-1.5 text-center">
                                    @if ($address->schedule_enabled)
                                        <span class="inline-flex text-emerald-300" title="У розкладі">
                                            @include('partials.icons.clock', ['class' => 'h-4 w-4'])
                                        </span>
                                    @endif
                                </td>
                                <td class="min-w-0 overflow-hidden px-3 py-1.5">
                                    <div class="flex min-w-0 items-center gap-1.5">
                                        <a href="{{ route('addresses.show', [$site, $address]) }}" wire:navigate title="{{ $address->endpoint }}" class="inline-flex min-w-0 items-center gap-1.5 font-mono text-xs text-cyan-300 hover:text-cyan-200 hover:underline">
                                            <span class="shrink-0 rounded bg-white/10 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-zinc-300">{{ $address->http_method ?: 'GET' }}</span>
                                            <span class="truncate">{{ $address->endpoint }}</span>
                                        </a>
                                        @if ($headerCount > 0)
                                            <span class="shrink-0 rounded bg-white/10 px-1.5 py-0.5 text-[11px] text-zinc-400">{{ $headerCount }} {{ $headerCount === 1 ? 'header' : 'headers' }}</span>
                                        @endif
                                        @if ($hasBody)
                                            <span class="shrink-0 rounded bg-white/10 px-1.5 py-0.5 text-[11px] text-zinc-400">body</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="truncate px-3 py-1.5 whitespace-nowrap text-zinc-400">
                                    {{ $address->last_checked_at?->format('d.m.Y H:i:s') ?? 'ще не перевірялася' }}
                                </td>
                                <td class="px-3 py-1.5">
                                    @if ($latest?->error_message)
                                        <span class="rounded border border-rose-400/20 bg-rose-400/10 px-2 py-0.5 text-xs font-medium text-rose-200">
                                            помилка
                                            @if ($statusChanged)
                                                <span class="font-mono font-normal">({{ $previous->status_code ?? '—' }})</span>
                                            @endif
                                        </span>
                                    @elseif ($latest)
                                        <span @class([
                                            'rounded px-2 py-0.5 font-mono text-xs',
                                            'border border-rose-400/20 bg-rose-400/10 text-rose-200' => $statusChanged,
                                            'bg-white/10 text-zinc-200' => ! $statusChanged,
                                        ])>
                                            {{ $latest->status_code }}
                                            @if ($statusChanged)
                                                <span class="font-normal">({{ $previous->status_code ?? '—' }})</span>
                                            @endif
                                        </span>
                                    @else
                                        <span class="text-zinc-500">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-1.5 text-zinc-300">
                                    @if ($latest)
                                        <span class="inline-flex items-center gap-1">
                                            {{ $latest->formattedTimeMs($metricEnum) }}
                                            @if ($responseTimeDelta !== null && $responseTimeDelta > 0)
                                                <span class="text-rose-400" title="Було {{ $previous->formattedTimeMs($metricEnum) }}">↑</span>
                                            @elseif ($responseTimeDelta !== null && $responseTimeDelta < 0)
                                                <span class="text-emerald-400" title="Було {{ $previous->formattedTimeMs($metricEnum) }}">↓</span>
                                            @endif
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-1.5">
                                    @if ($bodyChanged === true)
                                        <span class="rounded border border-amber-300/20 bg-amber-300/10 px-2 py-0.5 text-xs font-medium text-amber-100" title="Body змінився порівняно з попереднім знімком">
                                            змінено
                                        </span>
                                    @elseif ($bodyChanged === false)
                                        <span class="rounded border border-emerald-400/20 bg-emerald-400/10 px-2 py-0.5 text-xs font-medium text-emerald-300" title="Body збігається з попереднім знімком">
                                            без змін
                                        </span>
                                    @else
                                        <span class="text-zinc-500">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-1.5 text-zinc-300">
                                    {{ $stats['avg_response_time_ms'] !== null ? $stats['avg_response_time_ms'].' ms' : '—' }}
                                </td>
                                <td class="px-3 py-1.5 text-zinc-300">
                                    @if ($stats['checks_count'] > 0)
                                        <span title="Середня кількість помилок на перевірку">{{ $stats['avg_errors'] }}</span>
                                        <span class="text-xs text-zinc-500">{{ $stats['error_count'] }} / {{ $stats['checks_count'] }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-1.5">
                                    <div class="flex flex-nowrap justify-end gap-1.5">
                                        <x-check-button
                                            :action="route('addresses.check', [$site, $address])"
                                            :site-id="$site->id"
                                            :busy="in_array($site->id, $busySiteIds, true)"
                                            title="Перевірити"
                                        />
                                        <a
                                            href="{{ route('addresses.show', [$site, $address]) }}"
                                            wire:navigate
                                            title="Знімки"
                                            aria-label="Знімки"
                                            class="inline-flex items-center justify-center rounded-lg border border-white/15 p-2 text-zinc-300 transition hover:border-white/30 hover:bg-white/5 hover:text-white"
                                        >
                                            @include('partials.icons.eye')
                                        </a>
                                        <button
                                            type="button"
                                            wire:click="deleteAddress({{ $address->id }})"
                                            wire:confirm="Видалити адресу і всі її знімки?"
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
        @endif
    </section>
</div>

    <livewire:sites.site-settings-modal :site="$site" :key="'site-settings-'.$site->id" />
    <livewire:addresses.create-address-modal :site="$site" :key="'create-address-'.$site->id" />
    <livewire:sites.address-list-modal :site="$site" :key="'address-list-'.$site->id" />
    <livewire:sites.error-snapshots-modal :site="$site" :key="'error-snapshots-'.$site->id" />
    <livewire:charts.response-time-chart-modal
        mode="site"
        :site-id="$site->id"
        title="Історія часу відповіді та TTFB адрес"
        chart-id="site-response-time-chart"
        :key="'chart-site-'.$site->id"
    />
</div>
