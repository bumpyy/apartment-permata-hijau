<?php

namespace App\Http\Controllers;

use App\Mail\TestMail;
use Illuminate\Support\Facades\Mail;

class TestMailController extends Controller
{
    public function __invoke()
    {
        $data = [
            'name' => 'Ghassan uiu',
        ];

        $mail = new TestMail($data);
        Mail::to('fsghassan2429d@gmail.com')->send($mail);

    }
}
