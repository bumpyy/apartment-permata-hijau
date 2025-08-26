<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Committee extends Model implements HasMedia, Sortable
{
    use InteractsWithMedia,
        SortableTrait;

    protected $guarded = [];
}
