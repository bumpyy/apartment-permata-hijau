<?php

namespace App\Livewire;

use App\Models\Event;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Guava\Calendar\Actions\CreateAction;
use Guava\Calendar\Widgets\CalendarWidget;
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
                ->rules(['required', 'string', 'max:255']),
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

    public function getResources(): Collection|array
    {
        return [
            Event::first()->toCalendarEvent(),
        ];
    }

    public function getEvents(array $fetchInfo = []): Collection|array
    {
        return [
            Event::first()->toCalendarEvent(),
        ];
    }

    public function getDateClickContextMenuActions(): array
    {
        return [
            CreateAction::make('foo')
                ->model(Event::class)
                ->mountUsing(fn ($arguments, $form) => $form->fill([
                    'start_at' => data_get($arguments, 'dateStr'),
                    'end_at' => data_get($arguments, 'dateStr'),
                ])),
        ];
    }

    public function getOptions(): array
    {
        return [
            'nowIndicator' => true,
            // 'slotDuration' => '00:15:00',
        ];
    }
}
