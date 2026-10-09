<?php

namespace App\Filament\Resources\Shared;

use App\Support\Decimal;

class IntegerInput extends DecimalInput
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->integer()->rule(fn (): \Closure => Decimal::integerRule(false));
    }
}
