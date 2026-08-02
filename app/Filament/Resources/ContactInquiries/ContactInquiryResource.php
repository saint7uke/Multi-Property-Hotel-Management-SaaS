<?php

namespace App\Filament\Resources\ContactInquiries;

use App\Filament\Resources\ContactInquiries\Pages\ListContactInquiries;
use App\Filament\Resources\ContactInquiries\Tables\ContactInquiriesTable;
use App\Filament\Resources\HotelResource;
use App\Models\ContactInquiry;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ContactInquiryResource extends HotelResource
{
    protected static ?string $model = ContactInquiry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'Guest Experience';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Guest inquiries';

    protected static ?string $recordTitleAttribute = 'reference_number';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin') && auth()->user()?->can('inquiries.manage');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('status', 'new')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return ContactInquiriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => ListContactInquiries::route('/')];
    }
}
