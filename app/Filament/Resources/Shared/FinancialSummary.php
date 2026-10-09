<?php

namespace App\Filament\Resources\Shared;

use App\Models\Expense;
use App\Models\Sale;
use App\Services\FinancialSummary as SummaryService;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Builder;

class FinancialSummary
{
    private const SALES_CACHE_KEY = 'filament.sales-financial-summaries';

    public static function expenses(Builder $filtered): string
    {
        $service = app(SummaryService::class);
        $selected = $service->summarizeQueries(Sale::query()->whereRaw('1 = 0'), $filtered);
        $total = Decimal::money($selected['expenses']);
        $all = Decimal::money($service->aggregateExpenses(Expense::query()));
        $categories = collect($selected['categories'])->map(fn (string $amount, string $category): string => $category.': '.Decimal::money($amount))->implode(' · ');

        return "Selected period / filters: {$total} · All-time expenses: {$all}".($categories === '' ? '' : ' · Selected categories — '.$categories);
    }

    public static function sales(Builder $filtered): string
    {
        $request = request();
        $cache = $request->attributes->get(self::SALES_CACHE_KEY, []);
        $key = hash('sha256', $filtered->toSql().serialize($filtered->getBindings()));
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $service = app(SummaryService::class);
        $selected = $service->summarizeQueries($filtered, Expense::query()->whereRaw('1 = 0'));
        $all = Decimal::money($service->aggregateRevenue(Sale::query()));

        $cache[$key] = 'Selected period / filters revenue: '.Decimal::money($selected['revenue']).' · All-time revenue: '.$all
            .' · Current estimates for selected sales: printing '.Decimal::round($selected['printing_hours'], 2).' h, processing '.Decimal::round($selected['processing_hours'], 2).' h, electricity '.Decimal::money($selected['electricity_cost'])
            .' (based on current products and settings, not historical costs).';
        $request->attributes->set(self::SALES_CACHE_KEY, $cache);

        return $cache[$key];
    }
}
