<?php

namespace App\Http\Controllers;

class CommitteeController extends Controller
{
    public function __invoke()
    {
        $committees = \App\Models\Committee::all();

        return view('committee', compact('committees'));
    }
}
