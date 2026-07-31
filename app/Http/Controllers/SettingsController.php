<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class SettingsController extends Controller
{
    public function index(): View
    {
        return view('settings.index', [
            'databasePath' => database_path('database.sqlite'),
            'databaseExists' => File::exists(database_path('database.sqlite')),
        ]);
    }

    public function backup(): BinaryFileResponse|RedirectResponse
    {
        $path = database_path('database.sqlite');

        if (! File::exists($path)) {
            return redirect()
                ->route('settings.index')
                ->withErrors(['backup' => 'Файл бази даних не знайдено.']);
        }

        $filename = 'api-checker-'.now()->format('Y-m-d-His').'.sqlite';

        return response()->download($path, $filename);
    }

    public function restore(Request $request): RedirectResponse
    {
        $request->validate([
            'database' => ['required', 'file', 'max:51200'],
        ], [
            'database.required' => 'Оберіть файл бази даних.',
        ]);

        $upload = $request->file('database');
        $extension = strtolower($upload->getClientOriginalExtension());

        if (! in_array($extension, ['sqlite', 'db'], true)) {
            return redirect()
                ->route('settings.index')
                ->withErrors(['database' => 'Дозволені лише файли .sqlite або .db.']);
        }

        $target = database_path('database.sqlite');
        $backupDir = storage_path('app/backups');
        File::ensureDirectoryExists($backupDir);

        try {
            DB::disconnect();

            if (File::exists($target)) {
                File::copy(
                    $target,
                    $backupDir.DIRECTORY_SEPARATOR.'pre-restore-'.now()->format('Y-m-d-His').'.sqlite'
                );
            }

            $temporary = $backupDir.DIRECTORY_SEPARATOR.'restore-upload-'.uniqid('', true).'.sqlite';
            $upload->move(dirname($temporary), basename($temporary));

            if (! $this->looksLikeSqlite($temporary)) {
                File::delete($temporary);

                return redirect()
                    ->route('settings.index')
                    ->withErrors(['database' => 'Файл не схожий на SQLite базу даних.']);
            }

            File::move($temporary, $target);
        } catch (Throwable $e) {
            return redirect()
                ->route('settings.index')
                ->withErrors(['database' => 'Не вдалося відновити базу: '.$e->getMessage()]);
        }

        return redirect()
            ->route('settings.index')
            ->with('success', 'Базу даних відновлено з бекапу.');
    }

    private function looksLikeSqlite(string $path): bool
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        $header = fread($handle, 16);
        fclose($handle);

        return is_string($header) && str_starts_with($header, 'SQLite format 3');
    }
}
