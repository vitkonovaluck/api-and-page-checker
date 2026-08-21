@if ($compact)
        <div class="hidden items-center gap-2 sm:flex" role="group" aria-label="{{ __('color_scheme.title') }}">
        @include('livewire.settings.partials.color-scheme-controls')
    </div>
@else
    <section class="rounded-2xl border border-white/10 bg-zinc-900/80 p-5 shadow-xl shadow-cyan-950/20 backdrop-blur-sm lg:col-span-2">
        <h2 class="mb-2 text-base font-semibold text-white">{{ __('color_scheme.title') }}</h2>
        <p class="mb-4 text-sm text-zinc-400">{{ __('color_scheme.description') }}</p>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            @include('livewire.settings.partials.color-scheme-controls')
        </div>
    </section>
@endif
