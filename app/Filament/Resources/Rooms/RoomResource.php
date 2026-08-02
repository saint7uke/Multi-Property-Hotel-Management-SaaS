<?php

namespace App\Filament\Resources\Rooms;

use App\Filament\Resources\HotelResource;
use App\Filament\Resources\Rooms\Pages\CreateRoom;
use App\Filament\Resources\Rooms\Pages\EditRoom;
use App\Filament\Resources\Rooms\Pages\ListRooms;
use App\Filament\Resources\Rooms\Schemas\RoomForm;
use App\Filament\Resources\Rooms\Tables\RoomsTable;
use App\Models\Room;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class RoomResource extends HotelResource
{
    protected static ?string $model = Room::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static string|UnitEnum|null $navigationGroup = 'Hotel Operations';

    protected static ?string $recordTitleAttribute = 'room_number';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('rooms.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('rooms.manage') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();

        return $user?->can('rooms.manage') && ($user->hasRole('admin') || (int) $record->property_id === (int) $user->property_id);
    }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();

        return ($user?->can('rooms.manage') ?? false)
            && ($user->hasRole('admin') || (int) $record->property_id === (int) $user->property_id)
            && $record->status !== 'occupied'
            && ! $record->reservations()->exists();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        return $user?->hasRole('admin') ? $query : $query->where('property_id', $user?->property_id ?? 0);
    }

    public static function form(Schema $schema): Schema
    {
        return RoomForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RoomsTable::configure($table);
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
            'index' => ListRooms::route('/'),
            'create' => CreateRoom::route('/create'),
            'edit' => EditRoom::route('/{record}/edit'),
        ];
    }
}
