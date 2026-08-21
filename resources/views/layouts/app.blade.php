<!DOCTYPE html>
<html lang="uk" class="{{ $colorScheme->htmlClass() }}" data-theme="{{ $colorScheme->value }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>
        @hasSection('title')
            @yield('title')
        @else
            {{ $title ?? __('landing.brand') }}
        @endif
    </title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased">
    <div class="relative min-h-screen overflow-x-hidden">
        <div class="pointer-events-none absolute inset-0 landing-grid" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -top-40 left-1/2 h-[28rem] w-[28rem] -translate-x-1/2 rounded-full bg-cyan-400/15 blur-3xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute top-[32rem] -right-24 h-80 w-80 rounded-full bg-emerald-400/10 blur-3xl" aria-hidden="true"></div>

        <header class="sticky top-0 z-30 border-b border-white/10 bg-zinc-950/75 backdrop-blur-md">
            <div class="mx-auto flex w-full max-w-none items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <a href="{{ route('sites.index') }}" class="text-sm font-semibold tracking-tight text-white sm:text-base" wire:navigate>
                    {{ __('landing.brand') }}
                </a>
                <div class="flex items-center gap-3">
                    @auth
                        <livewire:settings.color-scheme-picker :compact="true" :key="'color-scheme-header'" />
                    @endauth
                    <nav class="hidden flex-wrap items-center gap-4 text-sm md:flex" aria-label="{{ __('landing.brand') }}">
                    @auth
                        <a
                            href="{{ route('sites.index') }}"
                            wire:navigate
                            class="transition focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-cyan-400 {{ request()->routeIs('sites.*', 'addresses.*') ? 'font-medium text-white' : 'text-zinc-400 hover:text-white' }}"
                        >
                            Сайти
                        </a>
                        <a
                            href="{{ route('settings.index') }}"
                            wire:navigate
                            class="transition focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-cyan-400 {{ request()->routeIs('settings.*') ? 'font-medium text-white' : 'text-zinc-400 hover:text-white' }}"
                        >
                            Налаштування
                        </a>
                        @if (auth()->user()->isAdmin())
                            <a
                                href="{{ url('/admincab') }}"
                                class="text-zinc-400 transition hover:text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-cyan-400"
                            >
                                Адмінка
                            </a>
                        @endif
                        <span class="text-zinc-500">{{ auth()->user()->name }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="text-zinc-400 transition hover:text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-cyan-400">Вийти</button>
                        </form>
                    @endauth
                </nav>

                @auth
                    <details class="relative md:hidden">
                        <summary class="flex h-10 w-10 list-none items-center justify-center rounded-lg border border-white/15 text-zinc-200 marker:content-none [&::-webkit-details-marker]:hidden">
                            <span class="sr-only">{{ __('landing.menu') }}</span>
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M3 5h14a1 1 0 0 1 0 2H3a1 1 0 1 1 0-2Zm0 4h14a1 1 0 0 1 0 2H3a1 1 0 1 1 0-2Zm0 4h14a1 1 0 0 1 0 2H3a1 1 0 1 1 0-2Z" clip-rule="evenodd" />
                            </svg>
                        </summary>
                        <div class="absolute right-0 mt-2 w-52 rounded-xl border border-white/10 bg-zinc-900 p-3 shadow-xl">
                            <a href="{{ route('sites.index') }}" wire:navigate class="block rounded-lg px-3 py-2 text-sm text-zinc-200 hover:bg-white/5">Сайти</a>
                            <a href="{{ route('settings.index') }}" wire:navigate class="block rounded-lg px-3 py-2 text-sm text-zinc-200 hover:bg-white/5">Налаштування</a>
                            @if (auth()->user()->isAdmin())
                                <a href="{{ url('/admincab') }}" class="block rounded-lg px-3 py-2 text-sm text-zinc-200 hover:bg-white/5">Адмінка</a>
                            @endif
                            <p class="px-3 py-2 text-xs text-zinc-500">{{ auth()->user()->name }}</p>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="block w-full rounded-lg px-3 py-2 text-left text-sm text-zinc-200 hover:bg-white/5">Вийти</button>
                            </form>
                        </div>
                    </details>
                @endauth
                </div>
            </div>
        </header>

        <main class="relative mx-auto w-full max-w-none px-4 py-8 sm:px-6 lg:px-8">
            @isset($slot)
                {{ $slot }}
            @else
                @yield('content')
            @endisset
        </main>
    </div>

    <x-toast />
    @livewireScripts
</body>
</html>
