<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Sale;
use App\Support\Decimal;
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

        $sales = Sale::query()->with('product.usages.filament')
            ->when($from, fn ($query) => $query->whereDate('sale_date', '>=', $from))
            ->when($until, fn ($query) => $query->whereDate('sale_date', '<=', $until))
            ->when($productId, fn ($query) => $query->where('product_id', $productId))->get();
        $expenses = Expense::query()
            ->when($from, fn ($query) => $query->whereDate('date', '>=', $from))
            ->when($until, fn ($query) => $query->whereDate('date', '<=', $until))->get();

        $revenue = $spent = $printingHours = $electricity = '0';
        $units = 0;
        $categories = array_fill_keys(Expense::CATEGORIES, '0');
        $periods = $products = [];
        $calculator = app(CostCalculator::class);
        $costs = [];

        foreach ($sales as $sale) {
            $total = Decimal::mul($sale->unit_price, (string) $sale->quantity);
            $revenue = Decimal::add($revenue, $total);
            $units += $sale->quantity;
            $month = $sale->sale_date->format('Y-m');
            $periods[$month] ??= ['revenue' => '0', 'expenses' => '0'];
            $periods[$month]['revenue'] = Decimal::add($periods[$month]['revenue'], $total);

            $cost = $costs[$sale->product_id] ??= $calculator->calculate($sale->product);
            $hours = Decimal::mul($cost['printing_hours'], (string) $sale->quantity);
            $energy = Decimal::mul($cost['electricity_cost'], (string) $sale->quantity);
            $printingHours = Decimal::add($printingHours, $hours);
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
            'electricity_cost' => $electricity,
            'categories' => $categories,
            'periods' => $periods,
            'products' => $products,
        ];
    }
}
