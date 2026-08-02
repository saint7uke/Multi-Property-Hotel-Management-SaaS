<?php

namespace App\Filament\Resources\Rooms\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class RoomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Room identity')
                    ->description('Room numbers are unique within each property.')
                    ->icon('heroicon-o-home-modern')
                    ->schema([
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
                            ->searchable()
                            ->preload()
                            ->required(),
                        Grid::make(2)->schema([
                            TextInput::make('room_number')
                                ->label('Room number')
                                ->required()
                                ->maxLength(40)
                                ->unique(
                                    table: 'rooms',
                                    column: 'room_number',
                                    ignoreRecord: true,
                                    modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('property_id', $get('property_id')),
                                ),
                            TextInput::make('type')
                                ->label('Room type')
                                ->placeholder('Deluxe King')
                                ->required()
                                ->maxLength(120),
                        ]),
                    ]),
                Section::make('Pricing and occupancy')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('rate')
                                ->label('Nightly rate')
                                ->prefix('PHP')
                                ->required()
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(99999999.99)
                                ->step(0.01),
                            TextInput::make('capacity')
                                ->label('Maximum guests')
                                ->required()
                                ->integer()
                                ->minValue(1)
                                ->maxValue(50)
                                ->default(2),
                        ]),
                        TagsInput::make('amenities')
                            ->placeholder('Add an amenity')
                            ->helperText('Press Enter after each amenity, for example Wi-Fi or King bed.')
                            ->separator(',')
                            ->nestedRecursiveRules(['string', 'max:100'])
                            ->columnSpanFull(),
                    ]),
                Section::make('Operational status')
                    ->description('Use Ready for rooms that may be assigned immediately. Housekeeping updates synchronize this field.')
                    ->icon('heroicon-o-check-circle')
                    ->schema([
                        Select::make('status')
                            ->options([
                                'available' => 'Available',
                                'ready' => 'Ready',
                                'occupied' => 'Occupied',
                                'dirty' => 'Dirty',
                                'cleaning' => 'Cleaning in progress',
                                'clean' => 'Clean',
                                'inspected' => 'Inspected',
                                'out_of_service' => 'Out of service',
                            ])
                            ->native(false)
                            ->required()
                            ->default('available'),
                    ]),
            ]);
    }
}
