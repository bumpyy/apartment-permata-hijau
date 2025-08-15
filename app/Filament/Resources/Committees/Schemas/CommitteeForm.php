<?php

namespace App\Filament\Resources\Committees\Schemas;

use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CommitteeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                SpatieMediaLibraryFileUpload::make('image')
                    ->collection('committee_image')
                    ->disk('public')
                    ->maxFiles(1)
                    ->image(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('position'),
                TextInput::make('period'),
            ]);
    }
}
