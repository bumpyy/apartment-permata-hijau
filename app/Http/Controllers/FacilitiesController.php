<?php

namespace App\Http\Controllers;

use App\Models\Court;

class FacilitiesController extends Controller
{
    public function index()
    {
        return view('facilities');
    }

    public function tennis()
    {
        $courts = Court::all();

        return view('facilities.tennis', compact('courts'));
    }
}
