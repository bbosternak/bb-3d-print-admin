<?php

namespace App\Filament\Resources\Expenses\Tables;

use App\Filament\Resources\Shared\DateRangeFilter;
use App\Filament\Resources\Shared\DecimalColumn;
use App\Filament\Resources\Shared\FinancialSummary;
use App\Models\Expense;
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

class ExpensesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->description(fn ($livewire): string => FinancialSummary::expenses($livewire->getFilteredTableQuery()))
            ->columns([
                TextColumn::make('date')->date()->sortable(),
                TextColumn::make('description')->searchable()->sortable(),
                TextColumn::make('category')->searchable()->sortable(),
                DecimalColumn::make('amount')->money('EUR')->summarize(Summarizer::make('total')->money('EUR')->label('Filtered total')
                    ->using(fn (QueryBuilder $query): string => array_reduce($query->pluck('amount')->all(),
                        fn (string $sum, mixed $amount): string => Decimal::add($sum, (string) $amount), '0'))),
                TextColumn::make('notes')->searchable()->limit(50)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                DateRangeFilter::make('date'),
                SelectFilter::make('category')->options(array_combine(Expense::CATEGORIES, Expense::CATEGORIES))->searchable(),
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
            ->defaultSort('date', 'desc')
            ->paginated([10, 25, 50, 100]);
    }
}
