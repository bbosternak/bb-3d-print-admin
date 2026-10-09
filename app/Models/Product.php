<?php

namespace App\Models;

use App\Support\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Product extends Model
{
    protected $guarded = [];

    protected $attributes = [
        'active' => true,
        'selling_price' => '0',
        'additional_material_cost' => '0',
        'batch_quantity' => 1,
        'batch_weight' => '0',
        'batch_seconds' => 0,
    ];

    protected function casts(): array
    {
        $casts = [
            'active' => 'boolean',
            'selling_price' => 'decimal:12',
            'additional_material_cost' => 'decimal:12',
            'batch_weight' => 'decimal:12',
            'batch_quantity' => 'integer',
            'batch_seconds' => 'integer',
        ];
        foreach (array_keys(Setting::financialRules()) as $field) {
            $casts[$field.'_override'] = 'decimal:12';
        }

        return $casts;
    }

    public function usages(): HasMany
    {
        return $this->hasMany(ProductFilament::class);
    }

    public function filaments(): BelongsToMany
    {
        return $this->belongsToMany(Filament::class, 'product_filaments')->withPivot('grams')->withTimestamps();
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function validateAttributes(): void
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($this->id)],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:2048'],
            'active' => ['required', 'boolean'],
            'selling_price' => ['required', Decimal::rule()],
            'additional_material_cost' => ['required', Decimal::rule()],
            'batch_quantity' => ['required', Decimal::integerRule()],
            'batch_weight' => ['required', Decimal::rule()],
            'batch_seconds' => ['required', Decimal::integerRule(false)],
        ];
        foreach (Setting::financialRules() as $field => $fieldRules) {
            $rules[$field.'_override'] = ['nullable', $fieldRules[1]];
        }
        Validator::make($this->getAttributes(), $rules)->validate();
    }

    protected static function booted(): void
    {
        static::saving(fn (self $product) => $product->validateAttributes());
        static::deleting(function (self $product): void {
            if ($product->sales()->exists()) {
                throw ValidationException::withMessages(['product' => 'This product has sales and cannot be deleted.']);
            }
        });
    }
}
