<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Shared\SavesProduct;
use App\Services\ProductService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditProduct extends EditRecord
{
    use SavesProduct;

    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['usages'] = $this->record->usages()->get(['filament_id', 'grams'])->toArray();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return $this->saveProduct($data, $record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('duplicate')->label('Duplicate')->requiresConfirmation()
                ->action(function (): void {
                    $copy = app(ProductService::class)->duplicate($this->record);
                    $this->redirect(ProductResource::getUrl('edit', ['record' => $copy]));
                }),
            DeleteAction::make()->visible(fn (): bool => ! $this->record->sales()->exists()),
        ];
    }
}
