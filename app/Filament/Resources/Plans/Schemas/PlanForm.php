<?php

declare(strict_types=1);

namespace App\Filament\Resources\Plans\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.plans.model'))
                    ->description(__('admin.plans.description'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('admin.plans.name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->label(__('admin.plans.slug'))
                            ->helperText(__('admin.plans.slug_help'))
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('max_sites')
                            ->label(__('admin.plans.max_sites'))
                            ->numeric()
                            ->required()
                            ->minValue(0),
                        TextInput::make('max_addresses_per_site')
                            ->label(__('admin.plans.max_addresses'))
                            ->numeric()
                            ->required()
                            ->minValue(0),
                        Toggle::make('is_default')
                            ->label(__('admin.plans.is_default'))
                            ->helperText(__('admin.plans.is_default_help')),
                        Toggle::make('is_active')
                            ->label(__('admin.plans.is_active'))
                            ->default(true),
                    ]),
            ]);
    }
}
