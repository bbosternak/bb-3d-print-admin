<?php

namespace App\Services;

use App\Models\Filament;
use App\Models\Product;
use App\Models\Setting;
use App\Support\Decimal as D;

class CostCalculator
{
    public function calculate(Product $product): array
    {
        if ($product->exists) {
            $dirty = $product->getDirty();
            $product = $product->fresh(['usages']) ?? $product;
            $product->fill($dirty);
        }
        $settings = Setting::current();
        $values = [];
        foreach (array_keys(Setting::financialRules()) as $field) {
            $values[$field] = (string) ($product->{$field.'_override'} ?? $settings->{$field});
        }
        $quantity = (string) $product->batch_quantity;
        $printingSeconds = D::div((string) $product->batch_seconds, $quantity);
        $printingHours = D::div($printingSeconds, '3600');
        $processingHours = D::div($values['processing_minutes'], '60');
        $filamentCost = '0';
        foreach ($product->usages as $usage) {
            $filament = Filament::query()->findOrFail($usage->filament_id);
            $filamentCost = D::add($filamentCost, D::mul($usage->grams, D::div($filament->purchase_price, $filament->spool_weight)));
        }
        $costs = [
            'filament_cost' => D::div($filamentCost, $quantity),
            'electricity_cost' => D::mul(D::mul($printingHours, D::div($values['printer_power'], '1000')), $values['electricity_price']),
            'depreciation_cost' => D::mul($printingHours, $values['depreciation_rate']),
            'labor_cost' => D::mul($processingHours, $values['labor_rate']),
            'additional_material_cost' => $product->additional_material_cost,
            'packaging_cost' => $values['packaging_cost'],
        ];
        $manufacturing = '0';
        foreach ($costs as $cost) {
            $manufacturing = D::add($manufacturing, $cost);
        }
        $price = $product->selling_price;
        $fee = D::mul($price, D::div($values['fee_percentage'], '100'));
        $total = D::add($manufacturing, $fee);
        $profit = D::sub($price, $total);

        return array_map(fn (string $value): string => D::trim($value), [
            ...$costs,
            'manufacturing_cost' => $manufacturing,
            'platform_fee' => $fee,
            'total_cost' => $total,
            'selling_price' => $price,
            'profit' => $profit,
            'margin' => $this->ratio(D::mul($profit, '100'), $price),
            'profit_per_printing_hour' => $this->ratio($profit, $printingHours),
            'profit_per_total_hour' => $this->ratio($profit, D::add($printingHours, $processingHours)),
            'printing_hours' => $printingHours,
            'processing_hours' => $processingHours,
            'filament_grams' => D::div($product->batch_weight, $quantity),
            'printing_seconds' => $printingSeconds,
            'processing_minutes' => $values['processing_minutes'],
        ]);
    }

    private function ratio(string $numerator, string $denominator): string
    {
        return D::compare($denominator, '0') === 0 ? '0' : D::div($numerator, $denominator);
    }
}
