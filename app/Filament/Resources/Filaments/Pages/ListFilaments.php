<?php

namespace App\Filament\Resources\Filaments\Pages;

use App\Filament\Resources\Filaments\FilamentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFilaments extends ListRecords
{
    protected static string $resource = FilamentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
