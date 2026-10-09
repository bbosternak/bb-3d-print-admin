<?php

namespace App\Filament\Resources\Shared;

use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;

class DateRangeFilter
{
    public static function make(string $column): Filter
    {
        return Filter::make('period')->label('Date range')->schema([
            DatePicker::make('from')->label('From')->rules(['nullable', 'date_format:Y-m-d']),
            DatePicker::make('until')->label('Until')->rules(['nullable', 'date_format:Y-m-d'])
                ->rule(fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                    $from = $get('from');
                    if (filled($from) && filled($value) && $value < $from) {
                        $fail('The until date must be on or after the from date.');
                    }
                }),
        ])->query(function (Builder $query, array $data, $livewire) use ($column): Builder {
            $statePath = ($livewire->getTable()->hasDeferredFilters() ? 'tableDeferredFilters' : 'tableFilters').'.period.';
            $livewire->resetErrorBag($statePath.'from');
            $livewire->resetErrorBag($statePath.'until');
            $validator = Validator::make($data, ['from' => ['nullable', 'date_format:Y-m-d'], 'until' => ['nullable', 'date_format:Y-m-d']]);
            if ($validator->fails()) {
                foreach ($validator->errors()->messages() as $field => $messages) {
                    $livewire->addError($statePath.$field, $messages[0]);
                }

                return $query->whereRaw('1 = 0');
            }
            if (filled($data['from'] ?? null) && filled($data['until'] ?? null) && $data['until'] < $data['from']) {
                $livewire->addError($statePath.'until', 'The until date must be on or after the from date.');

                return $query->whereRaw('1 = 0');
            }

            return $query
                ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate($column, '>=', $date))
                ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate($column, '<=', $date));
        })
            ->indicateUsing(fn (array $data): array => array_filter([
                filled($data['from'] ?? null) && filled($data['until'] ?? null) && $data['until'] < $data['from'] ? 'Invalid date range: until precedes from' : null,
                filled($data['from'] ?? null) ? 'From '.$data['from'] : null,
                filled($data['until'] ?? null) ? 'Until '.$data['until'] : null,
            ]));
    }
}
