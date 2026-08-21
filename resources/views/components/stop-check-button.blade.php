@props([
    'click' => 'stopManualCheckRun',
    'label' => 'Зупинити перевірку',
    'iconOnly' => false,
])

<button
    type="button"
    wire:click="{{ $click }}"
    wire:confirm="Зупинити перевірку та видалити всі знімки лише цього проходу? Цю дію неможливо скасувати."
    wire:loading.attr="disabled"
    wire:target="stopManualCheckRun"
    title="Зупинити перевірку"
    aria-label="Зупинити перевірку"
    {{ $attributes->class([
        'inline-flex items-center justify-center rounded-lg border border-rose-400/20 text-rose-200 transition hover:bg-rose-400/10 disabled:cursor-not-allowed disabled:opacity-50',
        'gap-1.5 px-3 py-1.5 text-sm font-medium' => ! $iconOnly,
        'p-2' => $iconOnly,
    ]) }}
>
    <span wire:loading.remove wire:target="stopManualCheckRun" class="inline-flex">
        @include('partials.icons.stop')
    </span>
    <span wire:loading wire:target="stopManualCheckRun" class="inline-flex">
        @include('partials.icons.spinner')
    </span>
    @unless ($iconOnly)
        <span wire:loading.remove wire:target="stopManualCheckRun">{{ $label }}</span>
        <span wire:loading wire:target="stopManualCheckRun">Зупиняється…</span>
    @endunless
</button>
