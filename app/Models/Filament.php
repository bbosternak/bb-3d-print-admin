<?php

namespace App\Models;

use App\Support\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class Filament extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['purchase_price' => 'decimal:12', 'spool_weight' => 'decimal:12'];
    }

    public function usages(): HasMany
    {
        return $this->hasMany(ProductFilament::class);
    }

    public function getNameAttribute(): string
    {
        return implode(' · ', array_filter([$this->brand, $this->material, $this->color], fn ($value) => $value !== null && $value !== ''));
    }

    public function getPricePerKgAttribute(): string
    {
        return Decimal::mul(Decimal::div($this->purchase_price, $this->spool_weight), '1000');
    }

    protected static function booted(): void
    {
        static::saving(function (self $filament): void {
            Validator::make($filament->getAttributes(), [
                'brand' => ['required', 'string', 'max:255'],
                'material' => ['required', 'string', 'max:255'],
                'color' => ['required', 'string', 'max:255'],
                'purchase_price' => ['required', Decimal::rule()],
                'spool_weight' => ['required', Decimal::rule(true)],
                'notes' => ['nullable', 'string'],
            ])->validate();
        });
        static::deleting(function (self $filament): void {
            if ($filament->usages()->exists()) {
                throw ValidationException::withMessages(['filament' => 'This filament is used by a product and cannot be deleted.']);
            }
        });
    }
}
