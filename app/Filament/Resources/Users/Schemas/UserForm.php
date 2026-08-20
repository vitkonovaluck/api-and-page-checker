<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\SocialProvider;
use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.users.model'))
                    ->description(__('admin.users.description'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('admin.users.name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label(__('admin.users.email'))
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('password')
                            ->label(__('admin.users.password'))
                            ->password()
                            ->revealable()
                            ->helperText(__('admin.users.password_help'))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                        Select::make('role')
                            ->label(__('admin.users.role'))
                            ->options(UserRole::options())
                            ->required()
                            ->default(UserRole::User->value),
                        Select::make('plan_id')
                            ->label(__('admin.users.plan'))
                            ->relationship('plan', 'name')
                            ->required()
                            ->default(fn (): mixed => Plan::query()->where('is_default', true)->value('id')),
                        TextEntry::make('social_providers')
                            ->label(__('admin.users.social_providers'))
                            ->state(function (?User $record): string {
                                if ($record === null) {
                                    return __('admin.users.none');
                                }

                                $labels = $record->socialAccounts
                                    ->map(fn ($account): string => $account->provider instanceof SocialProvider
                                        ? $account->provider->label()
                                        : (string) $account->provider)
                                    ->all();

                                return $labels === [] ? __('admin.users.none') : implode(', ', $labels);
                            }),
                    ]),
            ]);
    }
}
