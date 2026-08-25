<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Plan;
use App\Models\Site;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class PlanQuota
{
    public function remainingSites(User $user): ?int
    {
        return $this->remaining($this->plan($user)->max_sites, $user->sites()->count());
    }

    public function remainingAddresses(User $user, Site $site): ?int
    {
        $plan = $this->plan($user);
        $perSite = $this->remaining($plan->max_addresses_per_site, $site->addresses()->count());
        $total = $this->remaining($plan->max_addresses_total, $this->addressCount($user));

        return $this->minRemaining($perSite, $total);
    }

    public function canCreateSite(User $user): bool
    {
        return $this->hasRoom($this->remainingSites($user), 1);
    }

    public function canCreateAddresses(User $user, Site $site, int $adding = 1): bool
    {
        return $adding > 0 && $this->hasRoom($this->remainingAddresses($user, $site), $adding);
    }

    public function assertCanCreateSite(User $user): void
    {
        if ($this->canCreateSite($user)) {
            return;
        }

        throw ValidationException::withMessages([
            'name' => __('plans.quota.site_limit', ['max' => $this->plan($user)->max_sites]),
        ]);
    }

    public function assertCanCreateAddresses(User $user, Site $site, int $adding = 1): void
    {
        if ($this->canCreateAddresses($user, $site, $adding)) {
            return;
        }

        throw ValidationException::withMessages([
            'endpoints' => $this->addressLimitMessage($user, $adding),
        ]);
    }

    public function assertCanCreateAddressesOnNewSite(User $user, int $adding): void
    {
        $this->assertPerSiteAddressLimit($this->plan($user), $adding, 'file');
        $this->assertTotalAddressRoom($user, $adding, 'file');
    }

    /**
     * @param  list<array<string, mixed>>  $sites
     */
    public function assertCanImport(User $user, array $sites): void
    {
        $this->assertImportSiteRoom($user, count($sites));
        $this->assertImportAddressRoom($user, $sites);
    }

    /**
     * @return array{
     *     sites_used: int,
     *     sites_max: int|null,
     *     can_create_site: bool,
     *     addresses_used: int,
     *     addresses_total_max: int|null,
     *     addresses_per_site_max: int|null
     * }
     */
    public function siteUsage(User $user): array
    {
        $plan = $this->plan($user);
        $used = $user->sites()->count();

        return [
            'sites_used' => $used,
            'sites_max' => $plan->max_sites,
            'can_create_site' => $this->canCreateSite($user),
            'addresses_used' => $this->addressCount($user),
            'addresses_total_max' => $plan->max_addresses_total,
            'addresses_per_site_max' => $plan->max_addresses_per_site,
        ];
    }

    private function plan(User $user): Plan
    {
        $user->loadMissing('plan');

        if ($user->plan === null) {
            throw ValidationException::withMessages([
                'plan' => __('plans.quota.missing'),
            ]);
        }

        return $user->plan;
    }

    private function addressCount(User $user): int
    {
        return $user->addresses()->count();
    }

    private function remaining(mixed $max, int $used): ?int
    {
        if ($max === null || $max === '') {
            return null;
        }

        return max(0, (int) $max - $used);
    }

    private function hasRoom(?int $remaining, int $needed): bool
    {
        return $remaining === null || $remaining >= $needed;
    }

    private function minRemaining(?int $left, ?int $right): ?int
    {
        if ($left === null) {
            return $right;
        }

        if ($right === null) {
            return $left;
        }

        return min($left, $right);
    }

    private function addressLimitMessage(User $user, int $adding): string
    {
        $plan = $this->plan($user);
        $totalRemaining = $this->remaining($plan->max_addresses_total, $this->addressCount($user));

        if (! $this->hasRoom($totalRemaining, $adding)) {
            return __('plans.quota.address_total_limit', ['max' => $plan->max_addresses_total]);
        }

        return __('plans.quota.address_per_site_limit', ['max' => $plan->max_addresses_per_site]);
    }

    private function assertPerSiteAddressLimit(Plan $plan, int $adding, string $field): void
    {
        $max = $plan->max_addresses_per_site;

        if ($max === null || $max === '' || $adding <= (int) $max) {
            return;
        }

        throw ValidationException::withMessages([
            $field => __('plans.quota.address_per_site_import', ['max' => $plan->max_addresses_per_site]),
        ]);
    }

    private function assertTotalAddressRoom(User $user, int $adding, string $field): void
    {
        $plan = $this->plan($user);
        $remaining = $this->remaining($plan->max_addresses_total, $this->addressCount($user));

        if ($this->hasRoom($remaining, $adding)) {
            return;
        }

        throw ValidationException::withMessages([
            $field => __('plans.quota.address_total_limit', ['max' => $plan->max_addresses_total]),
        ]);
    }

    private function assertImportSiteRoom(User $user, int $incoming): void
    {
        $remaining = $this->remainingSites($user);

        if ($this->hasRoom($remaining, $incoming)) {
            return;
        }

        throw ValidationException::withMessages([
            'file' => __('plans.quota.import_sites', [
                'incoming' => $incoming,
                'remaining' => $remaining,
            ]),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $sites
     */
    private function assertImportAddressRoom(User $user, array $sites): void
    {
        $plan = $this->plan($user);
        $adding = 0;

        foreach ($sites as $site) {
            $addresses = $site['addresses'] ?? [];
            $count = is_array($addresses) ? count($addresses) : 0;
            $this->assertPerSiteAddressLimit($plan, $count, 'file');
            $adding += $count;
        }

        $this->assertTotalAddressRoom($user, $adding, 'file');
    }
}
