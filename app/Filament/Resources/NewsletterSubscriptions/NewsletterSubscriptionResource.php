<?php

namespace App\Filament\Resources\NewsletterSubscriptions;

use App\Filament\Resources\HotelResource;
use App\Filament\Resources\NewsletterSubscriptions\Pages\ListNewsletterSubscriptions;
use App\Filament\Resources\NewsletterSubscriptions\Tables\NewsletterSubscriptionsTable;
use App\Models\NewsletterSubscription;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class NewsletterSubscriptionResource extends HotelResource
{
    protected static ?string $model = NewsletterSubscription::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static string|UnitEnum|null $navigationGroup = 'Guest Experience';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Newsletter subscribers';

    protected static ?string $recordTitleAttribute = 'email';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin') && auth()->user()?->can('newsletter.manage');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return NewsletterSubscriptionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => ListNewsletterSubscriptions::route('/')];
    }
}
