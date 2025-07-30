<?php

namespace App\Models;

use Guava\Calendar\Contracts\Eventable;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Event extends Model implements Eventable, HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'title',
        'description',
        'start_at',
        'end_at',
    ];

    public function toCalendarEvent(): CalendarEvent|array
    {
        return CalendarEvent::make($this)
            ->title($this->title)
            ->start($this->start_at)
            ->end($this->end_at);
    }
}
