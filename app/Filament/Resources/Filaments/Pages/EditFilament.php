<?php

namespace App\Filament\Resources\Filaments\Pages;

use App\Filament\Resources\Filaments\FilamentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFilament extends EditRecord
{
    protected static string $resource = FilamentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(fn (): bool => ! $this->record->usages()->exists()),
        ];
    }
}
