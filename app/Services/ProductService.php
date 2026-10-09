<?php

namespace App\Services;

use App\Models\Filament;
use App\Models\Product;
use App\Models\ProductFilament;
use App\Models\Setting;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProductService
{
    public function save(array $data, ?Product $product = null): Product
    {
        return DB::transaction(function () use ($data, $product): Product {
            $candidate = $this->prepare($data, $product);
            $product ??= new Product;
            $product->fill(Arr::except($candidate->getAttributes(), ['id', 'created_at', 'updated_at']));
            $product->save();
            $product->usages()->delete();
            foreach ($candidate->usages as $usage) {
                $product->usages()->create(['filament_id' => $usage->filament_id, 'grams' => $usage->grams]);
            }

            return $product->fresh(['usages.filament']);
        });
    }

    public function simulate(array $data, ?Product $product = null): Product
    {
        $data['name'] ??= $product?->name ?? 'Temporary product';
        $candidate = $this->prepare($data, $product);
        unset($candidate->id);

        return $candidate;
    }

    public function duplicate(Product $product): Product
    {
        $product = $product->fresh(['usages']);
        $data = Arr::except($product->getAttributes(), ['id', 'created_at', 'updated_at']);
        $data['name'] = mb_substr($product->name, 0, 248).' (Copy)';
        $data['sku'] = null;
        $data['usages'] = $product->usages->map(fn (ProductFilament $usage): array => [
            'filament_id' => $usage->filament_id,
            'grams' => $usage->grams,
        ])->all();

        return $this->save($data);
    }

    private function prepare(array $data, ?Product $product): Product
    {
        $fields = [
            'name', 'sku', 'description', 'image', 'active', 'selling_price',
            'additional_material_cost', 'batch_quantity', 'batch_weight', 'batch_seconds',
            ...array_map(fn (string $field): string => $field.'_override', array_keys(Setting::financialRules())),
        ];
        $candidate = new Product($product ? Arr::only($product->getAttributes(), $fields) : []);
        if ($product) {
            $candidate->id = $product->id;
        }
        $candidate->fill(Arr::only($data, $fields));
        $candidate->validateAttributes();
        $usages = array_key_exists('usages', $data) ? $data['usages'] : ($product
            ? $product->usages()->get(['filament_id', 'grams'])->toArray()
            : []);
        Validator::make(['usages' => $usages], [
            'usages' => ['present', 'array'],
            'usages.*' => ['required', 'array:filament_id,grams'],
            'usages.*.filament_id' => ['required', Decimal::integerRule(), 'distinct', 'exists:filaments,id'],
            'usages.*.grams' => ['required', Decimal::rule(true)],
        ])->validate();
        $sum = '0';
        $models = new Collection;
        foreach ($usages as $usage) {
            $sum = Decimal::add($sum, (string) $usage['grams']);
            $model = new ProductFilament($usage);
            $model->setRelation('filament', Filament::query()->findOrFail($usage['filament_id']));
            $models->push($model);
        }
        if (Decimal::compare($sum, $candidate->batch_weight) !== 0) {
            throw ValidationException::withMessages(['usages' => 'Filament usage must exactly equal the total batch weight.']);
        }

        return $candidate->setRelation('usages', $models);
    }
}
