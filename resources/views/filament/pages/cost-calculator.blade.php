<x-filament-panels::page>
    <p>Estimates use current filament purchase prices and current global settings unless overridden. Simulation never changes stored data.</p>

    <form wire:submit="calculate" class="space-y-6">
        {{ $this->form }}
        <div class="flex flex-wrap gap-3">
            <x-filament::button type="submit" wire:loading.attr="disabled">Simulate costs</x-filament::button>
            <x-filament::button type="button" color="warning" wire:click="save" wire:confirm="Save these production parameters and overrides to the selected product?" wire:loading.attr="disabled">
                Save to selected product
            </x-filament::button>
        </div>
    </form>

    @if ($savedCosts)
        @include('filament.components.cost-breakdown', [
            'columns' => array_filter(['Saved product estimate' => $savedCosts, 'Temporary simulation' => $simulatedCosts]),
            'currency' => $currency,
        ])
    @endif
</x-filament-panels::page>
