<?php

namespace App\Filament\Resources\ContactInquiries\Tables;

use App\Services\GuestCommunicationWorkflow;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ContactInquiriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference_number')->label('Reference')->copyable()->searchable()->weight('semibold'),
                TextColumn::make('full_name')->label('Guest')->description(fn ($record): string => $record->email)->searchable(['full_name', 'email']),
                TextColumn::make('inquiry_type')->label('Type')->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->title())->badge(),
                TextColumn::make('property.name')->label('Property')->placeholder('Group-wide')->searchable(),
                TextColumn::make('message')->limit(70)->wrap()->tooltip(fn ($record): string => $record->message)->searchable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->title())->color(fn (string $state): string => match ($state) {
                    'resolved' => 'success',
                    'spam' => 'danger',
                    'in_progress' => 'info',
                    default => 'warning',
                }),
                TextColumn::make('assignee.name')->label('Assigned to')->placeholder('Unassigned')->toggleable(),
                TextColumn::make('created_at')->label('Received')->dateTime()->since()->description(fn ($record): string => $record->created_at->format('M j, Y g:i A'))->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'new' => 'New',
                    'in_progress' => 'In progress',
                    'resolved' => 'Resolved',
                    'spam' => 'Spam',
                ]),
                SelectFilter::make('inquiry_type')->options([
                    'room_reservation' => 'Room reservation',
                    'events' => 'Events',
                    'group_booking' => 'Group booking',
                    'guest_services' => 'Guest services',
                    'partnership' => 'Partnership',
                    'other' => 'Other',
                ]),
            ])
            ->recordActions([
                Action::make('start')
                    ->label('Start handling')
                    ->icon('heroicon-o-play')
                    ->visible(fn ($record): bool => $record->status === 'new')
                    ->action(fn ($record, Action $action) => tap(app(GuestCommunicationWorkflow::class)->updateInquiryStatus($record, 'in_progress', auth()->user()), fn () => $action->success()))
                    ->successNotificationTitle('Inquiry assigned to you'),
                Action::make('resolve')
                    ->label('Resolve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record): bool => in_array($record->status, ['new', 'in_progress'], true))
                    ->requiresConfirmation()
                    ->action(fn ($record, Action $action) => tap(app(GuestCommunicationWorkflow::class)->updateInquiryStatus($record, 'resolved', auth()->user()), fn () => $action->success()))
                    ->successNotificationTitle('Inquiry resolved'),
                Action::make('spam')
                    ->label('Mark spam')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn ($record): bool => $record->status !== 'spam')
                    ->requiresConfirmation()
                    ->action(fn ($record, Action $action) => tap(app(GuestCommunicationWorkflow::class)->updateInquiryStatus($record, 'spam', auth()->user()), fn () => $action->success()))
                    ->successNotificationTitle('Inquiry marked as spam'),
                Action::make('reopen')
                    ->label('Reopen')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn ($record): bool => in_array($record->status, ['resolved', 'spam'], true))
                    ->action(fn ($record, Action $action) => tap(app(GuestCommunicationWorkflow::class)->updateInquiryStatus($record, 'new', auth()->user()), fn () => $action->success()))
                    ->successNotificationTitle('Inquiry reopened'),
            ])
            ->emptyStateIcon('heroicon-o-envelope')
            ->emptyStateHeading('No guest inquiries')
            ->emptyStateDescription('New General Inquiry submissions from the contact page will appear here.')
            ->defaultSort('created_at', 'desc');
    }
}
