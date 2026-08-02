<?php

namespace App\Filament\Resources\Properties\Tables;

use App\Filament\Resources\Properties\PropertyResource;
use App\Services\PropertyLifecycleWorkflow;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PropertiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('hero_image_path')
                    ->label('Hero')
                    ->disk('public')
                    ->imageSize(44)
                    ->square(),
                TextColumn::make('name')
                    ->weight('semibold')
                    ->description(fn ($record): string => $record->city.', '.$record->country)
                    ->searchable(['name', 'city', 'country'])
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('amenities')
                    ->label('Amenities')
                    ->state(fn ($record): int => count($record->amenities ?? []))
                    ->badge()
                    ->color('gray'),
                IconColumn::make('offers_breakfast')
                    ->label('Breakfast')
                    ->boolean(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->title())
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray'),
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
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active and public',
                        'inactive' => 'Inactive and hidden',
                    ]),
                TernaryFilter::make('offers_breakfast')
                    ->label('Breakfast availability')
                    ->trueLabel('Offers breakfast')
                    ->falseLabel('No breakfast'),
            ])
            ->recordActions([
                Action::make('viewPublicPage')
                    ->label('View public page')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn ($record): string => route('hotels.show', $record))
                    ->openUrlInNewTab()
                    ->visible(fn ($record): bool => $record->status === 'active'),
                EditAction::make(),
                Action::make('deactivate')
                    ->label('Deactivate')
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->visible(fn ($record): bool => auth()->user()?->hasRole('admin') && $record->status === 'active')
                    ->requiresConfirmation()
                    ->modalHeading('Deactivate property')
                    ->modalDescription('The property will be hidden from public hotel, booking, and contact pages. Existing operational records remain available to authorized staff.')
                    ->action(function ($record, Action $action): void {
                        app(PropertyLifecycleWorkflow::class)->setStatus($record, 'inactive', auth()->user());
                        $action->success();
                    })
                    ->successNotificationTitle('Property deactivated'),
                Action::make('activate')
                    ->label('Reactivate')
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->visible(fn ($record): bool => auth()->user()?->hasRole('admin') && $record->status === 'inactive')
                    ->requiresConfirmation()
                    ->modalDescription('The property will become available on public hotel and booking pages again.')
                    ->action(function ($record, Action $action): void {
                        app(PropertyLifecycleWorkflow::class)->setStatus($record, 'active', auth()->user());
                        $action->success();
                    })
                    ->successNotificationTitle('Property reactivated'),
                DeleteAction::make()
                    ->label('Delete permanently')
                    ->visible(fn ($record): bool => PropertyResource::canDelete($record))
                    ->modalHeading('Delete unused property')
                    ->modalDescription('This permanently removes the property and its uploaded images. Only inactive properties without rooms, staff, bookings, reviews, or chat history can be deleted.')
                    ->using(fn ($record): bool => app(PropertyLifecycleWorkflow::class)->delete($record, auth()->user())),
            ])
            ->emptyStateIcon('heroicon-o-building-office-2')
            ->emptyStateHeading('No properties found')
            ->emptyStateDescription('Create a property or adjust the active filters.')
            ->defaultSort('name');
    }
}
