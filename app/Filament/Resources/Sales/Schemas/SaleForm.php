<?php

namespace App\Filament\Resources\Sales\Schemas;

use App\Filament\Resources\Shared\DecimalInput;
use App\Filament\Resources\Shared\IntegerInput;
use App\Models\Product;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class SaleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('sale_date')->required()->default(now()),
                Select::make('product_id')->label('Product')->relationship('product', 'name')->searchable()->preload()->required()
                    ->live()->afterStateUpdated(function (mixed $state, Set $set): void {
                        if ($product = Product::query()->find($state)) {
                            $set('unit_price', $product->selling_price);
                        }
                    }),
                DecimalInput::make('unit_price')->prefix('€')->minValue(0)->required()
                    ->helperText('Price snapshot for this sale. Changing product prices later does not change this value.'),
                IntegerInput::make('quantity')->minValue(1)->maxValue(2147483647)->required()->default(1),
                Textarea::make('notes')->columnSpanFull(),
            ]);
    }
}
