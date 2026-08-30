<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class SettingController extends Controller
{
    /**
     * Show system branding / district customization settings.
     */
    public function showSystemSettings()
    {
        return view('settings.system', [
            'branding' => Setting::branding(),
            'defaults' => Setting::brandingDefaults(),
        ]);
    }

    /**
     * Update branding text and optional logo / favicon / hero uploads.
     */
    public function updateSystemSettings(Request $request)
    {
        $request->validate([
            'org_name' => 'required|string|max:150',
            'org_short_name' => 'required|string|max:50',
            'org_address' => 'required|string|max:255',
            'org_tagline' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'favicon' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,ico|max:5120',
            'hero_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        Setting::set('org_name', trim($request->org_name));
        Setting::set('org_short_name', trim($request->org_short_name));
        Setting::set('org_address', trim($request->org_address));
        Setting::set('org_tagline', trim($request->org_tagline));

        $directory = public_path('WDMS/img/logo');
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        foreach (['logo', 'favicon', 'hero_image'] as $field) {
            if (!$request->hasFile($field)) {
                continue;
            }

            $file = $request->file($field);
            $filename = $field . '_' . time() . '.' . $file->getClientOriginalExtension();
            $file->move($directory, $filename);

            $relativePath = 'WDMS/img/logo/' . $filename;
            Setting::set($field === 'hero_image' ? 'hero_image' : $field, $relativePath);

            // Keep favicon in sync with logo when favicon was not uploaded separately
            if ($field === 'logo' && !$request->hasFile('favicon')) {
                Setting::set('favicon', $relativePath);
            }
        }

        return redirect()->route('settings.system')
            ->with('success', 'System branding settings updated successfully.');
    }

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
