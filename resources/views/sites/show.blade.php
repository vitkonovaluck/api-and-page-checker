@extends('layouts.app')

@section('title', $site->name.' — API Snapshot Checker')

@section('content')
    @php
        $openSettingsModal = old('address_schedule_submitted') !== null;
    @endphp

    <div class="mb-6">
        <a href="{{ route('sites.index') }}" class="text-sm text-sky-700 hover:underline">← До списку сайтів</a>
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
                    id="open-site-settings"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
                >
                    @include('partials.icons.cog')
                    Налаштування
                </button>
                <form method="POST" action="{{ route('sites.copy', $site) }}">
                    @csrf
                    <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">
                        Копіювати сайт
                    </button>
                </form>
                <form method="POST" action="{{ route('sites.check', $site) }}">
                    @csrf
                    <button
                        type="submit"
                        class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-50"
                        @disabled($site->addresses->isEmpty())
                    >
                        Перевірити всі адреси
                    </button>
                </form>
            </div>
        </div>
    </div>

    <dialog
        id="site-settings-modal"
        class="w-[calc(100%-2rem)] max-w-2xl rounded-xl border border-slate-200 bg-white p-0 shadow-xl backdrop:bg-slate-900/40"
        @if ($openSettingsModal) data-open-on-load="1" @endif
    >
        <form method="POST" action="{{ route('sites.update', $site) }}" class="flex max-h-[85vh] flex-col">
            @csrf
            @method('PUT')
            <input type="hidden" name="address_schedule_submitted" value="1">

            <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Налаштування сайту</h2>
                    <p class="mt-0.5 text-sm text-slate-500">Назва, базовий URL і розклад перевірок</p>
                </div>
                <button
                    type="button"
                    id="close-site-settings"
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
                            name="name"
                            id="settings_name"
                            value="{{ old('name', $site->name) }}"
                            required
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                        >
                    </div>
                    <div>
                        <label for="settings_base_url" class="mb-1 block text-sm font-medium text-slate-700">Базовий URL</label>
                        <input
                            type="url"
                            name="base_url"
                            id="settings_base_url"
                            value="{{ old('base_url', $site->base_url) }}"
                            required
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                        >
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <h3 class="mb-3 text-sm font-semibold text-slate-900">Розклад перевірок</h3>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                name="schedule_enabled"
                                value="1"
                                @checked(old('schedule_enabled', $site->schedule_enabled))
                                class="rounded border-slate-300 text-slate-900 focus:ring-slate-200"
                            >
                            Увімкнути розклад
                        </label>
                        <div>
                            <label for="schedule_interval" class="mb-1 block text-sm font-medium text-slate-700">Період</label>
                            <select
                                name="schedule_interval"
                                id="schedule_interval"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                            >
                                @foreach (\App\Models\Site::SCHEDULE_INTERVAL_LABELS as $value => $label)
                                    <option value="{{ $value }}" @selected(old('schedule_interval', $site->schedule_interval) === $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
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
                                    <li>
                                        <label class="flex items-start gap-2 text-sm text-slate-700">
                                            <input
                                                type="checkbox"
                                                name="address_schedule[]"
                                                value="{{ $address->id }}"
                                                @checked(in_array($address->id, array_map('intval', (array) old('address_schedule', $address->schedule_enabled ? [$address->id] : [])), true))
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
            </div>

            <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-4">
                <button
                    type="button"
                    id="cancel-site-settings"
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

    <section class="mb-8 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-4 text-base font-semibold text-slate-900">Додати адресу</h2>
        <form method="POST" action="{{ route('addresses.store', $site) }}" class="grid gap-4 sm:grid-cols-2">
            @csrf
            <div class="sm:col-span-2">
                <label for="endpoint" class="mb-1 block text-sm font-medium text-slate-700">Ендпоїнт</label>
                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-2">
                    <span class="shrink-0 font-mono text-xs text-slate-500">{{ $site->base_url }}</span>
                    <input
                        type="text"
                        name="endpoint"
                        id="endpoint"
                        value="{{ old('endpoint') }}"
                        required
                        placeholder="/api/users"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                </div>
            </div>
            <div>
                <label for="address_name" class="mb-1 block text-sm font-medium text-slate-700">Назва (необовʼязково)</label>
                <input
                    type="text"
                    name="name"
                    id="address_name"
                    value="{{ old('name') }}"
                    placeholder="Наприклад, Users"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                >
            </div>
            <div class="flex items-end gap-4">
                <label class="flex items-center gap-2 pb-2 text-sm text-slate-700">
                    <input type="hidden" name="schedule_enabled" value="0">
                    <input
                        type="checkbox"
                        name="schedule_enabled"
                        value="1"
                        @checked(old('schedule_enabled', '1') === '1' || old('schedule_enabled') === true || old('schedule_enabled') === 1)
                        class="rounded border-slate-300 text-slate-900 focus:ring-slate-200"
                    >
                    У розкладі
                </label>
                <button type="submit" class="inline-flex rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                    Додати адресу
                </button>
            </div>
        </form>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-base font-semibold text-slate-900">Адреси</h2>
        </div>

        @if ($site->addresses->isEmpty())
            <p class="px-5 py-8 text-sm text-slate-500">Ще немає адрес. Додайте ендпоїнт вище.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Назва / ендпоїнт</th>
                            <th class="px-5 py-3">Остання перевірка</th>
                            <th class="px-5 py-3">Статус</th>
                            <th class="px-5 py-3">Час відповіді</th>
                            <th class="px-5 py-3 text-right">Дії</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($site->addresses as $address)
                            @php
                                $address->setRelation('site', $site);
                                $latest = $address->latestSnapshot;
                            @endphp
                            <tr class="align-top hover:bg-slate-50/80">
                                <td class="px-5 py-4">
                                    <div class="font-medium text-slate-900">
                                        {{ $address->name ?: 'Без назви' }}
                                        @if ($address->schedule_enabled)
                                            <span class="ml-1 rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-emerald-700">schedule</span>
                                        @endif
                                    </div>
                                    <a href="{{ route('addresses.show', [$site, $address]) }}" class="mt-1 block break-all font-mono text-xs text-sky-700 hover:underline">
                                        {{ $address->endpoint }}
                                    </a>
                                    <div class="mt-0.5 break-all font-mono text-[11px] text-slate-400">{{ $address->fullUrl() }}</div>
                                </td>
                                <td class="px-5 py-4 text-slate-600">
                                    {{ $address->last_checked_at?->format('d.m.Y H:i:s') ?? 'ще не перевірялася' }}
                                </td>
                                <td class="px-5 py-4">
                                    @if ($latest?->error_message)
                                        <span class="rounded bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">помилка</span>
                                    @elseif ($latest)
                                        <span class="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-800">{{ $latest->status_code }}</span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-slate-700">
                                    {{ $latest ? $latest->response_time_ms.' ms' : '—' }}
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap justify-end gap-1.5">
                                        <form method="POST" action="{{ route('addresses.check', [$site, $address]) }}">
                                            @csrf
                                            <button
                                                type="submit"
                                                title="Перевірити"
                                                aria-label="Перевірити"
                                                class="inline-flex items-center justify-center rounded-lg bg-emerald-600 p-2 text-white hover:bg-emerald-500"
                                            >
                                                @include('partials.icons.refresh')
                                            </button>
                                        </form>
                                        <a
                                            href="{{ route('addresses.show', [$site, $address]) }}"
                                            title="Знімки"
                                            aria-label="Знімки"
                                            class="inline-flex items-center justify-center rounded-lg border border-slate-300 p-2 text-slate-700 hover:bg-slate-100"
                                        >
                                            @include('partials.icons.eye')
                                        </a>
                                        <form method="POST" action="{{ route('addresses.destroy', [$site, $address]) }}" onsubmit="return confirm('Видалити адресу і всі її знімки?')">
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                title="Видалити"
                                                aria-label="Видалити"
                                                class="inline-flex items-center justify-center rounded-lg border border-red-200 p-2 text-red-700 hover:bg-red-50"
                                            >
                                                @include('partials.icons.trash')
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <script>
        (() => {
            const modal = document.getElementById('site-settings-modal');
            if (!modal) return;

            const open = () => {
                if (typeof modal.showModal === 'function') {
                    modal.showModal();
                } else {
                    modal.setAttribute('open', '');
                }
            };

            const close = () => {
                if (typeof modal.close === 'function') {
                    modal.close();
                } else {
                    modal.removeAttribute('open');
                }
            };

            document.getElementById('open-site-settings')?.addEventListener('click', open);
            document.getElementById('close-site-settings')?.addEventListener('click', close);
            document.getElementById('cancel-site-settings')?.addEventListener('click', close);

            modal.addEventListener('click', (event) => {
                if (event.target === modal) {
                    close();
                }
            });

            if (modal.dataset.openOnLoad === '1') {
                open();
            }
        })();
    </script>
@endsection
