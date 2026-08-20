<!DOCTYPE html>
<html lang="uk" class="dark scroll-smooth">
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

        <header class="sticky top-0 z-30 border-b border-white/10 bg-zinc-950/75 backdrop-blur-md">
            <div class="mx-auto flex w-full max-w-lg items-center justify-between px-4 py-4 sm:px-6">
                <a href="{{ route('home') }}" class="text-sm font-semibold tracking-tight text-white hover:text-cyan-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-400 sm:text-base">
                    {{ __('landing.brand') }}
                </a>
            </div>
        </header>

        <main class="relative mx-auto w-full max-w-lg px-4 py-10 sm:px-6">
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
