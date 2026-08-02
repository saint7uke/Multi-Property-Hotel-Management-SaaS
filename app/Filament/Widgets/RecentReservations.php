<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Reservations\ReservationResource;
use App\Filament\Widgets\Concerns\ScopesHotelDashboard;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentReservations extends TableWidget
{
    use ScopesHotelDashboard;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('reservations.view') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recent bookings')
            ->description('Newest personal and event / group requests')
            ->query(
                $this->reservationsQuery()
                    ->with(['guest', 'property', 'room'])
                    ->latest()
                    ->limit(8)
            )
            ->columns([
                TextColumn::make('reference_number')
                    ->label('Reference')
                    ->searchable()
                    ->copyable()
                    ->weight('medium'),
                TextColumn::make('guest.name')
                    ->label('Guest')
                    ->searchable()
                    ->description(fn ($record): string => $record->booking_type === 'event'
                        ? ($record->event_name ?: 'Event / group request')
                        : ($record->room?->room_number ? 'Room '.$record->room->room_number : 'Room request')),
                TextColumn::make('property.name')
                    ->label('Property')
                    ->visible(fn (): bool => $this->dashboardUser()?->hasRole('admin') ?? false),
                TextColumn::make('booking_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'event' ? 'Event / Group' : 'Personal')
                    ->color(fn (string $state): string => $state === 'event' ? 'info' : 'gray'),
                TextColumn::make('check_in')
                    ->label('Arrival')
                    ->date('M j, Y')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->title()->toString())
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'confirmed' => 'info',
                        'checked_in' => 'primary',
                        'checked_out' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label('Received')
                    ->since()
                    ->dateTimeTooltip(),
            ])
            ->headerActions([
                Action::make('viewAll')
                    ->label('View all bookings')
                    ->icon(Heroicon::OutlinedArrowRight)
                    ->url(ReservationResource::getUrl('index')),
            ])
            ->recordUrl(fn ($record): string => ReservationResource::getUrl('index', ['tableSearch' => $record->reference_number]))
            ->emptyStateHeading('No bookings yet')
            ->emptyStateDescription('New website and staff bookings will appear here.')
            ->emptyStateIcon(Heroicon::OutlinedCalendarDays)
            ->paginated(false);
    }
}
