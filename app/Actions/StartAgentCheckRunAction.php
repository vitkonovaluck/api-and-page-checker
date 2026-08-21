<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Address;
use App\Models\CheckAgent;
use App\Models\CheckRun;
use App\Models\Site;
use Illuminate\Validation\ValidationException;

final class StartAgentCheckRunAction
{
    /**
     * @param  list<int>  $addressIds
     */
    public function execute(CheckAgent $agent, Site $site, array $addressIds): CheckRun
    {
        $this->assertAddressesBelongToSite($site, $addressIds);

        return CheckRun::start($site, CheckRun::SOURCE_AGENT, count($addressIds), $agent);
    }

    /**
     * @param  list<int>  $addressIds
     */
    private function assertAddressesBelongToSite(Site $site, array $addressIds): void
    {
        $uniqueIds = array_values(array_unique($addressIds));
        $ownedCount = Address::query()
            ->where('site_id', $site->id)
            ->whereIn('id', $uniqueIds)
            ->count();

        if ($ownedCount !== count($uniqueIds)) {
            throw ValidationException::withMessages([
                'address_ids' => __('agent.addresses_not_on_site'),
            ]);
        }
    }
}
