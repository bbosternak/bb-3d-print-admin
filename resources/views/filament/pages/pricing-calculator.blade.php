<x-filament-panels::page>
    <p>Price estimates use current filament purchase prices and global settings unless overridden. Neither temporary products nor suggested prices are saved.</p>

    <form wire:submit="calculate" class="space-y-6">
        {{ $this->form }}
        <x-filament::button type="submit" wire:loading.attr="disabled">Calculate suggested price</x-filament::button>
    </form>

    @if ($result && $roundedCosts)
        <x-filament::section heading="Suggested selling price">
            <dl class="space-y-2">
                <div>
                    <dt>Exact price (before upward rounding)</dt>
                    <dd>{{ $currency }} {{ $result['exact_price'] }}</dd>
                </div>
                <div>
                    <dt>Rounded upward selling price</dt>
                    <dd>{{ $currency }} {{ $result['rounded_price'] }}</dd>
                </div>
            </dl>
        </x-filament::section>
        @include('filament.components.cost-breakdown', [
            'columns' => ['Actual results at the rounded price' => $roundedCosts],
            'currency' => $currency,
        ])
    @endif
</x-filament-panels::page>
