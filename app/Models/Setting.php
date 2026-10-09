<?php

namespace App\Models;

use App\Support\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class Setting extends Model
{
    public const DEFAULTS = [
        'currency' => 'EUR',
        'electricity_price' => '0.17',
        'printer_power' => '150',
        'depreciation_rate' => '0.25',
        'labor_rate' => '10',
        'processing_minutes' => '15',
        'packaging_cost' => '0',
        'fee_percentage' => '0',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return array_fill_keys(array_diff(array_keys(self::DEFAULTS), ['currency']), 'decimal:12');
    }

    public static function current(): self
    {
        return self::query()->findOrFail(1);
    }

    public static function financialRules(): array
    {
        $rules = [];
        foreach (array_diff(array_keys(self::DEFAULTS), ['currency']) as $field) {
            $rules[$field] = ['required', Decimal::rule(false, $field === 'fee_percentage' ? '100' : '999999999999.999999999999')];
        }

        return $rules;
    }

    protected static function booted(): void
    {
        static::saving(function (self $setting): void {
            $setting->id ??= 1;
            Validator::make($setting->getAttributes(), [
                'id' => ['required', 'in:1'],
                'currency' => ['required', 'in:EUR'],
                ...self::financialRules(),
            ])->validate();
        });
        static::deleting(function (): void {
            throw ValidationException::withMessages(['setting' => 'The current settings cannot be deleted.']);
        });
    }
}
