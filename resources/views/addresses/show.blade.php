@extends('layouts.app')

@section('title', ($address->name ?: $address->endpoint).' — API Snapshot Checker')

@section('content')
    <div class="mb-6">
        <nav class="mb-2 text-sm text-slate-500">
            <a href="{{ route('sites.index') }}" class="text-sky-700 hover:underline">Сайти</a>
            <span class="mx-1">/</span>
            <a href="{{ route('sites.show', $site) }}" class="text-sky-700 hover:underline">{{ $site->name }}</a>
            <span class="mx-1">/</span>
            <span class="text-slate-700">{{ $address->name ?: 'Адреса' }}</span>
        </nav>
        <div class="mt-3 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">{{ $address->name ?: 'Без назви' }}</h1>
                <p class="mt-1 break-all font-mono text-sm text-slate-600">{{ $address->endpoint }}</p>
                <p class="mt-1 break-all font-mono text-xs text-slate-400">{{ $address->fullUrl() }}</p>
                <p class="mt-2 text-sm text-slate-500">
                    Остання перевірка:
                    {{ $address->last_checked_at?->format('d.m.Y H:i:s') ?? 'ще не перевірялася' }}
                </p>
            </div>
            <form method="POST" action="{{ route('addresses.check', [$site, $address]) }}">
                @csrf
                <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500">
                    Зробити знімок
                </button>
            </form>
        </div>
    </div>

    @include('partials.response-time-chart', [
        'chart' => $responseTimeChart,
        'chartId' => 'address-response-time-chart',
        'title' => 'Історія часу відповіді',
        'subtitle' => 'Середнє за періоди: останній час, 6 / 12 / 24 / 48 / 96 год, 1 тиждень',
    ])

    @if ($diff && $latest)
        <div class="mb-8">
            @include('partials.diff', ['diff' => $diff])
        </div>

        <section class="mb-8 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-lg font-semibold text-slate-900">Останній знімок</h2>
            <dl class="grid gap-3 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Дата</dt>
                    <dd class="mt-1 font-medium">{{ $latest->created_at->format('d.m.Y H:i:s') }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Статус</dt>
                    <dd class="mt-1 font-medium">{{ $latest->status_code ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Час відповіді</dt>
                    <dd class="mt-1 font-medium">{{ $latest->response_time_ms }} ms</dd>
                </div>
            </dl>
            @if ($latest->error_message)
                <p class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">{{ $latest->error_message }}</p>
            @endif
            <div class="mt-4">
                <h3 class="mb-2 text-sm font-semibold text-slate-800">Body</h3>
                <pre class="max-h-80 overflow-auto rounded-lg border border-slate-200 bg-slate-950 p-4 text-xs text-slate-100"><code>{{ $latest->body }}</code></pre>
            </div>
            <div class="mt-4">
                <a href="{{ route('addresses.snapshots.show', [$site, $address, $latest]) }}" class="text-sm text-sky-700 hover:underline">
                    Відкрити деталі знімка →
                </a>
            </div>
        </section>
    @endif

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-base font-semibold text-slate-900">Історія знімків</h2>
            @if (($stats['checks_count'] ?? 0) > 0)
                <dl class="mt-3 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Середній час</dt>
                        <dd class="mt-0.5 text-sm font-semibold text-slate-900">{{ $stats['avg_response_time_ms'] }} ms</dd>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Середня к-сть помилок</dt>
                        <dd class="mt-0.5 text-sm font-semibold text-slate-900">{{ $stats['avg_errors'] }}</dd>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Помилок / перевірок</dt>
                        <dd class="mt-0.5 text-sm font-semibold text-slate-900">{{ $stats['error_count'] }} / {{ $stats['checks_count'] }}</dd>
                    </div>
                </dl>
            @endif
        </div>

        @if ($snapshots->isEmpty())
            <p class="px-5 py-8 text-sm text-slate-500">Знімків ще немає. Натисніть «Зробити знімок».</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">#</th>
                            <th class="px-5 py-3">Дата</th>
                            <th class="px-5 py-3">Статус</th>
                            <th class="px-5 py-3">Час відповіді</th>
                            <th class="px-5 py-3">Результат</th>
                            <th class="px-5 py-3 text-right">Дії</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($snapshots as $snapshot)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-mono text-xs text-slate-500">{{ $snapshot->id }}</td>
                                <td class="px-5 py-3">{{ $snapshot->created_at->format('d.m.Y H:i:s') }}</td>
                                <td class="px-5 py-3">{{ $snapshot->status_code ?? '—' }}</td>
                                <td class="px-5 py-3">{{ $snapshot->response_time_ms }} ms</td>
                                <td class="px-5 py-3">
                                    @if ($snapshot->error_message)
                                        <span class="text-red-700">помилка</span>
                                    @else
                                        <span class="text-emerald-700">OK</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex flex-wrap justify-end gap-1.5">
                                        <a
                                            href="{{ route('addresses.snapshots.show', [$site, $address, $snapshot]) }}"
                                            title="Деталі"
                                            aria-label="Деталі"
                                            class="inline-flex items-center justify-center rounded-lg border border-slate-300 p-2 text-slate-700 hover:bg-slate-100"
                                        >
                                            @include('partials.icons.eye')
                                        </a>
                                        <form method="POST" action="{{ route('addresses.snapshots.destroy', [$site, $address, $snapshot]) }}" onsubmit="return confirm('Видалити цей знімок?')">
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                title="Видалити"
                                                aria-label="Видалити"
                                                class="inline-flex items-center justify-center rounded-lg border border-red-200 p-2 text-red-700 hover:bg-red-50"
                                            >
                                                @include('partials.icons.trash')
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
