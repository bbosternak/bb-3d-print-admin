<?php

namespace App\Filament\Resources\Products\Tables;

use App\Filament\Resources\Shared\DecimalColumn;
use App\Models\Filament;
use App\Models\Product;
use App\Services\ProductService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')->disk('local')->visibility('private'),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('sku')->searchable()->sortable()->toggleable(),
                IconColumn::make('active')->boolean()->sortable(),
                TextColumn::make('filaments.name')->label('Filaments')->listWithLineBreaks(),
                TextColumn::make('batch_quantity')->label('Batch qty')->sortable(),
                DecimalColumn::make('batch_weight')->suffix(' g'),
                TextColumn::make('batch_seconds')->label('Batch time (seconds)')->numeric()->sortable(),
                DecimalColumn::make('additional_material_cost')->money('EUR')->toggleable(isToggledHiddenByDefault: true),
                ...array_map(fn (string $key): TextColumn => TextColumn::make($key)
                    ->state(fn (Product $record): string => ProductCosts::value($record, $key))
                    ->numeric(decimalPlaces: 2)->suffix($key === 'processing_minutes' ? ' min/item' : ' h/item')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => ProductCosts::sort($query, $key, $direction))
                    ->toggleable(isToggledHiddenByDefault: true), ['printing_hours', 'processing_hours', 'processing_minutes']),
                ...array_map(fn (string $key): TextColumn => TextColumn::make($key)
                    ->state(fn (Product $record): string => ProductCosts::value($record, $key))
                    ->money('EUR')
                    ->color(fn (string $state): string => (float) $state < 0 ? 'danger' : 'success')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => ProductCosts::sort($query, $key, $direction))
                    ->toggleable(isToggledHiddenByDefault: ! in_array($key, ['manufacturing_cost', 'total_cost', 'profit', 'profit_per_printing_hour'])),
                    ['filament_cost', 'electricity_cost', 'depreciation_cost', 'labor_cost', 'packaging_cost', 'manufacturing_cost', 'platform_fee', 'total_cost', 'profit', 'profit_per_printing_hour', 'profit_per_total_hour']),
                DecimalColumn::make('selling_price')->money('EUR'),
                TextColumn::make('margin')->label('Margin')->suffix('%')
                    ->state(fn (Product $record): string => ProductCosts::value($record, 'margin'))
                    ->color(fn (string $state): string => (float) $state < 0 ? 'danger' : 'success')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => ProductCosts::sort($query, 'margin', $direction)),
            ])
            ->filters([
                TernaryFilter::make('active'),
                SelectFilter::make('material')->options(fn (): array => Filament::query()->distinct()->orderBy('material')->pluck('material', 'material')->all())
                    ->searchable()->query(fn (Builder $query, array $data): Builder => $query->when($data['value'] ?? null,
                        fn (Builder $query, string $material): Builder => $query->whereHas('usages.filament', fn (Builder $query): Builder => $query->where('material', $material)))),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('duplicate')->requiresConfirmation()->action(fn (Product $record): Product => app(ProductService::class)->duplicate($record)),
                DeleteAction::make()->visible(fn (Product $record): bool => ! $record->sales()->exists()),
            ])
            ->defaultSort('name')
            ->paginated([10, 25, 50, 100]);
    }
}
