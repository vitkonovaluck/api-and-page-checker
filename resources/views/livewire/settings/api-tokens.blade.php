<section class="rounded-2xl border border-white/10 bg-zinc-900/80 p-5 shadow-xl shadow-cyan-950/20 backdrop-blur-sm">
    <h2 class="mb-2 text-base font-semibold text-white">{{ __('alerts.ui.api_tokens_title') }}</h2>
    <p class="mb-4 text-sm text-zinc-400">{{ __('alerts.ui.api_tokens_lead') }}</p>

    @if ($plainTextToken)
        <div class="mb-4 rounded-lg border border-cyan-400/30 bg-cyan-400/10 p-3">
            <p class="mb-1 text-xs font-medium text-cyan-200">{{ __('alerts.ui.token_once') }}</p>
            <code class="block break-all font-mono text-xs text-cyan-100">{{ $plainTextToken }}</code>
        </div>
    @endif

    <form wire:submit="create" class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="grow">
            <label for="api-token-name" class="mb-1 block text-sm font-medium text-zinc-200">{{ __('alerts.ui.token_name') }}</label>
            <input
                id="api-token-name"
                type="text"
                wire:model="name"
                required
                placeholder="CI"
                class="w-full rounded-lg border border-white/15 bg-zinc-950 px-3 py-2 text-sm text-zinc-100"
            >
            @error('name') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-cyan-400 px-4 py-2 text-sm font-semibold text-zinc-950 transition hover:bg-cyan-300">
            {{ __('alerts.ui.create_token') }}
        </button>
    </form>

    @if ($tokens->isEmpty())
        <p class="text-sm text-zinc-500">{{ __('alerts.ui.no_api_tokens') }}</p>
    @else
        <ul class="space-y-2">
            @foreach ($tokens as $token)
                <li wire:key="api-token-{{ $token->id }}" class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-white/10 bg-zinc-950/60 px-3 py-2">
                    <div>
                        <p class="text-sm font-medium text-zinc-100">{{ $token->name }}</p>
                        <p class="text-xs text-zinc-500">{{ $token->created_at?->format('d.m.Y H:i') }}</p>
                    </div>
                    <button
                        type="button"
                        wire:click="revoke({{ $token->id }})"
                        wire:confirm="{{ __('alerts.ui.revoke_token_confirm') }}"
                        class="rounded-lg border border-rose-400/30 px-3 py-1.5 text-xs font-medium text-rose-200 transition hover:bg-rose-400/10"
                    >
                        {{ __('alerts.ui.revoke') }}
                    </button>
                </li>
            @endforeach
        </ul>
    @endif
</section>
