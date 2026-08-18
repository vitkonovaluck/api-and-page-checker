<?php

declare(strict_types=1);

namespace App\Livewire\Charts;

use App\Enums\ResponseTimeMetric;
use App\Models\Address;
use App\Models\Site;
use App\Services\CheckStats;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class ResponseTimeChartModal extends Component
{
    public string $mode = 'site';

    public ?int $siteId = null;

    public ?int $addressId = null;

    public string $title = 'Історія часу відповіді та TTFB';

    public string $chartId = 'response-time-chart';

    public bool $show = false;

    public string $period = CheckStats::DEFAULT_RESPONSE_TIME_PERIOD;

    public function mount(
        string $mode = 'site',
        ?int $siteId = null,
        ?int $addressId = null,
        string $title = 'Історія часу відповіді та TTFB',
        string $chartId = 'response-time-chart',
    ): void {
        $this->mode = $mode;
        $this->siteId = $siteId;
        $this->addressId = $addressId;
        $this->title = $title;
        $this->chartId = $chartId;
        $this->period = CheckStats::DEFAULT_RESPONSE_TIME_PERIOD;
    }

    #[On('open-response-time-chart')]
    public function open(): void
    {
        $this->show = true;
        unset($this->chart);
        $this->dispatch('chart-should-render');
    }

    public function close(): void
    {
        $this->show = false;
    }

    public function setPeriod(string $period): void
    {
        if (! array_key_exists($period, CheckStats::RESPONSE_TIME_PERIODS)) {
            return;
        }

        $this->period = $period;
        unset($this->chart);
        $this->dispatch('chart-should-render');
    }

    public function refreshChart(): void
    {
        if (! $this->show) {
            return;
        }

        unset($this->chart);
        $this->dispatch('chart-should-render');
    }

    public function chartHeading(): string
    {
        return match ($this->mode) {
            'site' => ResponseTimeMetric::combinedChartSiteTitle(),
            default => ResponseTimeMetric::combinedChartTitle(),
        };
    }

    public function chartDescription(): string
    {
        return match ($this->mode) {
            'site' => ResponseTimeMetric::combinedChartSiteDescription(),
            default => ResponseTimeMetric::combinedChartAddressDescription(),
        };
    }

    #[Computed]
    public function chart(): array
    {
        $checkStats = app(CheckStats::class);

        if ($this->mode === 'address') {
            $address = Address::query()->findOrFail($this->addressId);

            return $checkStats->responseTimeChartForAddress($address, $this->period);
        }

        $site = Site::query()->findOrFail($this->siteId);

        return $checkStats->responseTimeChartForSite($site, $this->period);
    }

    /**
     * @return array{
     *     labels: list<string>,
     *     values: list<int>,
     *     ttfb_values: list<int|null>,
     *     counts: list<int>,
     *     series: list<array{key: string, label: string, values: list<int|null>}>,
     *     period_label: string
     * }
     */
    public function chartPayload(): array
    {
        $chart = $this->chart;

        return [
            'labels' => $chart['labels'],
            'values' => $chart['values'],
            'ttfb_values' => $chart['ttfb_values'] ?? [],
            'counts' => $chart['counts'],
            'series' => $chart['series'] ?? [],
            'period_label' => $chart['period_label'] ?? '',
        ];
    }

    public function totalAverageLabel(): string
    {
        return ResponseTimeMetric::Total->historyAverageLabel();
    }

    public function ttfbAverageLabel(): string
    {
        return ResponseTimeMetric::Ttfb->historyAverageLabel();
    }

    public function render(): View
    {
        return view('livewire.charts.response-time-chart-modal');
    }
}
