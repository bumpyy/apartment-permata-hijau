<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\News;

class HomeController extends Controller
{
    public function __invoke()
    {

        $events = Event::take(10)->get();
        $news = News::take(10)->get();

        return view('welcome', compact('events', 'news'));
    }
}
