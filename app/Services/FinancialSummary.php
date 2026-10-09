<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Sale;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FinancialSummary
{
    public function summarize(?string $from = null, ?string $until = null, ?int $productId = null): array
    {
        Validator::make(
            ['from' => $from, 'until' => $until, 'product_id' => $productId],
            [
                'from' => ['nullable', 'date_format:Y-m-d'],
                'until' => ['nullable', 'date_format:Y-m-d'],
                'product_id' => ['nullable', 'integer', 'exists:products,id'],
            ],
        )->validate();

        if ($from !== null && $until !== null && $until < $from) {
            throw ValidationException::withMessages(['until' => 'The through date must be on or after the from date.']);
        }

        $sales = Sale::query()
            ->when($from, fn ($query) => $query->whereDate('sale_date', '>=', $from))
            ->when($until, fn ($query) => $query->whereDate('sale_date', '<=', $until))
            ->when($productId, fn ($query) => $query->where('product_id', $productId));
        $expenses = Expense::query()
            ->when($from, fn ($query) => $query->whereDate('date', '>=', $from))
            ->when($until, fn ($query) => $query->whereDate('date', '<=', $until));

        return $this->summarizeQueries($sales, $expenses);
    }

    public function summarizeQueries(Builder $sales, Builder $expenses): array
    {
        $sales = (clone $sales)->with('product.usages.filament')->get();
        $expenses = (clone $expenses)->get();
        $revenue = $spent = $printingHours = $processingHours = $electricity = '0';
        $units = 0;
        $categories = array_fill_keys(Expense::CATEGORIES, '0');
        $periods = $products = [];
        $calculator = app(CostCalculator::class);
        $costs = [];

        foreach ($sales as $sale) {
            $total = $sale->revenue;
            $revenue = Decimal::add($revenue, $total);
            $units += $sale->quantity;
            $month = $sale->sale_date->format('Y-m');
            $periods[$month] ??= ['revenue' => '0', 'expenses' => '0'];
            $periods[$month]['revenue'] = Decimal::add($periods[$month]['revenue'], $total);

            $cost = $costs[$sale->product_id] ??= $calculator->calculate($sale->product);
            $hours = Decimal::mul($cost['printing_hours'], (string) $sale->quantity);
            $processing = Decimal::mul($cost['processing_hours'], (string) $sale->quantity);
            $energy = Decimal::mul($cost['electricity_cost'], (string) $sale->quantity);
            $printingHours = Decimal::add($printingHours, $hours);
            $processingHours = Decimal::add($processingHours, $processing);
            $electricity = Decimal::add($electricity, $energy);
            $products[$sale->product_id] ??= [
                'name' => $sale->product->name,
                'units' => 0,
                'revenue' => '0',
                'printing_hours' => '0',
                'electricity_cost' => '0',
            ];
            $products[$sale->product_id]['units'] += $sale->quantity;
            foreach (['revenue' => $total, 'printing_hours' => $hours, 'electricity_cost' => $energy] as $key => $value) {
                $products[$sale->product_id][$key] = Decimal::add($products[$sale->product_id][$key], $value);
            }
        }

        foreach ($expenses as $expense) {
            $spent = Decimal::add($spent, $expense->amount);
            $categories[$expense->category] = Decimal::add($categories[$expense->category], $expense->amount);
            $month = $expense->date->format('Y-m');
            $periods[$month] ??= ['revenue' => '0', 'expenses' => '0'];
            $periods[$month]['expenses'] = Decimal::add($periods[$month]['expenses'], $expense->amount);
        }

        ksort($periods);
        uasort($products, fn (array $a, array $b): int => $b['units'] <=> $a['units']);

        return [
            'revenue' => $revenue,
            'expenses' => $spent,
            'cash_flow' => Decimal::sub($revenue, $spent),
            'units' => $units,
            'average_price' => $units > 0 ? Decimal::div($revenue, (string) $units) : '0',
            'printing_hours' => $printingHours,
            'processing_hours' => $processingHours,
            'electricity_cost' => $electricity,
            'categories' => $categories,
            'periods' => $periods,
            'products' => $products,
        ];
    }

    public function aggregateExpenses(Builder|QueryBuilder $query): string
    {
        return array_reduce((clone $query)->pluck('amount')->all(),
            fn (string $sum, mixed $amount): string => Decimal::add($sum, (string) $amount), '0');
    }

    public function aggregateRevenue(Builder|QueryBuilder $query): string
    {
        $total = '0';
        foreach ((clone $query)->get(['unit_price', 'quantity']) as $sale) {
            $revenue = $sale instanceof Sale ? $sale->revenue : Decimal::mul((string) $sale->unit_price, (string) $sale->quantity);
            $total = Decimal::add($total, $revenue);
        }

        return $total;
    }
}
