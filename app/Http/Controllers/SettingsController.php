<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class SettingsController extends Controller
{
    public function __construct(private DatabaseBackupService $backups) {}

    public function index(): View
    {
        return view('settings.index', $this->backups->viewData());
    }

    public function backup(): BinaryFileResponse|RedirectResponse
    {
        try {
            $backup = $this->backups->create();
        } catch (Throwable $e) {
            return redirect()
                ->route('settings.index')
                ->withErrors(['backup' => $e->getMessage()]);
        }

        $response = response()->download($backup['path'], $backup['filename']);

        if ($backup['delete_after_send']) {
            $response->deleteFileAfterSend();
        }

        return $response;
    }

    public function restore(Request $request): RedirectResponse
    {
        $request->validate([
            'database' => ['required', 'file', 'max:102400'],
        ], [
            'database.required' => 'Оберіть файл бази даних.',
            'database.max' => 'Файл занадто великий (макс. 100 МБ).',
        ]);

        $upload = $request->file('database');
        $extension = strtolower($upload->getClientOriginalExtension());
        $temporary = $this->backups->backupDir().DIRECTORY_SEPARATOR.'restore-upload-'.uniqid('', true).'.'.$extension;

        try {
            $upload->move(dirname($temporary), basename($temporary));
            $this->backups->restore($temporary, $extension);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('settings.index')
                ->withErrors(['database' => $e->getMessage()]);
        } catch (Throwable $e) {
            return redirect()
                ->route('settings.index')
                ->withErrors(['database' => 'Не вдалося відновити базу: '.$e->getMessage()]);
        } finally {
            if (File::exists($temporary)) {
                File::delete($temporary);
            }
        }

        return redirect()
            ->route('settings.index')
            ->with('success', 'Базу даних відновлено з бекапу.');
    }
}
