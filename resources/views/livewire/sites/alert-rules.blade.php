<section class="rounded-2xl border border-white/10 bg-zinc-900/80 p-5 shadow-xl shadow-cyan-950/20 backdrop-blur-sm">
    <h2 class="mb-2 text-base font-semibold text-white">{{ __('alerts.ui.rules_title') }}</h2>
    <p class="mb-4 text-sm text-zinc-400">{{ __('alerts.ui.rules_lead') }}</p>

    @if ($canUpdate)
        @if ($channels->isEmpty())
            <p class="mb-4 text-sm text-amber-200">{{ __('alerts.ui.rules_need_channel') }}</p>
        @else
            <form wire:submit="create" class="mb-6 space-y-3">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-zinc-200">{{ __('alerts.ui.channel') }}</label>
                        <select wire:model="notificationChannelId" class="w-full rounded-lg border border-white/15 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
                            <option value="">—</option>
                            @foreach ($channels as $channel)
                                <option value="{{ $channel->id }}">{{ $channel->channel->label() }}</option>
                            @endforeach
                        </select>
                        @error('notificationChannelId') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-zinc-200">{{ __('alerts.ui.address_optional') }}</label>
                        <select wire:model="addressId" class="w-full rounded-lg border border-white/15 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
                            <option value="">{{ __('alerts.ui.all_addresses') }}</option>
                            @foreach ($site->addresses as $address)
                                <option value="{{ $address->id }}">{{ $address->endpoint }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <p class="mb-1 text-sm font-medium text-zinc-200">{{ __('alerts.ui.events') }}</p>
                    <div class="flex flex-wrap gap-3">
                        @foreach ($eventOptions as $event)
                            <label class="inline-flex items-center gap-2 text-xs text-zinc-300">
                                <input type="checkbox" wire:model="events" value="{{ $event->value }}" class="rounded border-white/20 bg-zinc-950">
                                {{ $event->label() }}
                            </label>
                        @endforeach
                    </div>
                    @error('events') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-zinc-200">{{ __('alerts.ui.min_consecutive') }}</label>
                        <input type="number" min="1" wire:model="minConsecutive" class="w-full rounded-lg border border-white/15 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-zinc-200">{{ __('alerts.ui.cooldown_minutes') }}</label>
                        <input type="number" min="0" wire:model="cooldownMinutes" class="w-full rounded-lg border border-white/15 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
                    </div>
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-zinc-300">
                    <input type="checkbox" wire:model="notifyOnManual" class="rounded border-white/20 bg-zinc-950">
                    {{ __('alerts.ui.notify_on_manual') }}
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-zinc-300">
                    <input type="checkbox" wire:model="digestValueChanges" class="rounded border-white/20 bg-zinc-950">
                    {{ __('alerts.ui.digest_value_changes') }}
                </label>
                <div>
                    <button type="submit" class="inline-flex rounded-xl bg-cyan-400 px-4 py-2 text-sm font-semibold text-zinc-950 transition hover:bg-cyan-300">
                        {{ __('alerts.ui.add_rule') }}
                    </button>
                </div>
            </form>
        @endif
    @endif

    @if ($rules->isEmpty())
        <p class="text-sm text-zinc-500">{{ __('alerts.ui.no_rules') }}</p>
    @else
        <ul class="space-y-2">
            @foreach ($rules as $rule)
                <li wire:key="rule-{{ $rule->id }}" class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-white/10 bg-zinc-950/60 px-3 py-2">
                    <div>
                        <p class="text-sm text-zinc-100">
                            {{ $rule->notificationChannel?->channel?->label() }}
                            · {{ $rule->address?->endpoint ?? __('alerts.ui.all_addresses') }}
                        </p>
                        <p class="text-xs text-zinc-500">{{ implode(', ', $rule->events ?? []) }}</p>
                    </div>
                    @if ($canUpdate)
                        <button type="button" wire:click="delete({{ $rule->id }})" class="rounded-lg border border-rose-400/30 px-3 py-1.5 text-xs font-medium text-rose-200 transition hover:bg-rose-400/10">
                            {{ __('alerts.ui.delete') }}
                        </button>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</section>
