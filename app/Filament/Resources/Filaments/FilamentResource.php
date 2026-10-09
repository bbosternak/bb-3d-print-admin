<?php

namespace App\Filament\Resources\Filaments;

use App\Filament\Resources\Filaments\Pages\CreateFilament;
use App\Filament\Resources\Filaments\Pages\EditFilament;
use App\Filament\Resources\Filaments\Pages\ListFilaments;
use App\Filament\Resources\Filaments\Schemas\FilamentForm;
use App\Filament\Resources\Filaments\Tables\FilamentsTable;
use App\Models\Filament;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FilamentResource extends Resource
{
    protected static ?string $model = Filament::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Products';

    protected static ?string $recordTitleAttribute = 'brand';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return FilamentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FilamentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFilaments::route('/'),
            'create' => CreateFilament::route('/create'),
            'edit' => EditFilament::route('/{record}/edit'),
        ];
    }
}
