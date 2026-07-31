@extends('layouts.app')

@section('title', 'Налаштування — API Snapshot Checker')

@section('content')
    <div class="mb-8">
        <h1 class="text-2xl font-semibold text-slate-900">Налаштування</h1>
        <p class="mt-1 text-sm text-slate-600">Бекап і відновлення бази даних, службова інформація.</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-2 text-base font-semibold text-slate-900">Бекап бази даних</h2>
            <p class="mb-4 text-sm text-slate-600">
                Завантажити поточний файл SQLite
                @if ($databaseExists)
                    (<span class="font-mono text-xs">database/database.sqlite</span>).
                @else
                    (файл ще не створено).
                @endif
            </p>
            <form method="POST" action="{{ route('settings.backup') }}">
                @csrf
                <button
                    type="submit"
                    class="inline-flex rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
                    @disabled(! $databaseExists)
                >
                    Завантажити бекап
                </button>
            </form>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-2 text-base font-semibold text-slate-900">Відновлення з бекапу</h2>
            <p class="mb-4 text-sm text-slate-600">
                Перед заміною створюється копія поточної бази в
                <span class="font-mono text-xs">storage/app/backups</span>.
            </p>
            <form method="POST" action="{{ route('settings.restore') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div>
                    <label for="database" class="mb-1 block text-sm font-medium text-slate-700">Файл .sqlite / .db</label>
                    <input
                        type="file"
                        name="database"
                        id="database"
                        accept=".sqlite,.db"
                        required
                        class="block w-full text-sm text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-900 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-slate-800"
                    >
                </div>
                <button
                    type="submit"
                    class="inline-flex rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-900 hover:bg-amber-100"
                    onclick="return confirm('Замінити поточну базу даних цим файлом?')"
                >
                    Відновити базу
                </button>
            </form>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
            <h2 class="mb-2 text-base font-semibold text-slate-900">Розклад перевірок</h2>
            <p class="text-sm text-slate-600">
                На сервері додайте cron, щоб Laravel запускав розклад щохвилини:
            </p>
            <pre class="mt-3 overflow-x-auto rounded-lg border border-slate-200 bg-slate-950 p-4 text-xs text-slate-100"><code>* * * * * cd {{ base_path() }} &amp;&amp; php artisan schedule:run &gt;&gt; /dev/null 2&gt;&amp;1</code></pre>
            <p class="mt-3 text-sm text-slate-600">
                Локально можна перевірити командою
                <span class="font-mono text-xs">php artisan sites:run-scheduled</span>.
            </p>
        </section>
    </div>
@endsection
