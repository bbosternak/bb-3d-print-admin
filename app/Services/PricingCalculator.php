<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Setting;
use App\Support\Decimal as D;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PricingCalculator
{
    public function __construct(private readonly CostCalculator $calculator) {}

    public function calculate(Product $product, string $mode, string $target, string $increment = '0.10'): array
    {
        Validator::make(compact('mode', 'target', 'increment'), [
            'mode' => ['required', Rule::in(['profit', 'margin', 'printing_hour', 'total_hour'])],
            'target' => ['required', D::rule()],
            'increment' => ['required', D::rule(true)],
        ])->validate();
        $costs = $this->calculator->calculate($product);
        $current = $product->exists ? $product->fresh() : $product;
        if ($product->exists) {
            $current->fill($product->getDirty());
        }
        $fee = (string) ($current->fee_percentage_override ?? Setting::current()->fee_percentage);
        $denominator = D::sub('1', D::div($fee, '100'));
        $profit = $target;
        if ($mode === 'margin') {
            $denominator = D::sub($denominator, D::div($target, '100'));
            $profit = '0';
        } elseif ($mode === 'printing_hour' || $mode === 'total_hour') {
            $hours = $mode === 'printing_hour'
                ? $costs['printing_hours']
                : D::add($costs['printing_hours'], $costs['processing_hours']);
            if (D::compare($hours, '0') <= 0) {
                throw ValidationException::withMessages(['target' => 'The selected hourly target requires positive time.']);
            }
            $profit = D::mul($target, $hours);
        }
        if (D::compare($denominator, '0') <= 0) {
            throw ValidationException::withMessages(['target' => 'The fee and target leave no positive price denominator.']);
        }
        $numerator = D::add($costs['manufacturing_cost'], $profit);
        $exact = D::div($numerator, $denominator);
        $rounded = D::ceilTo($exact, $increment);
        // Compare before division so truncated repeating quotients never round down.
        if (D::compare(D::mul($rounded, $denominator), $numerator) < 0) {
            $rounded = D::add($rounded, $increment);
        }
        if ($mode === 'margin' && D::compare($rounded, '0') === 0 && D::compare($target, '0') > 0) {
            $rounded = $increment;
        }
        Validator::make(['rounded_price' => D::trim($rounded)], ['rounded_price' => ['required', D::rule()]])->validate();
        $priced = clone $product;
        $priced->selling_price = D::trim($rounded);

        return [
            'exact_price' => D::trim($exact),
            'rounded_price' => D::trim($rounded),
            'costs' => $this->calculator->calculate($priced),
        ];
    }
}
