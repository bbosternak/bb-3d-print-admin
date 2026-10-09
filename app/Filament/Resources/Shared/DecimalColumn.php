<?php

namespace App\Filament\Resources\Shared;

use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DecimalColumn
{
    public static function make(string $field): TextColumn
    {
        return TextColumn::make($field)->sortable(query: fn (Builder $query, string $direction): Builder => DecimalSort::apply(
            $query, $direction, fn (Model $record): string => (string) $record->getAttribute($field),
        ));
    }
}
