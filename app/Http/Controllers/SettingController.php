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

        $uploadErrors = [];

        foreach (['logo', 'favicon', 'hero_image'] as $field) {
            if (!$request->hasFile($field)) {
                continue;
            }

            try {
                $relativePath = $this->storeBrandingImage($request->file($field), $field);
                Setting::set($field === 'hero_image' ? 'hero_image' : $field, $relativePath);

                // Keep favicon in sync with logo when favicon was not uploaded separately
                if ($field === 'logo' && !$request->hasFile('favicon')) {
                    Setting::set('favicon', $relativePath);
                }
            } catch (\Throwable $e) {
                report($e);
                $uploadErrors[$field] = 'Unable to save this image. Ask the server administrator to make storage/app/public writable by the web server.';
            }
        }

        if ($uploadErrors) {
            return redirect()->route('settings.system')
                ->with('success', 'District content was saved, but one or more images could not be uploaded.')
                ->withErrors($uploadErrors);
        }

        return redirect()->route('settings.system')
            ->with('success', 'System branding settings updated successfully.');
    }

    /**
     * Serve an uploaded branding image from storage (no public/ write required).
     */
    public function serveBrandingFile(string $filename)
    {
        $filename = basename($filename);
        if (!preg_match('/^(logo|favicon|hero_image)_\d+\.(jpe?g|png|gif|webp|ico)$/i', $filename)) {
            abort(404);
        }

        $path = storage_path('app/public/branding/' . $filename);
        abort_unless(is_file($path), 404);

        return response()->file($path);
    }

    /**
     * Store a branding upload in storage/app/public (writable on typical deploys).
     * Falls back to public/WDMS/img/logo only when that directory is writable.
     */
    protected function storeBrandingImage(\Illuminate\Http\UploadedFile $file, string $field): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'png');
        $filename = $field . '_' . time() . '.' . $extension;

        $stored = $file->storeAs('branding', $filename, 'public');
        if ($stored) {
            return 'branding/' . $filename;
        }

        $publicDir = public_path('WDMS/img/logo');
        if (!File::isDirectory($publicDir)) {
            File::makeDirectory($publicDir, 0775, true);
        }

        if (!is_writable($publicDir)) {
            throw new \RuntimeException('Branding directories are not writable.');
        }

        $file->move($publicDir, $filename);

        return 'WDMS/img/logo/' . $filename;
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
