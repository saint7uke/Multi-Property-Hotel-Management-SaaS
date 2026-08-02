<?php

namespace App\Filament\Resources\Reviews\Tables;

use App\Services\ReviewWorkflow;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('guest.name')
                    ->label('Guest')
                    ->weight('semibold')
                    ->description(fn ($record): string => $record->guest?->email ?? '')
                    ->searchable(['name', 'email']),
                TextColumn::make('property.name')
                    ->label('Property')
                    ->searchable()
                    ->visible(fn (): bool => auth()->user()?->hasRole('admin') ?? false),
                TextColumn::make('rating')
                    ->badge()
                    ->suffix(' / 5')
                    ->color(fn (int $state): string => match (true) {
                        $state >= 5 => 'success',
                        $state >= 3 => 'warning',
                        default => 'danger',
                    })
                    ->sortable(),
                TextColumn::make('stay_type')
                    ->label('Stay type')
                    ->formatStateUsing(fn (?string $state): string => $state ? str($state)->replace('_', ' ')->title() : 'Not provided')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('message')
                    ->label('Review')
                    ->limit(90)
                    ->wrap()
                    ->tooltip(fn ($record): string => $record->message)
                    ->searchable(),
                TextColumn::make('reservation.reference_number')
                    ->label('Booking reference')
                    ->placeholder('Unverified submission')
                    ->copyable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reservation_id')
                    ->label('Stay')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state ? 'Verified stay' : 'Unverified')
                    ->color(fn ($state): string => $state ? 'success' : 'gray'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->title())
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('moderatedBy.name')
                    ->label('Last moderated by')
                    ->placeholder('Not reviewed')
                    ->sortable(),
                TextColumn::make('moderated_at')
                    ->label('Moderated')
                    ->dateTime()
                    ->since()
                    ->placeholder('Not reviewed')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('moderation_notes')
                    ->label('Internal notes')
                    ->limit(60)
                    ->placeholder('None')
                    ->tooltip(fn ($record): ?string => $record->moderation_notes)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('reservation_id')
                    ->label('Verified stay')
                    ->trueLabel('Verified stays only')
                    ->falseLabel('Unverified only')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('reservation_id'),
                        false: fn (Builder $query): Builder => $query->whereNull('reservation_id'),
                    ),
                SelectFilter::make('property')
                    ->relationship('property', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn (): bool => auth()->user()?->hasRole('admin') ?? false),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record): bool => $record->status === 'pending')
                    ->modalHeading('Approve and publish review')
                    ->modalDescription(fn ($record): string => $record->message)
                    ->schema([
                        Textarea::make('moderation_notes')
                            ->label('Internal notes')
                            ->helperText('Optional. These notes are never shown publicly.')
                            ->rows(3)
                            ->maxLength(1000),
                    ])
                    ->action(function ($record, array $data, Action $action): void {
                        app(ReviewWorkflow::class)->moderate($record, 'approved', auth()->user(), $data['moderation_notes'] ?? null);
                        $action->success();
                    })
                    ->successNotificationTitle('Review approved and published'),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record): bool => $record->status === 'pending')
                    ->modalHeading('Reject review')
                    ->modalDescription(fn ($record): string => $record->message)
                    ->schema([
                        Textarea::make('moderation_notes')
                            ->label('Internal reason')
                            ->helperText('Required for accountability. This reason is not shown publicly.')
                            ->rows(3)
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->action(function ($record, array $data, Action $action): void {
                        app(ReviewWorkflow::class)->moderate($record, 'rejected', auth()->user(), $data['moderation_notes']);
                        $action->success();
                    })
                    ->successNotificationTitle('Review rejected'),
                Action::make('reopen')
                    ->label('Reopen')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn ($record): bool => in_array($record->status, ['approved', 'rejected'], true))
                    ->requiresConfirmation()
                    ->modalHeading('Return review to moderation queue')
                    ->modalDescription('The review will no longer be published until it is approved again.')
                    ->action(function ($record, Action $action): void {
                        app(ReviewWorkflow::class)->moderate($record, 'pending', auth()->user());
                        $action->success();
                    })
                    ->successNotificationTitle('Review returned to moderation queue'),
            ])
            ->emptyStateIcon('heroicon-o-star')
            ->emptyStateHeading('No guest reviews found')
            ->emptyStateDescription('New guest submissions will appear here for moderation.')
            ->defaultSort('created_at', 'desc');
    }
}
