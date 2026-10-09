<?php

namespace App\Models;

use App\Support\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProductFilament extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['grams' => 'decimal:12'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function filament(): BelongsTo
    {
        return $this->belongsTo(Filament::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $usage): void {
            Validator::make($usage->getAttributes(), [
                'product_id' => ['required', Decimal::integerRule(), 'exists:products,id'],
                'filament_id' => [
                    'required', Decimal::integerRule(), 'exists:filaments,id',
                    Rule::unique('product_filaments', 'filament_id')->where('product_id', $usage->product_id)->ignore($usage->id),
                ],
                'grams' => ['required', Decimal::rule(true)],
            ])->validate();
        });
    }
}
