<div>
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Список сайтів</h1>
            <p class="mt-1 text-sm text-slate-600">У сайту вказується базовий URL, у адрес — лише ендпоїнти. Система об’єднує їх при перевірці.</p>
        </div>
        <button
            type="button"
            wire:click="$dispatch('open-create-site')"
            class="inline-flex rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
        >
            Додати сайт
        </button>
    </div>

    <livewire:sites.create-site-modal />

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-base font-semibold text-slate-900">Сайти ({{ $sites->count() }})</h2>
        </div>

        @if ($sites->isEmpty())
            <p class="px-5 py-8 text-sm text-slate-500">Поки немає сайтів. Додайте перший кнопкою вище.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Назва</th>
                            <th class="px-5 py-3">Базовий URL</th>
                            <th class="px-5 py-3">Адрес</th>
                            <th class="px-5 py-3">Остання перевірка</th>
                            <th class="px-5 py-3 text-right">Дії</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($sites as $site)
                            @php
                                $lastChecked = $site->addresses->max('last_checked_at');
                            @endphp
                            <tr class="align-top hover:bg-slate-50/80" wire:key="site-{{ $site->id }}">
                                <td class="px-5 py-4">
                                    <a href="{{ route('sites.show', $site) }}" wire:navigate class="font-medium text-slate-900 hover:underline">
                                        {{ $site->name }}
                                    </a>
                                    @if ($site->schedule_enabled)
                                        <div class="mt-1 text-xs text-emerald-700">розклад: {{ \App\Models\Site::SCHEDULE_INTERVAL_LABELS[$site->schedule_interval] ?? $site->schedule_interval }}</div>
                                    @endif
                                </td>
                                <td class="px-5 py-4 break-all font-mono text-xs text-slate-600">{{ $site->base_url }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $site->addresses_count }}</td>
                                <td class="px-5 py-4 text-slate-600">
                                    {{ $lastChecked ? \Illuminate\Support\Carbon::parse($lastChecked)->format('d.m.Y H:i:s') : 'ще не перевірявся' }}
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap justify-end gap-1.5">
                                        <form method="POST" action="{{ route('sites.check', $site) }}">
                                            @csrf
                                            <button
                                                type="submit"
                                                title="Перевірити сайт"
                                                aria-label="Перевірити сайт"
                                                class="inline-flex items-center justify-center rounded-lg bg-emerald-600 p-2 text-white hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-50"
                                                @disabled($site->addresses_count === 0)
                                            >
                                                @include('partials.icons.refresh')
                                            </button>
                                        </form>
                                        <a
                                            href="{{ route('sites.show', $site) }}"
                                            wire:navigate
                                            title="Відкрити"
                                            aria-label="Відкрити"
                                            class="inline-flex items-center justify-center rounded-lg border border-slate-300 p-2 text-slate-700 hover:bg-slate-100"
                                        >
                                            @include('partials.icons.eye')
                                        </a>
                                        <button
                                            type="button"
                                            wire:click="copy({{ $site->id }})"
                                            title="Копіювати сайт"
                                            aria-label="Копіювати сайт"
                                            class="inline-flex items-center justify-center rounded-lg border border-slate-300 p-2 text-slate-700 hover:bg-slate-100"
                                        >
                                            @include('partials.icons.copy')
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="delete({{ $site->id }})"
                                            wire:confirm="Видалити сайт, усі адреси і знімки?"
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
