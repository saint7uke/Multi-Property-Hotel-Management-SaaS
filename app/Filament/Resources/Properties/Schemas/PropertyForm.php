<?php

namespace App\Filament\Resources\Properties\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PropertyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Property CMS')
                    ->tabs([
                        Tab::make('Property details')
                            ->icon('heroicon-o-building-office-2')
                            ->schema([
                                Section::make('Identity')
                                    ->description('The property name and URL used across the website and booking system.')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextInput::make('name')
                                                ->required()
                                                ->maxLength(160)
                                                ->live(onBlur: true)
                                                ->afterStateUpdated(function ($state, callable $set, string $operation): void {
                                                    if ($operation === 'create') {
                                                        $set('slug', Str::slug((string) $state));
                                                    }
                                                }),
                                            TextInput::make('slug')
                                                ->required()
                                                ->unique(ignoreRecord: true)
                                                ->maxLength(160)
                                                ->helperText('Used in the public URL, for example /hotels/ma-skyline-singapore.'),
                                        ]),
                                    ]),
                                Section::make('Location')
                                    ->schema([
                                        TextInput::make('address')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpanFull(),
                                        Grid::make(2)->schema([
                                            TextInput::make('city')
                                                ->required()
                                                ->maxLength(120),
                                            TextInput::make('country')
                                                ->required()
                                                ->maxLength(120),
                                        ]),
                                    ]),
                                Section::make('Publishing')
                                    ->description('Inactive properties are hidden from public hotel, booking, and contact pages.')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Select::make('status')
                                                ->options([
                                                    'active' => 'Active and public',
                                                    'inactive' => 'Inactive and hidden',
                                                ])
                                                ->required()
                                                ->default('active'),
                                            Toggle::make('offers_breakfast')
                                                ->label('Breakfast is available')
                                                ->default(true),
                                        ]),
                                    ]),
                            ]),
                        Tab::make('Public page')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make('Hero and overview')
                                    ->description('Primary copy shown at the top of this hotel page.')
                                    ->schema([
                                        TextInput::make('tagline')
                                            ->maxLength(240)
                                            ->placeholder('A considered city stay with direct guest support.')
                                            ->columnSpanFull(),
                                        Textarea::make('description')
                                            ->rows(6)
                                            ->maxLength(5000)
                                            ->placeholder('Describe the property, its atmosphere, location, and ideal guests.')
                                            ->columnSpanFull(),
                                    ]),
                                Section::make('Amenities')
                                    ->description('Add the property-level facilities guests should see before choosing a room.')
                                    ->schema([
                                        Repeater::make('amenities')
                                            ->label('Amenity list')
                                            ->simple(
                                                TextInput::make('amenity')
                                                    ->required()
                                                    ->maxLength(100),
                                            )
                                            ->addActionLabel('Add amenity')
                                            ->reorderable()
                                            ->maxItems(30)
                                            ->columnSpanFull(),
                                    ]),
                                Section::make('Property highlights')
                                    ->description('Short editorial points displayed as a structured overview, not unrestricted HTML.')
                                    ->schema([
                                        Repeater::make('highlights')
                                            ->schema([
                                                TextInput::make('title')
                                                    ->required()
                                                    ->maxLength(100),
                                                Textarea::make('description')
                                                    ->required()
                                                    ->rows(3)
                                                    ->maxLength(400),
                                            ])
                                            ->columns(2)
                                            ->addActionLabel('Add highlight')
                                            ->reorderable()
                                            ->collapsible()
                                            ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                                            ->maxItems(8)
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                        Tab::make('Media')
                            ->icon('heroicon-o-photo')
                            ->schema([
                                Section::make('Hero image')
                                    ->description('Recommended landscape image: 2000 x 1200 pixels, JPG or WebP, up to 5 MB.')
                                    ->schema([
                                        FileUpload::make('hero_image_path')
                                            ->label('Hero image')
                                            ->image()
                                            ->disk('public')
                                            ->directory('properties/heroes')
                                            ->visibility('public')
                                            ->maxSize(5120)
                                            ->imageEditor()
                                            ->imageEditorAspectRatios(['16:9', '4:3'])
                                            ->columnSpanFull(),
                                    ]),
                                Section::make('Gallery')
                                    ->description('Upload up to eight property photos. Drag to control their display order.')
                                    ->schema([
                                        FileUpload::make('gallery_images')
                                            ->label('Property gallery')
                                            ->image()
                                            ->multiple()
                                            ->reorderable()
                                            ->appendFiles()
                                            ->disk('public')
                                            ->directory('properties/galleries')
                                            ->visibility('public')
                                            ->maxFiles(8)
                                            ->maxSize(5120)
                                            ->imageEditor()
                                            ->imageEditorAspectRatios(['16:9', '4:3', '1:1'])
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                        Tab::make('Guest information')
                            ->icon('heroicon-o-phone')
                            ->schema([
                                Section::make('Property contact')
                                    ->description('Shown to guests on this property page and booking confirmation content where applicable.')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextInput::make('contact_email')
                                                ->label('Contact email')
                                                ->email()
                                                ->maxLength(160),
                                            TextInput::make('contact_phone')
                                                ->label('Contact number')
                                                ->tel()
                                                ->maxLength(40),
                                        ]),
                                    ]),
                                Section::make('Arrival and departure')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TimePicker::make('check_in_time')
                                                ->label('Standard check-in time')
                                                ->seconds(false),
                                            TimePicker::make('check_out_time')
                                                ->label('Standard check-out time')
                                                ->seconds(false),
                                        ]),
                                    ]),
                            ]),
                        Tab::make('SEO')
                            ->icon('heroicon-o-magnifying-glass')
                            ->schema([
                                Section::make('Search appearance')
                                    ->description('Optional. The property name and overview are used when these fields are empty.')
                                    ->schema([
                                        TextInput::make('meta_title')
                                            ->label('Page title')
                                            ->maxLength(70)
                                            ->helperText('Aim for 50 to 60 characters.')
                                            ->columnSpanFull(),
                                        Textarea::make('meta_description')
                                            ->label('Meta description')
                                            ->rows(3)
                                            ->maxLength(170)
                                            ->helperText('Aim for 140 to 160 characters and describe this specific property.')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])
                    ->persistTabInQueryString()
                    ->columnSpanFull(),
            ]);
    }
}
