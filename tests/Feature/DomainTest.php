<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Filament;
use App\Models\Product;
use App\Models\ProductFilament;
use App\Models\Sale;
use App\Models\Setting;
use App\Services\ProductService;
use App\Support\Decimal;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DomainTest extends TestCase
{
    use RefreshDatabase;

    private function filament(array $data = []): Filament
    {
        return Filament::create([...[
            'brand' => 'Test', 'material' => 'PLA', 'color' => 'Blue',
            'purchase_price' => '11.90', 'spool_weight' => '1000',
        ], ...$data]);
    }

    private function assertInvalid(callable $action, string $field): void
    {
        try {
            $action();
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    public function test_settings_are_seeded_once_and_remain_a_singleton(): void
    {
        foreach (Setting::DEFAULTS as $field => $value) {
            $this->assertSame(0, $field === 'currency' ? strcmp($value, Setting::current()->{$field}) : Decimal::compare($value, Setting::current()->{$field}));
        }
        Setting::current()->update(['labor_rate' => '0']);
        $this->assertSame('0.000000000000', Setting::current()->labor_rate);
        $this->assertSame(1, Setting::count());
        $this->assertInvalid(fn () => Setting::current()->delete(), 'setting');
        $this->assertInvalid(fn () => Setting::create(['id' => 2, ...Setting::DEFAULTS]), 'id');
        $this->assertInvalid(fn () => Setting::current()->update(['fee_percentage' => '100.000000000001']), 'fee_percentage');
    }

    public function test_exact_weights_relationships_and_duplicate_preserve_all_attributes(): void
    {
        $first = $this->filament();
        $second = $this->filament(['color' => 'Red']);
        $product = app(ProductService::class)->save([
            'name' => 'Widget', 'sku' => 'W-1', 'description' => 'Notes', 'image' => 'products/a.png',
            'active' => false, 'selling_price' => '15.25', 'additional_material_cost' => '0.125',
            'batch_quantity' => 5, 'batch_weight' => '0.300000000003', 'batch_seconds' => 39600,
            'labor_rate_override' => '0', 'processing_minutes_override' => '7.5',
            'usages' => [
                ['filament_id' => $first->id, 'grams' => '0.100000000001'],
                ['filament_id' => $second->id, 'grams' => '0.200000000002'],
            ],
        ]);
        $this->assertCount(2, $product->filaments);
        $this->assertSame('0.100000000001', (string) $product->filaments->first()->pivot->grams);
        $this->assertTrue($product->usages->first()->product->is($product));
        $this->assertSame('Test · PLA · Blue', $first->name);
        $this->assertSame(0, Decimal::compare('11.90', $first->price_per_kg));
        $copy = app(ProductService::class)->duplicate($product);
        $this->assertSame('Widget (Copy)', $copy->name);
        $this->assertNull($copy->sku);
        foreach (['description', 'image', 'active', 'selling_price', 'additional_material_cost', 'batch_quantity', 'batch_weight', 'batch_seconds', 'labor_rate_override', 'processing_minutes_override'] as $field) {
            $this->assertSame($product->{$field}, $copy->{$field});
        }
        $this->assertSame($product->usages->pluck('grams')->all(), $copy->usages->pluck('grams')->all());
    }

    public function test_simulation_is_validated_and_never_writes_or_mutates_original(): void
    {
        $filament = $this->filament();
        $service = app(ProductService::class);
        $product = $service->save(['name' => 'Original', 'sku' => 'ABC']);
        $before = DB::table('products')->first();
        $simulation = $service->simulate([
            'batch_weight' => '0.1',
            'usages' => [['filament_id' => $filament->id, 'grams' => '0.1']],
            'selling_price' => '0.2',
        ], $product);
        $this->assertFalse($simulation->exists);
        $this->assertNull($simulation->id);
        $this->assertTrue($simulation->relationLoaded('usages'));
        $this->assertSame('Original', $simulation->name);
        $this->assertEquals($before, DB::table('products')->first());
        $this->assertSame(0, ProductFilament::count());
        $this->assertSame('Temporary product', $service->simulate([])->name);
        $this->assertInvalid(fn () => $service->simulate(['batch_weight' => '0.100000000001', 'usages' => [['filament_id' => $filament->id, 'grams' => '0.1']]]), 'usages');
        $this->assertInvalid(fn () => $service->simulate(['usages' => null]), 'usages');
    }

    public function test_invalid_usage_save_is_atomic_and_duplicate_filaments_are_rejected(): void
    {
        $filament = $this->filament();
        $service = app(ProductService::class);
        $data = ['name' => 'Widget', 'batch_weight' => '97', 'usages' => [['filament_id' => $filament->id, 'grams' => '97']]];
        $product = $service->save($data);
        $this->assertInvalid(fn () => $service->save(['name' => 'Changed', 'usages' => [['filament_id' => $filament->id, 'grams' => '96.999999999999']]], $product), 'usages');
        $this->assertSame('Widget', $product->fresh()->name);
        $this->assertSame('97.000000000000', $product->usages()->first()->grams);
        $this->assertInvalid(fn () => $service->save([...$data, 'usages' => [
            ['filament_id' => $filament->id, 'grams' => '48.5'],
            ['filament_id' => $filament->id, 'grams' => '48.5'],
        ]]), 'usages.0.filament_id');
        $this->assertSame(1, Product::count());
        $this->assertSame(1, ProductFilament::count());
        $this->assertInvalid(fn () => $service->simulate(['batch_quantity' => '1.5']), 'batch_quantity');
        $this->assertInvalid(fn () => $service->simulate(['batch_seconds' => '1.1']), 'batch_seconds');
        $this->assertInvalid(fn () => ProductFilament::create(['product_id' => $product->id, 'filament_id' => $filament->id, 'grams' => '97']), 'filament_id');
    }

    public function test_validation_rejects_negative_overflow_exponent_float_and_excess_precision_values(): void
    {
        foreach (['-1', '1000000000000', '0.1234567890123', '1e2', 'NaN', 1.5] as $value) {
            $this->assertInvalid(fn () => $this->filament(['purchase_price' => $value]), 'purchase_price');
            $this->assertInvalid(fn () => app(ProductService::class)->simulate(['selling_price' => $value]), 'selling_price');
            $this->assertInvalid(fn () => Expense::create(['date' => '2026-10-09', 'description' => 'Material', 'category' => 'Material', 'amount' => $value]), 'amount');
            $this->assertInvalid(fn () => Setting::current()->update(['labor_rate' => $value]), 'labor_rate');
        }
        $this->assertInvalid(fn () => $this->filament(['spool_weight' => '0']), 'spool_weight');
        $this->assertInvalid(fn () => $this->filament(['brand' => '']), 'brand');
        $this->assertInvalid(fn () => Product::create(['name' => 'Bad', 'batch_quantity' => 0]), 'batch_quantity');
        $this->assertInvalid(fn () => app(ProductService::class)->simulate(['fee_percentage_override' => '101']), 'fee_percentage_override');
        $this->assertInvalid(fn () => Expense::create(['date' => 'not a date', 'description' => 'Bad', 'category' => 'Other', 'amount' => '1']), 'date');
        $filament = $this->filament(['purchase_price' => '999999999999.999999999999']);
        $this->assertSame('999999999999.999999999999', $filament->fresh()->purchase_price);
    }

    public function test_sales_expenses_and_reference_deletion_are_guarded_by_models_and_database(): void
    {
        $filament = $this->filament();
        $product = app(ProductService::class)->save(['name' => 'Widget', 'batch_weight' => '1', 'usages' => [['filament_id' => $filament->id, 'grams' => '1']]]);
        $saleData = ['sale_date' => '2026-10-09', 'product_id' => $product->id, 'unit_price' => '0.123456789012', 'quantity' => 3];
        $sale = Sale::create($saleData);
        $this->assertSame(0, Decimal::compare('0.370370367036', $sale->revenue));
        $this->assertTrue($sale->product->is($product));
        foreach (['0', '-1', '2.5', 2.5, '2147483648'] as $quantity) {
            $this->assertInvalid(fn () => Sale::create([...$saleData, 'quantity' => $quantity]), 'quantity');
        }
        $this->assertInvalid(fn () => Sale::create([...$saleData, 'unit_price' => '-1']), 'unit_price');
        $this->assertInvalid(fn () => Sale::create([...$saleData, 'sale_date' => 'invalid']), 'sale_date');
        $this->assertInvalid(fn () => Sale::create([...$saleData, 'product_id' => 999]), 'product_id');
        $this->assertInvalid(fn () => $product->delete(), 'product');
        $this->assertInvalid(fn () => $filament->delete(), 'filament');
        foreach (['products' => $product->id, 'filaments' => $filament->id] as $table => $id) {
            try {
                DB::table($table)->where('id', $id)->delete();
                $this->fail('Expected database foreign key restriction.');
            } catch (QueryException $exception) {
                $this->assertStringContainsString('FOREIGN KEY', $exception->getMessage());
            }
        }
        foreach (Expense::CATEGORIES as $category) {
            Expense::create(['date' => '2026-10-09', 'description' => 'Expense', 'category' => $category, 'amount' => '0']);
        }
        $this->assertSame(5, Expense::count());
        $this->assertInvalid(fn () => Expense::create(['date' => '2026-10-09', 'description' => 'Expense', 'category' => 'Unknown', 'amount' => '1']), 'category');
        $sale->delete();
        $product->delete();
        $this->assertSame(0, ProductFilament::count());
        $this->assertTrue($filament->delete());
    }

    public function test_transaction_rolls_back_if_a_usage_save_fails_after_product_save(): void
    {
        $filament = $this->filament();
        ProductFilament::creating(function (): void {
            throw ValidationException::withMessages(['grams' => 'Rejected during persistence.']);
        });
        $this->assertInvalid(fn () => app(ProductService::class)->save([
            'name' => 'Rollback', 'batch_weight' => '1',
            'usages' => [['filament_id' => $filament->id, 'grams' => '1']],
        ]), 'grams');
        $this->assertSame(0, Product::count());
        $this->assertSame(0, ProductFilament::count());
    }
}
