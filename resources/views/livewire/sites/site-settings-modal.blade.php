<dialog
    x-data
    x-effect="$wire.show ? $el.showModal() : ($el.open && $el.close())"
    @click="if ($event.target === $el) $wire.close()"
    class="w-[calc(100%-2rem)] max-w-2xl rounded-xl border border-slate-200 bg-white p-0 shadow-xl backdrop:bg-slate-900/40"
>
    <form wire:submit="save" class="flex max-h-[85vh] flex-col">
        <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Налаштування сайту</h2>
                <p class="mt-0.5 text-sm text-slate-500">Назва, базовий URL і розклад перевірок</p>
            </div>
            <button
                type="button"
                wire:click="close"
                class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-800"
                title="Закрити"
                aria-label="Закрити"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="space-y-6 overflow-y-auto px-5 py-5">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="settings_name" class="mb-1 block text-sm font-medium text-slate-700">Назва</label>
                    <input
                        type="text"
                        id="settings_name"
                        wire:model="name"
                        required
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="settings_base_url" class="mb-1 block text-sm font-medium text-slate-700">Базовий URL</label>
                    <input
                        type="url"
                        id="settings_base_url"
                        wire:model="base_url"
                        required
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                    @error('base_url') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <h3 class="mb-3 text-sm font-semibold text-slate-900">Розклад перевірок</h3>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input
                            type="checkbox"
                            wire:model="schedule_enabled"
                            class="rounded border-slate-300 text-slate-900 focus:ring-slate-200"
                        >
                        Увімкнути розклад
                    </label>
                    <div>
                        <label for="schedule_interval" class="mb-1 block text-sm font-medium text-slate-700">Період</label>
                        <select
                            id="schedule_interval"
                            wire:model="schedule_interval"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                        >
                            @foreach (\App\Models\Site::SCHEDULE_INTERVAL_LABELS as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('schedule_interval') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                @if ($site->schedule_last_run_at)
                    <p class="mt-3 text-xs text-slate-500">
                        Останній запланований запуск: {{ $site->schedule_last_run_at->format('d.m.Y H:i:s') }}
                    </p>
                @endif

                @if ($site->addresses->isNotEmpty())
                    <div class="mt-4">
                        <p class="mb-2 text-sm font-medium text-slate-700">Адреси в розкладі</p>
                        <ul class="max-h-48 space-y-2 overflow-y-auto">
                            @foreach ($site->addresses as $address)
                                <li wire:key="addr-sched-{{ $address->id }}">
                                    <label class="flex items-start gap-2 text-sm text-slate-700">
                                        <input
                                            type="checkbox"
                                            wire:model="address_schedule"
                                            value="{{ $address->id }}"
                                            class="mt-0.5 rounded border-slate-300 text-slate-900 focus:ring-slate-200"
                                        >
                                        <span>
                                            <span class="font-medium">{{ $address->name ?: 'Без назви' }}</span>
                                            <span class="block font-mono text-xs text-slate-500">{{ $address->endpoint }}</span>
                                        </span>
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <div class="rounded-lg border border-red-200 bg-red-50/50 p-4">
                <h3 class="mb-1 text-sm font-semibold text-slate-900">Знімки (snapshots)</h3>
                <p class="mb-3 text-sm text-slate-600">
                    Збережено знімків: <span class="font-medium text-slate-900">{{ $snapshotsCount }}</span>.
                    Очищення видалить усю історію перевірок усіх адрес цього сайту.
                </p>
                <button
                    type="button"
                    wire:click="clearSnapshots"
                    wire:confirm="Видалити всі знімки цього сайту? Цю дію неможливо скасувати."
                    class="inline-flex rounded-lg border border-red-300 bg-white px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50"
                    @disabled($snapshotsCount === 0)
                >
                    Очистити всі знімки
                </button>
            </div>
        </div>

        <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-4">
            <button
                type="button"
                wire:click="close"
                class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
            >
                Скасувати
            </button>
            <button type="submit" class="inline-flex rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                Зберегти
            </button>
        </div>
    </form>
</dialog>
