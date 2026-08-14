@props([
    'action',
    'busy' => false,
    'disabled' => false,
    'label' => null,
    'busyLabel' => 'Перевірка…',
    'title' => null,
])

<form
    method="POST"
    action="{{ $action }}"
    x-data="{ local: false }"
    @submit="if ($wire.checksBusy || {{ $disabled ? 'true' : 'false' }}) { $event.preventDefault(); return; } local = true"
>
    @csrf
    <button
        type="submit"
        @if ($title)
            title="{{ $title }}"
            aria-label="{{ $title }}"
        @endif
        @disabled($busy || $disabled)
        x-bind:disabled="local || $wire.checksBusy || {{ $disabled ? 'true' : 'false' }}"
        {{ $attributes->class([
            'inline-flex items-center justify-center rounded-lg bg-emerald-600 text-white hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-50',
            'gap-2 px-4 py-2 text-sm font-medium' => $label !== null,
            'p-2' => $label === null,
        ]) }}
    >
        @if ($label === null)
            <span x-show="!local && !$wire.checksBusy" class="inline-flex">
                @include('partials.icons.refresh')
            </span>
            <span x-show="local || $wire.checksBusy" x-cloak class="inline-flex">
                @include('partials.icons.spinner')
            </span>
        @else
            <span x-show="local || $wire.checksBusy" x-cloak class="inline-flex">
                @include('partials.icons.spinner')
            </span>
            <span x-text="(local || $wire.checksBusy) ? @js($busyLabel) : @js($label)">
                {{ $busy ? $busyLabel : $label }}
            </span>
        @endif
    </button>
</form>
