<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Shared\DecimalInput;
use App\Models\Setting;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class ApplicationSettings extends Page
{
    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Application settings';

    protected string $view = 'filament.pages.application-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(Setting::current()->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            TextInput::make('currency')->default('EUR')->disabled()->dehydrated(false)
                ->helperText('All prices are in EUR.'),
            DecimalInput::make('electricity_price')->minValue(0)->required()->suffix('€/kWh'),
            DecimalInput::make('printer_power')->minValue(0)->required()->suffix('W'),
            DecimalInput::make('depreciation_rate')->minValue(0)->required()->suffix('€/printing hour'),
            DecimalInput::make('labor_rate')->minValue(0)->required()->suffix('€/hour'),
            DecimalInput::make('processing_minutes')->minValue(0)->required()->suffix('minutes/item'),
            DecimalInput::make('packaging_cost')->minValue(0)->required()->suffix('€/item'),
            DecimalInput::make('fee_percentage')->minValue(0)->maxValue(100)->required()->suffix('%'),
        ])->columns(2);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        Setting::current()->update([...$data, 'currency' => 'EUR']);
        Notification::make()->title('Settings saved')->success()->send();
    }
}
