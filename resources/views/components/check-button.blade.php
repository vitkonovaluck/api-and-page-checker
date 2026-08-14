@props([
    'action',
    'busy' => false,
    'disabled' => false,
    'label' => null,
    'busyLabel' => 'Перевірка…',
    'title' => null,
])

@php
    $isBlocked = $busy || $disabled;
@endphp

<form
    method="POST"
    action="{{ $action }}"
    x-data="{ local: false }"
    @submit="if (local || {{ $isBlocked ? 'true' : 'false' }}) { $event.preventDefault(); return; } local = true"
>
    @csrf
    <button
        type="submit"
        @if ($title)
            title="{{ $title }}"
            aria-label="{{ $title }}"
        @endif
        @disabled($isBlocked)
        x-bind:disabled="local || {{ $isBlocked ? 'true' : 'false' }}"
        {{ $attributes->class([
            'inline-flex items-center justify-center rounded-lg bg-emerald-600 text-white hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-50',
            'gap-2 px-4 py-2 text-sm font-medium' => $label !== null,
            'p-2' => $label === null,
        ]) }}
    >
        @if ($label === null)
            <span x-show="!local && !{{ $busy ? 'true' : 'false' }}" class="inline-flex">
                @include('partials.icons.refresh')
            </span>
            <span x-show="local || {{ $busy ? 'true' : 'false' }}" x-cloak class="inline-flex">
                @include('partials.icons.spinner')
            </span>
        @else
            <span x-show="local || {{ $busy ? 'true' : 'false' }}" x-cloak class="inline-flex">
                @include('partials.icons.spinner')
            </span>
            <span x-text="(local || {{ $busy ? 'true' : 'false' }}) ? @js($busyLabel) : @js($label)">
                {{ $busy ? $busyLabel : $label }}
            </span>
        @endif
    </button>
</form>
