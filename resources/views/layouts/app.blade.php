<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>
        @hasSection('title')
            @yield('title')
        @else
            {{ $title ?? 'API Snapshot Checker' }}
        @endif
    </title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex w-full max-w-none items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
            <a href="{{ route('sites.index') }}" class="text-lg font-semibold tracking-tight text-slate-900" wire:navigate>
                API Snapshot Checker
            </a>
            <nav class="flex items-center gap-4 text-sm">
                <a
                    href="{{ route('sites.index') }}"
                    wire:navigate
                    class="{{ request()->routeIs('sites.*', 'addresses.*') ? 'font-medium text-slate-900' : 'text-slate-500 hover:text-slate-800' }}"
                >
                    Сайти
                </a>
                <a
                    href="{{ route('settings.index') }}"
                    wire:navigate
                    class="{{ request()->routeIs('settings.*') ? 'font-medium text-slate-900' : 'text-slate-500 hover:text-slate-800' }}"
                >
                    Налаштування
                </a>
            </nav>
        </div>
    </header>

    <main class="mx-auto w-full max-w-none px-4 py-8 sm:px-6 lg:px-8">
        @isset($slot)
            {{ $slot }}
        @else
            @yield('content')
        @endisset
    </main>

    <x-toast />
    @livewireScripts
</body>
</html>
