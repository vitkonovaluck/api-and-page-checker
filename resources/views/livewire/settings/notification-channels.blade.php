<section class="rounded-2xl border border-white/10 bg-zinc-900/80 p-5 shadow-xl shadow-cyan-950/20 backdrop-blur-sm">
    <h2 class="mb-2 text-base font-semibold text-white">{{ __('alerts.ui.channels_title') }}</h2>
    <p class="mb-4 text-sm text-zinc-400">{{ __('alerts.ui.channels_lead') }}</p>

    <form wire:submit="create" class="mb-6 space-y-3">
        <div>
            <label class="mb-1 block text-sm font-medium text-zinc-200">{{ __('alerts.ui.channel') }}</label>
            <select wire:model.live="channel" class="w-full rounded-lg border border-white/15 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 sm:max-w-xs">
                @foreach ($channelOptions as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </select>
        </div>
        @if ($channel === \App\Enums\AlertChannel::Mail->value)
            <div>
                <label class="mb-1 block text-sm font-medium text-zinc-200">Email</label>
                <input type="email" wire:model="email" class="w-full rounded-lg border border-white/15 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 sm:max-w-md">
                @error('email') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
            </div>
        @elseif ($channel === \App\Enums\AlertChannel::Webhook->value)
            <div>
                <label class="mb-1 block text-sm font-medium text-zinc-200">Webhook URL</label>
                <input type="url" wire:model="webhookUrl" class="w-full rounded-lg border border-white/15 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
                @error('webhookUrl') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-zinc-200">HMAC secret</label>
                <input type="text" wire:model="webhookSecret" class="w-full rounded-lg border border-white/15 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 sm:max-w-md">
            </div>
        @else
            <div>
                <label class="mb-1 block text-sm font-medium text-zinc-200">Telegram chat id</label>
                <input type="text" wire:model="telegramChatId" class="w-full rounded-lg border border-white/15 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 sm:max-w-md">
                @error('telegramChatId') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
            </div>
        @endif
        <button type="submit" class="inline-flex rounded-xl bg-cyan-400 px-4 py-2 text-sm font-semibold text-zinc-950 transition hover:bg-cyan-300">
            {{ __('alerts.ui.add_channel') }}
        </button>
    </form>

    @if ($channels->isEmpty())
        <p class="text-sm text-zinc-500">{{ __('alerts.ui.no_channels') }}</p>
    @else
        <ul class="space-y-2">
            @foreach ($channels as $item)
                <li wire:key="channel-{{ $item->id }}" class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-white/10 bg-zinc-950/60 px-3 py-2">
                    <div>
                        <p class="text-sm font-medium text-zinc-100">{{ $item->channel->label() }}</p>
                        <p class="text-xs text-zinc-500">
                            @if ($item->channel === \App\Enums\AlertChannel::Mail)
                                {{ $item->emailAddress() }}
                            @elseif ($item->channel === \App\Enums\AlertChannel::Webhook)
                                {{ $item->webhookUrl() }}
                            @else
                                {{ $item->telegramChatId() }}
                            @endif
                            · {{ $item->is_enabled ? __('alerts.ui.enabled') : __('alerts.ui.disabled') }}
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" wire:click="toggle({{ $item->id }})" class="rounded-lg border border-white/15 px-3 py-1.5 text-xs font-medium text-zinc-200 transition hover:bg-white/5">
                            {{ $item->is_enabled ? __('alerts.ui.disable') : __('alerts.ui.enable') }}
                        </button>
                        <button type="button" wire:click="delete({{ $item->id }})" wire:confirm="{{ __('alerts.ui.delete_channel_confirm') }}" class="rounded-lg border border-rose-400/30 px-3 py-1.5 text-xs font-medium text-rose-200 transition hover:bg-rose-400/10">
                            {{ __('alerts.ui.delete') }}
                        </button>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>
