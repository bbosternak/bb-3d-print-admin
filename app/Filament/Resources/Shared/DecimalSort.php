<?php

namespace App\Filament\Resources\Shared;

use App\Support\Decimal;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DecimalSort
{
    public static function apply(Builder $query, string $direction, Closure $value): Builder
    {
        // Rank authoritative decimal values before SQL pagination, including SQLite text decimals.
        $values = (clone $query)->reorder()->get()
            ->mapWithKeys(fn (Model $record): array => [$record->getKey() => (string) $value($record)])->all();
        uasort($values, fn (string $left, string $right): int => Decimal::compare($left, $right));

        if ($values === []) {
            return $query;
        }

        $cases = [];
        foreach (array_keys($values) as $rank => $id) {
            $cases[] = 'WHEN '.(int) $id.' THEN '.$rank;
        }

        $key = $query->getModel()->getQualifiedKeyName();

        return $query->orderByRaw('CASE '.$key.' '.implode(' ', $cases).' END '.($direction === 'desc' ? 'desc' : 'asc'))->orderBy($key);
    }
}
