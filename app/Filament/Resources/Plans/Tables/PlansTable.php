<?php

declare(strict_types=1);

namespace App\Filament\Resources\Plans\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class PlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('admin.plans.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label(__('admin.plans.slug'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('max_sites')
                    ->label(__('admin.plans.max_sites'))
                    ->sortable(),
                TextColumn::make('max_addresses_per_site')
                    ->label(__('admin.plans.max_addresses'))
                    ->sortable(),
                IconColumn::make('is_default')
                    ->label(__('admin.plans.is_default'))
                    ->boolean()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('admin.plans.is_active'))
                    ->boolean()
                    ->sortable(),
                TextColumn::make('users_count')
                    ->label(__('admin.plans.users_count'))
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_default')
                    ->label(__('admin.plans.is_default')),
                TernaryFilter::make('is_active')
                    ->label(__('admin.plans.is_active')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
