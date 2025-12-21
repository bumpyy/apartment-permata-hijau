<?php

namespace App\Http\Controllers;

use App\Models\Event;

class EventController extends Controller
{
    public function index()
    {
        $events = Event::paginate(3)->sortBy('start_at');

        return view('event.index', compact('events'));
    }

    public function show()
    {
        return view('event.show');
    }
}
