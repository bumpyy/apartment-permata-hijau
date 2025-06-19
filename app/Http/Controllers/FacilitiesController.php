<?php

namespace App\Http\Controllers;

use App\Models\Court;

class FacilitiesController extends Controller
{
    public function __invoke()
    {
        $courts = Court::all();

        return view('facilities', compact('courts'));
    }
}
