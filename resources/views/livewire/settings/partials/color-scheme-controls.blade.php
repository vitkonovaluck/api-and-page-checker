<div class="flex flex-wrap items-center gap-3">
    <div class="flex rounded-lg border border-white/15 p-0.5" role="group" aria-label="{{ __('color_scheme.appearance') }}">
        <button
            type="button"
            wire:click="select('dark-{{ $current->accent()->value }}')"
            wire:loading.attr="disabled"
            class="rounded-md px-2.5 py-1 text-xs font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-400 disabled:opacity-100 {{ $current->isDark() ? 'bg-white/10 text-white' : 'text-zinc-400 hover:text-white' }}"
            @disabled($current->isDark())
        >
            {{ __('color_scheme.dark') }}
        </button>
        <button
            type="button"
            wire:click="select('light-{{ $current->accent()->value }}')"
            wire:loading.attr="disabled"
            class="rounded-md px-2.5 py-1 text-xs font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-400 disabled:opacity-100 {{ $current->isDark() ? 'text-zinc-400 hover:text-white' : 'bg-white/10 text-white' }}"
            @disabled(! $current->isDark())
        >
            {{ __('color_scheme.light') }}
        </button>
    </div>

    <div class="flex items-center gap-1.5" role="group" aria-label="{{ __('color_scheme.accent') }}">
        @foreach ($accents as $accent)
            <button
                type="button"
                wire:key="accent-{{ $accent->value }}"
                wire:click="select('{{ $current->isDark() ? 'dark' : 'light' }}-{{ $accent->value }}')"
                wire:loading.attr="disabled"
                class="h-6 w-6 rounded-full border-2 transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-400 disabled:opacity-100 {{ $current->accent() === $accent ? 'scale-110 border-white' : 'border-transparent hover:border-white/40' }}"
                style="background-color: {{ $accent->swatchHex() }}"
                @disabled($current->accent() === $accent)
                title="{{ $accent->label() }}"
            >
                <span class="sr-only">{{ $accent->label() }}</span>
            </button>
        @endforeach
    </div>
</div>
