<?php

namespace App\Filament\Resources\Shared;

use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class DecimalColumn
{
    public static function make(string $field): TextColumn
    {
        return TextColumn::make($field)->sortable(query: fn (Builder $query, string $direction): Builder => $query
            ->orderByRaw('CAST('.$query->getModel()->qualifyColumn($field).' AS DECIMAL(36, 12)) '.($direction === 'desc' ? 'desc' : 'asc')));
    }
}
