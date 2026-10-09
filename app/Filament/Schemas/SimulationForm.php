<?php

namespace App\Filament\Schemas;

use App\Models\Filament;
use App\Models\Product;
use App\Models\Setting;
use App\Support\Decimal;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

class SimulationForm
{
    public const OVERRIDES = [
        'electricity_price' => 'Electricity price / kWh',
        'printer_power' => 'Printer power (W)',
        'depreciation_rate' => 'Depreciation / printing hour',
        'labor_rate' => 'Labor / hour',
        'processing_minutes' => 'Processing minutes / unit',
        'packaging_cost' => 'Packaging / unit',
        'fee_percentage' => 'Platform fee (%)',
    ];

    public const METRICS = [
        'filament_cost' => 'Filament / unit',
        'electricity_cost' => 'Electricity / unit',
        'depreciation_cost' => 'Depreciation / unit',
        'labor_cost' => 'Labor / unit',
        'additional_material_cost' => 'Additional materials / unit',
        'packaging_cost' => 'Packaging / unit',
        'manufacturing_cost' => 'Manufacturing cost / unit',
        'platform_fee' => 'Platform fee / unit',
        'total_cost' => 'Total cost / unit',
        'selling_price' => 'Selling price / unit',
        'profit' => 'Profit / unit',
        'margin' => 'Margin (%)',
        'profit_per_printing_hour' => 'Profit / printing hour',
        'profit_per_total_hour' => 'Profit / total working hour',
        'printing_hours' => 'Printing hours / unit',
        'processing_hours' => 'Processing hours / unit',
        'filament_grams' => 'Filament grams / unit',
        'printing_seconds' => 'Printing seconds / unit',
        'processing_minutes' => 'Processing minutes / unit',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components(self::components());
    }

    public static function components(): array
    {
        return [
            Section::make('Production simulation')
                ->description('Batch weight must equal the sum of filament usage. Costs are calculated per unit.')
                ->schema([
                    TextInput::make('batch_quantity')->integer()->minValue(1)->maxValue(2147483647)->required(),
                    self::decimal('batch_weight', 'Batch weight (g)')->required(),
                    TextInput::make('batch_seconds')->label('Batch printing time (seconds)')
                        ->integer()->minValue(0)->maxValue(2147483647)->required(),
                    self::decimal('selling_price', 'Selling price / unit')->required(),
                    self::decimal('additional_material_cost', 'Additional materials / unit')->required(),
                    Repeater::make('usages')
                        ->label('Batch filament usage')
                        ->schema([
                            Select::make('filament_id')
                                ->label('Filament')
                                ->options(fn (): array => Filament::query()->get()->pluck('name', 'id')->all())
                                ->searchable()->required(),
                            self::decimal('grams', 'Grams')->required(),
                        ])
                        ->columns(2)->defaultItems(0)->columnSpanFull(),
                ])->columns(2),
            Section::make('Settings overrides')
                ->description('Blank uses the current global default. Zero is an explicit override.')
                ->schema(array_map(
                    fn (string $field, string $label): TextInput => self::decimal($field.'_override', $label)
                        ->placeholder(fn (): string => (string) Setting::current()->getAttribute($field))
                        ->maxValue($field === 'fee_percentage' ? '100' : '999999999999.999999999999'),
                    array_keys(self::OVERRIDES),
                    array_values(self::OVERRIDES),
                ))->columns(2),
        ];
    }

    public static function defaults(): array
    {
        return [
            'batch_quantity' => 1,
            'batch_weight' => '0',
            'batch_seconds' => 0,
            'selling_price' => '0',
            'additional_material_cost' => '0',
            'usages' => [],
            ...array_fill_keys(array_map(fn (string $field): string => $field.'_override', array_keys(self::OVERRIDES)), null),
        ];
    }

    public static function productData(Product $product): array
    {
        return [
            ...$product->only(array_keys(self::defaults())),
            'usages' => $product->usages()->get()->map(fn ($usage): array => $usage->only(['filament_id', 'grams']))->all(),
        ];
    }

    public static function rethrowValidation(ValidationException $exception): never
    {
        $errors = [];

        foreach ($exception->errors() as $key => $messages) {
            $errors[str_starts_with($key, 'data.') ? $key : 'data.'.$key] = $messages;
        }

        throw ValidationException::withMessages($errors);
    }

    private static function decimal(string $name, string $label): TextInput
    {
        return TextInput::make($name)->label($label)->inputMode('decimal')->rule('numeric')->minValue(0)
            ->maxValue('999999999999.999999999999')->step('any')
            ->rules([fn (): \Closure => Decimal::rule()]);
    }
}
