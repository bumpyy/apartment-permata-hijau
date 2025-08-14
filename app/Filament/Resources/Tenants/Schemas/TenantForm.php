<?php

namespace App\Filament\Resources\Tenants\Schemas;

use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class TenantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                SpatieMediaLibraryFileUpload::make('profile_picture')
                    ->collection('tenant_profile_pictures')
                    ->maxFiles(1)
                    ->image(),
                TextInput::make('tenant_id')
                    ->label('Tenant ID')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('name')
                    ->required(),

                TextInput::make('tower'),
                TextInput::make('unit'),

                TextInput::make('email')
                    ->email(),
                TextInput::make('phone'),
                TextInput::make('password')
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $context): bool => $context === 'create'),
                // TextInput::make('booking_limit')
                //     ->required()
                //     ->numeric()
                //     ->default(3),
                Toggle::make('is_active')
                    ->default(true)
                    ->required(),
            ]);
    }
}
