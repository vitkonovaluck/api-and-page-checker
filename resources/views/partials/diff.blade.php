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

    $typeLabels = [
        'added' => 'додано',
        'removed' => 'видалено',
        'changed' => 'змінено',
    ];
@endphp

<section class="rounded-2xl border border-white/10 bg-zinc-900/80 p-5 shadow-xl shadow-cyan-950/20 backdrop-blur-sm {{ session('diff_highlight') ? 'ring-2 ring-amber-300/50' : '' }}">
    <h2 class="mb-4 text-lg font-semibold text-white">Аналіз змін</h2>

    @if ($diff['is_first'])
        <p class="mb-4 rounded-lg border border-cyan-400/20 bg-cyan-400/10 px-3 py-2 text-sm text-cyan-200">
            Це перший знімок для цього сайту — порівняння ще недоступне.
        </p>
    @elseif (! $diff['has_changes'])
        <p class="mb-4 rounded-lg border border-emerald-400/20 bg-emerald-400/10 px-3 py-2 text-sm text-emerald-200">
            Змін у відповіді не виявлено (status, headers і body збігаються з попереднім знімком).
        </p>
    @else
        <p class="mb-4 rounded-lg border border-amber-300/20 bg-amber-300/10 px-3 py-2 text-sm text-amber-100">
            Виявлено зміни відносно попереднього знімка.
        </p>
    @endif

    <div class="mb-6 grid gap-3 sm:grid-cols-3">
        <div class="rounded-lg border border-white/10 bg-white/5 p-3">
            <div class="text-xs font-medium uppercase tracking-wide text-zinc-400">HTTP статус</div>
            <div class="mt-1 text-sm font-semibold">
                @if ($diff['status_code']['changed'] && ! $diff['is_first'])
                    <span class="text-zinc-400">{{ $diff['status_code']['old'] ?? '—' }}</span>
                    <span class="mx-1 text-zinc-500">→</span>
                    <span class="text-amber-300">{{ $diff['status_code']['new'] ?? '—' }}</span>
                @else
                    {{ $diff['status_code']['new'] ?? '—' }}
                @endif
            </div>
        </div>

        <div class="rounded-lg border border-white/10 bg-white/5 p-3">
            <div class="text-xs font-medium uppercase tracking-wide text-zinc-400">Час відповіді</div>
            <div class="mt-1 text-sm font-semibold">
                @if ($diff['response_time_ms']['old'] !== null)
                    <span class="text-zinc-400">{{ $diff['response_time_ms']['old'] }} ms</span>
                    <span class="mx-1 text-zinc-500">→</span>
                    <span>{{ $diff['response_time_ms']['new'] }} ms</span>
                    @php $delta = $diff['response_time_ms']['delta']; @endphp
                    <span class="ml-1 text-xs font-normal {{ $delta > 0 ? 'text-rose-400' : ($delta < 0 ? 'text-emerald-400' : 'text-zinc-400') }}">
                        ({{ $delta > 0 ? '+' : '' }}{{ $delta }} ms)
                    </span>
                @else
                    {{ $diff['response_time_ms']['new'] }} ms
                @endif
            </div>
        </div>

        <div class="rounded-lg border border-white/10 bg-white/5 p-3">
            <div class="text-xs font-medium uppercase tracking-wide text-zinc-400">Помилка мережі</div>
            <div class="mt-1 text-sm">
                @if ($diff['error_message']['changed'] && ! $diff['is_first'])
                    <div class="text-zinc-400 line-through">{{ $diff['error_message']['old'] ?: 'немає' }}</div>
                    <div class="text-rose-300">{{ $diff['error_message']['new'] ?: 'немає' }}</div>
                @else
                    <span class="{{ $diff['error_message']['new'] ? 'text-rose-300' : 'text-zinc-300' }}">
                        {{ $diff['error_message']['new'] ?: 'немає' }}
                    </span>
                @endif
            </div>
        </div>
    </div>

    @if (! empty($diff['headers']))
        <div class="mb-6">
            <h3 class="mb-2 text-sm font-semibold text-zinc-100">Зміни заголовків</h3>
            <div class="overflow-x-auto rounded-lg border border-white/10">
                <table class="min-w-full divide-y divide-white/10 text-sm">
                    <thead class="bg-white/5 text-left text-xs uppercase text-zinc-500">
                        <tr>
                            <th class="px-3 py-2">Заголовок</th>
                            <th class="px-3 py-2">Тип</th>
                            <th class="px-3 py-2">Було</th>
                            <th class="px-3 py-2">Стало</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5 bg-zinc-900/40">
                        @foreach ($diff['headers'] as $change)
                            <tr>
                                <td class="px-3 py-2 font-mono text-xs">{{ $change['key'] }}</td>
                                <td class="px-3 py-2">{{ $typeLabels[$change['type']] ?? $change['type'] }}</td>
                                <td class="px-3 py-2 font-mono text-xs text-rose-300">{{ $change['old'] ?? '—' }}</td>
                                <td class="px-3 py-2 font-mono text-xs text-emerald-300">{{ $change['new'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div>
        <h3 class="mb-2 text-sm font-semibold text-zinc-100">Зміни body</h3>

        @if ($diff['is_first'])
            <p class="mb-3 text-sm text-zinc-400">Повний body першого знімка:</p>
            <pre class="max-h-96 overflow-auto rounded-lg border border-white/10 bg-zinc-950 p-4 text-xs leading-relaxed text-zinc-100"><code>{{ $diff['body']['new_preview'] ?? '' }}</code></pre>
        @elseif (! $diff['body']['changed'])
            <p class="rounded-lg border border-emerald-400/20 bg-emerald-400/10 px-3 py-2 text-sm text-emerald-200">
                Body не змінився порівняно з попереднім знімком.
            </p>
        @else
            @if ($diff['body']['type'] === 'json' && ! empty($diff['body']['changes']))
                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-zinc-400">Поля JSON, що змінилися</p>
                <div class="mb-4 overflow-x-auto rounded-lg border border-white/10">
                    <table class="min-w-full divide-y divide-white/10 text-sm">
                        <thead class="bg-white/5 text-left text-xs uppercase text-zinc-500">
                            <tr>
                                <th class="px-3 py-2">Шлях</th>
                                <th class="px-3 py-2">Тип</th>
                                <th class="px-3 py-2">Було</th>
                                <th class="px-3 py-2">Стало</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5 bg-zinc-900/40">
                            @foreach ($diff['body']['changes'] as $change)
                                <tr>
                                    <td class="px-3 py-2 font-mono text-xs">{{ $change['path'] }}</td>
                                    <td class="px-3 py-2">{{ $typeLabels[$change['type']] ?? $change['type'] }}</td>
                                    <td class="max-w-xs whitespace-pre-wrap break-all px-3 py-2 font-mono text-xs text-rose-300">{{ $formatDiffValue($change['old']) }}</td>
                                    <td class="max-w-xs whitespace-pre-wrap break-all px-3 py-2 font-mono text-xs text-emerald-300">{{ $formatDiffValue($change['new']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if (! empty($diff['body']['text_diff']))
                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-zinc-400">
                    Diff body
                    <span class="ml-1 font-normal normal-case text-zinc-500">(- видалено, + додано)</span>
                </p>
                <pre class="mb-4 max-h-96 overflow-auto rounded-lg border border-white/10 bg-zinc-950 p-4 text-xs leading-relaxed text-zinc-100"><code>@foreach ($diff['body']['text_diff'] as $line)
@if (str_starts_with($line, '+ '))
<span class="block bg-emerald-900/50 text-emerald-300">{{ $line }}</span>
@elseif (str_starts_with($line, '- '))
<span class="block bg-rose-400/10 text-rose-300">{{ $line }}</span>
@else
<span class="block text-zinc-500">{{ $line }}</span>
@endif
@endforeach</code></pre>
            @endif

            <div class="grid gap-4 lg:grid-cols-2">
                <div>
                    <p class="mb-2 text-xs font-medium uppercase tracking-wide text-zinc-400">Body було</p>
                    <pre class="max-h-80 overflow-auto rounded-lg border border-rose-400/20 bg-rose-400/10 p-3 text-xs text-rose-200"><code>{{ $diff['body']['old_preview'] ?? '' }}</code></pre>
                </div>
                <div>
                    <p class="mb-2 text-xs font-medium uppercase tracking-wide text-zinc-400">Body стало</p>
                    <pre class="max-h-80 overflow-auto rounded-lg border border-emerald-400/20 bg-emerald-400/10 p-3 text-xs text-emerald-200"><code>{{ $diff['body']['new_preview'] ?? '' }}</code></pre>
                </div>
            </div>
        @endif
    </div>
</section>
