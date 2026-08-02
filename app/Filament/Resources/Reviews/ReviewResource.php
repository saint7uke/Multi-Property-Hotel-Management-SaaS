<?php

namespace App\Filament\Resources\Reviews;

use App\Filament\Resources\HotelResource;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Filament\Resources\Reviews\Tables\ReviewsTable;
use App\Models\Review;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ReviewResource extends HotelResource
{
    protected static ?string $model = Review::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|UnitEnum|null $navigationGroup = 'Guest Experience';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'message';

    protected static ?string $navigationLabel = 'Review moderation';

    protected static ?string $modelLabel = 'guest review';

    protected static ?string $pluralModelLabel = 'guest reviews';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('reviews.moderate') ?? false;
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
        $query = parent::getEloquentQuery()->with(['guest', 'property', 'reservation', 'moderatedBy']);
        $user = auth()->user();

        return $user?->hasRole('admin') ? $query : $query->where('property_id', $user?->property_id ?? 0);
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
        return ReviewsTable::configure($table);
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
            'index' => ListReviews::route('/'),
        ];
    }
}
