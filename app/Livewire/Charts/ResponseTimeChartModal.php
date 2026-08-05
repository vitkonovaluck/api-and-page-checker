<?php

namespace App\Livewire\Charts;

use App\Models\Address;
use App\Models\Site;
use App\Services\CheckStats;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class ResponseTimeChartModal extends Component
{
    public string $mode = 'site';

    public ?int $siteId = null;

    public ?int $addressId = null;

    public string $title = 'Історія часу відповіді';

    public string $chartId = 'response-time-chart';

    public bool $show = false;

    public string $period = CheckStats::DEFAULT_RESPONSE_TIME_PERIOD;

    public function mount(
        string $mode = 'site',
        ?int $siteId = null,
        ?int $addressId = null,
        string $title = 'Історія часу відповіді',
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
        $this->dispatch('chart-should-render');
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

    public function render()
    {
        return view('livewire.charts.response-time-chart-modal');
    }
}
