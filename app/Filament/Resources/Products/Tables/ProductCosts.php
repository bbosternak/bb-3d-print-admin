<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Product;
use App\Services\CostCalculator;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Builder;

class ProductCosts
{
    public static function value(Product $product, string $key): string
    {
        return app(CostCalculator::class)->calculate($product)[$key];
    }

    public static function sort(Builder $query, string $key, string $direction): Builder
    {
        // Rank with the authoritative decimal calculator before SQL pagination.
        $values = (clone $query)->reorder()->with('usages.filament')->get()
            ->mapWithKeys(fn (Product $product): array => [$product->getKey() => self::value($product, $key)])->all();
        uasort($values, fn (string $left, string $right): int => Decimal::compare($left, $right));

        if ($values === []) {
            return $query;
        }

        $cases = [];
        foreach (array_keys($values) as $rank => $id) {
            $cases[] = 'WHEN '.(int) $id.' THEN '.$rank;
        }

        return $query->orderByRaw('CASE products.id '.implode(' ', $cases).' END '.($direction === 'desc' ? 'desc' : 'asc'))->orderBy('products.id');
    }
}
