<?php

namespace App\Filament\Resources\News\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class NewsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required(),
                SpatieMediaLibraryFileUpload::make('featured_image')
                    ->collection('news_images')
                    ->image()
                    ->required(),
                RichEditor::make('content')
                    ->required()
                    ->columnSpanFull(),

                TextInput::make('author'),
                DateTimePicker::make('published_at')
                    ->default(now())
                    ->required(),
            ]);
    }
}
