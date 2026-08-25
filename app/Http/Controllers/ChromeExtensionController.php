<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\BuildChromeExtensionZipAction;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ChromeExtensionController extends Controller
{
    public function __invoke(BuildChromeExtensionZipAction $build): BinaryFileResponse
    {
        try {
            $package = $build->execute(rtrim((string) url('/'), '/'));
        } catch (RuntimeException $exception) {
            abort(503, $exception->getMessage());
        }

        return response()
            ->download($package['path'], $package['filename'], [
                'Content-Type' => 'application/zip',
            ])
            ->deleteFileAfterSend();
    }
}
