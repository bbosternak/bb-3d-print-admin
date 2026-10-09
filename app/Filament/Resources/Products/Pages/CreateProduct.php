<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Shared\SavesProduct;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProduct extends CreateRecord
{
    use SavesProduct;

    protected static string $resource = ProductResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return $this->saveProduct($data);
    }
}
