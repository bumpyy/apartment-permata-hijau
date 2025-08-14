<?php

namespace App\Http\Controllers;

use App\Models\Court;
use App\Settings\SiteSettings;

class FacilitiesController extends Controller
{
    public function index(SiteSettings $siteSettings)
    {
        $whatsappNumber = $siteSettings->whatsapp_number;

        return view('facilities', compact('whatsappNumber'));
    }

    public function tennis()
    {
        $courts = Court::all();

        return view('facilities.tennis', compact('courts'));
    }
}
