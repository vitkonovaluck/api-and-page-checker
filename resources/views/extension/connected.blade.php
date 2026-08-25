@extends('layouts.guest')

@section('title', __('agent.extension_connected_title'))

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-900/80 p-6 shadow-xl shadow-cyan-950/20">
        @if ($errors->any())
            <h1 class="text-xl font-semibold tracking-tight text-white">{{ __('agent.extension_connected_failed_title') }}</h1>
            <p class="mt-3 text-sm leading-relaxed text-zinc-400">{{ __('agent.extension_connected_failed_body') }}</p>
            <ul class="mt-4 list-disc space-y-1 pl-5 text-sm text-rose-300">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        @else
            <h1 class="text-xl font-semibold tracking-tight text-white">{{ __('agent.extension_connected_title') }}</h1>
            <p class="mt-3 text-sm leading-relaxed text-zinc-400">{{ __('agent.extension_connected_body') }}</p>
        @endif
    </div>
@endsection
