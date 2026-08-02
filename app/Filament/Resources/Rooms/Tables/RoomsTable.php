<?php

namespace App\Filament\Resources\Rooms\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RoomsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('property.name')
                    ->label('Property')
                    ->searchable()
                    ->sortable()
                    ->visible(fn (): bool => auth()->user()?->hasRole('admin') ?? false),
                TextColumn::make('room_number')
                    ->label('Room')
                    ->weight('semibold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Room type')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('rate')
                    ->label('Nightly rate')
                    ->money('PHP')
                    ->sortable(),
                TextColumn::make('capacity')
                    ->label('Guests')
                    ->suffix(' guests')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->title())
                    ->color(fn (string $state): string => match ($state) {
                        'available', 'ready' => 'success',
                        'occupied' => 'primary',
                        'dirty', 'out_of_service' => 'danger',
                        'cleaning', 'clean', 'inspected' => 'warning',
                        default => 'gray',
                    }),
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
                SelectFilter::make('property')
                    ->relationship('property', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn (): bool => auth()->user()?->hasRole('admin') ?? false),
                SelectFilter::make('status')
                    ->options([
                        'available' => 'Available',
                        'ready' => 'Ready',
                        'occupied' => 'Occupied',
                        'dirty' => 'Dirty',
                        'cleaning' => 'Cleaning in progress',
                        'clean' => 'Clean',
                        'inspected' => 'Inspected',
                        'out_of_service' => 'Out of service',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalHeading('Remove room')
                    ->modalDescription('Only rooms without reservation history can be removed. This action cannot be undone.'),
            ])
            ->emptyStateIcon('heroicon-o-home-modern')
            ->emptyStateHeading('No rooms found')
            ->emptyStateDescription('Create a room or adjust the active filters.')
            ->defaultSort('room_number');
    }
}
