<?php

namespace App\Filament\Resources\HousekeepingTasks\Schemas;

use App\Models\HousekeepingTask;
use App\Models\Room;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class HousekeepingTaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Room assignment')
                    ->description('Each room has one active housekeeping task. Ready rooms without a task remain available for assignment.')
                    ->icon('heroicon-o-home-modern')
                    ->schema([
                        Select::make('room_id')
                            ->label('Room')
                            ->relationship('room', 'room_number', modifyQueryUsing: function (Builder $query, ?HousekeepingTask $record): Builder {
                                return $query
                                    ->when(
                                        ! auth()->user()?->hasRole('admin'),
                                        fn (Builder $query) => $query->where('property_id', auth()->user()?->property_id ?? 0),
                                    )
                                    ->where(function (Builder $query) use ($record): void {
                                        $query->whereDoesntHave('housekeepingTask')
                                            ->when($record, fn (Builder $query) => $query->orWhereKey($record->room_id));
                                    });
                            })
                            ->getOptionLabelFromRecordUsing(fn (Room $record): string => "{$record->property->name} - Room {$record->room_number} - {$record->type}")
                            ->disabled(fn (?HousekeepingTask $record): bool => filled($record))
                            ->dehydrated()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('assigned_to', null))
                            ->searchable(['room_number', 'type'])
                            ->preload()
                            ->required(),
                        Select::make('assigned_to')
                            ->label('Assigned housekeeper')
                            ->relationship('assignee', 'name', modifyQueryUsing: function (Builder $query, Get $get): Builder {
                                $propertyId = Room::query()->whereKey($get('room_id'))->value('property_id');

                                return $query
                                    ->role('housekeeping')
                                    ->where('status', 'active')
                                    ->when($propertyId, fn (Builder $query) => $query->where('property_id', $propertyId));
                            })
                            ->searchable()
                            ->preload()
                            ->helperText('Only active housekeeping staff from the room property are shown.'),
                    ]),
                Section::make('Shift and room status')
                    ->icon('heroicon-o-sparkles')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('status')
                                ->options([
                                    'dirty' => 'Dirty',
                                    'cleaning' => 'Cleaning in progress',
                                    'clean' => 'Clean',
                                    'inspected' => 'Inspected',
                                    'ready' => 'Ready for guest',
                                    'out_of_service' => 'Out of service',
                                ])
                                ->native(false)
                                ->required()
                                ->default('dirty'),
                            DatePicker::make('shift_date')
                                ->label('Shift date')
                                ->native(false)
                                ->default(today())
                                ->required(),
                        ]),
                        Textarea::make('notes')
                            ->rows(4)
                            ->maxLength(1000)
                            ->placeholder('Maintenance concerns, minibar notes, or inspection details.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
