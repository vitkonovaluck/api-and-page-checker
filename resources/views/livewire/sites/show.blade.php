<div wire:poll.3s="refreshData">
    <div class="mb-6">
        <a href="{{ route('sites.index') }}" wire:navigate class="text-sm text-sky-700 hover:underline">← До списку сайтів</a>
        <div class="mt-3 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">{{ $site->name }}</h1>
                <p class="mt-1 break-all font-mono text-sm text-slate-600">{{ $site->base_url }}</p>
                <p class="mt-1 text-sm text-slate-600">
                    Адреси цього сайту ({{ $site->addresses->count() }})
                    @if ($site->schedule_enabled)
                        <span class="ml-2 text-emerald-700">
                            · розклад: {{ \App\Models\Site::SCHEDULE_INTERVAL_LABELS[$site->schedule_interval] ?? $site->schedule_interval }}
                        </span>
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    wire:click="$dispatch('open-site-settings')"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
                >
                    @include('partials.icons.cog')
                    Налаштування
                </button>
                <button
                    type="button"
                    wire:click="$dispatch('open-address-list')"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
                >
                    Список адрес
                </button>
                <button
                    type="button"
                    wire:click="$dispatch('open-response-time-chart')"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
                >
                    Графік
                </button>
                <button
                    type="button"
                    wire:click="$dispatch('open-create-address')"
                    class="inline-flex rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
                >
                    Додати адресу
                </button>
                <button
                    type="button"
                    wire:click="copy"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
                >
                    Копіювати сайт
                </button>
                <x-check-button
                    :action="route('sites.check', $site)"
                    :busy="$checksBusy"
                    :disabled="$site->addresses->isEmpty()"
                    label="Перевірити всі адреси"
                />
            </div>
        </div>
    </div>

    <livewire:sites.site-settings-modal :site="$site" :key="'site-settings-'.$site->id" />
    <livewire:addresses.create-address-modal :site="$site" :key="'create-address-'.$site->id" />
    <livewire:sites.address-list-modal :site="$site" :key="'address-list-'.$site->id" />
    <livewire:sites.error-snapshots-modal :site="$site" :key="'error-snapshots-'.$site->id" />
    <livewire:charts.response-time-chart-modal
        mode="site"
        :site-id="$site->id"
        title="Історія часу відповіді адрес"
        chart-id="site-response-time-chart"
        :key="'chart-site-'.$site->id"
    />

    @if (($scheduleStats && $scheduleStats['checks_count'] > 0) || ($siteStats['checks_count'] ?? 0) > 0)
        <section class="mb-8 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-1 text-base font-semibold text-slate-900">Середні показники перевірок</h2>
            <p class="mb-4 text-sm text-slate-500">
                Середньоарифметичні значення за історією знімків
                @if ($site->schedule_enabled)
                    (для розкладу — окремо по запусках)
                @endif
            </p>
            <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @if ($site->schedule_enabled && $scheduleStats && $scheduleStats['checks_count'] > 0)
                    <div class="rounded-lg border border-emerald-100 bg-emerald-50/60 px-4 py-3">
                        <dt class="text-xs uppercase tracking-wide text-emerald-800/70">Сер. час (розклад)</dt>
                        <dd class="mt-1 text-lg font-semibold text-emerald-900">
                            {{ $scheduleStats['avg_response_time_ms'] !== null ? $scheduleStats['avg_response_time_ms'].' ms' : '—' }}
                        </dd>
                        <p class="mt-1 text-xs text-emerald-800/70">
                            {{ $scheduleStats['checks_count'] }} перевірок у розкладі
                        </p>
                    </div>
                    <div class="rounded-lg border border-emerald-100 bg-emerald-50/60 px-4 py-3">
                        <dt class="text-xs uppercase tracking-wide text-emerald-800/70">Сер. помилок / запуск</dt>
                        <dd class="mt-1 text-lg font-semibold text-emerald-900">
                            {{ $scheduleStats['avg_errors_per_run'] !== null ? $scheduleStats['avg_errors_per_run'] : '—' }}
                        </dd>
                        <p class="mt-1 text-xs text-emerald-800/70">
                            {{ $scheduleStats['error_count'] }} помилок за {{ $scheduleStats['runs_count'] }} запусків
                        </p>
                        @if (($scheduleStats['error_count'] ?? 0) > 0)
                            <button
                                type="button"
                                wire:click="$dispatch('open-error-snapshots')"
                                class="mt-2 inline-flex items-center gap-1.5 rounded-md border border-emerald-200 bg-white/80 px-2.5 py-1 text-xs font-medium text-emerald-900 hover:bg-white"
                            >
                                @include('partials.icons.eye', ['class' => 'h-3.5 w-3.5'])
                                Переглянути помилки
                            </button>
                        @endif
                    </div>
                @endif
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Сер. час (остання)</dt>
                    <dd class="mt-1 text-lg font-semibold text-slate-900">
                        {{ $siteStats['avg_latest_response_time_ms'] !== null ? $siteStats['avg_latest_response_time_ms'].' ms' : '—' }}
                    </dd>
                    <p class="mt-1 text-xs text-slate-500">
                        середнє по останніх знімках · {{ $siteStats['latest_checks_count'] }} адрес
                    </p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Сер. час (усі)</dt>
                    <dd class="mt-1 text-lg font-semibold text-slate-900">
                        {{ $siteStats['avg_response_time_ms'] !== null ? $siteStats['avg_response_time_ms'].' ms' : '—' }}
                    </dd>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ $siteStats['checks_count'] }} перевірок загалом
                    </p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Сер. помилок / перевірка</dt>
                    <dd class="mt-1 text-lg font-semibold text-slate-900">
                        {{ $siteStats['avg_errors'] !== null ? $siteStats['avg_errors'] : '—' }}
                    </dd>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ $siteStats['error_count'] }} помилок загалом
                    </p>
                </div>
            </dl>
        </section>
    @endif

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4 flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-slate-900">Адреси</h2>
            <button
                type="button"
                wire:click="$dispatch('open-create-address')"
                class="inline-flex rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800"
            >
                Додати адресу
            </button>
        </div>

        @if ($site->addresses->isEmpty())
            <p class="px-5 py-8 text-sm text-slate-500">Ще немає адрес. Додайте ендпоїнт кнопкою вище.</p>
        @else
            <div class="w-full max-w-full overflow-hidden">
                <table class="w-full table-fixed divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="w-10 px-3 py-2" title="Розклад">
                                <span class="sr-only">Розклад</span>
                                @include('partials.icons.clock', ['class' => 'h-3.5 w-3.5'])
                            </th>
                            <th class="px-3 py-2">Ендпоїнт</th>
                            <th class="w-40 px-3 py-2">Остання перевірка</th>
                            <th class="w-24 px-3 py-2">Статус</th>
                            <th class="w-28 px-3 py-2">Час відповіді</th>
                            <th class="w-24 px-3 py-2">Body</th>
                            <th class="w-24 px-3 py-2">Сер. час</th>
                            <th class="w-28 px-3 py-2">Сер. помилок</th>
                            <th class="w-36 px-3 py-2 text-right">Дії</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($site->addresses as $address)
                            @php
                                $address->setRelation('site', $site);
                                $latest = $address->latestSnapshot;
                                $previous = $address->previousSnapshot;
                                $statusChanged = $latest && $previous && $previous->status_code !== $latest->status_code;
                                $responseTimeDelta = ($latest && $previous)
                                    ? $latest->response_time_ms - $previous->response_time_ms
                                    : null;
                                $bodyChanged = $latest && $previous
                                    ? $latest->body_hash !== $previous->body_hash
                                    : null;
                                $stats = $addressStats[$address->id] ?? ['checks_count' => 0, 'avg_response_time_ms' => null, 'error_count' => 0, 'avg_errors' => null];
                                $headerCount = is_array($address->request_headers) ? count($address->request_headers) : 0;
                                $hasBody = filled($address->request_body);
                            @endphp
                            <tr class="hover:bg-slate-50/80" wire:key="address-{{ $address->id }}">
                                <td class="px-3 py-1.5 text-center">
                                    @if ($address->schedule_enabled)
                                        <span class="inline-flex text-emerald-700" title="У розкладі">
                                            @include('partials.icons.clock', ['class' => 'h-4 w-4'])
                                        </span>
                                    @endif
                                </td>
                                <td class="min-w-0 overflow-hidden px-3 py-1.5">
                                    <div class="flex min-w-0 items-center gap-1.5">
                                        <a href="{{ route('addresses.show', [$site, $address]) }}" wire:navigate title="{{ $address->endpoint }}" class="inline-flex min-w-0 items-center gap-1.5 font-mono text-xs text-sky-700 hover:underline">
                                            <span class="shrink-0 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-700">{{ $address->http_method ?: 'GET' }}</span>
                                            <span class="truncate">{{ $address->endpoint }}</span>
                                        </a>
                                        @if ($headerCount > 0)
                                            <span class="shrink-0 rounded bg-slate-100 px-1.5 py-0.5 text-[11px] text-slate-500">{{ $headerCount }} {{ $headerCount === 1 ? 'header' : 'headers' }}</span>
                                        @endif
                                        @if ($hasBody)
                                            <span class="shrink-0 rounded bg-slate-100 px-1.5 py-0.5 text-[11px] text-slate-500">body</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="truncate px-3 py-1.5 whitespace-nowrap text-slate-600">
                                    {{ $address->last_checked_at?->format('d.m.Y H:i:s') ?? 'ще не перевірялася' }}
                                </td>
                                <td class="px-3 py-1.5">
                                    @if ($latest?->error_message)
                                        <span class="rounded bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">
                                            помилка
                                            @if ($statusChanged)
                                                <span class="font-mono font-normal">({{ $previous->status_code ?? '—' }})</span>
                                            @endif
                                        </span>
                                    @elseif ($latest)
                                        <span @class([
                                            'rounded px-2 py-0.5 font-mono text-xs',
                                            'bg-red-100 text-red-800' => $statusChanged,
                                            'bg-slate-100 text-slate-800' => ! $statusChanged,
                                        ])>
                                            {{ $latest->status_code }}
                                            @if ($statusChanged)
                                                <span class="font-normal">({{ $previous->status_code ?? '—' }})</span>
                                            @endif
                                        </span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-1.5 text-slate-700">
                                    @if ($latest)
                                        <span class="inline-flex items-center gap-1">
                                            {{ $latest->response_time_ms }} ms
                                            @if ($responseTimeDelta !== null && $responseTimeDelta > 0)
                                                <span class="text-red-600" title="Було {{ $previous->response_time_ms }} ms">↑</span>
                                            @elseif ($responseTimeDelta !== null && $responseTimeDelta < 0)
                                                <span class="text-emerald-600" title="Було {{ $previous->response_time_ms }} ms">↓</span>
                                            @endif
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-1.5">
                                    @if ($bodyChanged === true)
                                        <span class="rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900" title="Body змінився порівняно з попереднім знімком">
                                            змінено
                                        </span>
                                    @elseif ($bodyChanged === false)
                                        <span class="rounded bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-800" title="Body збігається з попереднім знімком">
                                            без змін
                                        </span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-1.5 text-slate-700">
                                    {{ $stats['avg_response_time_ms'] !== null ? $stats['avg_response_time_ms'].' ms' : '—' }}
                                </td>
                                <td class="px-3 py-1.5 text-slate-700">
                                    @if ($stats['checks_count'] > 0)
                                        <span title="Середня кількість помилок на перевірку">{{ $stats['avg_errors'] }}</span>
                                        <span class="text-xs text-slate-400">{{ $stats['error_count'] }} / {{ $stats['checks_count'] }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-1.5">
                                    <div class="flex flex-nowrap justify-end gap-1.5">
                                        <x-check-button
                                            :action="route('addresses.check', [$site, $address])"
                                            :busy="$checksBusy"
                                            title="Перевірити"
                                        />
                                        <a
                                            href="{{ route('addresses.show', [$site, $address]) }}"
                                            wire:navigate
                                            title="Знімки"
                                            aria-label="Знімки"
                                            class="inline-flex items-center justify-center rounded-lg border border-slate-300 p-2 text-slate-700 hover:bg-slate-100"
                                        >
                                            @include('partials.icons.eye')
                                        </a>
                                        <button
                                            type="button"
                                            wire:click="deleteAddress({{ $address->id }})"
                                            wire:confirm="Видалити адресу і всі її знімки?"
                                            title="Видалити"
                                            aria-label="Видалити"
                                            class="inline-flex items-center justify-center rounded-lg border border-red-200 p-2 text-red-700 hover:bg-red-50"
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
