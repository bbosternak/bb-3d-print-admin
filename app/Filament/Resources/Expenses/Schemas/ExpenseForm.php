<?php

namespace App\Filament\Resources\Expenses\Schemas;

use App\Filament\Resources\Shared\DecimalInput;
use App\Models\Expense;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ExpenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('date')->required()->default(now()),
                TextInput::make('description')->required()->maxLength(255),
                Select::make('category')->options(array_combine(Expense::CATEGORIES, Expense::CATEGORIES))->required()->searchable(),
                DecimalInput::make('amount')->minValue(0)->prefix('€')->required(),
                Textarea::make('notes')->columnSpanFull(),
            ]);
    }
}
