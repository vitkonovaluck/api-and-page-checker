@extends('layouts.app')

@section('title', 'Знімок #'.$snapshot->id.' — API Snapshot Checker')

@section('content')
    <div class="mb-6">
        <nav class="mb-2 text-sm text-slate-500">
            <a href="{{ route('sites.index') }}" class="text-sky-700 hover:underline">Сайти</a>
            <span class="mx-1">/</span>
            <a href="{{ route('sites.show', $site) }}" class="text-sky-700 hover:underline">{{ $site->name }}</a>
            <span class="mx-1">/</span>
            <a href="{{ route('addresses.show', [$site, $address]) }}" class="text-sky-700 hover:underline">{{ $address->name ?: 'Адреса' }}</a>
            <span class="mx-1">/</span>
            <span class="text-slate-700">Знімок #{{ $snapshot->id }}</span>
        </nav>
        <h1 class="mt-3 text-2xl font-semibold text-slate-900">Знімок #{{ $snapshot->id }}</h1>
        <p class="mt-1 break-all font-mono text-sm text-slate-600">{{ $address->endpoint }}</p>
        <p class="mt-1 break-all font-mono text-xs text-slate-400">{{ $address->fullUrl() }}</p>
        <div class="mt-3 flex flex-wrap items-center gap-3">
            <p class="text-sm text-slate-500">{{ $snapshot->created_at->format('d.m.Y H:i:s') }}</p>
            <form method="POST" action="{{ route('addresses.snapshots.destroy', [$site, $address, $snapshot]) }}" onsubmit="return confirm('Видалити цей знімок?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50">
                    Видалити знімок
                </button>
            </form>
        </div>
    </div>

    <div class="mb-8">
        @include('partials.diff', ['diff' => $diff])
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-lg font-semibold text-slate-900">Поточний знімок</h2>
            <dl class="mb-4 grid gap-2 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Статус</dt>
                    <dd class="font-medium">{{ $snapshot->status_code ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Час відповіді</dt>
                    <dd class="font-medium">{{ $snapshot->response_time_ms }} ms</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Hash body</dt>
                    <dd class="font-mono text-xs">{{ $snapshot->body_hash }}</dd>
                </div>
            </dl>
            @include('partials.timing', ['timing' => $snapshot->timing])
            @if ($snapshot->error_message)
                <p class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">{{ $snapshot->error_message }}</p>
            @endif
            <h3 class="mb-2 text-sm font-semibold text-slate-800">Headers</h3>
            <pre class="mb-4 max-h-48 overflow-auto rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs"><code>{{ json_encode($snapshot->headers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</code></pre>
            <h3 class="mb-2 text-sm font-semibold text-slate-800">Body</h3>
            <pre class="max-h-96 overflow-auto rounded-lg border border-slate-200 bg-slate-950 p-4 text-xs text-slate-100"><code>{{ $snapshot->body }}</code></pre>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-lg font-semibold text-slate-900">Попередній знімок</h2>
            @if (! $previous)
                <p class="text-sm text-slate-500">Немає попереднього знімка для порівняння.</p>
            @else
                <dl class="mb-4 grid gap-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">ID</dt>
                        <dd class="font-medium">
                            <a href="{{ route('addresses.snapshots.show', [$site, $address, $previous]) }}" class="text-sky-700 hover:underline">#{{ $previous->id }}</a>
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Дата</dt>
                        <dd class="font-medium">{{ $previous->created_at->format('d.m.Y H:i:s') }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Статус</dt>
                        <dd class="font-medium">{{ $previous->status_code ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Час відповіді</dt>
                        <dd class="font-medium">{{ $previous->response_time_ms }} ms</dd>
                    </div>
                </dl>
                @include('partials.timing', ['timing' => $previous->timing])
                <h3 class="mb-2 mt-4 text-sm font-semibold text-slate-800">Body</h3>
                <pre class="max-h-96 overflow-auto rounded-lg border border-slate-200 bg-slate-950 p-4 text-xs text-slate-100"><code>{{ $previous->body }}</code></pre>
            @endif
        </section>
    </div>
@endsection
