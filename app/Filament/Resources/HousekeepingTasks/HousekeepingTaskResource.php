<?php

namespace App\Filament\Resources\HousekeepingTasks;

use App\Filament\Resources\HotelResource;
use App\Filament\Resources\HousekeepingTasks\Pages\CreateHousekeepingTask;
use App\Filament\Resources\HousekeepingTasks\Pages\EditHousekeepingTask;
use App\Filament\Resources\HousekeepingTasks\Pages\ListHousekeepingTasks;
use App\Filament\Resources\HousekeepingTasks\Schemas\HousekeepingTaskForm;
use App\Filament\Resources\HousekeepingTasks\Tables\HousekeepingTasksTable;
use App\Models\HousekeepingTask;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class HousekeepingTaskResource extends HotelResource
{
    protected static ?string $model = HousekeepingTask::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Hotel Operations';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('housekeeping.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('housekeeping.manage') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();

        return $user?->can('housekeeping.manage') && ($user->hasRole('admin') || (int) $record->room?->property_id === (int) $user->property_id);
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['room.property', 'assignee']);
        $user = auth()->user();

        return $user?->hasRole('admin') ? $query : $query->whereHas('room', fn ($room) => $room->where('property_id', $user?->property_id ?? 0));
    }

    public static function form(Schema $schema): Schema
    {
        return HousekeepingTaskForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HousekeepingTasksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHousekeepingTasks::route('/'),
            'create' => CreateHousekeepingTask::route('/create'),
            'edit' => EditHousekeepingTask::route('/{record}/edit'),
        ];
    }
}
