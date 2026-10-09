<x-filament::section heading="Per-unit cost and profit estimates">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <caption class="sr-only">Cost, weight, time and profitability comparison</caption>
            <thead>
                <tr>
                    <th scope="col" class="p-3 text-left">Metric</th>
                    @foreach ($columns as $name => $costs)
                        <th scope="col" class="p-3 text-right">{{ $name }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach (\App\Filament\Schemas\SimulationForm::METRICS as $metric => $label)
                    <tr class="border-t">
                        <th scope="row" class="whitespace-nowrap p-3 text-left">{{ $label }}</th>
                        @foreach ($columns as $costs)
                            @php
                                $value = $costs[$metric] ?? null;
                                if (($metric === 'margin' && \App\Support\Decimal::compare($costs['selling_price'], '0') === 0)
                                    || ($metric === 'profit_per_printing_hour' && \App\Support\Decimal::compare($costs['printing_hours'], '0') === 0)
                                    || ($metric === 'profit_per_total_hour' && \App\Support\Decimal::compare(\App\Support\Decimal::add($costs['printing_hours'], $costs['processing_hours']), '0') === 0)) {
                                    $value = null;
                                }
                                $isMoney = ! in_array($metric, ['margin', 'printing_hours', 'processing_hours', 'filament_grams', 'printing_seconds', 'processing_minutes'], true);
                                $negative = $value !== null && \App\Support\Decimal::compare($value, '0') < 0;
                                $formatted = $value === null ? null : ($metric === 'printing_seconds'
                                    ? \App\Support\Decimal::duration($value)
                                    : \App\Support\Decimal::round($value, $isMoney ? 2 : 6));
                            @endphp
                            <td class="whitespace-nowrap p-3 text-right">
                                @if ($value === null)
                                    Not defined
                                @elseif ($negative)
                                    <x-filament::badge color="danger">{{ $isMoney ? $currency.' ' : '' }}{{ $formatted }}{{ $metric === 'margin' ? '%' : '' }}</x-filament::badge>
                                @else
                                    {{ $isMoney ? $currency.' ' : '' }}{{ $formatted }}{{ $metric === 'margin' ? '%' : '' }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="mt-3 text-sm">Undefined margins or hourly profits are shown as “Not defined”, never ranked as zero.</p>
</x-filament::section>
