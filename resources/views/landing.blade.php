@extends('layouts.landing')

@section('title', __('landing.meta_title'))
@section('meta_description', __('landing.meta_description', ['sites' => $maxSites]))

@section('content')
    <div class="landing-page relative overflow-x-hidden">
        <div class="pointer-events-none absolute inset-0 landing-grid" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -top-40 left-1/2 h-[28rem] w-[28rem] -translate-x-1/2 rounded-full bg-cyan-400/15 blur-3xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute top-[32rem] -right-24 h-80 w-80 rounded-full bg-emerald-400/10 blur-3xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute top-[72rem] -left-16 h-72 w-72 rounded-full bg-violet-500/10 blur-3xl" aria-hidden="true"></div>

        <header class="sticky top-0 z-30 border-b border-white/10 bg-zinc-950/75 backdrop-blur-md">
            <div class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                <a href="{{ route('home') }}" class="text-sm font-semibold tracking-tight text-white sm:text-base">
                    {{ __('landing.brand') }}
                </a>

                <nav class="hidden items-center gap-6 text-sm text-zinc-300 md:flex" aria-label="{{ __('landing.brand') }}">
                    <a href="#features" class="transition hover:text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-cyan-400">{{ __('landing.nav_features') }}</a>
                    <a href="#how" class="transition hover:text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-cyan-400">{{ __('landing.nav_how') }}</a>
                    <a href="#extension" class="transition hover:text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-cyan-400">{{ __('landing.nav_extension') }}</a>
                    <a href="#pricing" class="transition hover:text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-cyan-400">{{ __('landing.nav_pricing') }}</a>
                </nav>

                <div class="flex items-center gap-2 sm:gap-3">
                    <x-auth-modal-trigger
                        mode="login"
                        class="hidden rounded-lg px-3 py-2 text-sm text-zinc-300 transition hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-400 sm:inline-flex"
                    >
                        {{ __('landing.login') }}
                    </x-auth-modal-trigger>
                    <x-auth-modal-trigger
                        mode="register"
                        class="inline-flex rounded-lg bg-cyan-400 px-3 py-2 text-sm font-semibold text-zinc-950 transition hover:bg-cyan-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-400"
                    >
                        {{ __('landing.register') }}
                    </x-auth-modal-trigger>

                    <details class="relative md:hidden">
                        <summary class="flex h-10 w-10 list-none items-center justify-center rounded-lg border border-white/15 text-zinc-200 marker:content-none [&::-webkit-details-marker]:hidden">
                            <span class="sr-only">{{ __('landing.menu') }}</span>
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M3 5h14a1 1 0 0 1 0 2H3a1 1 0 1 1 0-2Zm0 4h14a1 1 0 0 1 0 2H3a1 1 0 1 1 0-2Zm0 4h14a1 1 0 0 1 0 2H3a1 1 0 1 1 0-2Z" clip-rule="evenodd" />
                            </svg>
                        </summary>
                        <div class="absolute right-0 mt-2 w-52 rounded-xl border border-white/10 bg-zinc-900 p-3 shadow-xl">
                            <a href="#features" class="block rounded-lg px-3 py-2 text-sm text-zinc-200 hover:bg-white/5">{{ __('landing.nav_features') }}</a>
                            <a href="#how" class="block rounded-lg px-3 py-2 text-sm text-zinc-200 hover:bg-white/5">{{ __('landing.nav_how') }}</a>
                            <a href="#extension" class="block rounded-lg px-3 py-2 text-sm text-zinc-200 hover:bg-white/5">{{ __('landing.nav_extension') }}</a>
                            <a href="#pricing" class="block rounded-lg px-3 py-2 text-sm text-zinc-200 hover:bg-white/5">{{ __('landing.nav_pricing') }}</a>
                            <x-auth-modal-trigger mode="login" class="mt-1 block rounded-lg px-3 py-2 text-sm text-zinc-200 hover:bg-white/5">
                                {{ __('landing.login') }}
                            </x-auth-modal-trigger>
                        </div>
                    </details>
                </div>
            </div>
        </header>

        <main>
            <section class="relative mx-auto grid w-full max-w-6xl gap-12 px-4 py-16 sm:px-6 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)] lg:items-center lg:py-24">
                <div>
                    <p class="inline-flex items-center gap-2 rounded-full border border-cyan-400/30 bg-cyan-400/10 px-3 py-1 text-xs font-medium uppercase tracking-wider text-cyan-200">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 motion-safe:animate-landing-pulse-dot"></span>
                        {{ __('landing.hero_eyebrow') }}
                    </p>
                    <h1 class="mt-5 max-w-xl text-4xl font-semibold tracking-tight text-white sm:text-5xl sm:leading-tight">
                        {{ __('landing.hero_title') }}
                    </h1>
                    <p class="mt-5 max-w-xl text-base leading-relaxed text-zinc-400 sm:text-lg">
                        {{ __('landing.hero_lead') }}
                    </p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                        <x-auth-modal-trigger
                            mode="register"
                            class="inline-flex items-center justify-center rounded-xl bg-cyan-400 px-5 py-3 text-sm font-semibold text-zinc-950 transition hover:bg-cyan-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-400"
                        >
                            {{ __('landing.register') }}
                        </x-auth-modal-trigger>
                        <x-auth-modal-trigger
                            mode="login"
                            class="inline-flex items-center justify-center rounded-xl border border-white/15 px-5 py-3 text-sm font-medium text-zinc-200 transition hover:border-white/30 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-400"
                        >
                            {{ __('landing.hero_secondary') }}
                        </x-auth-modal-trigger>
                    </div>
                </div>

                <div class="lg:[transform:perspective(1600px)_rotateX(8deg)_rotateY(-12deg)] lg:transition-transform lg:duration-500 lg:hover:[transform:perspective(1600px)_rotateX(0)_rotateY(0)]">
                    <div class="motion-safe:animate-landing-float" aria-hidden="true">
                        <div class="landing-mock relative overflow-hidden rounded-2xl border border-white/10 bg-zinc-900/80 shadow-2xl shadow-cyan-950/40 backdrop-blur-sm">
                        <div class="landing-scan pointer-events-none absolute inset-x-0 z-10 h-24 bg-gradient-to-b from-cyan-300/0 via-cyan-300/25 to-cyan-300/0 motion-safe:animate-landing-scan"></div>
                        <div class="flex items-center justify-between border-b border-white/10 px-4 py-3">
                            <div class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full bg-rose-400"></span>
                                <span class="h-2.5 w-2.5 rounded-full bg-amber-300"></span>
                                <span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
                                <span class="ml-2 font-mono text-xs text-zinc-400">shop.example.com</span>
                            </div>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-400/10 px-2 py-0.5 font-mono text-[11px] uppercase tracking-wide text-emerald-300">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 motion-safe:animate-landing-pulse-dot"></span>
                                {{ __('landing.mock_live') }}
                            </span>
                        </div>
                        <div class="space-y-2 p-4 font-mono text-xs">
                            <div class="flex items-center justify-between rounded-lg bg-white/5 px-3 py-2 text-zinc-300">
                                <span>GET /v1/catalog</span>
                                <span class="flex items-center gap-3">
                                    <span class="text-zinc-500">42ms</span>
                                    <span class="text-emerald-400">200 {{ __('landing.mock_ok') }}</span>
                                </span>
                            </div>
                            <div class="flex items-center justify-between rounded-lg border border-amber-300/20 bg-amber-300/10 px-3 py-2 text-amber-100">
                                <span>GET /v1/orders</span>
                                <span class="flex items-center gap-3">
                                    <span class="text-amber-200/70">118ms</span>
                                    <span class="text-amber-300">200 {{ __('landing.mock_changed') }}</span>
                                </span>
                            </div>
                            <div class="flex items-center justify-between rounded-lg border border-rose-400/20 bg-rose-400/10 px-3 py-2 text-rose-100">
                                <span>GET /v1/health</span>
                                <span class="flex items-center gap-3">
                                    <span class="text-rose-200/70">890ms</span>
                                    <span class="text-rose-300">500 {{ __('landing.mock_error') }}</span>
                                </span>
                            </div>
                            <div class="mt-3 overflow-hidden rounded-lg border border-white/10 bg-zinc-950 p-3 text-[11px] leading-5">
                                <p class="mb-2 text-zinc-500">diff · /v1/orders</p>
                                <p class="text-rose-300">- "stock": 14</p>
                                <p class="text-emerald-300">+ "stock": 9</p>
                                <p class="text-zinc-500">  "currency": "UAH"</p>
                            </div>
                        </div>
                    </div>
                    </div>
                </div>
            </section>

            <section class="relative border-y border-white/10 bg-white/5">
                <div class="mx-auto grid w-full max-w-6xl grid-cols-2 gap-6 px-4 py-8 sm:px-6 lg:grid-cols-4">
                    <div>
                        <p class="text-sm font-semibold text-white">{{ __('landing.stat_snapshots') }}</p>
                        <p class="mt-1 text-xs text-zinc-400">{{ __('landing.stat_snapshots_hint') }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-white">{{ __('landing.stat_diffs') }}</p>
                        <p class="mt-1 text-xs text-zinc-400">{{ __('landing.stat_diffs_hint') }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-white">{{ __('landing.stat_schedule') }}</p>
                        <p class="mt-1 text-xs text-zinc-400">{{ __('landing.stat_schedule_hint') }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-white">{{ __('landing.stat_timing') }}</p>
                        <p class="mt-1 text-xs text-zinc-400">{{ __('landing.stat_timing_hint') }}</p>
                    </div>
                </div>
            </section>

            <section id="features" class="relative mx-auto w-full max-w-6xl scroll-mt-24 px-4 py-20 sm:px-6">
                <p class="text-xs font-medium uppercase tracking-wider text-cyan-300">{{ __('landing.features_eyebrow') }}</p>
                <h2 class="mt-3 max-w-2xl text-3xl font-semibold tracking-tight text-white sm:text-4xl">{{ __('landing.features_title') }}</h2>
                <p class="mt-4 max-w-2xl text-zinc-400">{{ __('landing.features_lead') }}</p>

                <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach (__('landing.features') as $feature)
                        <article class="rounded-2xl border border-white/10 bg-white/5 p-5 transition hover:-translate-y-0.5 hover:border-cyan-400/30 hover:bg-white/10">
                            <span class="font-mono text-xs text-cyan-300">0{{ $loop->iteration }}</span>
                            <h3 class="mt-3 text-base font-semibold text-white">{{ $feature['title'] }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-zinc-400">{{ $feature['body'] }}</p>
                        </article>
                    @endforeach
                </div>
            </section>

            <section id="how" class="relative mx-auto w-full max-w-6xl scroll-mt-24 px-4 pb-20 sm:px-6">
                <p class="text-xs font-medium uppercase tracking-wider text-cyan-300">{{ __('landing.how_eyebrow') }}</p>
                <h2 class="mt-3 max-w-2xl text-3xl font-semibold tracking-tight text-white sm:text-4xl">{{ __('landing.how_title') }}</h2>

                <ol class="mt-12 grid gap-4 lg:grid-cols-3">
                    @foreach (__('landing.how_steps') as $index => $step)
                        <li class="relative rounded-2xl border border-white/10 bg-zinc-900/60 p-6">
                            <span class="font-mono text-sm text-cyan-300">0{{ $index + 1 }}</span>
                            <h3 class="mt-3 text-lg font-semibold text-white">{{ $step['title'] }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-zinc-400">{{ $step['body'] }}</p>
                        </li>
                    @endforeach
                </ol>
            </section>

            <section id="extension" class="relative mx-auto w-full max-w-6xl scroll-mt-24 px-4 pb-20 sm:px-6">
                <div class="grid gap-10 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)] lg:items-center">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-cyan-300">{{ __('landing.extension_eyebrow') }}</p>
                        <h2 class="mt-3 max-w-2xl text-3xl font-semibold tracking-tight text-white sm:text-4xl">{{ __('landing.extension_title') }}</h2>
                        <p class="mt-4 max-w-2xl text-zinc-400">{{ __('landing.extension_lead') }}</p>
                        <a
                            href="{{ route('extension.chrome') }}"
                            class="mt-8 inline-flex items-center justify-center gap-2 rounded-xl bg-cyan-400 px-5 py-3 text-sm font-semibold text-zinc-950 transition hover:bg-cyan-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-400"
                        >
                            @include('partials.icons.download')
                            {{ __('landing.extension_download') }}
                        </a>
                        <p class="mt-3 max-w-xl text-xs text-zinc-500">{{ __('landing.extension_download_hint') }}</p>
                    </div>
                    <ol class="grid gap-3 sm:grid-cols-2">
                        @foreach (__('landing.extension_steps') as $index => $step)
                            <li class="rounded-2xl border border-white/10 bg-zinc-900/60 p-5">
                                <span class="font-mono text-sm text-cyan-300">0{{ $index + 1 }}</span>
                                <h3 class="mt-3 text-base font-semibold text-white">{{ $step['title'] }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-zinc-400">{{ $step['body'] }}</p>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </section>

            <section id="pricing" class="relative mx-auto w-full max-w-6xl scroll-mt-24 px-4 pb-20 sm:px-6">
                <div class="grid gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)] lg:items-center">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-cyan-300">{{ __('landing.pricing_eyebrow') }}</p>
                        <h2 class="mt-3 max-w-xl text-3xl font-semibold tracking-tight text-white sm:text-4xl">{{ __('landing.pricing_title') }}</h2>
                        <p class="mt-4 max-w-xl text-zinc-400">{{ __('landing.pricing_lead') }}</p>
                    </div>

                    <div class="rounded-2xl border border-cyan-400/30 bg-gradient-to-b from-cyan-400/10 to-transparent p-6">
                        <p class="text-sm font-medium text-cyan-200">{{ __('landing.pricing_name') }}</p>
                        <ul class="mt-4 space-y-2 text-sm text-zinc-300">
                            <li>{{ __('landing.pricing_sites', ['count' => $maxSites]) }}</li>
                            <li>{{ __('landing.pricing_addresses', ['count' => $maxAddresses]) }}</li>
                            <li>{{ __('landing.pricing_item_snapshots') }}</li>
                            <li>{{ __('landing.pricing_item_diffs') }}</li>
                            <li>{{ __('landing.pricing_item_schedule') }}</li>
                            <li>{{ __('landing.pricing_item_charts') }}</li>
                        </ul>
                        <x-auth-modal-trigger
                            mode="register"
                            class="mt-6 inline-flex w-full items-center justify-center rounded-xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-zinc-950 transition hover:bg-cyan-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-400"
                        >
                            {{ __('landing.register') }}
                        </x-auth-modal-trigger>
                    </div>
                </div>
            </section>

            <section class="relative px-4 pb-20 sm:px-6">
                <div class="relative mx-auto max-w-6xl overflow-hidden rounded-3xl border border-white/10 bg-zinc-900 px-6 py-12 text-center sm:px-12">
                    <div class="pointer-events-none absolute inset-x-0 mx-auto h-40 w-80 rounded-full bg-cyan-400/10 blur-3xl" aria-hidden="true"></div>
                    <h2 class="relative text-3xl font-semibold tracking-tight text-white sm:text-4xl">{{ __('landing.cta_title') }}</h2>
                    <p class="relative mx-auto mt-4 max-w-2xl text-zinc-400">{{ __('landing.cta_lead') }}</p>
                    <x-auth-modal-trigger
                        mode="register"
                        class="relative mt-8 inline-flex rounded-xl bg-cyan-400 px-6 py-3 text-sm font-semibold text-zinc-950 transition hover:bg-cyan-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-400"
                    >
                        {{ __('landing.register') }}
                    </x-auth-modal-trigger>
                </div>
            </section>
        </main>

        <footer class="border-t border-white/10">
            <div class="mx-auto flex w-full max-w-6xl flex-col gap-3 px-4 py-8 text-sm text-zinc-500 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <p>{{ __('landing.brand') }} · {{ now()->year }} · {{ __('landing.footer_tagline') }}</p>
                <div class="flex gap-4">
                    <a href="{{ route('extension.chrome') }}" class="hover:text-zinc-300">{{ __('landing.nav_extension') }}</a>
                    <x-auth-modal-trigger mode="login" class="hover:text-zinc-300">{{ __('landing.login') }}</x-auth-modal-trigger>
                    <x-auth-modal-trigger mode="register" class="hover:text-zinc-300">{{ __('landing.register') }}</x-auth-modal-trigger>
                </div>
            </div>
        </footer>

        <livewire:auth.login :show="$openAuth === 'login'" />
        <livewire:auth.register :show="$openAuth === 'register'" />
    </div>
@endsection
