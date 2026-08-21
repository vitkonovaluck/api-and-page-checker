<section class="rounded-2xl border border-white/10 bg-zinc-900/80 p-5 shadow-xl shadow-cyan-950/20 backdrop-blur-sm">
    <h2 class="mb-2 text-base font-semibold text-white">Агенти перевірок</h2>
    <p class="mb-4 text-sm text-zinc-400">
        Токен прив’язаний до вашого облікового запису. Windows-агент використовує його замість пароля
        (потрібно для входу через Google). Токен показується лише один раз.
    </p>

    @if ($plainTextToken)
        <div class="mb-4 rounded-lg border border-cyan-400/30 bg-cyan-400/10 p-3">
            <p class="mb-1 text-xs font-medium text-cyan-200">Скопіюйте токен зараз — його більше не буде видно.</p>
            <code class="block break-all font-mono text-xs text-cyan-100">{{ $plainTextToken }}</code>
        </div>
    @endif

    <form wire:submit="create" class="mb-6 grid gap-3 sm:grid-cols-[1fr_1fr_auto]">
        <div>
            <label for="agent-name" class="mb-1 block text-sm font-medium text-zinc-200">Назва агента</label>
            <input
                id="agent-name"
                type="text"
                wire:model="name"
                required
                placeholder="Office-PC"
                class="w-full rounded-lg border border-white/15 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 shadow-sm focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-400/30"
            >
            @error('name') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="agent-hostname" class="mb-1 block text-sm font-medium text-zinc-200">Hostname (необовʼязково)</label>
            <input
                id="agent-hostname"
                type="text"
                wire:model="hostname"
                placeholder="DESKTOP-ABC"
                class="w-full rounded-lg border border-white/15 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 shadow-sm focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-400/30"
            >
            @error('hostname') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
        </div>
        <div class="flex items-end">
            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="create"
                class="inline-flex w-full items-center justify-center rounded-xl bg-cyan-400 px-4 py-2 text-sm font-semibold text-zinc-950 transition hover:bg-cyan-300 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"
            >
                <span wire:loading.remove wire:target="create">Створити токен</span>
                <span wire:loading wire:target="create">Створення…</span>
            </button>
        </div>
    </form>

    @if ($agents->isEmpty())
        <p class="text-sm text-zinc-500">Ще немає агентів.</p>
    @else
        <ul class="space-y-2">
            @foreach ($agents as $agent)
                <li wire:key="agent-{{ $agent->id }}" class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-white/10 bg-zinc-950/60 px-3 py-2">
                    <div>
                        <p class="text-sm font-medium text-zinc-100">{{ $agent->name }}</p>
                        <p class="text-xs text-zinc-500">
                            @if ($agent->hostname)
                                {{ $agent->hostname }}
                                ·
                            @endif
                            @if ($agent->hasActiveToken())
                                токен активний
                            @else
                                токен відкликано
                            @endif
                            @if ($agent->last_seen_at)
                                · востаннє {{ $agent->last_seen_at->diffForHumans() }}
                            @endif
                        </p>
                    </div>
                    <button
                        type="button"
                        wire:click="revoke({{ $agent->id }})"
                        wire:confirm="Відкликати токен агента {{ $agent->name }}?"
                        wire:loading.attr="disabled"
                        wire:target="revoke({{ $agent->id }})"
                        class="rounded-lg border border-rose-400/30 px-3 py-1.5 text-xs font-medium text-rose-200 transition hover:bg-rose-400/10"
                    >
                        Відкликати
                    </button>
                </li>
            @endforeach
        </ul>
    @endif
</section>
