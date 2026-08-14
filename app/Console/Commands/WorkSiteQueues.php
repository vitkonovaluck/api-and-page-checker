<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\CheckAddressJob;
use App\Models\Site;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

#[Signature('queue:work-site-queues {--tries=3 : Number of times to attempt a job} {--timeout=90 : Seconds a child worker may run a job} {--sleep=1 : Seconds to wait when a queue is empty}')]
#[Description('Run a dedicated queue worker for each site so checks proceed in parallel')]
class WorkSiteQueues extends Command
{
    /**
     * @var array<string, Process>
     */
    private array $workers = [];

    private bool $shouldQuit = false;

    public function handle(): int
    {
        $this->trapSignals();

        $this->info('Starting per-site queue workers.');

        try {
            while (! $this->shouldQuit) {
                $this->syncWorkers();
                sleep(1);
            }
        } finally {
            $this->stopWorkers();
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    public function wantedQueueNames(): array
    {
        $queues = [];

        foreach (Site::query()->orderBy('id')->pluck('id') as $siteId) {
            $queues[] = CheckAddressJob::queueNameForSite((int) $siteId);
        }

        $queues[] = 'default';

        return $queues;
    }

    private function trapSignals(): void
    {
        $signals = [];

        if (defined('SIGTERM')) {
            $signals[] = SIGTERM;
        }

        if (defined('SIGINT')) {
            $signals[] = SIGINT;
        }

        if ($signals === []) {
            return;
        }

        $this->trap($signals, function (): void {
            $this->shouldQuit = true;
        });
    }

    private function syncWorkers(): void
    {
        $wanted = $this->wantedQueueNames();

        foreach (array_keys($this->workers) as $queue) {
            $process = $this->workers[$queue];
            if (! in_array($queue, $wanted, true) || ! $process->isRunning()) {
                $this->stopWorker($queue);
            }
        }

        foreach ($wanted as $queue) {
            if (! isset($this->workers[$queue])) {
                $this->startWorker($queue);
            }
        }
    }

    private function startWorker(string $queue): void
    {
        $process = new Process(
            [
                PHP_BINARY,
                base_path('artisan'),
                'queue:work',
                '--queue='.$queue,
                '--tries='.(string) $this->option('tries'),
                '--timeout='.(string) $this->option('timeout'),
                '--sleep='.(string) $this->option('sleep'),
                '--no-interaction',
            ],
            base_path(),
        );
        $process->setTimeout(null);
        $process->start();

        $this->workers[$queue] = $process;
        $this->info("Worker started for queue [{$queue}].");
    }

    private function stopWorker(string $queue): void
    {
        if (! isset($this->workers[$queue])) {
            return;
        }

        $process = $this->workers[$queue];
        if ($process->isRunning()) {
            $process->stop(3);
        }

        unset($this->workers[$queue]);
        $this->info("Worker stopped for queue [{$queue}].");
    }

    private function stopWorkers(): void
    {
        foreach (array_keys($this->workers) as $queue) {
            $this->stopWorker($queue);
        }
    }
}
