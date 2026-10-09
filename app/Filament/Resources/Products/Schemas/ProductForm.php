<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Filament\Resources\Shared\DecimalInput;
use App\Filament\Resources\Shared\IntegerInput;
use App\Models\Filament;
use App\Models\Setting;
use App\Support\Decimal;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Product')->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('sku')->label('SKU')->maxLength(255)->unique(ignoreRecord: true),
                    Textarea::make('description')->columnSpanFull(),
                    FileUpload::make('image')->image()->disk('local')->visibility('private')->directory('products')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(5120)->preventFilePathTampering()->columnSpanFull(),
                    Toggle::make('active')->default(true),
                    DecimalInput::make('selling_price')->prefix('€')->minValue(0)->required()->default('0'),
                ])->columns(2)->columnSpanFull(),
                Section::make('Printing batch')->description('Enter the material and time for the entire batch. Costs are divided by the batch quantity.')
                    ->schema([
                        IntegerInput::make('batch_quantity')->minValue(1)->maxValue(2147483647)->required()->default(1),
                        DecimalInput::make('batch_weight')->label('Total batch weight')->suffix('g')->minValue(0)->required(),
                        IntegerInput::make('batch_seconds')->label('Batch printing time')->suffix('seconds')->minValue(0)->maxValue(2147483647)->required(),
                        DecimalInput::make('additional_material_cost')->label('Additional material cost per item')->prefix('€')->minValue(0)->required()->default('0'),
                        Repeater::make('usages')->label('Batch filament usage')->schema([
                            Select::make('filament_id')->label('Filament')
                                ->options(fn (): array => Filament::query()->get()->pluck('name', 'id')->all())
                                ->searchable()->required()->distinct()->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                            DecimalInput::make('grams')->minValue('0.000000000001')->suffix('g')->required(),
                        ])->columns(2)->defaultItems(1)->columnSpanFull()
                            ->helperText('The sum of all filament weights must equal the total batch weight.'),
                    ])->columns(2)->columnSpanFull(),
                Section::make('Cost overrides')->description('Leave blank to inherit the application setting. Zero is an explicit override.')
                    ->schema(self::overrideFields())->columns(2)->collapsible()->columnSpanFull(),
            ]);
    }

    public static function overrideFields(): array
    {
        return array_map(function (string $field): DecimalInput {
            $suffix = match ($field) {
                'electricity_price' => '€/kWh',
                'printer_power' => 'W',
                'depreciation_rate', 'labor_rate' => '€/hour',
                'processing_minutes' => 'minutes/item',
                'packaging_cost' => '€/item',
                'fee_percentage' => '%',
            };

            $input = DecimalInput::make($field.'_override')->label(ucwords(str_replace('_', ' ', $field)))
                ->minValue(0)->suffix($suffix)->nullable()
                ->helperText(fn (): string => 'Inherited: '.Decimal::trim(Setting::current()->getAttribute($field)).' '.$suffix);

            return $field === 'fee_percentage' ? $input->maxValue(100) : $input;
        }, ['electricity_price', 'printer_power', 'depreciation_rate', 'labor_rate', 'processing_minutes', 'packaging_cost', 'fee_percentage']);
    }
}
