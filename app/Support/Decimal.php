<?php

namespace App\Support;

use Closure;
use Illuminate\Validation\ValidationException;

final class Decimal
{
    public const SCALE = 36;

    public static function add(string $a, string $b): string
    {
        return bcadd($a, $b, self::SCALE);
    }

    public static function sub(string $a, string $b): string
    {
        return bcsub($a, $b, self::SCALE);
    }

    public static function mul(string $a, string $b): string
    {
        return bcmul($a, $b, self::SCALE);
    }

    public static function div(string $a, string $b): string
    {
        if (self::compare($b, '0') === 0) {
            throw ValidationException::withMessages(['denominator' => 'The denominator must not be zero.']);
        }

        return bcdiv($a, $b, self::SCALE);
    }

    public static function compare(string $a, string $b): int
    {
        return bccomp($a, $b, self::SCALE);
    }

    public static function round(string $value, int $scale = 2): string
    {
        if ($scale < 0 || $scale > self::SCALE) {
            throw ValidationException::withMessages(['scale' => 'Invalid decimal scale.']);
        }

        $half = $scale === 0 ? '0.5' : '0.'.str_repeat('0', $scale).'5';
        $adjusted = self::compare($value, '0') < 0
            ? self::sub($value, $half)
            : self::add($value, $half);

        return bcadd($adjusted, '0', $scale);
    }

    public static function money(string $value): string
    {
        return '€'.self::round($value);
    }

    public static function duration(string $seconds): string
    {
        if (self::compare($seconds, '0') < 0) {
            return '-'.self::duration(self::sub('0', $seconds));
        }

        $seconds = self::round($seconds, 2);
        $hours = bcdiv($seconds, '3600', 0);
        $remaining = self::sub($seconds, self::mul($hours, '3600'));
        $minutes = bcdiv($remaining, '60', 0);
        $seconds = self::trim(self::sub($remaining, self::mul($minutes, '60')));
        $parts = [];
        if ($hours !== '0') {
            $parts[] = $hours.'h';
        }
        if ($minutes !== '0') {
            $parts[] = $minutes.'m';
        }
        if ($seconds !== '0' || $parts === []) {
            $parts[] = $seconds.'s';
        }

        return implode(' ', $parts);
    }

    public static function trim(string $value): string
    {
        $value = str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;

        return $value === '-0' ? '0' : $value;
    }

    public static function rule(bool $positive = false, string $maximum = '999999999999.999999999999'): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($positive, $maximum): void {
            if ((! is_string($value) && ! is_int($value))
                || ! preg_match('/^\d{1,12}(?:\.\d{1,12})?$/D', (string) $value)
                || self::compare((string) $value, '0') < ($positive ? 1 : 0)
                || self::compare((string) $value, $maximum) > 0) {
                $fail("The {$attribute} must be a ".($positive ? 'positive' : 'nonnegative').' decimal with at most 12 integer and 12 fractional digits.');
            }
        };
    }

    public static function integerRule(bool $positive = true): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($positive): void {
            if ((! is_string($value) && ! is_int($value))
                || ! preg_match('/^\d+$/D', (string) $value)
                || bccomp((string) $value, $positive ? '1' : '0', 0) < 0
                || bccomp((string) $value, '2147483647', 0) > 0) {
                $fail("The {$attribute} must be a ".($positive ? 'positive' : 'nonnegative').' integer no greater than 2147483647.');
            }
        };
    }

    public static function ceilTo(string $value, string $increment): string
    {
        $steps = bcdiv($value, $increment, 0);
        $rounded = self::mul($steps, $increment);
        if (self::compare($rounded, $value) < 0) {
            $rounded = self::add($rounded, $increment);
        }

        return $rounded;
    }
}
