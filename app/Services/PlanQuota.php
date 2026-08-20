<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Plan;
use App\Models\Site;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class PlanQuota
{
    public function remainingSites(User $user): int
    {
        return max(0, $this->plan($user)->max_sites - $user->sites()->count());
    }

    public function remainingAddresses(User $user, Site $site): int
    {
        return max(0, $this->plan($user)->max_addresses_per_site - $site->addresses()->count());
    }

    public function canCreateSite(User $user): bool
    {
        return $this->remainingSites($user) > 0;
    }

    public function canCreateAddresses(User $user, Site $site, int $adding = 1): bool
    {
        return $adding > 0 && $this->remainingAddresses($user, $site) >= $adding;
    }

    public function assertCanCreateSite(User $user): void
    {
        if ($this->canCreateSite($user)) {
            return;
        }

        throw ValidationException::withMessages([
            'name' => 'Досягнуто ліміт сайтів для вашого тарифу ('.$this->plan($user)->max_sites.').',
        ]);
    }

    public function assertCanCreateAddresses(User $user, Site $site, int $adding = 1): void
    {
        if ($this->canCreateAddresses($user, $site, $adding)) {
            return;
        }

        throw ValidationException::withMessages([
            'endpoints' => 'Досягнуто ліміт адрес на сайт для вашого тарифу ('.$this->plan($user)->max_addresses_per_site.').',
        ]);
    }

    public function assertCanCreateAddressesOnNewSite(User $user, int $adding): void
    {
        $max = $this->plan($user)->max_addresses_per_site;

        if ($adding <= $max) {
            return;
        }

        throw ValidationException::withMessages([
            'file' => 'Тариф дозволяє щонайбільше '.$max.' адрес на сайт.',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $sites
     */
    public function assertCanImport(User $user, array $sites): void
    {
        $remaining = $this->remainingSites($user);
        $incoming = count($sites);

        if ($incoming > $remaining) {
            throw ValidationException::withMessages([
                'file' => 'Імпорт додасть '.$incoming.' сайт(ів), доступно ще '.$remaining.'.',
            ]);
        }

        foreach ($sites as $site) {
            $addresses = $site['addresses'] ?? [];
            $count = is_array($addresses) ? count($addresses) : 0;
            $this->assertCanCreateAddressesOnNewSite($user, $count);
        }
    }

    /**
     * @return array{sites_used: int, sites_max: int, can_create_site: bool}
     */
    public function siteUsage(User $user): array
    {
        $plan = $this->plan($user);
        $used = $user->sites()->count();

        return [
            'sites_used' => $used,
            'sites_max' => $plan->max_sites,
            'can_create_site' => $used < $plan->max_sites,
        ];
    }

    private function plan(User $user): Plan
    {
        $user->loadMissing('plan');

        if ($user->plan === null) {
            throw ValidationException::withMessages([
                'plan' => 'Тариф не призначено. Зверніться до адміністратора.',
            ]);
        }

        return $user->plan;
    }
}
