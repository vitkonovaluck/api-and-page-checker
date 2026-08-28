<section class="rounded-2xl border border-white/10 bg-zinc-900/80 p-5 shadow-xl shadow-cyan-950/20 backdrop-blur-sm">
    <h2 class="mb-2 text-base font-semibold text-white">{{ __('alerts.ui.org_title') }}</h2>
    <p class="mb-4 text-sm text-zinc-400">{{ $organization->name }}</p>

    @if ($canManage)
        <form wire:submit="add" class="mb-6 grid gap-3 sm:grid-cols-[1fr_auto_auto]">
            <div>
                <label class="mb-1 block text-sm font-medium text-zinc-200">Email</label>
                <input type="email" wire:model="email" required class="w-full rounded-lg border border-white/15 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
                @error('email') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-zinc-200">{{ __('alerts.ui.role') }}</label>
                <select wire:model="role" class="w-full rounded-lg border border-white/15 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
                    @foreach ($roleOptions as $option)
                        <option value="{{ $option->value }}">{{ $option->value }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="inline-flex rounded-xl bg-cyan-400 px-4 py-2 text-sm font-semibold text-zinc-950 transition hover:bg-cyan-300">
                    {{ __('alerts.ui.add_member') }}
                </button>
            </div>
        </form>
    @endif

    <ul class="space-y-2">
        @foreach ($memberships as $membership)
            <li wire:key="member-{{ $membership->id }}" class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-white/10 bg-zinc-950/60 px-3 py-2">
                <div>
                    <p class="text-sm font-medium text-zinc-100">{{ $membership->user?->name }}</p>
                    <p class="text-xs text-zinc-500">{{ $membership->user?->email }} · {{ $membership->role?->value }}</p>
                </div>
                @if ($canManage && $membership->user_id !== $organization->owner_user_id)
                    <button type="button" wire:click="remove({{ $membership->id }})" class="rounded-lg border border-rose-400/30 px-3 py-1.5 text-xs font-medium text-rose-200 transition hover:bg-rose-400/10">
                        {{ __('alerts.ui.remove') }}
                    </button>
                @endif
            </li>
        @endforeach
    </ul>
</section>
