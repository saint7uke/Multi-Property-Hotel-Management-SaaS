<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Models\Property;
use App\Services\PaymentWorkflow;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reservation.reference_number')
                    ->label('Reservation')
                    ->weight('semibold')
                    ->description(fn ($record): string => $record->reservation?->guest?->name ?? '')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('reservation.property.name')
                    ->label('Property')
                    ->placeholder(fn ($record): string => $record->reservation?->room?->property?->name ?? 'Not assigned')
                    ->visible(fn (): bool => auth()->user()?->hasRole('admin') ?? false),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->money('PHP')
                    ->weight('semibold')
                    ->sortable(),
                TextColumn::make('method')
                    ->formatStateUsing(fn (string $state): string => PaymentWorkflow::METHOD_OPTIONS[$state] ?? str($state)->replace('_', ' ')->title())
                    ->badge()
                    ->color('info'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->title())
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'failed', 'cancelled' => 'danger',
                        'refunded' => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('provider')
                    ->label('Channel')
                    ->placeholder('Not verified')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('provider_reference')
                    ->label('Transaction reference')
                    ->placeholder('Not verified')
                    ->copyable()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('parentPayment.provider_reference')
                    ->label('Original payment')
                    ->placeholder('Not a refund')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('processedBy.name')
                    ->label('Processed by')
                    ->placeholder('Not processed')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('paid_at')
                    ->label('Paid')
                    ->dateTime()
                    ->placeholder('Not paid')
                    ->sortable(),
                TextColumn::make('refunded_at')
                    ->label('Refunded')
                    ->dateTime()
                    ->placeholder('Not refunded')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('internal_notes')
                    ->label('Internal notes')
                    ->limit(60)
                    ->placeholder('None')
                    ->tooltip(fn ($record): ?string => $record->internal_notes)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('method')
                    ->options(PaymentWorkflow::METHOD_OPTIONS),
                SelectFilter::make('property_id')
                    ->label('Property')
                    ->options(fn (): array => Property::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, int|string $propertyId): Builder => $query->whereHas(
                            'reservation',
                            fn (Builder $reservation): Builder => $reservation->where('property_id', $propertyId)
                                ->orWhereHas('room', fn (Builder $room): Builder => $room->where('property_id', $propertyId)),
                        ),
                    ))
                    ->visible(fn (): bool => auth()->user()?->hasRole('admin') ?? false),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('setQuote')
                        ->label('Set quote')
                        ->icon('heroicon-o-calculator')
                        ->visible(fn ($record): bool => $record->status === 'pending' && $record->reservation?->booking_type === 'event')
                        ->fillForm(fn ($record): array => [
                            'amount' => (float) $record->amount ?: null,
                            'internal_notes' => $record->internal_notes,
                        ])
                        ->schema([
                            TextInput::make('amount')
                                ->label('Quoted total')
                                ->prefix('PHP')
                                ->numeric()
                                ->minValue(0.01)
                                ->step(0.01)
                                ->required(),
                            Textarea::make('internal_notes')
                                ->label('Quote notes')
                                ->rows(3)
                                ->maxLength(1000),
                        ])
                        ->action(function ($record, array $data, Action $action): void {
                            app(PaymentWorkflow::class)->setEventQuote($record, (float) $data['amount'], auth()->user(), $data['internal_notes'] ?? null);
                            $action->success();
                        })
                        ->successNotificationTitle('Event quote updated'),
                    Action::make('recordPayment')
                        ->label('Verify payment')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->visible(fn ($record): bool => $record->status === 'pending' && (float) $record->amount > 0)
                        ->fillForm(fn ($record): array => [
                            'method' => $record->method,
                            'provider' => $record->provider === 'manual-review' || $record->provider === 'staff-entry' ? null : $record->provider,
                        ])
                        ->schema([
                            Select::make('method')
                                ->options(PaymentWorkflow::METHOD_OPTIONS)
                                ->native(false)
                                ->required(),
                            TextInput::make('provider')
                                ->label('Provider / receiving channel')
                                ->placeholder('Example: GCash, BDO, front desk cash drawer')
                                ->required()
                                ->maxLength(120),
                            TextInput::make('provider_reference')
                                ->label('Transaction reference')
                                ->required()
                                ->maxLength(160),
                            Textarea::make('internal_notes')
                                ->label('Internal notes')
                                ->rows(3)
                                ->maxLength(1000),
                        ])
                        ->action(function ($record, array $data, Action $action): void {
                            app(PaymentWorkflow::class)->markPaid($record, $data, auth()->user());
                            $action->success();
                        })
                        ->successNotificationTitle('Payment verified'),
                    Action::make('markFailed')
                        ->label('Mark failed')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn ($record): bool => $record->status === 'pending')
                        ->schema([
                            Textarea::make('internal_notes')
                                ->label('Failure reason')
                                ->required()
                                ->rows(3)
                                ->maxLength(1000),
                        ])
                        ->action(function ($record, array $data, Action $action): void {
                            app(PaymentWorkflow::class)->markFailed($record, $data['internal_notes'], auth()->user());
                            $action->success();
                        })
                        ->successNotificationTitle('Payment marked as failed'),
                    Action::make('retry')
                        ->label('Create retry')
                        ->icon('heroicon-o-arrow-path')
                        ->visible(fn ($record): bool => in_array($record->status, ['failed', 'cancelled'], true) && $record->reservation?->status !== 'cancelled')
                        ->requiresConfirmation()
                        ->modalDescription('A new pending payment attempt will be created. The previous record remains unchanged for audit history.')
                        ->action(function ($record, Action $action): void {
                            app(PaymentWorkflow::class)->retry($record, auth()->user());
                            $action->success();
                        })
                        ->successNotificationTitle('Payment retry created'),
                    Action::make('refund')
                        ->label('Refund')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('danger')
                        ->visible(fn ($record): bool => $record->status === 'paid' && ! $record->refunds->contains('status', 'refunded'))
                        ->schema([
                            Textarea::make('internal_notes')
                                ->label('Refund reason')
                                ->required()
                                ->rows(3)
                                ->maxLength(1000),
                        ])
                        ->requiresConfirmation()
                        ->action(function ($record, array $data, Action $action): void {
                            app(PaymentWorkflow::class)->refund($record, $data['internal_notes'], auth()->user());
                            $action->success();
                        })
                        ->successNotificationTitle('Payment refunded'),
                ])
                    ->label('Payment actions')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->iconButton()
                    ->tooltip('Payment actions'),
            ])
            ->emptyStateIcon('heroicon-o-credit-card')
            ->emptyStateHeading('No payments found')
            ->emptyStateDescription('Payment attempts are generated automatically from reservations.')
            ->defaultSort('created_at', 'desc');
    }
}
