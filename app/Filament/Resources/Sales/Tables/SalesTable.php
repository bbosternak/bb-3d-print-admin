<?php

namespace App\Filament\Resources\Sales\Tables;

use App\Filament\Resources\Shared\DateRangeFilter;
use App\Filament\Resources\Shared\DecimalColumn;
use App\Filament\Resources\Shared\FinancialSummary;
use App\Services\FinancialSummary as SummaryService;
use App\Support\Decimal;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Query\Builder as QueryBuilder;

class SalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->description(fn ($livewire): string => FinancialSummary::sales($livewire->getFilteredTableQuery()))
            ->columns([
                TextColumn::make('sale_date')->date()->sortable(),
                TextColumn::make('product.name')->searchable()->sortable(),
                TextColumn::make('quantity')->sortable(),
                DecimalColumn::make('unit_price')->formatStateUsing(fn (string $state): string => Decimal::money($state)),
                DecimalColumn::make('revenue')->formatStateUsing(fn (string $state): string => Decimal::money($state))
                    ->summarize(Summarizer::make('total')->label('Revenue')->formatStateUsing(fn (string $state): string => Decimal::money($state))
                        ->using(fn (QueryBuilder $query): string => app(SummaryService::class)->aggregateRevenue($query))),
                TextColumn::make('notes')->searchable()->limit(50)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                DateRangeFilter::make('sale_date'),
                SelectFilter::make('product_id')->label('Product')->relationship('product', 'name')->searchable()->preload(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sale_date', 'desc')
            ->paginated([10, 25, 50, 100]);
    }
}
