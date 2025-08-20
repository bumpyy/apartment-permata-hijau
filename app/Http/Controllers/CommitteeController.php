<?php

namespace App\Http\Controllers;

use App\Models\Committee;

class CommitteeController extends Controller
{
    public function __invoke()
    {
        $committees = Committee::orderBy('order_column')->get();

        return view('committee', compact('committees'));
    }
}
