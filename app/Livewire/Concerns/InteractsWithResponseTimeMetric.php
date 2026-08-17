<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Enums\ResponseTimeMetric;
use Livewire\Attributes\On;

trait InteractsWithResponseTimeMetric
{
    public string $metric = 'total';

    public function setMetric(string $metric): void
    {
        $this->applyResponseTimeMetric($metric);
        $this->dispatch('response-time-metric-changed', metric: $this->metric);
    }

    #[On('response-time-metric-changed')]
    public function onResponseTimeMetricChanged(string $metric): void
    {
        $this->applyResponseTimeMetric($metric);
        $this->afterResponseTimeMetricChanged();
    }

    protected function hydrateResponseTimeMetric(): void
    {
        $this->metric = ResponseTimeMetric::normalize(session(ResponseTimeMetric::SESSION_KEY))->value;
    }

    protected function applyResponseTimeMetric(string $metric): void
    {
        $this->metric = ResponseTimeMetric::normalize($metric)->value;
        session([ResponseTimeMetric::SESSION_KEY => $this->metric]);
    }

    protected function afterResponseTimeMetricChanged(): void {}

    public function responseTimeMetric(): ResponseTimeMetric
    {
        return ResponseTimeMetric::normalize($this->metric);
    }
}
