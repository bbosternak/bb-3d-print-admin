<x-filament-panels::page>
    <p>Estimates use current filament prices and current settings. Each comparison reloads saved production parameters. Selling price overrides are temporary and never saved.</p>

    <form wire:submit="calculate" class="space-y-6">
        {{ $this->form }}
        <x-filament::button type="submit" wire:loading.attr="disabled">Compare products</x-filament::button>
    </form>

    @if ($comparison)
        <x-filament::section heading="Profit leaders">
            <p>Highest profit / unit:
                @foreach ($unitWinners as $id)
                    <x-filament::badge color="success">{{ $comparison[$id]['name'] }} (#{{ $id }})</x-filament::badge>
                @endforeach
            </p>
            <p>Highest profit / printing hour:
                @forelse ($hourWinners as $id)
                    <x-filament::badge color="success">{{ $comparison[$id]['name'] }} (#{{ $id }})</x-filament::badge>
                @empty
                    Not defined — no positive printing time.
                @endforelse
            </p>
            <p>Products with zero printing time are excluded from the printing-hour ranking. Ties share the lead.</p>
        </x-filament::section>
        @include('filament.components.cost-breakdown', [
            'columns' => collect($comparison)->mapWithKeys(fn ($row, $id) => [$row['name'].' (#'.$id.')' => $row['costs']])->all(),
            'currency' => $currency,
        ])
    @endif
</x-filament-panels::page>
