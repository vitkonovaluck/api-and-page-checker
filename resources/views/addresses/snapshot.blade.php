@extends('layouts.app')

@section('title', 'Знімок #'.$snapshot->id.' — API Snapshot Checker')

@section('content')
    <div class="mb-6">
        <nav class="mb-2 text-sm text-zinc-400">
            <a href="{{ route('sites.index') }}" class="text-cyan-300 hover:text-cyan-200 hover:underline">Сайти</a>
            <span class="mx-1">/</span>
            <a href="{{ route('sites.show', $site) }}" class="text-cyan-300 hover:text-cyan-200 hover:underline">{{ $site->name }}</a>
            <span class="mx-1">/</span>
            <a href="{{ route('addresses.show', [$site, $address]) }}" class="text-cyan-300 hover:text-cyan-200 hover:underline">{{ $address->name ?: 'Адреса' }}</a>
            <span class="mx-1">/</span>
            <span class="text-zinc-300">Знімок #{{ $snapshot->id }}</span>
        </nav>
        <h1 class="mt-3 text-2xl font-semibold tracking-tight text-white">Знімок #{{ $snapshot->id }}</h1>
        <p class="mt-1 break-all font-mono text-sm text-zinc-400">{{ $address->endpoint }}</p>
        <p class="mt-1 break-all font-mono text-xs text-zinc-500">{{ $address->fullUrl() }}</p>
        <div class="mt-3 flex flex-wrap items-center gap-3">
            <p class="text-sm text-zinc-400">{{ $snapshot->created_at->format('d.m.Y H:i:s') }}</p>
            <form method="POST" action="{{ route('addresses.snapshots.destroy', [$site, $address, $snapshot]) }}" onsubmit="return confirm('Видалити цей знімок?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-lg border border-rose-400/20 px-3 py-1.5 text-xs font-medium text-rose-300 transition hover:bg-rose-400/10">
                    Видалити знімок
                </button>
            </form>
        </div>
    </div>

    <div class="mb-8">
        @include('partials.diff', ['diff' => $diff])
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-white/10 bg-zinc-900/80 p-5 shadow-xl shadow-cyan-950/20 backdrop-blur-sm">
            <h2 class="mb-3 text-lg font-semibold text-white">Поточний знімок</h2>
            <dl class="mb-4 grid gap-2 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-400">Статус</dt>
                    <dd class="font-medium">{{ $snapshot->status_code ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-400">Час відповіді</dt>
                    <dd class="font-medium">{{ $snapshot->response_time_ms }} ms</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-400">Hash body</dt>
                    <dd class="font-mono text-xs">{{ $snapshot->body_hash }}</dd>
                </div>
            </dl>
            @include('partials.timing', ['timing' => $snapshot->timing])
            @if ($snapshot->error_message)
                <p class="mb-4 rounded-lg border border-rose-400/20 bg-rose-400/10 px-3 py-2 text-sm text-rose-200">{{ $snapshot->error_message }}</p>
            @endif
            <h3 class="mb-2 text-sm font-semibold text-zinc-100">Headers</h3>
            <pre class="mb-4 max-h-48 overflow-auto rounded-lg border border-white/10 bg-white/5 p-3 text-xs"><code>{{ json_encode($snapshot->headers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</code></pre>
            <h3 class="mb-2 text-sm font-semibold text-zinc-100">Body</h3>
            <pre class="max-h-96 overflow-auto rounded-lg border border-white/10 bg-zinc-950 p-4 text-xs text-zinc-100"><code>{{ $snapshot->body }}</code></pre>
        </section>

        <section class="rounded-2xl border border-white/10 bg-zinc-900/80 p-5 shadow-xl shadow-cyan-950/20 backdrop-blur-sm">
            <h2 class="mb-3 text-lg font-semibold text-white">Попередній знімок</h2>
            @if (! $previous)
                <p class="text-sm text-zinc-400">Немає попереднього знімка для порівняння.</p>
            @else
                <dl class="mb-4 grid gap-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-400">ID</dt>
                        <dd class="font-medium">
                            <a href="{{ route('addresses.snapshots.show', [$site, $address, $previous]) }}" class="text-cyan-300 hover:text-cyan-200 hover:underline">#{{ $previous->id }}</a>
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-400">Дата</dt>
                        <dd class="font-medium">{{ $previous->created_at->format('d.m.Y H:i:s') }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-400">Статус</dt>
                        <dd class="font-medium">{{ $previous->status_code ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-400">Час відповіді</dt>
                        <dd class="font-medium">{{ $previous->response_time_ms }} ms</dd>
                    </div>
                </dl>
                @include('partials.timing', ['timing' => $previous->timing])
                <h3 class="mb-2 mt-4 text-sm font-semibold text-zinc-100">Body</h3>
                <pre class="max-h-96 overflow-auto rounded-lg border border-white/10 bg-zinc-950 p-4 text-xs text-zinc-100"><code>{{ $previous->body }}</code></pre>
            @endif
        </section>
    </div>
@endsection
