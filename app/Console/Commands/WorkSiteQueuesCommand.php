<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Site;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

#[Signature('sites:queue-work
            {--listen : Use queue:listen instead of queue:work}
            {--tries=0 : Number of times to attempt a job (0 = use the job class)}
            {--timeout=90 : Seconds a child job may run}
            {--sleep=3 : Seconds to wait when no job is available}
            {--pretend : List per-site queues without starting workers}')]
#[Description('Run a dedicated queue worker process for each site')]
class WorkSiteQueuesCommand extends Command
{
    /**
     * @var array<int, Process>
     */
    private array $workers = [];

    private bool $stopping = false;

    public function handle(): int
    {
        if ($this->option('pretend')) {
            return $this->pretend();
        }

        $this->registerStopSignals();
        register_shutdown_function($this->stopAllWorkers(...));

        $this->info('Starting one queue worker per site. Press Ctrl+C to stop.');

        try {
            $this->runWorkerLoop();
        } finally {
            $this->stopAllWorkers();
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    public static function childArtisanArguments(
        int $siteId,
        bool $listen = false,
        int $tries = 0,
        int $timeout = 90,
        int $sleep = 3,
    ): array {
        return [
            $listen ? 'queue:listen' : 'queue:work',
            '--queue='.Site::checkQueueName($siteId),
            '--tries='.(string) $tries,
            '--timeout='.(string) $timeout,
            '--sleep='.(string) $sleep,
        ];
    }

    private function pretend(): int
    {
        $sites = Site::query()->orderBy('id')->get(['id', 'name']);

        if ($sites->isEmpty()) {
            $this->warn('No sites found.');

            return self::SUCCESS;
        }

        foreach ($sites as $site) {
            $this->line("#{$site->id} {$site->name} → {$site->checkQueue()}");
        }

        return self::SUCCESS;
    }

    private function registerStopSignals(): void
    {
        $signals = [];

        if (defined('SIGINT')) {
            $signals[] = SIGINT;
        }

        if (defined('SIGTERM')) {
            $signals[] = SIGTERM;
        }

        if ($signals === []) {
            return;
        }

        $this->trap($signals, function (): void {
            $this->stopping = true;
        });
    }

    private function runWorkerLoop(): void
    {
        $lastSyncAt = 0;
        $scanSeconds = max(1, (int) config('checking.worker_scan_seconds', 5));

        while (! $this->stopping) {
            if ((time() - $lastSyncAt) >= $scanSeconds) {
                $this->syncWorkers();
                $lastSyncAt = time();
            }

            $this->drainOutput();
            usleep(200_000);
        }
    }

    private function syncWorkers(): void
    {
        $siteIds = Site::query()->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all();

        foreach (array_diff(array_keys($this->workers), $siteIds) as $removedId) {
            $this->stopWorker((int) $removedId);
        }

        foreach ($siteIds as $siteId) {
            $worker = $this->workers[$siteId] ?? null;
            if ($worker instanceof Process && $worker->isRunning()) {
                continue;
            }

            $this->startWorker($siteId);
        }
    }

    private function startWorker(int $siteId): void
    {
        $process = new Process(
            [PHP_BINARY, base_path('artisan'), ...self::childArtisanArguments(
                $siteId,
                (bool) $this->option('listen'),
                (int) $this->option('tries'),
                (int) $this->option('timeout'),
                (int) $this->option('sleep'),
            )],
            base_path(),
        );
        $process->setTimeout(null);
        $process->start();

        $this->workers[$siteId] = $process;
        $this->info('Started worker for '.Site::checkQueueName($siteId));
    }

    private function drainOutput(): void
    {
        foreach ($this->workers as $siteId => $process) {
            $output = $process->getIncrementalOutput().$process->getIncrementalErrorOutput();
            if ($output === '') {
                continue;
            }

            $this->output->write('[site-'.$siteId.'] '.$output);
        }
    }

    private function stopWorker(int $siteId): void
    {
        $process = $this->workers[$siteId] ?? null;
        unset($this->workers[$siteId]);

        if (! $process instanceof Process || ! $process->isRunning()) {
            return;
        }

        $process->stop(3);
        $this->info('Stopped worker for '.Site::checkQueueName($siteId));
    }

    private function stopAllWorkers(): void
    {
        foreach (array_keys($this->workers) as $siteId) {
            $this->stopWorker((int) $siteId);
        }
    }
}
