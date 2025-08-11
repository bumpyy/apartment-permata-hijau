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
                TextInput::make('tenant_id'),
                SpatieMediaLibraryFileUpload::make('profile_picture')
                    ->collection('tenant_profile_pictures')
                    ->maxFiles(1)
                    ->image(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->email()
                    ->required(),
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
