<x-filament-panels::page>
    <form wire:submit="applyFilters" class="space-y-4">
        {{ $this->form }}
        <x-filament::button type="submit">Apply date range and product filter</x-filament::button>
    </form>

    <p>Recorded cash flow is revenue minus recorded expenses, not accounting profit.
        Production costs below are estimates using current product parameters and settings and are never deducted automatically.</p>

    @foreach (['Selected period' => $selected, 'All time · all products' => $allTime] as $heading => $totals)
        <x-filament::section :heading="$heading">
            @if ($heading === 'Selected period')
                <p>{{ $this->period['from'] ?: 'Beginning' }} — {{ $this->period['until'] ?: 'Present' }}</p>
            @endif
            <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach (['revenue' => 'Recorded sales revenue', 'expenses' => 'Recorded expenses', 'cash_flow' => 'Net cash flow', 'average_price' => 'Average selling price per unit', 'electricity_cost' => 'Estimated electricity for sales'] as $key => $label)
                    <div>
                        <dt>{{ $label }}</dt>
                        <dd @class(['font-semibold', 'text-danger-600' => \App\Support\Decimal::compare($totals[$key], '0') < 0])>
                            {{ \App\Support\Decimal::money($totals[$key]) }}
                        </dd>
                    </div>
                @endforeach
                <div><dt>Units sold</dt><dd>{{ $totals['units'] }}</dd></div>
                <div><dt>Estimated printing time for sales</dt><dd>{{ \App\Support\Decimal::duration(\App\Support\Decimal::mul($totals['printing_hours'], '3600')) }} ({{ \App\Support\Decimal::round($totals['printing_hours'], 2) }} hours)</dd></div>
            </dl>
            <h3 class="mt-4 font-semibold">Recorded expenses by category</h3>
            <dl class="grid gap-2 sm:grid-cols-2">
                @foreach ($totals['categories'] as $category => $amount)
                    <div><dt>{{ $category }}</dt><dd>{{ \App\Support\Decimal::money($amount) }}</dd></div>
                @endforeach
            </dl>
        </x-filament::section>
    @endforeach

    @livewire(\App\Filament\Widgets\CashFlowChart::class, [
        'from' => $this->period['from'] ?? null,
        'until' => $this->period['until'] ?? null,
        'productId' => isset($this->period['product_id']) ? (int) $this->period['product_id'] : null,
    ], key('cash-flow-'.md5(json_encode($this->period))))

    <x-filament::section heading="Selected-period recorded revenue and expenses by month">
        <div style="overflow-x: auto">
            <table class="w-full text-left">
                <thead><tr><th>Month</th><th>Revenue</th><th>Expenses</th><th>Net cash flow</th></tr></thead>
                <tbody>
                    @forelse ($selected['periods'] as $month => $amounts)
                        @php($cash = \App\Support\Decimal::sub($amounts['revenue'], $amounts['expenses']))
                        <tr>
                            <td>{{ $month }}</td>
                            <td>{{ \App\Support\Decimal::money($amounts['revenue']) }}</td>
                            <td>{{ \App\Support\Decimal::money($amounts['expenses']) }}</td>
                            <td @class(['text-danger-600' => \App\Support\Decimal::compare($cash, '0') < 0])>{{ \App\Support\Decimal::money($cash) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4">No recorded transactions in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section heading="Selected-period most frequently sold products and printing estimates">
        <div style="overflow-x: auto">
            <table class="w-full text-left">
                <thead><tr><th>Product</th><th>Units sold</th><th>Recorded revenue</th><th>Estimated printing time</th><th>Estimated electricity</th></tr></thead>
                <tbody>
                    @forelse ($selected['products'] as $product)
                        <tr>
                            <td>{{ $product['name'] }}</td>
                            <td>{{ $product['units'] }}</td>
                            <td>{{ \App\Support\Decimal::money($product['revenue']) }}</td>
                            <td>{{ \App\Support\Decimal::duration(\App\Support\Decimal::mul($product['printing_hours'], '3600')) }}</td>
                            <td>{{ \App\Support\Decimal::money($product['electricity_cost']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No sales in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
