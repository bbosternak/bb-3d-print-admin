<?php

namespace Tests\Feature;

use App\Filament\Pages\ApplicationSettings;
use App\Filament\Resources\Expenses\Pages\CreateExpense;
use App\Filament\Resources\Expenses\Pages\EditExpense;
use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Filament\Resources\Filaments\Pages\CreateFilament;
use App\Filament\Resources\Filaments\Pages\EditFilament;
use App\Filament\Resources\Filaments\Pages\ListFilaments;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Sales\Pages\CreateSale;
use App\Filament\Resources\Sales\Pages\EditSale;
use App\Filament\Resources\Sales\Pages\ListSales;
use App\Models\Expense;
use App\Models\Filament;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use App\Services\ProductService;
use App\Support\Decimal;
use Filament\Facades\Filament as FilamentPanel;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Columns\ImageColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FilamentPanel::setCurrentPanel(FilamentPanel::getPanel('admin'));
        $this->actingAs(User::factory()->create());
    }

    public function test_filament_create_edit_search_filters_and_linked_deletion_guard(): void
    {
        Livewire::test(CreateFilament::class)->fillForm([
            'brand' => 'Prusament', 'material' => 'PLA', 'color' => 'Black',
            'purchase_price' => '20', 'spool_weight' => '1000',
        ])->call('create')->assertHasNoFormErrors();
        $filament = Filament::query()->firstOrFail();
        Livewire::test(EditFilament::class, ['record' => $filament->id])
            ->fillForm(['purchase_price' => '25'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('25.000000000000', $filament->fresh()->purchase_price);
        $other = $this->filament(['brand' => 'Other', 'material' => 'PETG', 'color' => 'White']);
        $this->product(['usages' => [['filament_id' => $filament->id, 'grams' => '100']]]);
        Livewire::test(ListFilaments::class)->assertSuccessful()
            ->searchTable('Prusament')->assertCanSeeTableRecords([$filament])->assertCanNotSeeTableRecords([$other])
            ->searchTable('')->filterTable('material', 'PLA')->assertCanNotSeeTableRecords([$other])
            ->assertTableActionHidden('delete', $filament)
            ->assertTableColumnStateSet('price_per_kg', '25.000000000000000000000000000000000000', $filament);
    }

    public function test_product_create_edit_preserves_zero_overrides_and_replaces_usages_atomically(): void
    {
        $first = $this->filament();
        $second = $this->filament(['brand' => 'Second', 'material' => 'PETG']);
        Livewire::test(CreateProduct::class)->fillForm($this->productData([
            'electricity_price_override' => '0',
            'usages' => [['filament_id' => $first->id, 'grams' => '60'], ['filament_id' => $second->id, 'grams' => '40']],
        ]))->call('create')->assertHasNoFormErrors();
        $product = Product::query()->firstOrFail();
        $this->assertSame('0.000000000000', $product->electricity_price_override);
        $this->assertNull($product->labor_rate_override);
        $this->assertCount(2, $product->usages);
        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->assertFormSet(['batch_quantity' => 2])
            ->fillForm(['batch_weight' => '50', 'usages' => [['filament_id' => $second->id, 'grams' => '50']], 'labor_rate_override' => '0'])
            ->call('save')->assertHasNoFormErrors();
        $this->assertCount(1, $product->fresh()->usages);
        $this->assertSame($second->id, $product->fresh()->usages->first()->filament_id);
        $this->assertSame('0.000000000000', $product->fresh()->labor_rate_override);
        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->fillForm(['labor_rate_override' => ''])->call('save')->assertHasNoFormErrors();
        $this->assertNull($product->fresh()->labor_rate_override);
    }

    public function test_invalid_product_weight_and_batch_quantity_do_not_create_partial_records(): void
    {
        $filament = $this->filament();
        Livewire::test(CreateProduct::class)->fillForm($this->productData([
            'usages' => [['filament_id' => $filament->id, 'grams' => '99']],
        ]))->call('create')->assertHasFormErrors(['usages']);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_filaments', 0);
        Livewire::test(CreateProduct::class)->fillForm($this->productData([
            'batch_quantity' => 0, 'usages' => [['filament_id' => $filament->id, 'grams' => '100']],
        ]))->call('create')->assertHasFormErrors(['batch_quantity']);
        $product = $this->product();
        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->fillForm(['batch_weight' => '200'])->call('save')->assertHasFormErrors(['usages']);
        $this->assertSame('100.000000000000', $product->fresh()->batch_weight);
        $this->assertSame('100.000000000000', $product->fresh()->usages->first()->grams);
    }

    public function test_product_duplicate_uses_service_and_cost_columns_sort_before_pagination(): void
    {
        $low = $this->product(['name' => 'Low', 'sku' => 'LOW', 'selling_price' => '1', 'electricity_price_override' => '0']);
        $high = $this->product(['name' => 'High', 'selling_price' => '50', 'active' => false]);
        Livewire::test(ListProducts::class)->assertSuccessful()
            ->sortTable('profit')->assertCanSeeTableRecords([$low, $high], inOrder: true)
            ->sortTable('profit', 'desc')->assertCanSeeTableRecords([$high, $low], inOrder: true)
            ->callTableAction('duplicate', $low);
        $copy = Product::query()->whereNotIn('id', [$low->id, $high->id])->firstOrFail();
        $this->assertNotSame($low->sku, $copy->sku);
        $this->assertSame($low->electricity_price_override, $copy->electricity_price_override);
        $this->assertCount(1, $copy->usages);
        Livewire::test(ListProducts::class)->filterTable('active', false)
            ->assertCanSeeTableRecords([$high])->assertCanNotSeeTableRecords([$low, $copy])
            ->filterTable('material', 'PETG')->assertCanNotSeeTableRecords([$high]);
    }

    public function test_decimal_inputs_preserve_precision_and_image_paths_cannot_be_forged(): void
    {
        Livewire::test(CreateFilament::class)->fillForm([
            'brand' => 'Precise', 'material' => 'PLA', 'color' => 'Black',
            'purchase_price' => '123456789012.123456789012', 'spool_weight' => '1000',
        ])->call('create')->assertHasNoFormErrors();
        $this->assertSame('123456789012.123456789012', Filament::query()->firstOrFail()->purchase_price);

        Livewire::test(CreateProduct::class)->fillForm($this->productData([
            'batch_weight' => '0', 'usages' => [], 'image' => ['products/unauthorized.png'],
        ]))->call('create')->assertHasFormErrors(['image']);
        $this->assertDatabaseCount('products', 0);
        Livewire::test(CreateProduct::class)->assertFormFieldExists('image',
            fn (FileUpload $field): bool => $field->getDiskName() === 'local' && $field->getVisibility() === 'private');
        Livewire::test(ListProducts::class)->assertTableColumnExists('image',
            fn (ImageColumn $column): bool => $column->getDiskName() === 'local' && $column->getVisibility() === 'private');
    }

    public function test_financial_table_summaries_use_exact_decimals(): void
    {
        $amount = '123456789012.123456789012';
        foreach (range(1, 2) as $number) {
            Expense::query()->create([
                'date' => '2026-10-01', 'description' => 'Precise expense '.$number, 'category' => 'Material', 'amount' => $amount,
            ]);
        }
        $expenses = Livewire::test(ListExpenses::class)->instance();
        $expenseSummary = $expenses->getTable()->getColumn('amount')->getSummarizer('total')
            ->query($expenses->getAllTableSummaryQuery())->getState();
        $this->assertSame(Decimal::add($amount, $amount), $expenseSummary);

        Sale::query()->create([
            'product_id' => $this->product()->id, 'sale_date' => '2026-10-01', 'quantity' => 3, 'unit_price' => $amount,
        ]);
        $sales = Livewire::test(ListSales::class)->instance();
        $saleSummary = $sales->getTable()->getColumn('revenue')->getSummarizer('total')
            ->query($sales->getAllTableSummaryQuery())->getState();
        $this->assertSame(Decimal::mul($amount, '3'), $saleSummary);
    }

    public function test_expense_crud_period_category_filters_and_totals(): void
    {
        Livewire::test(CreateExpense::class)->fillForm([
            'date' => '2026-10-01', 'description' => 'Spools', 'category' => 'Material', 'amount' => '10',
        ])->call('create')->assertHasNoFormErrors();
        $expense = Expense::query()->firstOrFail();
        Livewire::test(EditExpense::class, ['record' => $expense->id])->fillForm(['amount' => '12'])
            ->call('save')->assertHasNoFormErrors();
        $other = Expense::query()->create(['date' => '2026-09-01', 'description' => 'Tools', 'category' => 'Tools', 'amount' => '5']);
        Livewire::test(ListExpenses::class)->assertSuccessful()
            ->assertSee('All-time expenses: €17.00')->filterTable('period', ['from' => '2026-10-01', 'until' => '2026-10-31'])
            ->assertCanSeeTableRecords([$expense])->assertCanNotSeeTableRecords([$other])
            ->assertSee('Selected period / filters: €12.00')
            ->filterTable('category', 'Tools')->assertCanNotSeeTableRecords([$expense]);
    }

    public function test_sale_price_prefill_edit_preserves_snapshot_and_revenue_filters_render(): void
    {
        $product = $this->product(['selling_price' => '12']);
        Livewire::test(CreateSale::class)->set('data.product_id', $product->id)
            ->assertFormSet(['unit_price' => '12.000000000000'])
            ->fillForm(['sale_date' => '2026-10-02', 'unit_price' => '11', 'quantity' => 3])
            ->call('create')->assertHasNoFormErrors();
        $sale = Sale::query()->firstOrFail();
        $product->update(['selling_price' => '99']);
        Livewire::test(EditSale::class, ['record' => $sale->id])
            ->assertFormSet(['unit_price' => '11.000000000000'])
            ->fillForm(['notes' => 'Price honored'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('11.000000000000', $sale->fresh()->unit_price);
        $other = Sale::query()->create(['product_id' => $product->id, 'sale_date' => '2026-09-01', 'quantity' => 1, 'unit_price' => '5']);
        Livewire::test(ListSales::class)->assertSuccessful()->assertSee('All-time revenue: €38.00')
            ->filterTable('product_id', $product->id)
            ->filterTable('period', ['from' => '2026-10-01', 'until' => '2026-10-31'])
            ->assertCanSeeTableRecords([$sale])->assertCanNotSeeTableRecords([$other])
            ->assertSee('Selected period / filters revenue: €33.00')->assertSee('Current estimates');
    }

    public function test_settings_are_editable_singleton_and_reject_invalid_rates(): void
    {
        Livewire::test(ApplicationSettings::class)->assertSuccessful()
            ->fillForm(['electricity_price' => '0', 'fee_percentage' => '101'])->call('save')
            ->assertHasFormErrors(['fee_percentage'])
            ->fillForm(['fee_percentage' => '10'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('0.000000000000', Setting::current()->electricity_price);
        $this->assertSame('EUR', Setting::current()->currency);
        $this->assertDatabaseCount('settings', 1);
    }

    private function filament(array $data = []): Filament
    {
        return Filament::query()->create([...[
            'brand' => 'Test', 'material' => 'PLA', 'color' => 'Black', 'purchase_price' => '20', 'spool_weight' => '1000',
        ], ...$data]);
    }

    private function productData(array $data = []): array
    {
        return [...[
            'name' => 'Widget', 'sku' => null, 'description' => null, 'image' => null, 'active' => true,
            'selling_price' => '10', 'additional_material_cost' => '0', 'batch_quantity' => 2,
            'batch_weight' => '100', 'batch_seconds' => 3600,
        ], ...$data];
    }

    private function product(array $data = []): Product
    {
        if (! isset($data['usages'])) {
            $data['usages'] = [['filament_id' => $this->filament()->id, 'grams' => $data['batch_weight'] ?? '100']];
        }

        return app(ProductService::class)->save($this->productData($data));
    }
}
