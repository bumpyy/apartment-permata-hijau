<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{
    public function index()
    {
        // Logic to display the contact form
        return view('contact.index');
    }

    public function store(Request $request)
    {
        // Logic to handle form submission
        $request->validate([
            'name' => 'required|string|max:191',
            // 'phone' => 'required|regex:/^(\+?\d{1,3}[- ]?)?\d{10}$/',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|max:191',
            'message' => 'required|string|max:5000',
        ]);

        // Create a new message
        try {
            Message::create($request->all());
        } catch (\Exception $e) {
            Log::error('Failed to save message: '.$e->getMessage(), [
                'request' => $request->all(),
            ]);

            return redirect()->route('contact.index')->with('error', 'Failed to send message. Please try again later.');
        }

        // Validate and save the message
        return redirect()->route('contact.index')->with('success', 'Message sent successfully!');
    }
}
