<?php

namespace App\Filament\Resources\Filaments\Tables;

use App\Filament\Resources\Shared\DecimalColumn;
use App\Models\Filament;
use App\Support\Decimal;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FilamentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('brand')->searchable()->sortable(),
                TextColumn::make('material')->searchable()->sortable(),
                TextColumn::make('color')->searchable()->sortable(),
                DecimalColumn::make('purchase_price')->label('Spool price')->formatStateUsing(fn (string $state): string => Decimal::money($state)),
                DecimalColumn::make('spool_weight')->suffix(' g'),
                DecimalColumn::make('price_per_kg')->label('Current €/kg')->formatStateUsing(fn (string $state): string => Decimal::money($state)),
            ])
            ->filters([
                ...array_map(fn (string $field): SelectFilter => SelectFilter::make($field)
                    ->options(fn (): array => Filament::query()->distinct()->orderBy($field)->pluck($field, $field)->all())
                    ->searchable(), ['brand', 'material', 'color']),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->visible(fn (Filament $record): bool => ! $record->usages()->exists()),
            ])
            ->toolbarActions([
            ])
            ->defaultSort('brand')
            ->paginated([10, 25, 50, 100]);
    }
}
