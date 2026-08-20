<dialog
    x-data
    x-effect="$wire.show ? $el.showModal() : ($el.open && $el.close())"
    @click="if ($event.target === $el) $wire.close()"
    @close="if ($wire.show) $wire.close()"
    class="w-[calc(100%-2rem)] max-w-md rounded-2xl border border-white/10 bg-zinc-900 p-0 text-zinc-100 shadow-2xl backdrop:bg-zinc-950/70"
>
    <form wire:submit="login" class="flex max-h-[85vh] flex-col">
        <div class="flex items-start justify-between gap-4 border-b border-white/10 px-5 py-4">
            <div>
                <h2 class="text-base font-semibold text-white">Вхід</h2>
                <p class="mt-0.5 text-sm text-zinc-400">Увійдіть, щоб керувати своїми сайтами.</p>
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

        <div class="space-y-4 overflow-y-auto px-5 py-5">
            <div>
                <label for="login-email" class="mb-1 block text-sm font-medium text-zinc-200">Email</label>
                <input
                    id="login-email"
                    type="email"
                    wire:model="email"
                    required
                    autocomplete="email"
                    class="w-full rounded-lg border border-white/15 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 shadow-sm focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-400/30"
                >
                @error('email') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="login-password" class="mb-1 block text-sm font-medium text-zinc-200">Пароль</label>
                <input
                    id="login-password"
                    type="password"
                    wire:model="password"
                    required
                    autocomplete="current-password"
                    class="w-full rounded-lg border border-white/15 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 shadow-sm focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-400/30"
                >
                @error('password') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
            </div>
            <label class="flex items-center gap-2 text-sm text-zinc-300">
                <input type="checkbox" wire:model="remember" class="rounded border-white/20 bg-zinc-950 text-cyan-400 focus:ring-cyan-400/40">
                Запам’ятати мене
            </label>
            <button
                type="submit"
                wire:loading.attr="disabled"
                class="inline-flex w-full justify-center rounded-lg bg-cyan-400 px-4 py-2 text-sm font-semibold text-zinc-950 transition hover:bg-cyan-300 disabled:opacity-60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-400"
            >
                <span wire:loading.remove wire:target="login">Увійти</span>
                <span wire:loading wire:target="login">Вхід…</span>
            </button>

            @include('partials.social-login-buttons', ['providers' => $providers])
        </div>

        <p class="border-t border-white/10 px-5 py-4 text-center text-sm text-zinc-400">
            Немає акаунта?
            <button type="button" wire:click="$dispatch('open-register')" class="font-medium text-cyan-300 hover:text-cyan-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-400">
                Зареєструватися
            </button>
        </p>
    </form>
</dialog>
