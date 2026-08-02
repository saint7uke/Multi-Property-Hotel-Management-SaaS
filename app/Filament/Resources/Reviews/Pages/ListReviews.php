<?php

namespace App\Filament\Resources\Reviews\Pages;

use App\Filament\Resources\Reviews\ReviewResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListReviews extends ListRecords
{
    protected static string $resource = ReviewResource::class;

    public function getTitle(): string
    {
        return 'Guest review moderation';
    }

    public function getTabs(): array
    {
        $query = ReviewResource::getEloquentQuery();

        return [
            'pending' => Tab::make('Pending')
                ->badge((clone $query)->where('status', 'pending')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'pending')),
            'approved' => Tab::make('Published')
                ->badge((clone $query)->where('status', 'approved')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'approved')),
            'rejected' => Tab::make('Rejected')
                ->badge((clone $query)->where('status', 'rejected')->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'rejected')),
            'all' => Tab::make('All')
                ->badge((clone $query)->count()),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'pending';
    }
}
