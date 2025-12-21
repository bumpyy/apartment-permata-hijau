<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\News;
use Carbon\Carbon;

class HomeController extends Controller
{
    public function __invoke()
    {
        seo()
            ->title(env('APP_NAME', 'Permata Hijau'), template: false)
            ->description('Selamat datang di Permata Hijau.');

        $events = Event::orderByDesc('start_at')
            // ->whereDate('end_at', '>=', Carbon::today())
            ->take(2)
            ->get();

        $news = News::take(10)
            ->orderBy('published_at', 'desc')
            ->get();

        return view('welcome', compact('events', 'news'));
    }
}
