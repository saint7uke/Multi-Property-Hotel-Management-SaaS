<?php

namespace App\Filament\Resources\Reservations;

use App\Filament\Resources\HotelResource;
use App\Filament\Resources\Reservations\Pages\CreateReservation;
use App\Filament\Resources\Reservations\Pages\EditReservation;
use App\Filament\Resources\Reservations\Pages\ListReservations;
use App\Filament\Resources\Reservations\Schemas\ReservationForm;
use App\Filament\Resources\Reservations\Tables\ReservationsTable;
use App\Models\Reservation;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ReservationResource extends HotelResource
{
    protected static ?string $model = Reservation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Hotel Operations';

    protected static ?string $recordTitleAttribute = 'reference_number';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('reservations.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('reservations.manage') ?? false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['guest', 'property', 'room.property', 'payments']);
        $user = auth()->user();

        if ($user?->hasRole('admin')) {
            return $query;
        }

        return $query->where(function ($query) use ($user) {
            $query->where('property_id', $user?->property_id ?? 0)
                ->orWhereHas('room', fn ($room) => $room->where('property_id', $user?->property_id ?? 0));
        });
    }

    public static function form(Schema $schema): Schema
    {
        return ReservationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReservationsTable::configure($table);
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
            'index' => ListReservations::route('/'),
            'create' => CreateReservation::route('/create'),
            'edit' => EditReservation::route('/{record}/edit'),
        ];
    }
}
