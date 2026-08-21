<!DOCTYPE html>
<html lang="uk" class="dark scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('landing.meta_title'))</title>
    <meta name="description" content="@yield('meta_description', __('landing.meta_description'))">
    <link rel="canonical" href="{{ route('home') }}">
    @isset($openAuth)
        <meta name="robots" content="noindex, nofollow">
    @endisset
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased">
    @yield('content')

    <x-toast />
    @livewireScripts
</body>
</html>
