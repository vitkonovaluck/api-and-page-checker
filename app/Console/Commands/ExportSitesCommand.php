<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Site;
use App\Services\SiteTransferService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('sites:export {site? : Site ID to export} {--path= : Write JSON to this file instead of stdout}')]
#[Description('Export site configuration (and addresses) as portable JSON')]
final class ExportSitesCommand extends Command
{
    public function handle(SiteTransferService $transfer): int
    {
        $payload = $this->payload($transfer);

        if ($payload === null) {
            return self::FAILURE;
        }

        return $this->writeJson($transfer->encode($payload));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function payload(SiteTransferService $transfer): ?array
    {
        $siteId = $this->argument('site');

        if ($siteId === null) {
            return $transfer->exportAll();
        }

        $site = Site::query()->find((int) $siteId);

        if ($site === null) {
            $this->error("Site {$siteId} was not found.");

            return null;
        }

        return $transfer->exportSite($site);
    }

    private function writeJson(string $json): int
    {
        $path = $this->option('path');

        if (! is_string($path) || $path === '') {
            $this->output->write($json.PHP_EOL);

            return self::SUCCESS;
        }

        File::put($path, $json);
        $this->info("Exported to {$path}");

        return self::SUCCESS;
    }
}
