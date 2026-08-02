<?php

namespace App\Filament\Resources\Reservations\Tables;

use App\Services\ReservationWorkflow;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReservationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference_number')
                    ->label('Reference')
                    ->weight('semibold')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('guest.name')
                    ->label('Guest')
                    ->description(fn ($record): string => $record->guest?->email ?? '')
                    ->searchable(['name', 'email']),
                TextColumn::make('property.name')
                    ->label('Property')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('room.room_number')
                    ->label('Room')
                    ->placeholder('Event / group')
                    ->searchable(),
                TextColumn::make('booking_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'event' ? 'Event / Group' : 'Personal')
                    ->color(fn (string $state): string => $state === 'event' ? 'warning' : 'info'),
                TextColumn::make('event_name')
                    ->label('Event')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('check_in')
                    ->label('Arrival')
                    ->date()
                    ->sortable(),
                TextColumn::make('check_out')
                    ->label('Departure')
                    ->date()
                    ->sortable(),
                TextColumn::make('adults')
                    ->label('Adults')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('children')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->title())
                    ->color(fn (string $state): string => match ($state) {
                        'confirmed' => 'info',
                        'checked_in' => 'primary',
                        'checked_out' => 'success',
                        'cancelled' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->title())
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'failed', 'refunded' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('estimated_total')
                    ->label('Total')
                    ->money('PHP')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('source')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->title())
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(['pending' => 'Pending', 'confirmed' => 'Confirmed', 'checked_in' => 'Checked in', 'checked_out' => 'Checked out', 'cancelled' => 'Cancelled']),
                SelectFilter::make('booking_type')->options(['personal' => 'Personal', 'event' => 'Event / Group']),
                SelectFilter::make('property')->relationship('property', 'name')->visible(fn () => auth()->user()?->hasRole('admin')),
            ])
            ->recordActions([
                Action::make('changeStatus')
                    ->label('Update status')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn ($record): bool => (auth()->user()?->can('reservations.manage') ?? false)
                        && app(ReservationWorkflow::class)->availableStatuses($record) !== [])
                    ->schema([
                        Select::make('status')
                            ->label('Next status')
                            ->options(fn ($record): array => app(ReservationWorkflow::class)->availableStatuses($record))
                            ->native(false)
                            ->required(),
                    ])
                    ->action(function ($record, array $data, Action $action): void {
                        app(ReservationWorkflow::class)->updateStatus($record, $data['status'], auth()->user());
                        $action->success();
                    })
                    ->successNotificationTitle('Reservation status updated'),
            ])
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->emptyStateHeading('No reservations found')
            ->emptyStateDescription('Create a reservation or adjust the active filters.')
            ->defaultSort('created_at', 'desc');
    }
}
