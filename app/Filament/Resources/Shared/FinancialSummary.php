<?php

namespace App\Filament\Resources\Shared;

use App\Models\Expense;
use App\Models\Sale;
use App\Services\CostCalculator;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Builder;

class FinancialSummary
{
    public static function expenses(Builder $filtered): string
    {
        $selected = (clone $filtered)->get();
        $total = self::sum($selected->pluck('amount')->all());
        $all = self::sum(Expense::query()->pluck('amount')->all());
        $categories = $selected->groupBy('category')->map(fn ($expenses, string $category): string => $category.': '.self::sum($expenses->pluck('amount')->all()))->implode(' · ');

        return "Selected period / filters: {$total} · All-time expenses: {$all}".($categories === '' ? '' : ' · Selected categories — '.$categories);
    }

    public static function sales(Builder $filtered): string
    {
        $selected = (clone $filtered)->with('product.usages.filament')->get();
        $revenue = '0';
        $printing = '0';
        $processing = '0';
        $electricity = '0';
        foreach ($selected as $sale) {
            $revenue = Decimal::add($revenue, $sale->revenue);
            if (! $sale->product) {
                continue;
            }
            $cost = app(CostCalculator::class)->calculate($sale->product);
            $printing = Decimal::add($printing, Decimal::mul($cost['printing_hours'], (string) $sale->quantity));
            $processing = Decimal::add($processing, Decimal::mul($cost['processing_hours'], (string) $sale->quantity));
            $electricity = Decimal::add($electricity, Decimal::mul($cost['electricity_cost'], (string) $sale->quantity));
        }
        $all = self::sum(Sale::query()->get()->pluck('revenue')->all());

        return 'Selected period / filters revenue: '.Decimal::money($revenue).' · All-time revenue: '.$all
            .' · Current estimates for selected sales: printing '.Decimal::round($printing, 2).' h, processing '.Decimal::round($processing, 2).' h, electricity '.Decimal::money($electricity)
            .' (based on current products and settings, not historical costs).';
    }

    private static function sum(array $amounts): string
    {
        return Decimal::money(array_reduce($amounts, fn (string $sum, mixed $amount): string => Decimal::add($sum, (string) $amount), '0'));
    }
}
