<div>
    <label for="{{ $fieldId }}" class="mb-1 block text-sm font-medium text-zinc-200">Токен</label>
    <select
        id="{{ $fieldId }}"
        wire:model="siteTokenId"
        class="w-full rounded-lg border border-white/15 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 shadow-sm focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-400/30 sm:max-w-xs"
    >
        <option value="">Не підключено</option>
        @foreach ($site->tokens as $token)
            <option value="{{ $token->id }}" wire:key="site-token-option-{{ $token->id }}">{{ $token->name }}</option>
        @endforeach
    </select>
    @error('siteTokenId') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
    <p class="mt-1 text-xs text-zinc-400">Під час перевірки додається як Authorization: Bearer. Токени керуються в налаштуваннях сайту.</p>
</div>
