<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    public function getTitle(): string
    {
        return 'Payment reconciliation';
    }

    public function getTabs(): array
    {
        $query = PaymentResource::getEloquentQuery();

        return [
            'pending' => Tab::make('Pending verification')
                ->badge((clone $query)->where('status', 'pending')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'pending')),
            'paid' => Tab::make('Paid')
                ->badge((clone $query)->where('status', 'paid')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'paid')),
            'attention' => Tab::make('Needs attention')
                ->badge((clone $query)->whereIn('status', ['failed', 'cancelled'])->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', ['failed', 'cancelled'])),
            'refunded' => Tab::make('Refunded')
                ->badge((clone $query)->where('status', 'refunded')->count())
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'refunded')),
            'all' => Tab::make('All')
                ->badge((clone $query)->count()),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'pending';
    }
}
