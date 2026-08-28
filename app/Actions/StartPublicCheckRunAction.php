<?php

declare(strict_types=1);

namespace App\Actions;

use App\Jobs\CheckAddressJob;
use App\Models\CheckRun;
use App\Models\Site;
use Illuminate\Validation\ValidationException;

final class StartPublicCheckRunAction
{
    /**
     * @param  list<int>  $addressIds
     */
    public function execute(Site $site, array $addressIds = []): CheckRun
    {
        $query = $site->addresses()->orderBy('id');

        if ($addressIds !== []) {
            $query->whereIn('id', $addressIds);
        }

        $addresses = $query->get();

        if ($addresses->isEmpty()) {
            throw ValidationException::withMessages([
                'address_ids' => __('alerts.ui.no_addresses_to_check'),
            ]);
        }

        return CheckAddressJob::dispatchForSite($site, CheckRun::SOURCE_MANUAL, $addresses);
    }
}
