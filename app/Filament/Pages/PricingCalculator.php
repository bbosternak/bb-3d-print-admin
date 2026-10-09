<?php

namespace App\Filament\Pages;

use App\Filament\Schemas\SimulationForm;
use App\Models\Product;
use App\Models\Setting;
use App\Services\CostCalculator;
use App\Services\PricingCalculator as Calculator;
use App\Services\ProductService;
use App\Support\Decimal;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

class PricingCalculator extends Page
{
    protected static string|\UnitEnum|null $navigationGroup = 'Calculators';

    protected string $view = 'filament.pages.pricing-calculator';

    public ?array $data = [];

    public ?array $result = null;

    public ?array $roundedCosts = null;

    public string $currency = 'EUR';

    public function mount(): void
    {
        $this->form->fill([...SimulationForm::defaults(), 'mode' => 'profit', 'target' => '1', 'increment' => '0.10']);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('product_id')->label('Saved product (optional)')
                ->helperText('Leave blank to price a temporary product without saving it.')
                ->options(fn (): array => Product::query()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()->live()->afterStateUpdated(fn ($state) => $this->loadProduct($state)),
            ...SimulationForm::components(),
            Section::make('Price target')->schema([
                Select::make('mode')->label('Target type')->options([
                    'profit' => 'Profit / unit',
                    'margin' => 'Margin (%)',
                    'printing_hour' => 'Profit / printing hour',
                    'total_hour' => 'Profit / total working hour',
                ])->required(),
                TextInput::make('target')->label('Target value')->inputMode('decimal')->rule('numeric')->minValue(0)
                    ->maxValue('999999999999.999999999999')->rules([fn (): \Closure => Decimal::rule()])->required(),
                TextInput::make('increment')->label('Round price upward to increment')->inputMode('decimal')->rule('numeric')
                    ->minValue('0.000000000001')->maxValue('999999999999.999999999999')
                    ->rules([fn (): \Closure => Decimal::rule(true)])->required(),
            ])->columns(3),
        ])->statePath('data');
    }

    public function loadProduct(mixed $id): void
    {
        $this->reset('result', 'roundedCosts');
        $product = filled($id) ? Product::query()->findOrFail($id) : null;
        $this->form->fill([
            ...($product ? SimulationForm::productData($product) : SimulationForm::defaults()),
            'product_id' => $id,
            'mode' => $this->data['mode'] ?? 'profit',
            'target' => $this->data['target'] ?? '1',
            'increment' => $this->data['increment'] ?? '0.10',
        ]);
    }

    public function calculate(): void
    {
        $this->reset('result', 'roundedCosts');
        $data = $this->form->getState();
        $product = filled($data['product_id'] ?? null) ? Product::query()->findOrFail($data['product_id']) : null;

        try {
            $simulation = app(ProductService::class)->simulate($data, $product);
            $this->result = app(Calculator::class)->calculate($simulation, $data['mode'], (string) $data['target'], (string) $data['increment']);
            $simulation->selling_price = $this->result['rounded_price'];
            $this->roundedCosts = app(CostCalculator::class)->calculate($simulation);
            $this->currency = Setting::current()->currency;
        } catch (ValidationException $exception) {
            SimulationForm::rethrowValidation($exception);
        }
    }
}
