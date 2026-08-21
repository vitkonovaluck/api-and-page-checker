@extends('layouts.app')

@section('title', 'Налаштування — API Snapshot Checker')

@section('content')
    <div class="mb-8">
        <h1 class="text-2xl font-semibold tracking-tight text-white">Налаштування</h1>
        <p class="mt-1 text-sm text-zinc-400">Перенесення сайтів між серверами, агенти перевірок, бекап бази та службова інформація.</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-white/10 bg-zinc-900/80 p-5 shadow-xl shadow-cyan-950/20 backdrop-blur-sm">
            <h2 class="mb-2 text-base font-semibold text-white">Експорт сайтів</h2>
            <p class="mb-4 text-sm text-zinc-400">
                Завантажити JSON з налаштуваннями всіх сайтів і адрес. Файл можна імпортувати на іншому сервері
                (SQLite чи MySQL). Історія перевірок не входить — для повної копії бази скористайтесь бекапом нижче.
            </p>
            <form method="GET" action="{{ route('sites.export-all') }}">
                <button
                    type="submit"
                    class="inline-flex items-center gap-2 rounded-xl bg-cyan-400 px-4 py-2 text-sm font-semibold text-zinc-950 transition hover:bg-cyan-300"
                >
                    @include('partials.icons.download')
                    Завантажити JSON
                </button>
            </form>
        </section>

        <section class="rounded-2xl border border-white/10 bg-zinc-900/80 p-5 shadow-xl shadow-cyan-950/20 backdrop-blur-sm">
            <h2 class="mb-2 text-base font-semibold text-white">Імпорт сайтів</h2>
            <p class="mb-4 text-sm text-zinc-400">
                Додає сайти з JSON-експорту. Поточні сайти не видаляються і не перезаписуються.
            </p>
            <form method="POST" action="{{ route('sites.import') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div>
                    <label for="sites-import-file" class="mb-1 block text-sm font-medium text-zinc-200">Файл .json</label>
                    <input
                        type="file"
                        name="file"
                        id="sites-import-file"
                        accept=".json,application/json"
                        required
                        class="block w-full text-sm text-zinc-300 file:mr-3 file:rounded-lg file:border-0 file:bg-cyan-400 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-zinc-950 hover:file:bg-cyan-300"
                    >
                </div>
                <button
                    type="submit"
                    class="inline-flex items-center gap-2 rounded-lg border border-white/15 px-4 py-2 text-sm font-medium text-zinc-200 transition hover:border-white/30 hover:bg-white/5 hover:text-white"
                >
                    @include('partials.icons.upload')
                    Імпортувати
                </button>
            </form>
        </section>

        <livewire:settings.agent-tokens />

        @if ($isAdmin)
        <section class="rounded-2xl border border-white/10 bg-zinc-900/80 p-5 shadow-xl shadow-cyan-950/20 backdrop-blur-sm">
            <h2 class="mb-2 text-base font-semibold text-white">Бекап бази даних</h2>
            @if ($is_mysql)
                <p class="mb-4 text-sm text-zinc-400">
                    Завантажити SQL-дамп поточної бази MySQL
                    @if ($database_name)
                        <span class="font-mono text-xs">{{ $database_name }}</span>
                    @endif
                    @if ($database_host)
                        ({{ $database_host }}{{ $database_port ? ':'.$database_port : '' }}).
                    @else
                        .
                    @endif
                </p>
            @elseif ($is_sqlite)
                <p class="mb-4 text-sm text-zinc-400">
                    Завантажити поточний файл SQLite
                    @if ($sqlite_exists)
                        (<span class="font-mono text-xs">{{ $sqlite_path }}</span>).
                    @else
                        (файл ще не створено).
                    @endif
                </p>
            @else
                <p class="mb-4 text-sm text-zinc-400">
                    Бекап для драйвера <span class="font-mono text-xs">{{ $driver }}</span> не підтримується.
                </p>
            @endif
            <form method="POST" action="{{ route('settings.backup') }}">
                @csrf
                <button
                    type="submit"
                    class="inline-flex rounded-xl bg-cyan-400 px-4 py-2 text-sm font-semibold text-zinc-950 transition hover:bg-cyan-300 disabled:cursor-not-allowed disabled:opacity-50"
                    @disabled(! $can_backup)
                >
                    Завантажити бекап
                </button>
            </form>
        </section>

        <section class="rounded-2xl border border-white/10 bg-zinc-900/80 p-5 shadow-xl shadow-cyan-950/20 backdrop-blur-sm">
            <h2 class="mb-2 text-base font-semibold text-white">Відновлення з бекапу</h2>
            <p class="mb-4 text-sm text-zinc-400">
                Перед заміною створюється копія поточної бази в
                <span class="font-mono text-xs">storage/app/backups</span>.
                @if ($is_mysql)
                    Потрібен файл <span class="font-mono text-xs">.sql</span> (дамп MySQL).
                @elseif ($is_sqlite)
                    Потрібен файл <span class="font-mono text-xs">.sqlite</span> або <span class="font-mono text-xs">.db</span>.
                @endif
            </p>
            <form method="POST" action="{{ route('settings.restore') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div>
                    <label for="database" class="mb-1 block text-sm font-medium text-zinc-200">Файл {{ $accepted_extensions }}</label>
                    <input
                        type="file"
                        name="database"
                        id="database"
                        accept="{{ $accepted_accept }}"
                        required
                        class="block w-full text-sm text-zinc-300 file:mr-3 file:rounded-lg file:border-0 file:bg-cyan-400 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-zinc-950 hover:file:bg-cyan-300"
                    >
                </div>
                <button
                    type="submit"
                    class="inline-flex rounded-lg border border-amber-300/30 bg-amber-300/10 px-4 py-2 text-sm font-medium text-amber-100 transition hover:bg-amber-300/20"
                    onclick="return confirm('Замінити поточну базу даних цим файлом?')"
                    @disabled(! $is_mysql && ! $is_sqlite)
                >
                    Відновити базу
                </button>
            </form>
        </section>
        @endif

        <section class="rounded-2xl border border-white/10 bg-zinc-900/80 p-5 shadow-xl shadow-cyan-950/20 backdrop-blur-sm lg:col-span-2">
            <h2 class="mb-2 text-base font-semibold text-white">Розклад перевірок</h2>
            <p class="text-sm text-zinc-400">
                На сервері додайте cron, щоб Laravel щохвилини ставив due-адреси в чергу.
                Старт — у вирівняні моменти (5/15/30 хв, щогодини тощо); сам HTTP-прогін виконує queue worker до останньої адреси:
            </p>
            <pre class="mt-3 overflow-x-auto rounded-lg border border-white/10 bg-zinc-950 p-4 text-xs text-zinc-100"><code>* * * * * cd {{ base_path() }} &amp;&amp; php artisan schedule:run &gt;&gt; /dev/null 2&gt;&amp;1</code></pre>
            <p class="mt-3 text-sm text-zinc-400">
                Окремо тримайте воркери черг: по одному процесу на кожен сайт (окремі черги, перевірки йдуть паралельно):
            </p>
            <pre class="mt-3 overflow-x-auto rounded-lg border border-white/10 bg-zinc-950 p-4 text-xs text-zinc-100"><code>php artisan sites:queue-work</code></pre>
            <p class="mt-3 text-sm text-zinc-400">
                Переглянути черги без запуску: <span class="font-mono text-xs">php artisan sites:queue-work --pretend</span>.
                Локально: <span class="font-mono text-xs">composer run dev</span> уже запускає
                <span class="font-mono text-xs">sites:queue-work --listen</span>, або вручну
                <span class="font-mono text-xs">php artisan sites:run-scheduled</span> (лише enqueue).
                Не запускайте одночасно cron <span class="font-mono text-xs">schedule:run</span> і окремий
                <span class="font-mono text-xs">sites:run-scheduled</span> — буде подвійна постановка в чергу.
            </p>
        </section>
    </div>
@endsection
