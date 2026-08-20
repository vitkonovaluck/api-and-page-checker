<dialog
    wire:ignore.self
    x-data
    x-effect="$wire.show ? ($el.open || $el.showModal()) : ($el.open && $el.close())"
    x-on:cancel.prevent=""
    x-on:close="if ($wire.show) { $nextTick(() => { if ($wire.show && ! $el.open) { $el.showModal() } }) }"
    class="w-[calc(100%-2rem)] max-w-2xl rounded-2xl border border-white/10 bg-zinc-900 p-0 text-zinc-100 shadow-2xl backdrop:bg-zinc-950/70"
>
    <form wire:submit.prevent="save" class="flex max-h-[85vh] flex-col">
        <div class="flex items-start justify-between gap-4 border-b border-white/10 px-5 py-4">
            <div>
                <h2 class="text-base font-semibold text-white">Налаштування сайту</h2>
                <p class="mt-0.5 text-sm text-zinc-400">Назва, базовий URL, темп і розклад перевірок</p>
            </div>
            <button
                type="button"
                wire:click="close"
                class="rounded-lg p-2 text-zinc-400 transition hover:bg-white/5 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-400"
                title="Закрити"
                aria-label="Закрити"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="space-y-6 overflow-y-auto px-5 py-5">
            @if ($errors->any())
                <div class="rounded-lg border border-rose-400/20 bg-rose-400/10 px-3 py-2 text-sm text-rose-200" role="alert">
                    <ul class="list-disc space-y-1 pl-4">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="settings_name" class="mb-1 block text-sm font-medium text-zinc-200">Назва</label>
                    <input
                        type="text"
                        id="settings_name"
                        wire:model="name"
                        required
                        class="w-full rounded-lg border border-white/15 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 shadow-sm focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-400/30"
                    >
                    @error('name') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="settings_base_url" class="mb-1 block text-sm font-medium text-zinc-200">Базовий URL</label>
                    <input
                        type="url"
                        id="settings_base_url"
                        wire:model="base_url"
                        required
                        class="w-full rounded-lg border border-white/15 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 shadow-sm focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-400/30"
                    >
                    @error('base_url') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
            </div>

            <div
                class="rounded-lg border border-white/10 bg-white/5 p-4"
                x-data="{ rpm: {{ (int) $requestsPerMinute }} }"
            >
                <h3 class="mb-3 text-sm font-semibold text-white">Темп перевірок</h3>
                <label for="settings_requests_per_minute" class="mb-1 block text-sm font-medium text-zinc-200">Перевірок на хвилину</label>
                <input
                    type="number"
                    id="settings_requests_per_minute"
                    min="{{ \App\Models\Site::CHECKS_PER_MINUTE_MIN }}"
                    max="{{ \App\Models\Site::CHECKS_PER_MINUTE_MAX }}"
                    step="1"
                    wire:model="requestsPerMinute"
                    x-model.number="rpm"
                    required
                    class="w-full max-w-xs rounded-lg border border-white/15 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 shadow-sm focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-400/30"
                >
                @error('requestsPerMinute') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                <p class="mt-2 text-xs text-zinc-400">
                    <span x-text="rpm > 0 ? ('Пауза між перевірками ≈ ' + Math.round(60 / rpm) + ' с. ') : ''"></span>
                    Для сторінок, що шлють кілька API-запитів, ставте 5–10, щоб уникати 429.
                </p>
            </div>

            <div class="rounded-lg border border-white/10 bg-white/5 p-4">
                <h3 class="mb-3 text-sm font-semibold text-white">Розклад перевірок</h3>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="flex items-center gap-2 text-sm text-zinc-300">
                        <input
                            type="checkbox"
                            wire:model.live="schedule_enabled"
                            class="rounded border-white/20 bg-zinc-950 text-cyan-400 focus:ring-cyan-400/40"
                        >
                        Увімкнути розклад
                    </label>
                    <div>
                        <label for="schedule_interval" class="mb-1 block text-sm font-medium text-zinc-200">Період</label>
                        <select
                            id="schedule_interval"
                            wire:model.live="schedule_interval"
                            class="w-full rounded-lg border border-white/15 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 shadow-sm focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-400/30"
                        >
                            @foreach (\App\Models\Site::SCHEDULE_INTERVAL_LABELS as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('schedule_interval') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                    </div>
                </div>
                <p class="mt-3 text-xs text-zinc-400">
                    @if ($schedule_interval === \App\Models\Site::SCHEDULE_INTERVAL_AFTER)
                        Після завершення попередньої перевірки наступна запуститься через 1 хв. Перший запуск — кнопкою «Перевірити всі адреси».
                    @else
                        Запуски вирівнюються по годиннику (наприклад, кожні 15 хв — о :00, :15, :30, :45), щоб контролювати навантаження.
                    @endif
                </p>
                @if ($site->schedule_last_run_at)
                    <p class="mt-3 text-xs text-zinc-400">
                        Останній запланований запуск: {{ $site->schedule_last_run_at->format('d.m.Y H:i:s') }}
                    </p>
                @endif

                @if ($site->addresses->isNotEmpty())
                    <div class="mt-4">
                        <p class="mb-2 text-sm font-medium text-zinc-300">Адреси в розкладі</p>
                        <ul class="max-h-48 space-y-2 overflow-y-auto">
                            @foreach ($site->addresses as $address)
                                <li wire:key="addr-sched-{{ $address->id }}">
                                    <label class="flex items-start gap-2 text-sm text-zinc-300">
                                        <input
                                            type="checkbox"
                                            wire:model.live="address_schedule"
                                            value="{{ $address->id }}"
                                            class="mt-0.5 rounded border-white/20 bg-zinc-950 text-cyan-400 focus:ring-cyan-400/40"
                                        >
                                        <span>
                                            <span class="font-medium">{{ $address->name ?: 'Без назви' }}</span>
                                            <span class="block font-mono text-xs text-zinc-400">{{ $address->endpoint }}</span>
                                        </span>
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <div class="rounded-lg border border-white/10 bg-white/5 p-4">
                <h3 class="mb-1 text-sm font-semibold text-white">Експорт і копія</h3>
                <p class="mb-3 text-sm text-zinc-400">
                    Експорт зберігає JSON цього сайту для іншого сервера. Копія створює дублікат тут, без історії перевірок.
                </p>
                <div class="flex flex-wrap gap-2">
                    <a
                        href="{{ route('sites.export', $site) }}"
                        class="inline-flex items-center gap-2 rounded-lg border border-white/15 px-3 py-1.5 text-sm font-medium text-zinc-200 transition hover:border-white/30 hover:bg-white/5 hover:text-white"
                    >
                        @include('partials.icons.download')
                        Експортувати
                    </a>
                    <button
                        type="button"
                        wire:click="copy"
                        wire:loading.attr="disabled"
                        wire:target="copy"
                        class="inline-flex items-center gap-2 rounded-lg border border-white/15 px-3 py-1.5 text-sm font-medium text-zinc-200 transition hover:border-white/30 hover:bg-white/5 hover:text-white disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        @include('partials.icons.copy')
                        <span wire:loading.remove wire:target="copy">Копіювати сайт</span>
                        <span wire:loading wire:target="copy">Копіювання…</span>
                    </button>
                </div>
            </div>

            <div class="rounded-lg border border-rose-400/20 bg-rose-400/10 p-4">
                <h3 class="mb-1 text-sm font-semibold text-white">Знімки (snapshots)</h3>
                <p class="mb-3 text-sm text-zinc-400">
                    Збережено знімків: <span class="font-medium text-white">{{ $snapshotsCount }}</span>.
                    Очищення видалить усю історію перевірок усіх адрес цього сайту.
                </p>
                <button
                    type="button"
                    wire:click="clearSnapshots"
                    wire:confirm="Видалити всі знімки цього сайту? Цю дію неможливо скасувати."
                    class="inline-flex rounded-lg border border-rose-400/30 bg-zinc-950 px-3 py-1.5 text-sm font-medium text-rose-300 transition hover:bg-rose-400/10 disabled:cursor-not-allowed disabled:opacity-50"
                    @disabled($snapshotsCount === 0)
                >
                    Очистити всі знімки
                </button>
            </div>
        </div>

        <div class="flex justify-end gap-2 border-t border-white/10 px-5 py-4">
            <button
                type="button"
                wire:click="close"
                class="rounded-lg border border-white/15 px-4 py-2 text-sm font-medium text-zinc-200 transition hover:border-white/30 hover:bg-white/5 hover:text-white"
            >
                Скасувати
            </button>
            <button type="submit" class="inline-flex rounded-xl bg-cyan-400 px-4 py-2 text-sm font-semibold text-zinc-950 transition hover:bg-cyan-300">
                Зберегти
            </button>
        </div>
    </form>
</dialog>
