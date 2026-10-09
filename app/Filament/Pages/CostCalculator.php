<?php

namespace App\Filament\Pages;

use App\Filament\Schemas\SimulationForm;
use App\Models\Product;
use App\Models\Setting;
use App\Services\CostCalculator as Calculator;
use App\Services\ProductService;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

class CostCalculator extends Page
{
    protected static string|\UnitEnum|null $navigationGroup = 'Calculators';

    protected string $view = 'filament.pages.cost-calculator';

    public ?array $data = [];

    public ?array $savedCosts = null;

    public ?array $simulatedCosts = null;

    public string $currency = 'EUR';

    public function mount(): void
    {
        $this->form->fill(SimulationForm::defaults());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('product_id')->label('Saved product')
                ->options(fn (): array => Product::query()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()->required()->live()
                ->afterStateUpdated(fn ($state) => $this->loadProduct($state)),
            ...SimulationForm::components(),
        ])->statePath('data');
    }

    public function loadProduct(mixed $id): void
    {
        $this->reset('savedCosts', 'simulatedCosts');
        $product = filled($id) ? Product::query()->findOrFail($id) : null;
        $this->form->fill([
            ...($product ? SimulationForm::productData($product) : SimulationForm::defaults()),
            'product_id' => $id,
        ]);

        if ($product) {
            $this->savedCosts = app(Calculator::class)->calculate($product);
            $this->currency = Setting::current()->currency;
        }
    }

    public function calculate(): void
    {
        $this->reset('simulatedCosts');
        $data = $this->form->getState();
        $product = Product::query()->findOrFail($data['product_id']);

        try {
            $simulation = app(ProductService::class)->simulate($data, $product);
            $this->savedCosts = app(Calculator::class)->calculate($product);
            $this->simulatedCosts = app(Calculator::class)->calculate($simulation);
            $this->currency = Setting::current()->currency;
        } catch (ValidationException $exception) {
            SimulationForm::rethrowValidation($exception);
        }
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $product = Product::query()->findOrFail($data['product_id']);

        try {
            $product = app(ProductService::class)->save($data, $product);
            $this->loadProduct($product->getKey());
            Notification::make()->title('Product production parameters saved')->success()->send();
        } catch (ValidationException $exception) {
            SimulationForm::rethrowValidation($exception);
        }
    }
}
