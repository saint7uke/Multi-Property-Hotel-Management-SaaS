<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Models\AuditLog;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime()
                    ->since()
                    ->description(fn ($record): string => $record->created_at?->format('M j, Y g:i A') ?? '')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Actor')
                    ->placeholder('Public / system')
                    ->description(fn ($record): string => $record->user?->email ?? '')
                    ->searchable(['name', 'email']),
                TextColumn::make('action')
                    ->label('Event')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::eventLabel($state))
                    ->color(fn (string $state): string => match (true) {
                        str_contains($state, 'failed'), str_contains($state, 'denied'), str_contains($state, 'refunded') => 'danger',
                        str_contains($state, 'created'), str_contains($state, 'succeeded'), str_contains($state, 'verified') => 'success',
                        str_contains($state, 'updated'), str_contains($state, 'changed'), str_contains($state, 'moderated') => 'warning',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('subject_type')
                    ->label('Subject')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : 'General event')
                    ->description(fn ($record): string => $record->subject_id ? 'Record #'.$record->subject_id : '')
                    ->searchable(),
                TextColumn::make('ip_address')
                    ->label('IP address')
                    ->placeholder('Unknown')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('Actor')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('action')
                    ->label('Event')
                    ->options(fn (): array => AuditLog::query()->distinct()->orderBy('action')->pluck('action', 'action')->all())
                    ->searchable(),
                Filter::make('created_at')
                    ->label('Event date')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date))),
            ])
            ->recordActions([
                Action::make('details')
                    ->label('Details')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn ($record): string => self::eventLabel($record->action))
                    ->modalWidth('3xl')
                    ->modalContent(fn ($record) => view('filament.audit-log-details', ['log' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->emptyStateHeading('No audit events found')
            ->emptyStateDescription('Operational and security events will appear here automatically.')
            ->defaultSort('created_at', 'desc');
    }

    private static function eventLabel(string $action): string
    {
        [$domain, $event] = array_pad(explode('.', $action, 2), 2, '');
        $domainLabel = match ($domain) {
            'auth' => 'Staff',
            'users' => 'Staff account',
            'properties' => 'Property',
            'rooms' => 'Room',
            'reservations' => 'Reservation',
            'payments' => 'Payment',
            'reviews' => 'Review',
            'inquiries' => 'Guest inquiry',
            'newsletter' => 'Newsletter',
            'housekeeping' => 'Housekeeping',
            default => str($domain)->replace('_', ' ')->title()->toString(),
        };

        return trim($domainLabel.' '.str($event)->replace('_', ' ')->title());
    }
}
