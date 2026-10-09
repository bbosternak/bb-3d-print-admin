<?php

namespace App\Filament\Resources\Products\Tables;

use App\Filament\Resources\Shared\DecimalSort;
use App\Models\Product;
use App\Services\CostCalculator;
use Illuminate\Database\Eloquent\Builder;

class ProductCosts
{
    private const CACHE_KEY = 'filament.product-cost-breakdowns';

    public static function value(Product $product, string $key): string
    {
        $request = request();
        $cache = $request->attributes->get(self::CACHE_KEY, []);
        $id = $product->getKey() ?? 'transient:'.spl_object_id($product);

        if (! array_key_exists($id, $cache)) {
            $cache[$id] = app(CostCalculator::class)->calculate($product);
            $request->attributes->set(self::CACHE_KEY, $cache);
        }

        return $cache[$id][$key];
    }

    public static function sort(Builder $query, string $key, string $direction): Builder
    {
        return DecimalSort::apply($query->with('usages.filament'), $direction, fn (Product $product): string => self::value($product, $key));
    }
}
