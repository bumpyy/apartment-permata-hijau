<?php

namespace App\Filament\Resources\Courts\Schemas;

use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CourtForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                SpatieMediaLibraryFileUpload::make('court_image')
                    ->collection('court_images')
                    ->image()
                    ->disk('public')
                    ->columnSpanFull(),
                TextInput::make('name')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('hourly_rate')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('light_surcharge')
                    ->required()
                    ->numeric()
                    ->default(50000),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('operating_hours'),
            ]);
    }
}
