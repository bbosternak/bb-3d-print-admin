<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Models\Setting;
use App\Services\CostCalculator;
use App\Support\Decimal;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Validator;

class ProductComparison extends Page
{
    protected static string|\UnitEnum|null $navigationGroup = 'Calculators';

    protected string $view = 'filament.pages.product-comparison';

    public ?array $data = [];

    public array $comparison = [];

    public array $unitWinners = [];

    public array $hourWinners = [];

    public string $currency = 'EUR';

    public function mount(): void
    {
        $this->form->fill(['product_ids' => [], 'selling_prices' => []]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('product_ids')->label('Products to compare')
                ->options(fn (): array => Product::query()->orderBy('name')->pluck('name', 'id')->all())
                ->multiple()->searchable()->minItems(2)->required()->live()
                ->afterStateUpdated(fn () => $this->reset('comparison', 'unitWinners', 'hourWinners')),
            Section::make('Temporary selling prices')
                ->description('Blank uses the saved price. Zero is valid. These prices are never saved.')
                ->schema(fn (Get $get): array => Product::query()->whereIn('id', $get('product_ids') ?? [])->get()
                    ->map(fn (Product $product): TextInput => TextInput::make('selling_prices.'.$product->getKey())
                        ->label($product->name)->placeholder((string) $product->selling_price)
                        ->inputMode('decimal')->rule('numeric')->minValue(0)->maxValue('999999999999.999999999999')
                        ->rules([fn (): \Closure => Decimal::rule()]))->all())
                ->columns(2),
        ])->statePath('data');
    }

    public function calculate(): void
    {
        $this->reset('comparison', 'unitWinners', 'hourWinners');
        $data = $this->form->getState();
        Validator::make(['data' => $data], [
            'data.product_ids' => ['required', 'array', 'min:2', 'max:100'],
            'data.product_ids.*' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'data.selling_prices' => ['nullable', 'array'],
            'data.selling_prices.*' => ['nullable', Decimal::rule()],
        ])->validate();

        $products = Product::query()->with('usages.filament')->whereIn('id', $data['product_ids'])->get()->keyBy('id');

        foreach ($data['product_ids'] as $id) {
            $product = $products->get($id);
            $price = $data['selling_prices'][$id] ?? null;

            if ($price !== null && $price !== '') {
                $product->selling_price = (string) $price;
            }

            $this->comparison[$id] = [
                'name' => $product->name,
                'costs' => app(CostCalculator::class)->calculate($product),
            ];
        }

        $this->unitWinners = $this->winners('profit');
        $this->hourWinners = $this->winners('profit_per_printing_hour');
        $this->currency = Setting::current()->currency;
    }

    private function winners(string $metric): array
    {
        $best = null;
        $winners = [];

        foreach ($this->comparison as $id => $row) {
            $value = $row['costs'][$metric] ?? null;

            if ($value === null || ($metric === 'profit_per_printing_hour' && Decimal::compare($row['costs']['printing_hours'], '0') <= 0)) {
                continue;
            }

            $rank = $best === null ? 1 : Decimal::compare($value, $best);

            if ($rank > 0) {
                $best = $value;
                $winners = [$id];
            } elseif ($rank === 0) {
                $winners[] = $id;
            }
        }

        return $winners;
    }
}
