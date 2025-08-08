<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\News;

class HomeController extends Controller
{
    public function __invoke()
    {
        seo()
            ->title(env('APP_NAME', 'Permata Hijau'), template: false)
            ->description('Selamat datang di Permata Hijau.');

        $events = Event::take(10)->get();
        $news = News::take(10)->get();

        return view('welcome', compact('events', 'news'));
    }
}
