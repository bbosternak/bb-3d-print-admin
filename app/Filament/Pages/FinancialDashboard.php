<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Services\FinancialSummary;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Livewire\Attributes\Locked;

class FinancialDashboard extends Page
{
    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = 'Financial Dashboard';

    protected static ?int $navigationSort = -2;

    protected string $view = 'filament.pages.financial-dashboard';

    public ?array $data = [];

    #[Locked]
    public array $period = ['from' => null, 'until' => null, 'product_id' => null];

    public function mount(): void
    {
        $this->form->fill($this->period);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('from')->label('From date'),
            DatePicker::make('until')->label('Through date'),
            Select::make('product_id')->label('Sales product')
                ->options(fn () => Product::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()->rules(['nullable', 'exists:products,id'])
                ->helperText('Filters sales and printing estimates only. Expenses remain business-wide.'),
        ])->columns(3)->statePath('data');
    }

    public function applyFilters(): void
    {
        $data = $this->form->getState();
        app(FinancialSummary::class)->summarize(
            $data['from'] ?? null,
            $data['until'] ?? null,
            isset($data['product_id']) ? (int) $data['product_id'] : null,
        );
        $this->period = $data;
    }

    protected function getViewData(): array
    {
        $summary = app(FinancialSummary::class);

        return [
            'selected' => $summary->summarize(
                $this->period['from'] ?? null,
                $this->period['until'] ?? null,
                isset($this->period['product_id']) ? (int) $this->period['product_id'] : null,
            ),
            'allTime' => $summary->summarize(),
        ];
    }
}
