<?php

namespace Tests\Feature;

use App\Filament\Pages\CostCalculator;
use App\Filament\Pages\PricingCalculator;
use App\Filament\Pages\ProductComparison;
use App\Filament\Schemas\SimulationForm;
use App\Models\Filament as Material;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\ProductService;
use App\Support\Decimal;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CalculatorPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
    }

    public function test_cost_simulation_loads_all_inputs_without_mutating_any_saved_data(): void
    {
        $product = $this->product('Saved product');
        $before = $product->getAttributes();
        $usages = $product->usages()->get()->toArray();
        $settings = Setting::current()->getAttributes();
        $filament = Material::query()->first()->getAttributes();
        $other = $this->filament('Other');

        $page = Livewire::test(CostCalculator::class)
            ->call('loadProduct', $product->id)
            ->assertSet('data.batch_quantity', 1)
            ->assertSet('data.electricity_price_override', '0.000000000000')
            ->assertSet('data.usages', fn ($usages) => count($usages) === 1 && (int) array_values($usages)[0]['filament_id'] === $filament['id'])
            ->fillForm([
                'batch_quantity' => 2,
                'batch_weight' => '30',
                'batch_seconds' => 1800,
                'selling_price' => '0',
                'additional_material_cost' => '1',
                'usages' => [['filament_id' => $other->id, 'grams' => '30']],
                'electricity_price_override' => '0.3',
                'printer_power_override' => '100',
                'depreciation_rate_override' => '0.5',
                'labor_rate_override' => '12',
                'processing_minutes_override' => '5',
                'packaging_cost_override' => '0.2',
                'fee_percentage_override' => '0',
            ])
            ->call('calculate')->assertHasNoFormErrors()
            ->assertSee('Saved product estimate')->assertSee('Temporary simulation');

        $this->assertLessThan(0, Decimal::compare($page->get('simulatedCosts.profit'), '0'));
        $this->assertSame($before, $product->fresh()->getAttributes());
        $this->assertSame($usages, $product->usages()->get()->toArray());
        $this->assertSame($settings, Setting::current()->getAttributes());
        $this->assertSame($filament, Material::query()->findOrFail($filament['id'])->getAttributes());
    }

    public function test_only_explicit_save_persists_selected_product_and_its_usages(): void
    {
        $product = $this->product('Selected');
        $untouched = $this->product('Untouched');
        $before = $untouched->getAttributes();
        $material = $this->filament('Replacement');

        Livewire::test(CostCalculator::class)
            ->call('loadProduct', $product->id)
            ->fillForm([
                'batch_quantity' => 2,
                'batch_weight' => '40',
                'batch_seconds' => 7200,
                'selling_price' => '12',
                'additional_material_cost' => '1',
                'usages' => [['filament_id' => $material->id, 'grams' => '40']],
                'electricity_price_override' => null,
                'labor_rate_override' => '0',
            ])->call('save')->assertHasNoFormErrors();

        $product->refresh();
        $this->assertSame('Selected', $product->name);
        $this->assertSame(2, $product->batch_quantity);
        $this->assertSame(0, Decimal::compare($product->batch_weight, '40'));
        $this->assertSame(0, Decimal::compare($product->selling_price, '12'));
        $this->assertNull($product->electricity_price_override);
        $this->assertSame(0, Decimal::compare($product->labor_rate_override, '0'));
        $this->assertSame([$material->id], $product->usages()->pluck('filament_id')->all());
        $this->assertSame($before, $untouched->fresh()->getAttributes());
    }

    public function test_invalid_batch_cannot_be_simulated_or_saved(): void
    {
        $product = $this->product('Invalid simulation');
        $before = $product->getAttributes();

        Livewire::test(CostCalculator::class)
            ->call('loadProduct', $product->id)
            ->fillForm(['batch_weight' => '11'])
            ->call('calculate')->assertHasFormErrors(['usages'])
            ->call('save')->assertHasFormErrors(['usages']);

        $this->assertSame($before, $product->fresh()->getAttributes());
    }

    public function test_pricing_temporary_product_shows_exact_and_rounded_results_without_saving(): void
    {
        $material = $this->filament();
        $input = $this->production($material);
        $count = Product::query()->count();

        $page = Livewire::test(PricingCalculator::class)
            ->fillForm([...$input, 'mode' => 'profit', 'target' => '1.23', 'increment' => '0.50'])
            ->call('calculate')->assertHasNoFormErrors()
            ->assertSee('Exact price (before upward rounding)')
            ->assertSee('Actual results at the rounded price');

        $this->assertSame(0, Decimal::compare($page->get('result.exact_price'), '1.43'));
        $this->assertSame(0, Decimal::compare($page->get('result.rounded_price'), '1.50'));
        $this->assertSame(0, Decimal::compare($page->get('roundedCosts.profit'), '1.30'));
        $this->assertSame($count, Product::query()->count());
        $this->assertDatabaseCount('product_filaments', 0);
    }

    public function test_pricing_saved_product_never_saves_suggested_price_and_rejects_impossible_margin(): void
    {
        $product = $this->product('Pricing');
        $before = $product->getAttributes();
        $usages = $product->usages()->get()->toArray();

        Livewire::test(PricingCalculator::class)
            ->call('loadProduct', $product->id)
            ->fillForm(['mode' => 'margin', 'target' => '20', 'increment' => '0.10', 'selling_price' => '99'])
            ->call('calculate')->assertHasNoFormErrors()
            ->fillForm(['target' => '100'])
            ->call('calculate')->assertHasFormErrors(['target'])
            ->assertSet('result', null);

        $this->assertSame($before, $product->fresh()->getAttributes());
        $this->assertSame($usages, $product->usages()->get()->toArray());
    }

    public function test_product_selector_loads_the_saved_form_and_can_return_to_temporary_pricing(): void
    {
        $product = $this->product('Selected by field', ['batch_quantity' => 3, 'batch_seconds' => 1234]);

        Livewire::test(CostCalculator::class)
            ->set('data.product_id', (string) $product->id)
            ->assertSet('data.batch_quantity', 3)
            ->assertSet('data.batch_seconds', 1234)
            ->call('calculate')->assertHasNoFormErrors();

        Livewire::test(PricingCalculator::class)
            ->set('data.product_id', (string) $product->id)
            ->assertSet('data.batch_quantity', 3)
            ->set('data.product_id', null)
            ->assertSet('data.batch_quantity', 1)
            ->assertSet('data.usages', []);
    }

    public function test_hourly_targets_preserve_decimal_precision_and_impossible_time_targets_fail(): void
    {
        $input = $this->production($this->filament());

        foreach (['printing_hour', 'total_hour'] as $mode) {
            $page = Livewire::test(PricingCalculator::class)
                ->fillForm([...$input, 'mode' => $mode, 'target' => '7.123456789012', 'increment' => '0.10'])
                ->call('calculate')->assertHasNoFormErrors()
                ->assertSee('7.323456789012');

            $this->assertSame(0, Decimal::compare($page->get('result.rounded_price'), '7.40'));
            $page->fillForm(['batch_seconds' => 0])
                ->call('calculate')->assertHasFormErrors(['target']);
        }

        Livewire::test(PricingCalculator::class)
            ->fillForm([...$input, 'mode' => 'profit', 'target' => '1', 'increment' => '0.10', 'fee_percentage_override' => '100'])
            ->call('calculate')->assertHasFormErrors(['target']);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_comparison_uses_unsaved_prices_and_distinct_profit_and_hourly_winners(): void
    {
        $slow = $this->product('High profit', ['selling_price' => '10', 'batch_seconds' => 7200]);
        $fast = $this->product('Fast print', ['selling_price' => '6', 'batch_seconds' => 3600]);
        $before = [$slow->getAttributes(), $fast->getAttributes()];

        $page = Livewire::test(ProductComparison::class)
            ->fillForm(['product_ids' => [$slow->id, $fast->id]])
            ->call('calculate')->assertHasNoFormErrors()
            ->assertSet('unitWinners', [$slow->id])->assertSet('hourWinners', [$fast->id])
            ->assertSee('Highest profit / unit')->assertSee('Highest profit / printing hour')
            ->fillForm(['selling_prices' => [$slow->id => '0', $fast->id => '2']])
            ->call('calculate')->assertHasNoFormErrors()
            ->assertSet('unitWinners', [$fast->id]);

        $this->assertSame(0, Decimal::compare($page->get("comparison.{$slow->id}.costs.selling_price"), '0'));
        $this->assertSame($before, [$slow->fresh()->getAttributes(), $fast->fresh()->getAttributes()]);
    }

    public function test_comparison_reloads_settings_filament_prices_and_products_on_each_calculation(): void
    {
        $first = $this->product('First');
        $second = $this->product('Second');

        $page = Livewire::test(ProductComparison::class)
            ->fillForm(['product_ids' => [$first->id, $second->id]])
            ->call('calculate')->assertHasNoFormErrors();
        $initial = $page->get("comparison.{$first->id}.costs.filament_cost");
        $first->usages()->first()->filament->update(['purchase_price' => '40']);
        $first->update(['batch_seconds' => 0, 'labor_rate_override' => null, 'processing_minutes_override' => null]);
        Setting::current()->update(['labor_rate' => '20', 'processing_minutes' => '30']);

        $page->call('calculate')->assertHasNoFormErrors()
            ->assertSet('hourWinners', [$second->id]);
        $this->assertGreaterThan(0, Decimal::compare($page->get("comparison.{$first->id}.costs.filament_cost"), $initial));
        $this->assertSame(0, Decimal::compare($page->get("comparison.{$first->id}.costs.labor_cost"), '10'));
    }

    public function test_zero_times_and_zero_prices_have_no_undefined_hourly_winner(): void
    {
        $first = $this->product('Zero A', ['batch_seconds' => 0, 'selling_price' => '0']);
        $second = $this->product('Zero B', ['batch_seconds' => 0, 'selling_price' => '0']);

        Livewire::test(ProductComparison::class)
            ->fillForm(['product_ids' => [$first->id, $second->id]])
            ->call('calculate')->assertHasNoFormErrors()
            ->assertSet('hourWinners', [])
            ->assertSet('unitWinners', [$first->id, $second->id])
            ->assertSee('Not defined');
    }

    public function test_undefined_hourly_profit_cannot_beat_a_negative_defined_hourly_profit(): void
    {
        $zero = $this->product('No printing time', ['batch_seconds' => 0, 'selling_price' => '0']);
        $printing = $this->product('Negative hourly profit', ['batch_seconds' => 3600, 'selling_price' => '0']);

        Livewire::test(ProductComparison::class)
            ->fillForm(['product_ids' => [$zero->id, $printing->id]])
            ->call('calculate')->assertHasNoFormErrors()
            ->assertSet('hourWinners', [$printing->id]);
    }

    public function test_comparison_rejects_negative_exponential_and_unbounded_price_overrides(): void
    {
        $first = $this->product('A');
        $second = $this->product('B');

        foreach (['-1', '1e2', '1000000000000', '0.1234567890123'] as $price) {
            Livewire::test(ProductComparison::class)
                ->fillForm(['product_ids' => [$first->id, $second->id], 'selling_prices' => [$first->id => $price]])
                ->call('calculate')->assertHasFormErrors(["selling_prices.{$first->id}"]);
        }

        $this->assertSame(0, Decimal::compare($first->fresh()->selling_price, '5'));
    }

    private function filament(string $brand = 'Test'): Material
    {
        return Material::query()->create([
            'brand' => $brand, 'material' => 'PLA', 'color' => 'Black',
            'purchase_price' => '20', 'spool_weight' => '1000',
        ]);
    }

    private function production(Material $filament): array
    {
        return [
            ...SimulationForm::defaults(),
            ...array_fill_keys(array_map(fn ($field) => $field.'_override', array_keys(SimulationForm::OVERRIDES)), '0'),
            'batch_quantity' => 1,
            'batch_weight' => '10',
            'batch_seconds' => 3600,
            'selling_price' => '5',
            'usages' => [['filament_id' => $filament->id, 'grams' => '10']],
        ];
    }

    private function product(string $name, array $overrides = []): Product
    {
        return app(ProductService::class)->save([
            ...$this->production($this->filament($name)),
            'name' => $name,
            ...$overrides,
        ]);
    }
}
