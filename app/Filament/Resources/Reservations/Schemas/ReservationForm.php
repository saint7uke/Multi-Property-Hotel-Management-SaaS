<?php

namespace App\Filament\Resources\Reservations\Schemas;

use App\Models\Room;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ReservationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Booking setup')
                    ->description('Choose the booking type and property before selecting dates or a room.')
                    ->icon('heroicon-o-calendar-days')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('booking_type')
                                ->label('Booking type')
                                ->options([
                                    'personal' => 'Personal / Room',
                                    'event' => 'Event / Group',
                                ])
                                ->native(false)
                                ->live()
                                ->afterStateUpdated(function (?string $state, Set $set): void {
                                    $set('room_id', null);
                                    $set('status', $state === 'event' ? 'pending' : 'confirmed');
                                })
                                ->required()
                                ->default('personal'),
                            Select::make('property_id')
                                ->label('Property')
                                ->relationship('property', 'name', modifyQueryUsing: fn (Builder $query) => $query->when(
                                    ! auth()->user()?->hasRole('admin'),
                                    fn (Builder $query) => $query->whereKey(auth()->user()?->property_id ?? 0)
                                ))
                                ->default(fn () => auth()->user()?->property_id)
                                ->disabled(fn () => ! auth()->user()?->hasRole('admin'))
                                ->dehydrated()
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $set('room_id', null))
                                ->searchable()
                                ->preload()
                                ->required(),
                        ]),
                        TextInput::make('event_name')
                            ->label('Event or group name')
                            ->placeholder('Company retreat or family celebration')
                            ->visible(fn (Get $get): bool => $get('booking_type') === 'event')
                            ->required(fn (Get $get): bool => $get('booking_type') === 'event')
                            ->maxLength(160)
                            ->columnSpanFull(),
                    ]),
                Section::make('Stay dates and accommodation')
                    ->description('Available rooms are filtered by property, readiness, and overlapping reservations.')
                    ->icon('heroicon-o-key')
                    ->schema([
                        Grid::make(2)->schema([
                            DatePicker::make('check_in')
                                ->label('Check-in')
                                ->native(false)
                                ->minDate(today())
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $set('room_id', null))
                                ->required(),
                            DatePicker::make('check_out')
                                ->label('Check-out')
                                ->native(false)
                                ->minDate(fn (Get $get): Carbon => filled($get('check_in'))
                                    ? Carbon::parse($get('check_in'))->addDay()
                                    : today()->addDay())
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $set('room_id', null))
                                ->required(),
                        ]),
                        Select::make('room_id')
                            ->label('Available room')
                            ->relationship('room', 'room_number', modifyQueryUsing: function (Builder $query, Get $get): Builder {
                                $checkIn = $get('check_in');
                                $checkOut = $get('check_out');

                                return $query
                                    ->whereIn('status', ['available', 'ready'])
                                    ->when(
                                        $get('property_id'),
                                        fn (Builder $query, $propertyId) => $query->where('property_id', $propertyId),
                                    )
                                    ->when(
                                        $checkIn && $checkOut,
                                        fn (Builder $query) => $query->whereDoesntHave(
                                            'reservations',
                                            fn (Builder $reservations) => $reservations->overlapping($checkIn, $checkOut),
                                        ),
                                    );
                            })
                            ->getOptionLabelFromRecordUsing(fn (Room $record): string => "Room {$record->room_number} - {$record->type} - {$record->capacity} guests")
                            ->searchable(['room_number', 'type'])
                            ->preload()
                            ->visible(fn (Get $get): bool => $get('booking_type') === 'personal')
                            ->required(fn (Get $get): bool => $get('booking_type') === 'personal')
                            ->helperText('Select the property and dates first to see eligible rooms.')
                            ->columnSpanFull(),
                    ]),
                Section::make('Guest details')
                    ->icon('heroicon-o-user')
                    ->schema([
                        TextInput::make('guest_name')
                            ->label('Full name')
                            ->dehydrated(false)
                            ->required()
                            ->maxLength(160),
                        Grid::make(2)->schema([
                            TextInput::make('email')
                                ->label('Email address')
                                ->email()
                                ->dehydrated(false)
                                ->required()
                                ->maxLength(160),
                            TextInput::make('phone')
                                ->label('Contact number')
                                ->tel()
                                ->dehydrated(false)
                                ->required()
                                ->maxLength(40),
                        ]),
                        Grid::make(2)->schema([
                            TextInput::make('adults')
                                ->required()
                                ->integer()
                                ->minValue(1)
                                ->maxValue(20)
                                ->default(1),
                            TextInput::make('children')
                                ->required()
                                ->integer()
                                ->minValue(0)
                                ->maxValue(20)
                                ->default(0),
                        ]),
                        Textarea::make('special_request')
                            ->label('Special requests')
                            ->rows(4)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ]),
                Section::make('Initial status')
                    ->description('Room bookings may be confirmed immediately. Event and group requests should remain pending until reviewed.')
                    ->icon('heroicon-o-check-badge')
                    ->schema([
                        Select::make('status')
                            ->options([
                                'pending' => 'Pending review',
                                'confirmed' => 'Confirmed',
                            ])
                            ->native(false)
                            ->required()
                            ->default('confirmed'),
                    ]),
            ]);
    }
}
