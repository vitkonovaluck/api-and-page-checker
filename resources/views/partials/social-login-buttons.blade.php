@if ($providers !== [])
    <div>
        <div class="relative mb-4">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-white/10"></div>
            </div>
            <div class="relative flex justify-center text-xs uppercase tracking-wide text-zinc-500">
                <span class="bg-zinc-900 px-2">або через соцмережу</span>
            </div>
        </div>
        <div class="grid gap-2">
            @foreach ($providers as $provider)
                <a
                    wire:key="social-{{ $provider->value }}"
                    href="{{ route('auth.social.redirect', $provider->value) }}"
                    class="inline-flex w-full justify-center rounded-lg border border-white/15 px-4 py-2 text-sm font-medium text-zinc-200 transition hover:bg-white/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-400"
                >
                    Увійти через {{ $provider->label() }}
                </a>
            @endforeach
        </div>
    </div>
@endif
