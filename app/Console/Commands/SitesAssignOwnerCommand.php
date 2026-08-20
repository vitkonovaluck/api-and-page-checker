<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Site;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sites:assign-owner {email : User email that should own unassigned sites}')]
#[Description('Assign sites without an owner to the given user')]
final class SitesAssignOwnerCommand extends Command
{
    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("User {$email} was not found.");

            return self::FAILURE;
        }

        $updated = Site::query()
            ->whereNull('user_id')
            ->update(['user_id' => $user->id]);

        $this->info("Assigned {$updated} site(s) to {$email}.");

        return self::SUCCESS;
    }
}
