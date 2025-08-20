<?php

namespace App\Livewire;

use App\Models\Event;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Guava\Calendar\Filament\CalendarWidget;
use Guava\Calendar\ValueObjects\FetchInfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CustomCalendarWidget extends CalendarWidget
{
    // protected bool $dateClickEnabled = true;

    public ?array $data = [];

    public function defaultSchema(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->required()
                ->rules(['required', 'string', 'max:191']),
            Textarea::make('description')
                ->columnSpanFull(),
            DatePicker::make('start_at')
                // ->hidden()
                ->label('Start Date')
                ->required(),
            DatePicker::make('end_at')
                // ->hidden()
                ->label('End Date')
                ->required(),
        ])->statePath('data');
    }

    protected function getEvents(FetchInfo $info): Collection|array|Builder
    {
        // If you need to display multiple types of models,
        // you will need to combine the results of each
        // query builder manually:
        return Event::query();
    }

    public function getOptions(): array
    {
        return [
            'nowIndicator' => true,
            // 'slotDuration' => '00:15:00',
        ];
    }
}
