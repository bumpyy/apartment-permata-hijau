<?php

namespace App\Http\Controllers;

use App\Models\Event;

class EventController extends Controller
{
    public function index()
    {
        $events = Event::orderByDesc('start_at')->paginate(3);

        return view('event.index', compact('events'));
    }

    public function show()
    {
        return view('event.show');
    }
}
