<?php

namespace App\Filament\Resources\NewsletterSubscriptions\Tables;

use App\Services\GuestCommunicationWorkflow;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class NewsletterSubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')->weight('semibold')->copyable()->searchable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state): string => str($state)->title())->color(fn (string $state): string => $state === 'subscribed' ? 'success' : 'gray'),
                TextColumn::make('source')->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->title())->toggleable(),
                TextColumn::make('subscribed_at')->label('Subscribed')->dateTime()->since()->sortable(),
                TextColumn::make('unsubscribed_at')->label('Unsubscribed')->dateTime()->placeholder('Active')->sortable()->toggleable(),
                TextColumn::make('created_at')->label('First joined')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'subscribed' => 'Subscribed',
                    'unsubscribed' => 'Unsubscribed',
                ]),
            ])
            ->recordActions([
                Action::make('unsubscribe')
                    ->label('Unsubscribe')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record): bool => $record->status === 'subscribed')
                    ->requiresConfirmation()
                    ->action(fn ($record, Action $action) => tap(app(GuestCommunicationWorkflow::class)->updateSubscriptionStatus($record, 'unsubscribed', auth()->user()), fn () => $action->success()))
                    ->successNotificationTitle('Subscriber unsubscribed'),
                Action::make('resubscribe')
                    ->label('Resubscribe')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->visible(fn ($record): bool => $record->status === 'unsubscribed')
                    ->requiresConfirmation()
                    ->action(fn ($record, Action $action) => tap(app(GuestCommunicationWorkflow::class)->updateSubscriptionStatus($record, 'subscribed', auth()->user()), fn () => $action->success()))
                    ->successNotificationTitle('Subscriber reactivated'),
            ])
            ->emptyStateIcon('heroicon-o-paper-airplane')
            ->emptyStateHeading('No newsletter subscribers')
            ->emptyStateDescription('Member Getaway Rates subscriptions from the website footer will appear here.')
            ->defaultSort('subscribed_at', 'desc');
    }
}
