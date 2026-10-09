<?php

namespace App\Filament\Resources\Filaments\Tables;

use App\Filament\Resources\Shared\DecimalColumn;
use App\Models\Filament;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FilamentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('brand')->searchable()->sortable(),
                TextColumn::make('material')->searchable()->sortable(),
                TextColumn::make('color')->searchable()->sortable(),
                DecimalColumn::make('purchase_price')->label('Spool price')->money('EUR'),
                DecimalColumn::make('spool_weight')->suffix(' g'),
                TextColumn::make('price_per_kg')->label('Current €/kg')->money('EUR')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw("purchase_price * 1000.0 / NULLIF(spool_weight, 0) {$direction}")),
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
