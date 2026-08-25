<div wire:poll.3s="refreshData">
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-white">Список сайтів</h1>
            <p class="mt-1 text-sm text-zinc-400">У сайту вказується базовий URL, у адрес — лише ендпоїнти. Система об’єднує їх при перевірці. Тариф: {{ $quotaSummary }}.</p>
        </div>
        <button
            type="button"
            wire:click="$dispatch('open-create-site')"
            @disabled(! $canCreateSite)
            class="inline-flex rounded-xl bg-cyan-400 px-4 py-2 text-sm font-semibold text-zinc-950 transition hover:bg-cyan-300 disabled:cursor-not-allowed disabled:opacity-50"
        >
            Додати сайт
        </button>
    </div>

    <livewire:sites.create-site-modal />

    <section class="rounded-2xl border border-white/10 bg-zinc-900/80 shadow-xl shadow-cyan-950/20 backdrop-blur-sm">
        <div class="border-b border-white/10 px-5 py-4">
            <h2 class="text-base font-semibold text-white">Сайти ({{ $sites->count() }})</h2>
        </div>

        @if ($sites->isEmpty())
            <p class="px-5 py-8 text-sm text-zinc-400">Поки немає сайтів. Додайте перший кнопкою вище.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-white/10 text-sm">
                    <thead class="bg-white/5 text-left text-xs uppercase tracking-wide text-zinc-500">
                        <tr>
                            <th class="px-5 py-3">Назва</th>
                            <th class="px-5 py-3">Базовий URL</th>
                            <th class="px-5 py-3">Адрес</th>
                            <th class="px-5 py-3">Остання перевірка</th>
                            <th class="px-5 py-3">
                                <div>Сер. час перевірки</div>
                                <div class="mt-0.5 font-normal normal-case tracking-normal text-zinc-500">остання / 1 год / 24 год / разом</div>
                            </th>
                            <th class="px-5 py-3 text-right">Дії</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach ($sites as $site)
                            @php
                                $lastChecked = $site->addresses->max('last_checked_at');
                                $isChecking = in_array($site->id, $busySiteIds, true);
                            @endphp
                            <tr
                                class="align-top hover:bg-white/5 {{ $isChecking ? 'bg-emerald-400/10 hover:bg-emerald-400/10' : '' }}"
                                wire:key="site-{{ $site->id }}"
                            >
                                <td class="px-5 py-4">
                                    <a href="{{ route('sites.show', $site) }}" wire:navigate class="font-medium text-white hover:underline">
                                        {{ $site->name }}
                                    </a>
                                    @if ($isChecking)
                                        <div class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-emerald-300">
                                            @include('partials.icons.spinner', ['class' => 'h-3 w-3 animate-spin'])
                                            Перевіряється…
                                        </div>
                                    @endif
                                    @if ($site->schedule_enabled)
                                        <div class="mt-1 text-xs text-emerald-300">розклад: {{ \App\Models\Site::SCHEDULE_INTERVAL_LABELS[$site->schedule_interval] ?? $site->schedule_interval }}</div>
                                    @endif
                                </td>
                                <td class="px-5 py-4 break-all font-mono text-xs text-zinc-400">{{ $site->base_url }}</td>
                                <td class="px-5 py-4 text-zinc-400">{{ $site->addresses_count }}</td>
                                <td class="px-5 py-4 text-zinc-400">
                                    {{ $lastChecked ? \Illuminate\Support\Carbon::parse($lastChecked)->format('d.m.Y H:i:s') : 'ще не перевірявся' }}
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap text-xs tabular-nums text-zinc-400">{{ $checkTimes[$site->id] }}</td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap justify-end gap-1.5">
                                        <x-check-button
                                            :action="route('sites.check', $site)"
                                            :site-id="$site->id"
                                            :busy="$isChecking"
                                            :disabled="$site->addresses_count === 0"
                                            title="Перевірити сайт"
                                        />
                                        @if (in_array($site->id, $stoppableSiteIds, true))
                                            <x-stop-check-button :click="'stopManualCheckRun('.$site->id.')'" icon-only />
                                        @endif
                                        <a
                                            href="{{ route('sites.show', $site) }}"
                                            wire:navigate
                                            title="Відкрити"
                                            aria-label="Відкрити"
                                            class="inline-flex items-center justify-center rounded-lg border border-white/15 p-2 text-zinc-300 transition hover:border-white/30 hover:bg-white/5 hover:text-white"
                                        >
                                            @include('partials.icons.eye')
                                        </a>
                                        <button
                                            type="button"
                                            wire:click="delete({{ $site->id }})"
                                            wire:confirm="Видалити сайт, усі адреси і знімки?"
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
