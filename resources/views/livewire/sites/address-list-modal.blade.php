<div>
    <dialog
        wire:ignore.self
        x-data
        x-effect="$wire.show ? ($el.open || $el.showModal()) : ($el.open && $el.close())"
        @close="$wire.close()"
        @click="if ($event.target === $el) $wire.close()"
        class="w-[calc(100%-2rem)] max-w-2xl rounded-2xl border border-white/10 bg-zinc-900 p-0 text-zinc-100 shadow-2xl backdrop:bg-zinc-950/70"
    >
        <div class="flex max-h-[90vh] flex-col">
            <div class="flex items-start justify-between gap-4 border-b border-white/10 px-5 py-4">
                <div>
                    <h2 class="text-base font-semibold text-white">Адреси для перевірки</h2>
                    <p class="mt-0.5 text-sm text-zinc-400">
                        Усі ендпоїнти сайту «{{ $site->name }}»
                        @if ($show)
                            ({{ $addresses->count() }})
                        @endif
                    </p>
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

            <div class="overflow-y-auto px-5 py-5">
                @if (! $show)
                    <p class="py-6 text-center text-sm text-zinc-400">Немає даних.</p>
                @elseif ($addresses->isEmpty())
                    <p class="py-6 text-center text-sm text-zinc-400">Адрес для перевірки ще немає.</p>
                @else
                    <ul class="space-y-1.5 font-mono text-sm text-zinc-100">
                        @foreach ($addresses as $address)
                            <li class="break-all" wire:key="address-list-{{ $address->id }}">
                                {{ $address->http_method ?: 'GET' }} {{ $address->endpoint }}
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="flex justify-end border-t border-white/10 px-5 py-4">
                <button
                    type="button"
                    wire:click="close"
                    class="rounded-lg border border-white/15 px-4 py-2 text-sm font-medium text-zinc-200 transition hover:border-white/30 hover:bg-white/5 hover:text-white"
                >
                    Закрити
                </button>
            </div>
        </div>
    </dialog>
</div>
