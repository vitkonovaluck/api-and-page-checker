<div>
    <dialog
        wire:ignore.self
        x-data
        x-effect="$wire.show ? ($el.open || $el.showModal()) : ($el.open && $el.close())"
        @close="$wire.close()"
        @click="if ($event.target === $el) $wire.close()"
        class="w-[calc(100%-2rem)] max-w-4xl rounded-xl border border-slate-200 bg-white p-0 shadow-xl backdrop:bg-slate-900/40"
    >
        <div class="flex max-h-[90vh] flex-col">
            <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Помилкові запити (розклад)</h2>
                    <p class="mt-0.5 text-sm text-slate-500">
                        Знімки з помилками з адрес, увімкнених у розкладі
                    </p>
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

            <div class="overflow-y-auto px-5 py-5">
                @if (! $show)
                    <p class="py-6 text-center text-sm text-slate-500">Немає даних.</p>
                @elseif ($snapshots->isEmpty())
                    <p class="py-6 text-center text-sm text-slate-500">Помилкових запитів за розкладом немає.</p>
                @else
                    <div class="overflow-x-auto rounded-lg border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Дата</th>
                                    <th class="px-4 py-3">Адреса</th>
                                    <th class="px-4 py-3">Статус</th>
                                    <th class="px-4 py-3">Помилка</th>
                                    <th class="px-4 py-3 text-right">Дії</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($snapshots as $snapshot)
                                    <tr class="align-top hover:bg-slate-50/80" wire:key="error-snapshot-{{ $snapshot->id }}">
                                        <td class="whitespace-nowrap px-4 py-3 text-slate-700">
                                            {{ $snapshot->created_at->format('d.m.Y H:i:s') }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-slate-900">
                                                {{ $snapshot->address->name ?: 'Без назви' }}
                                            </div>
                                            <div class="mt-0.5 break-all font-mono text-xs text-slate-500">
                                                <span class="mr-1 rounded bg-slate-100 px-1 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600">
                                                    {{ $snapshot->address->http_method ?: 'GET' }}
                                                </span>
                                                {{ $snapshot->address->endpoint }}
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-slate-700">
                                            {{ $snapshot->status_code ?? '—' }}
                                        </td>
                                        <td class="max-w-xs px-4 py-3 text-red-800">
                                            <span class="line-clamp-3 break-words" title="{{ $snapshot->error_message }}">
                                                {{ $snapshot->error_message }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex justify-end">
                                                <a
                                                    href="{{ route('addresses.snapshots.show', [$site, $snapshot->address, $snapshot]) }}"
                                                    wire:navigate
                                                    title="Деталі"
                                                    aria-label="Деталі"
                                                    class="inline-flex items-center justify-center rounded-lg border border-slate-300 p-2 text-slate-700 hover:bg-slate-100"
                                                >
                                                    @include('partials.icons.eye')
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($snapshots->hasPages())
                        <div class="mt-4">
                            {{ $snapshots->links() }}
                        </div>
                    @endif
                @endif
            </div>

            <div class="flex justify-end border-t border-slate-200 px-5 py-4">
                <button
                    type="button"
                    wire:click="close"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
                >
                    Закрити
                </button>
            </div>
        </div>
    </dialog>
</div>
