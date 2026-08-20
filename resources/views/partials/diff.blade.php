@php
    $formatDiffValue = static function (mixed $value): string {
        if (is_null($value)) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '';
        }

        return (string) $value;
    };

    $typeLabels = \App\Enums\DiffChangeType::labels();
@endphp

<section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm {{ session('diff_highlight') ? 'ring-2 ring-amber-300' : '' }}">
    <h2 class="mb-4 text-lg font-semibold text-slate-900">Аналіз змін</h2>

    @if ($diff['is_first'])
        <p class="mb-4 rounded-lg bg-sky-50 px-3 py-2 text-sm text-sky-800">
            Це перший знімок для цього сайту — порівняння ще недоступне.
        </p>
    @elseif (! $diff['has_changes'])
        <p class="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
            Змін у відповіді не виявлено (status, headers і body збігаються з попереднім знімком).
        </p>
    @else
        <p class="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">
            Виявлено зміни відносно попереднього знімка.
        </p>
    @endif

    <div class="mb-6 grid gap-3 sm:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">HTTP статус</div>
            <div class="mt-1 text-sm font-semibold">
                @if ($diff['status_code']['changed'] && ! $diff['is_first'])
                    <span class="text-slate-500">{{ $diff['status_code']['old'] ?? '—' }}</span>
                    <span class="mx-1 text-slate-400">→</span>
                    <span class="text-amber-700">{{ $diff['status_code']['new'] ?? '—' }}</span>
                @else
                    {{ $diff['status_code']['new'] ?? '—' }}
                @endif
            </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Час відповіді</div>
            <div class="mt-1 text-sm font-semibold">
                @if ($diff['response_time_ms']['old'] !== null)
                    <span class="text-slate-500">{{ $diff['response_time_ms']['old'] }} ms</span>
                    <span class="mx-1 text-slate-400">→</span>
                    <span>{{ $diff['response_time_ms']['new'] }} ms</span>
                    @php $delta = $diff['response_time_ms']['delta']; @endphp
                    <span class="ml-1 text-xs font-normal {{ $delta > 0 ? 'text-red-600' : ($delta < 0 ? 'text-emerald-600' : 'text-slate-500') }}">
                        ({{ $delta > 0 ? '+' : '' }}{{ $delta }} ms)
                    </span>
                @else
                    {{ $diff['response_time_ms']['new'] }} ms
                @endif
            </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Помилка мережі</div>
            <div class="mt-1 text-sm">
                @if ($diff['error_message']['changed'] && ! $diff['is_first'])
                    <div class="text-slate-500 line-through">{{ $diff['error_message']['old'] ?: 'немає' }}</div>
                    <div class="text-red-700">{{ $diff['error_message']['new'] ?: 'немає' }}</div>
                @else
                    <span class="{{ $diff['error_message']['new'] ? 'text-red-700' : 'text-slate-700' }}">
                        {{ $diff['error_message']['new'] ?: 'немає' }}
                    </span>
                @endif
            </div>
        </div>
    </div>

    @if (! empty($diff['headers']))
        <div class="mb-6">
            <h3 class="mb-2 text-sm font-semibold text-slate-800">Зміни заголовків</h3>
            <div class="overflow-x-auto rounded-lg border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Заголовок</th>
                            <th class="px-3 py-2">Тип</th>
                            <th class="px-3 py-2">Було</th>
                            <th class="px-3 py-2">Стало</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($diff['headers'] as $change)
                            <tr>
                                <td class="px-3 py-2 font-mono text-xs">{{ $change['key'] }}</td>
                                <td class="px-3 py-2">{{ $typeLabels[$change['type']] ?? $change['type'] }}</td>
                                <td class="px-3 py-2 font-mono text-xs text-red-700">{{ $change['old'] ?? '—' }}</td>
                                <td class="px-3 py-2 font-mono text-xs text-emerald-700">{{ $change['new'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div>
        <h3 class="mb-2 text-sm font-semibold text-slate-800">Зміни body</h3>

        @if ($diff['is_first'])
            <p class="mb-3 text-sm text-slate-600">Повний body першого знімка:</p>
            <pre class="max-h-96 overflow-auto rounded-lg border border-slate-200 bg-slate-950 p-4 text-xs leading-relaxed text-slate-100"><code>{{ $diff['body']['new_preview'] ?? '' }}</code></pre>
        @elseif (! $diff['body']['changed'])
            <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                Body не змінився порівняно з попереднім знімком.
            </p>
        @else
            @if ($diff['body']['type'] === 'json' && ! empty($diff['body']['changes']))
                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-500">Поля JSON, що змінилися</p>
                <div class="mb-4 overflow-x-auto rounded-lg border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-3 py-2">Шлях</th>
                                <th class="px-3 py-2">Тип</th>
                                <th class="px-3 py-2">Було</th>
                                <th class="px-3 py-2">Стало</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach ($diff['body']['changes'] as $change)
                                <tr>
                                    <td class="px-3 py-2 font-mono text-xs">{{ $change['path'] }}</td>
                                    <td class="px-3 py-2">{{ $typeLabels[$change['type']] ?? $change['type'] }}</td>
                                    <td class="max-w-xs whitespace-pre-wrap break-all px-3 py-2 font-mono text-xs text-red-700">{{ $formatDiffValue($change['old']) }}</td>
                                    <td class="max-w-xs whitespace-pre-wrap break-all px-3 py-2 font-mono text-xs text-emerald-700">{{ $formatDiffValue($change['new']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if (! empty($diff['body']['text_diff']))
                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-500">
                    Diff body
                    <span class="ml-1 font-normal normal-case text-slate-400">(- видалено, + додано)</span>
                </p>
                <pre class="mb-4 max-h-96 overflow-auto rounded-lg border border-slate-200 bg-slate-950 p-4 text-xs leading-relaxed text-slate-100"><code>@foreach ($diff['body']['text_diff'] as $line)
@if (str_starts_with($line, '+ '))
<span class="block bg-emerald-900/50 text-emerald-300">{{ $line }}</span>
@elseif (str_starts_with($line, '- '))
<span class="block bg-red-900/50 text-red-300">{{ $line }}</span>
@else
<span class="block text-slate-400">{{ $line }}</span>
@endif
@endforeach</code></pre>
            @endif

            <div class="grid gap-4 lg:grid-cols-2">
                <div>
                    <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-500">Body було</p>
                    <pre class="max-h-80 overflow-auto rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-900"><code>{{ $diff['body']['old_preview'] ?? '' }}</code></pre>
                </div>
                <div>
                    <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-500">Body стало</p>
                    <pre class="max-h-80 overflow-auto rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-900"><code>{{ $diff['body']['new_preview'] ?? '' }}</code></pre>
                </div>
            </div>
        @endif
    </div>
</section>
