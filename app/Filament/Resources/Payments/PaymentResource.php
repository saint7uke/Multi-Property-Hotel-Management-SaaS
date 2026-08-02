<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Resources\HotelResource;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Tables\PaymentsTable;
use App\Models\Payment;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PaymentResource extends HotelResource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Payment reconciliation';

    protected static ?string $modelLabel = 'payment';

    protected static ?string $pluralModelLabel = 'payments';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('payments.manage') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['reservation.guest', 'reservation.property', 'reservation.room.property', 'processedBy', 'parentPayment', 'refunds']);
        $user = auth()->user();

        return $user?->hasRole('admin') ? $query : $query->whereHas('reservation', fn ($reservation) => $reservation->where('property_id', $user?->property_id ?? 0)->orWhereHas('room', fn ($room) => $room->where('property_id', $user?->property_id ?? 0)));
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('status', 'pending')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return PaymentsTable::configure($table);
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
            'index' => ListPayments::route('/'),
        ];
    }
}
