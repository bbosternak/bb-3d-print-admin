<?php

namespace App\Filament\Resources\Shared;

use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Validation\ValidationException;

trait SavesProduct
{
    protected function saveProduct(array $data, ?Product $product = null): Product
    {
        try {
            return app(ProductService::class)->save($data, $product);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn (array $messages, string $field): array => ['data.'.$field => $messages])->all(),
            );
        }
    }
}
