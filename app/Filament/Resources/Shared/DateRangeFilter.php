<?php

namespace App\Filament\Resources\Shared;

use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class DateRangeFilter
{
    public static function make(string $column): Filter
    {
        return Filter::make('period')->label('Date range')->schema([
            DatePicker::make('from')->label('From'),
            DatePicker::make('until')->label('Until')->afterOrEqual('from'),
        ])->query(fn (Builder $query, array $data): Builder => $query
            ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate($column, '>=', $date))
            ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate($column, '<=', $date)))
            ->indicateUsing(fn (array $data): array => array_filter([
                filled($data['from'] ?? null) ? 'From '.$data['from'] : null,
                filled($data['until'] ?? null) ? 'Until '.$data['until'] : null,
            ]));
    }
}
