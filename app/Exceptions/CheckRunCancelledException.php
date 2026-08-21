<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

final class CheckRunCancelledException extends Exception implements ShouldntReport
{
    public function __construct(public readonly int $checkRunId)
    {
        parent::__construct("Check run {$checkRunId} was cancelled.");
    }

    /**
     * @return array{check_run_id: int}
     */
    public function context(): array
    {
        return ['check_run_id' => $this->checkRunId];
    }
}
