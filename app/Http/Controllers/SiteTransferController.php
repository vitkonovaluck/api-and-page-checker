<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ImportSitesRequest;
use App\Models\Site;
use App\Models\User;
use App\Services\SiteTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SiteTransferController extends Controller
{
    public function __construct(private SiteTransferService $transfer) {}

    public function export(Site $site): StreamedResponse
    {
        $this->authorize('view', $site);

        $payload = $this->transfer->exportSite($site);

        return $this->download(
            $this->transfer->encode($payload),
            $this->transfer->filenameForSite($site),
        );
    }

    public function exportAll(): StreamedResponse
    {
        $payload = $this->transfer->exportAll($this->currentUser());

        return $this->download(
            $this->transfer->encode($payload),
            $this->transfer->filenameForAll(),
        );
    }

    public function import(ImportSitesRequest $request): RedirectResponse
    {
        $path = $request->file('file')?->getRealPath();

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'file' => 'Не вдалося прочитати файл імпорту.',
            ]);
        }

        return $this->redirectAfterImport($this->transfer->importFile($path, $this->currentUser()));
    }

    /**
     * @param  Collection<int, Site>  $sites
     */
    private function redirectAfterImport(Collection $sites): RedirectResponse
    {
        $count = $sites->count();
        $message = $count === 1
            ? 'Сайт імпортовано.'
            : "Імпортовано сайтів: {$count}.";

        if ($count === 1) {
            return redirect()
                ->route('sites.show', $sites->first())
                ->with('success', $message);
        }

        return redirect()
            ->route('sites.index')
            ->with('success', $message);
    }

    private function download(string $json, string $filename): StreamedResponse
    {
        return response()->streamDownload(
            function () use ($json): void {
                echo $json;
            },
            $filename,
            ['Content-Type' => 'application/json; charset=UTF-8'],
        );
    }

    private function currentUser(): User
    {
        $user = request()->user();
        assert($user instanceof User);

        return $user;
    }
}
