<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SiteTransferService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

#[Signature('sites:import {file : Path to an exported JSON file} {--user= : Owner email for imported sites}')]
#[Description('Import sites from a portable JSON export')]
final class ImportSitesCommand extends Command
{
    public function handle(SiteTransferService $transfer): int
    {
        $path = (string) $this->argument('file');
        $email = (string) $this->option('user');

        if ($email === '') {
            $this->error('Provide --user=email to assign imported sites.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("User {$email} was not found.");

            return self::FAILURE;
        }

        if (! File::isFile($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        try {
            $sites = $transfer->importFile($path, $user);
        } catch (ValidationException $exception) {
            $this->error($exception->validator->errors()->first() ?: 'Import failed.');

            return self::FAILURE;
        }

        $count = $sites->count();
        $this->info($count === 1 ? 'Imported 1 site.' : "Imported {$count} sites.");

        return self::SUCCESS;
    }
}
