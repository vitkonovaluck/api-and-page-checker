@props([
    'mode' => 'login',
])

<a
    href="{{ $mode === 'register' ? route('register') : route('login') }}"
    @click="window.Livewire && ($event.preventDefault(), Livewire.dispatch('{{ $mode === 'register' ? 'open-register' : 'open-login' }}'))"
    {{ $attributes }}
>
    {{ $slot }}
</a>
