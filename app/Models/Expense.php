<?php

namespace App\Models;

use App\Support\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class Expense extends Model
{
    public const CATEGORIES = ['Material', 'Equipment', 'Tools', 'Shipping Costs', 'Other'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date', 'amount' => 'decimal:12'];
    }

    public function setDateAttribute(mixed $value): void
    {
        $this->attributes['date'] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
    }

    protected static function booted(): void
    {
        static::saving(function (self $expense): void {
            Validator::make($expense->getAttributes(), [
                'date' => ['required', 'date'],
                'description' => ['required', 'string', 'max:255'],
                'category' => ['required', Rule::in(self::CATEGORIES)],
                'amount' => ['required', Decimal::rule()],
                'notes' => ['nullable', 'string'],
            ])->validate();
        });
    }
}
