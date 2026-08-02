<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use App\Services\StaffUserWorkflow;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Staff identity')
                    ->description('Contact details used for the central staff sign-in.')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('Full name')
                                ->required()
                                ->maxLength(160),
                            TextInput::make('email')
                                ->label('Email address')
                                ->email()
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(160),
                        ]),
                    ]),
                Section::make('Role and hotel access')
                    ->description('Each staff account has one role and, except administrators, one assigned property.')
                    ->icon('heroicon-o-key')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('role')
                                ->options(fn (): array => auth()->user()?->hasRole('admin')
                                    ? StaffUserWorkflow::ROLE_OPTIONS
                                    : collect(StaffUserWorkflow::ROLE_OPTIONS)->except('admin')->all())
                                ->native(false)
                                ->live()
                                ->required()
                                ->default('receptionist')
                                ->disabled(fn (?User $record): bool => $record?->is(auth()->user()) ?? false)
                                ->dehydrated(),
                            Select::make('property_id')
                                ->label('Hotel property')
                                ->relationship(
                                    'property',
                                    'name',
                                    fn (Builder $query): Builder => $query->where('status', 'active')->orderBy('name'),
                                )
                                ->default(fn () => auth()->user()?->property_id)
                                ->searchable()
                                ->preload()
                                ->visible(fn (Get $get): bool => $get('role') !== 'admin')
                                ->required(fn (Get $get): bool => $get('role') !== 'admin')
                                ->disabled(fn (): bool => ! auth()->user()?->hasRole('admin'))
                                ->dehydrated(),
                        ]),
                        Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                            ])
                            ->native(false)
                            ->required()
                            ->default('active')
                            ->disabled(fn (?User $record): bool => $record?->is(auth()->user()) ?? false)
                            ->dehydrated()
                            ->helperText('Inactive accounts are blocked from every staff panel and API.'),
                    ]),
                Section::make('Account security')
                    ->description('Use at least 12 characters with uppercase, lowercase, and numbers.')
                    ->icon('heroicon-o-lock-closed')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('password')
                                ->password()
                                ->revealable()
                                ->required(fn (string $operation): bool => $operation === 'create')
                                ->rule(Password::min(12)->mixedCase()->letters()->numbers())
                                ->maxLength(100)
                                ->dehydrated(fn ($state): bool => filled($state)),
                            TextInput::make('password_confirmation')
                                ->label('Confirm password')
                                ->password()
                                ->revealable()
                                ->same('password')
                                ->required(fn (Get $get, string $operation): bool => $operation === 'create' || filled($get('password')))
                                ->dehydrated(false),
                        ]),
                    ]),
            ]);
    }
}
