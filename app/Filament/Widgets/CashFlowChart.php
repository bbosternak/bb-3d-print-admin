<?php

namespace App\Filament\Widgets;

use App\Services\FinancialSummary;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Locked;

class CashFlowChart extends ChartWidget
{
    protected ?string $heading = 'Recorded revenue versus expenses (EUR, monthly)';

    protected static bool $isDiscovered = false;

    #[Locked]
    public ?string $from = null;

    #[Locked]
    public ?string $until = null;

    #[Locked]
    public ?int $productId = null;

    protected function getData(): array
    {
        $periods = app(FinancialSummary::class)->summarize($this->from, $this->until, $this->productId)['periods'];

        return [
            'datasets' => [
                ['label' => 'Recorded revenue', 'data' => array_column($periods, 'revenue'), 'borderColor' => '#16a34a'],
                ['label' => 'Recorded expenses', 'data' => array_column($periods, 'expenses'), 'borderColor' => '#dc2626'],
            ],
            'labels' => array_keys($periods),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
