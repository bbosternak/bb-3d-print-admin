<?php

namespace App\Filament\Resources\Shared;

use App\Support\Decimal;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\StateCasts\NumberStateCast;

class DecimalInput extends TextInput
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->numeric()->rule(fn (): \Closure => Decimal::rule());
        $this->dehydrateStateUsing(fn (mixed $state): ?string => blank($state) ? null : (string) $state);
    }

    public function getDefaultStateCasts(): array
    {
        // Filament's numeric cast converts exact decimal strings to floats.
        return array_values(array_filter(parent::getDefaultStateCasts(), fn ($cast): bool => ! $cast instanceof NumberStateCast));
    }
}
