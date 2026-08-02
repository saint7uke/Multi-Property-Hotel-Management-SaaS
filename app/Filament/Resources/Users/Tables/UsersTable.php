<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Resources\Users\UserResource;
use App\Services\StaffUserWorkflow;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Staff member')
                    ->weight('semibold')
                    ->description(fn ($record): string => $record->email)
                    ->searchable(['name', 'email'])
                    ->sortable(),
                TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => StaffUserWorkflow::ROLE_OPTIONS[$state] ?? str($state)->title())
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'danger',
                        'manager' => 'primary',
                        'receptionist' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('property.name')
                    ->label('Hotel property')
                    ->placeholder('All properties')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->title())
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'danger'),
                TextColumn::make('last_login_at')
                    ->label('Last sign-in')
                    ->dateTime()
                    ->since()
                    ->placeholder('Never')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(['active' => 'Active', 'inactive' => 'Inactive']),
                SelectFilter::make('role')
                    ->relationship('roles', 'name')
                    ->options(StaffUserWorkflow::ROLE_OPTIONS),
                SelectFilter::make('property')
                    ->relationship('property', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn (): bool => auth()->user()?->hasRole('admin') ?? false),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn ($record): bool => UserResource::canEdit($record)),
                Action::make('deactivate')
                    ->label('Deactivate')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn ($record): bool => $record->status === 'active' && ! $record->is(auth()->user()) && UserResource::canEdit($record))
                    ->requiresConfirmation()
                    ->modalDescription('The account will be blocked and its active API tokens and other sessions will be revoked.')
                    ->action(function ($record, Action $action): void {
                        app(StaffUserWorkflow::class)->update($record, [
                            'name' => $record->name,
                            'email' => $record->email,
                            'role' => $record->getRoleNames()->sole(),
                            'property_id' => $record->property_id,
                            'status' => 'inactive',
                        ], auth()->user());
                        $action->success();
                    })
                    ->successNotificationTitle('Staff account deactivated'),
                Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record): bool => $record->status === 'inactive' && ! $record->is(auth()->user()) && UserResource::canEdit($record))
                    ->requiresConfirmation()
                    ->action(function ($record, Action $action): void {
                        app(StaffUserWorkflow::class)->update($record, [
                            'name' => $record->name,
                            'email' => $record->email,
                            'role' => $record->getRoleNames()->sole(),
                            'property_id' => $record->property_id,
                            'status' => 'active',
                        ], auth()->user());
                        $action->success();
                    })
                    ->successNotificationTitle('Staff account activated'),
            ])
            ->emptyStateIcon('heroicon-o-users')
            ->emptyStateHeading('No staff accounts found')
            ->emptyStateDescription('Create a staff account or adjust the active filters.')
            ->defaultSort('name');
    }
}
