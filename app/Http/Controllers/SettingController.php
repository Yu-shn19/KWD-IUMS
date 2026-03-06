<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * Show the form to change the consumer edit PIN.
     */
    public function showConsumerEditPin()
    {
        return view('settings.consumer-edit-pin');
    }

    /**
     * Update the consumer edit PIN.
     */
    public function updateConsumerEditPin(Request $request)
    {
        $request->validate([
            'current_pin' => 'required|string',
            'new_pin' => 'required|string|min:4|confirmed',
        ], [
            'new_pin.min' => 'New PIN must be at least 4 characters.',
            'new_pin.confirmed' => 'New PIN confirmation does not match.',
        ]);

        if (!Setting::verifyConsumerEditPin($request->current_pin)) {
            return back()->withErrors(['current_pin' => 'Current PIN is incorrect.'])->withInput();
        }

        Setting::setConsumerEditPin($request->new_pin);

        return redirect()->route('settings.consumer-edit-pin')
            ->with('success', 'Consumer edit PIN updated successfully.');
    }
}
