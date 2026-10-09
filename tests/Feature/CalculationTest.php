<?php

namespace Tests\Feature;

use App\Models\Filament;
use App\Models\Product;
use App\Models\Setting;
use App\Services\CostCalculator;
use App\Services\PricingCalculator;
use App\Services\ProductService;
use App\Support\Decimal as D;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CalculationTest extends TestCase
{
    use RefreshDatabase;

    private function filament(string $price = '11.90'): Filament
    {
        return Filament::create(['brand' => 'Test', 'material' => 'PLA', 'color' => 'Blue', 'purchase_price' => $price, 'spool_weight' => '1000']);
    }

    private function reference(array $overrides = []): Product
    {
        $filament = $this->filament();

        return app(ProductService::class)->save([
            'name' => 'Reference', 'batch_weight' => '97', 'batch_seconds' => 32400,
            'selling_price' => '12',
            'usages' => [['filament_id' => $filament->id, 'grams' => '97']],
            ...$overrides,
        ]);
    }

    private function assertDecimal(string $expected, string $actual): void
    {
        $this->assertSame(0, D::compare($expected, $actual), "{$expected} != {$actual}");
    }

    private function assertInvalid(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    public function test_reference_costs_are_exact_without_intermediate_money_rounding(): void
    {
        $product = $this->reference();
        $costs = app(CostCalculator::class)->calculate($product);
        foreach ([
            'filament_cost' => '1.1543', 'electricity_cost' => '0.2295',
            'depreciation_cost' => '2.25', 'labor_cost' => '2.5',
            'manufacturing_cost' => '6.1338', 'total_cost' => '6.1338',
            'platform_fee' => '0', 'profit' => '5.8662', 'margin' => '48.885',
            'printing_hours' => '9', 'processing_hours' => '0.25',
            'filament_grams' => '97', 'printing_seconds' => '32400',
            'processing_minutes' => '15', 'selling_price' => '12',
            'profit_per_printing_hour' => D::div('5.8662', '9'),
            'profit_per_total_hour' => D::div('5.8662', '9.25'),
        ] as $field => $value) {
            $this->assertIsString($costs[$field]);
            $this->assertDecimal($value, $costs[$field]);
        }
        $product->update(['selling_price' => '20', 'fee_percentage_override' => '10', 'additional_material_cost' => '0.5', 'packaging_cost_override' => '1.25']);
        $costs = app(CostCalculator::class)->calculate($product);
        $this->assertDecimal('7.8838', $costs['manufacturing_cost']);
        $this->assertDecimal('2', $costs['platform_fee']);
        $this->assertDecimal('9.8838', $costs['total_cost']);
        $this->assertDecimal('10.1162', $costs['profit']);
    }

    public function test_multiple_filaments_and_batch_costs_allocate_print_time_but_not_per_piece_processing(): void
    {
        $first = $this->filament('11.90');
        $second = $this->filament('20');
        $product = app(ProductService::class)->save([
            'name' => 'Batch', 'batch_quantity' => 5, 'batch_weight' => '150', 'batch_seconds' => 39600,
            'selling_price' => '8', 'additional_material_cost' => '0.4', 'packaging_cost_override' => '0.2',
            'usages' => [
                ['filament_id' => $first->id, 'grams' => '100'],
                ['filament_id' => $second->id, 'grams' => '50'],
            ],
        ]);
        $costs = app(CostCalculator::class)->calculate($product);
        foreach ([
            'filament_cost' => '0.438', 'electricity_cost' => '0.0561',
            'depreciation_cost' => '0.55', 'labor_cost' => '2.5',
            'manufacturing_cost' => '4.1441', 'printing_hours' => '2.2',
            'printing_seconds' => '7920', 'filament_grams' => '30', 'processing_hours' => '0.25',
        ] as $field => $value) {
            $this->assertDecimal($value, $costs[$field]);
        }
    }

    public function test_current_prices_settings_and_product_edits_apply_even_to_cached_models(): void
    {
        $product = $this->reference()->load('usages.filament');
        $filament = $product->usages->first()->filament;
        $this->assertNull($product->labor_rate_override);
        Setting::current()->update(['labor_rate' => '20', 'electricity_price' => '0.20']);
        $filament->update(['purchase_price' => '20']);
        Product::findOrFail($product->id)->update(['additional_material_cost' => '0.1', 'selling_price' => '30']);
        $costs = app(CostCalculator::class)->calculate($product);
        $this->assertDecimal('1.94', $costs['filament_cost']);
        $this->assertDecimal('0.27', $costs['electricity_cost']);
        $this->assertDecimal('5', $costs['labor_cost']);
        $this->assertDecimal('0.1', $costs['additional_material_cost']);
        $this->assertDecimal('30', $costs['selling_price']);
        $current = $product->fresh();
        foreach (array_keys(Setting::financialRules()) as $field) {
            $current->{$field.'_override'} = '0';
        }
        $current->save();
        $costs = app(CostCalculator::class)->calculate($product);
        foreach (['electricity_cost', 'depreciation_cost', 'labor_cost', 'packaging_cost', 'platform_fee', 'processing_hours'] as $field) {
            $this->assertSame('0', $costs[$field]);
        }
        $current->update(['labor_rate_override' => null, 'processing_minutes_override' => null]);
        $this->assertDecimal('5', app(CostCalculator::class)->calculate($product)['labor_cost']);
    }

    public function test_simulated_values_survive_refreshes_and_use_current_prices(): void
    {
        $product = $this->reference();
        $simulation = app(ProductService::class)->simulate([
            'batch_weight' => '50', 'batch_seconds' => 3600, 'batch_quantity' => 2,
            'selling_price' => '7', 'labor_rate_override' => '0',
            'usages' => [['filament_id' => $product->usages->first()->filament_id, 'grams' => '50']],
        ], $product);
        $product->usages->first()->filament->update(['purchase_price' => '20']);
        $costs = app(CostCalculator::class)->calculate($simulation);
        $this->assertDecimal('0.5', $costs['filament_cost']);
        $this->assertDecimal('0.5', $costs['printing_hours']);
        $this->assertDecimal('7', $costs['selling_price']);
        $this->assertDecimal('0', $costs['labor_cost']);
        $this->assertSame('97.000000000000', $product->fresh()->batch_weight);
    }

    public function test_pricing_modes_include_platform_fees_and_round_up_to_arbitrary_increments(): void
    {
        $product = $this->reference(['fee_percentage_override' => '10']);
        $calculator = app(PricingCalculator::class);
        foreach ([
            ['profit', '5', '12.4', 'profit'],
            ['margin', '30', '10.3', 'margin'],
            ['printing_hour', '2', '26.9', 'profit_per_printing_hour'],
            ['total_hour', '2', '27.4', 'profit_per_total_hour'],
        ] as [$mode, $target, $expected, $metric]) {
            $result = $calculator->calculate($product, $mode, $target);
            $this->assertDecimal($expected, $result['rounded_price']);
            $this->assertGreaterThanOrEqual(0, D::compare($result['costs'][$metric], $target));
            $this->assertDecimal($expected, $result['costs']['selling_price']);
            $this->assertGreaterThanOrEqual(0, D::compare($result['rounded_price'], $result['exact_price']));
        }
        $result = $calculator->calculate($product, 'profit', '5', '0.25');
        $this->assertDecimal('12.5', $result['rounded_price']);
        $this->assertDecimal('12', $product->fresh()->selling_price);
    }

    public function test_pricing_precision_near_increment_boundary_never_rounds_down(): void
    {
        $product = app(ProductService::class)->simulate(['labor_rate_override' => '0', 'additional_material_cost' => '0.100000000001']);
        $result = app(PricingCalculator::class)->calculate($product, 'profit', '0', '0.1');
        $this->assertDecimal('0.2', $result['rounded_price']);
        $product->additional_material_cost = '0.1';
        $result = app(PricingCalculator::class)->calculate($product, 'profit', '0', '0.1');
        $this->assertDecimal('0.1', $result['rounded_price']);
        $product->fee_percentage_override = '33.333333333333';
        $result = app(PricingCalculator::class)->calculate($product, 'profit', '0.000000000001', '0.000000000001');
        $this->assertGreaterThanOrEqual(0, D::compare($result['costs']['profit'], '0.000000000001'));
        $this->assertSame(0, bccomp(bcmod($result['rounded_price'], '0.000000000001', D::SCALE), '0', D::SCALE));
    }

    public function test_zero_edges_and_impossible_targets_raise_clear_validation_errors(): void
    {
        $product = app(ProductService::class)->simulate(['processing_minutes_override' => '0']);
        $costs = app(CostCalculator::class)->calculate($product);
        foreach (['margin', 'profit_per_printing_hour', 'profit_per_total_hour', 'manufacturing_cost'] as $field) {
            $this->assertSame('0', $costs[$field]);
        }
        $pricing = app(PricingCalculator::class);
        $this->assertDecimal('0', $pricing->calculate($product, 'profit', '0')['rounded_price']);
        $this->assertDecimal('0.1', $pricing->calculate($product, 'margin', '20')['rounded_price']);
        foreach ([
            ['profit', '-1', '0.1'], ['unknown', '1', '0.1'],
            ['profit', '1', '0'], ['profit', '1', '-0.1'],
            ['margin', '100', '0.1'], ['printing_hour', '1', '0.1'],
            ['total_hour', '1', '0.1'],
        ] as [$mode, $target, $increment]) {
            $this->assertInvalid(fn () => $pricing->calculate($product, $mode, $target, $increment));
        }
        $product->fee_percentage_override = '100';
        $this->assertInvalid(fn () => $pricing->calculate($product, 'profit', '0'));
        $product->fee_percentage_override = '20';
        $this->assertInvalid(fn () => $pricing->calculate($product, 'margin', '80'));
    }

    public function test_decimal_helpers_do_not_use_floats_and_preserve_fractional_seconds(): void
    {
        $this->assertDecimal('0.3', D::add('0.1', '0.2'));
        $this->assertDecimal('0.000000000001', D::sub('999999999999.999999999999', '999999999999.999999999998'));
        $this->assertDecimal('0.000000000002', D::mul('0.000000000001', '2'));
        $this->assertSame('1.01', D::round('1.005'));
        $this->assertSame('-1.01', D::round('-1.005'));
        $this->assertSame('€6.13', D::money('6.1338'));
        $this->assertSame('1h 1m 1.123456789012s', D::duration('3661.123456789012'));
        $this->assertSame('0s', D::duration('0'));
        $this->assertSame('0.000000000001s', D::duration('0.000000000001'));
        $this->assertSame('-1m 1.5s', D::duration('-61.5'));
        $this->assertInvalid(fn () => D::div('1', '0'));
    }
}
