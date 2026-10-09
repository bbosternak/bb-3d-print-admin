<?php

namespace App\Models;

use App\Support\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Validator;

class Sale extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['sale_date' => 'date', 'unit_price' => 'decimal:12', 'quantity' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function setSaleDateAttribute(mixed $value): void
    {
        $this->attributes['sale_date'] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
    }

    public function getRevenueAttribute(): string
    {
        return Decimal::mul($this->unit_price, (string) $this->quantity);
    }

    protected static function booted(): void
    {
        static::saving(function (self $sale): void {
            Validator::make($sale->getAttributes(), [
                'sale_date' => ['required', 'date'],
                'product_id' => ['required', Decimal::integerRule(), 'exists:products,id'],
                'unit_price' => ['required', Decimal::rule()],
                'quantity' => ['required', Decimal::integerRule()],
                'notes' => ['nullable', 'string'],
            ])->validate();
        });
    }
}
