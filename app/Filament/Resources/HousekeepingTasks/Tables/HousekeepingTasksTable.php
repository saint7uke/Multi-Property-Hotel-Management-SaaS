<?php

namespace App\Filament\Resources\HousekeepingTasks\Tables;

use App\Filament\Resources\HousekeepingTasks\HousekeepingTaskResource;
use App\Services\HousekeepingWorkflow;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class HousekeepingTasksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('room.room_number')
                    ->label('Room')
                    ->weight('semibold')
                    ->description(fn ($record): string => $record->room?->type ?? '')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('room.property.name')
                    ->label('Property')
                    ->visible(fn (): bool => auth()->user()?->hasRole('admin') ?? false),
                TextColumn::make('assignee.name')
                    ->label('Assigned to')
                    ->placeholder('Unassigned')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->title())
                    ->color(fn (string $state): string => match ($state) {
                        'ready' => 'success',
                        'inspected', 'clean' => 'info',
                        'cleaning' => 'warning',
                        'dirty', 'out_of_service' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('shift_date')
                    ->label('Shift')
                    ->date()
                    ->sortable(),
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
                        'dirty' => 'Dirty',
                        'cleaning' => 'Cleaning in progress',
                        'clean' => 'Clean',
                        'inspected' => 'Inspected',
                        'ready' => 'Ready for guest',
                        'out_of_service' => 'Out of service',
                    ]),
                SelectFilter::make('assigned_to')
                    ->label('Housekeeper')
                    ->relationship('assignee', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('updateStatus')
                    ->label('Update status')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn ($record): bool => HousekeepingTaskResource::canEdit($record))
                    ->fillForm(fn ($record): array => [
                        'status' => $record->status,
                        'notes' => $record->notes,
                    ])
                    ->schema([
                        Select::make('status')
                            ->options([
                                'dirty' => 'Dirty',
                                'cleaning' => 'Cleaning in progress',
                                'clean' => 'Clean',
                                'inspected' => 'Inspected',
                                'ready' => 'Ready for guest',
                                'out_of_service' => 'Out of service',
                            ])
                            ->native(false)
                            ->required(),
                        Textarea::make('notes')
                            ->rows(3)
                            ->maxLength(1000),
                    ])
                    ->action(function ($record, array $data, Action $action): void {
                        app(HousekeepingWorkflow::class)->save([
                            'room_id' => $record->room_id,
                            'assigned_to' => $record->assigned_to,
                            'status' => $data['status'],
                            'shift_date' => today()->toDateString(),
                            'notes' => $data['notes'] ?? null,
                        ], auth()->user(), $record);
                        $action->success();
                    })
                    ->successNotificationTitle('Room status updated'),
                EditAction::make(),
                Action::make('remove')
                    ->label('Remove task')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn ($record): bool => HousekeepingTaskResource::canDelete($record))
                    ->requiresConfirmation()
                    ->modalHeading('Remove housekeeping task')
                    ->modalDescription('The room status remains unchanged. This action removes only the active task.')
                    ->action(function ($record, Action $action): void {
                        app(HousekeepingWorkflow::class)->delete($record, auth()->user());
                        $action->success();
                    })
                    ->successNotificationTitle('Housekeeping task removed'),
            ])
            ->emptyStateIcon('heroicon-o-sparkles')
            ->emptyStateHeading('No housekeeping tasks found')
            ->emptyStateDescription('Create a task or adjust the active filters.')
            ->defaultSort('shift_date', 'desc');
    }
}
