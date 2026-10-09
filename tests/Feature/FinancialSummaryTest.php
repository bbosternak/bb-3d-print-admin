<?php

namespace Tests\Feature;

use App\Filament\Pages\FinancialDashboard;
use App\Filament\Widgets\CashFlowChart;
use App\Models\Expense;
use App\Models\Filament;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use App\Services\FinancialSummary;
use App\Services\ProductService;
use App\Support\Decimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class FinancialSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $name = 'Test product'): Product
    {
        $filament = Filament::create([
            'brand' => 'Test', 'material' => 'PLA', 'color' => 'Blue',
            'purchase_price' => '11.90', 'spool_weight' => '1000',
        ]);

        return app(ProductService::class)->save([
            'name' => $name, 'selling_price' => '14.90', 'active' => true,
            'additional_material_cost' => '0.20',
            'batch_quantity' => 5, 'batch_weight' => '485', 'batch_seconds' => 162000,
            'usages' => [['filament_id' => $filament->id, 'grams' => '485']],
        ]);
    }

    private function sale(Product $product, string $date, string $price, int $quantity): Sale
    {
        return Sale::create([
            'product_id' => $product->id, 'sale_date' => $date,
            'unit_price' => $price, 'quantity' => $quantity,
        ]);
    }

    private function expense(string $date, string $amount, string $category): void
    {
        Expense::create(compact('date', 'amount', 'category') + ['description' => 'Business purchase']);
    }

    private function assertDecimal(string $expected, string $actual): void
    {
        $this->assertSame(0, Decimal::compare($expected, $actual), "$expected != $actual");
    }

    public function test_recorded_totals_categories_and_monthly_periods_keep_estimates_out_of_cash_flow(): void
    {
        $product = $this->product();
        $this->sale($product, '2026-01-01', '14.90', 2);
        $this->sale($product, '2026-02-28', '20.00', 3);
        $this->expense('2026-01-01', '11.90', 'Material');
        $this->expense('2026-01-31', '30.00', 'Equipment');
        $this->expense('2026-02-01', '5.00', 'Tools');

        $summary = app(FinancialSummary::class)->summarize();
        $this->assertDecimal('89.80', $summary['revenue']);
        $this->assertDecimal('46.90', $summary['expenses']);
        $this->assertDecimal('42.90', $summary['cash_flow']);
        $this->assertDecimal('17.96', $summary['average_price']);
        $this->assertSame(5, $summary['units']);
        $this->assertDecimal('45', $summary['printing_hours']);
        $this->assertDecimal('1.1475', $summary['electricity_cost']);
        $this->assertDecimal('11.90', $summary['categories']['Material']);
        $this->assertDecimal('30', $summary['categories']['Equipment']);
        $this->assertDecimal('0', $summary['categories']['Other']);
        $this->assertDecimal('29.80', $summary['periods']['2026-01']['revenue']);
        $this->assertDecimal('41.90', $summary['periods']['2026-01']['expenses']);
        $this->assertCount(3, Expense::all());
    }

    public function test_date_boundaries_and_product_filter_preserve_business_wide_expenses(): void
    {
        $first = $this->product('First');
        $second = $this->product('Second');
        $this->sale($first, '2026-01-01', '10', 2);
        $this->sale($first, '2026-01-31', '10', 3);
        $this->sale($second, '2026-01-20', '20', 7);
        $this->sale($first, '2026-02-01', '10', 4);
        $this->expense('2026-01-31', '6', 'Other');
        $this->expense('2026-02-01', '100', 'Other');

        $summary = app(FinancialSummary::class)->summarize('2026-01-01', '2026-01-31', $first->id);
        $this->assertDecimal('50', $summary['revenue']);
        $this->assertDecimal('6', $summary['expenses']);
        $this->assertSame(5, $summary['units']);
        $this->assertCount(1, $summary['products']);
        $all = app(FinancialSummary::class)->summarize();
        $this->assertSame('First', array_values($all['products'])[0]['name']);
        $this->assertDecimal('190', app(FinancialSummary::class)->summarize(null, '2026-01-31')['revenue']);
    }

    public function test_sales_keep_price_snapshots_but_recalculate_effort_from_current_parameters_and_settings(): void
    {
        $product = $this->product();
        $sale = $this->sale($product, '2026-01-01', '14.90', 2);
        $product->update(['selling_price' => '100', 'batch_seconds' => 18000]);
        Setting::current()->update(['electricity_price' => '0.20', 'printer_power' => '200']);

        $summary = app(FinancialSummary::class)->summarize();
        $this->assertDecimal('29.80', $sale->fresh()->revenue);
        $this->assertDecimal('29.80', $summary['revenue']);
        $this->assertDecimal('2', $summary['printing_hours']);
        $this->assertDecimal('0.08', $summary['electricity_cost']);
        $this->assertDecimal('29.80', $summary['cash_flow']);
        $this->assertSame(0, Expense::count());
        $product->update(['electricity_price_override' => '0']);
        $this->assertDecimal('0', app(FinancialSummary::class)->summarize()['electricity_cost']);
    }

    public function test_empty_period_and_zero_duration_sales_are_safe(): void
    {
        $empty = app(FinancialSummary::class)->summarize();
        $this->assertDecimal('0', $empty['average_price']);
        $this->assertDecimal('0', $empty['cash_flow']);
        $product = $this->product();
        $product->update(['batch_seconds' => 0]);
        $this->sale($product, '2026-01-01', '0', 2);
        $summary = app(FinancialSummary::class)->summarize();
        $this->assertDecimal('0', $summary['printing_hours']);
        $this->assertDecimal('0', $summary['electricity_cost']);
    }

    public function test_reversed_date_range_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(FinancialSummary::class)->summarize('2026-02-01', '2026-01-01');
    }

    public function test_invalid_date_and_missing_product_filters_are_rejected(): void
    {
        foreach ([['2026-02-30', null, null], [null, null, 999]] as $arguments) {
            try {
                app(FinancialSummary::class)->summarize(...$arguments);
                $this->fail('An invalid summary filter was accepted.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
    }

    public function test_dashboard_renders_and_applies_period_filter_with_explicit_estimate_labels(): void
    {
        $this->actingAs(User::factory()->create());
        $this->sale($this->product(), '2026-01-10', '14.90', 2);

        Livewire::test(FinancialDashboard::class)
            ->assertSee('All time')
            ->assertSee('not accounting profit')
            ->assertSee('Estimated electricity')
            ->fillForm(['from' => '2026-02-01', 'until' => '2026-02-28'])
            ->call('applyFilters')
            ->assertHasNoFormErrors()
            ->assertSee('No sales in this period.');
    }

    public function test_decimal_cash_flow_does_not_accumulate_float_errors(): void
    {
        $product = $this->product();
        for ($i = 0; $i < 10; $i++) {
            $this->sale($product, '2026-01-01', '0.10', 1);
        }
        $this->expense('2026-01-01', '0.90', 'Other');

        $this->assertDecimal('0.10', app(FinancialSummary::class)->summarize()['cash_flow']);
    }

    public function test_cash_flow_chart_uses_selected_period_recorded_transactions(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->product();
        $this->sale($product, '2026-01-10', '14.90', 2);
        $this->sale($product, '2026-02-10', '14.90', 3);
        $this->expense('2026-01-15', '11.90', 'Material');

        Livewire::withoutLazyLoading()->test(CashFlowChart::class, [
            'from' => '2026-01-01', 'until' => '2026-01-31', 'productId' => $product->id,
        ])
            ->assertSee('2026-01')
            ->assertDontSee('2026-02')
            ->assertSee('Recorded revenue')
            ->assertSee('Recorded expenses');
    }
}
