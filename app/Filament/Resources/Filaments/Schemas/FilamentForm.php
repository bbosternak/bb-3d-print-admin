<?php

namespace App\Filament\Resources\Filaments\Schemas;

use App\Filament\Resources\Shared\DecimalInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FilamentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('brand')->required()->maxLength(255),
                TextInput::make('material')->required()->maxLength(255),
                TextInput::make('color')->required()->maxLength(255),
                DecimalInput::make('purchase_price')->label('Spool purchase price')->prefix('€')->minValue(0)->required(),
                DecimalInput::make('spool_weight')->label('Spool weight')->suffix('g')->minValue('0.000000000001')->required(),
                Textarea::make('notes')->columnSpanFull(),
            ]);
    }
}
