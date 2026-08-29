<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Default branding values for a water district install.
     */
    public static function brandingDefaults(): array
    {
        return [
            'org_name' => 'Malalag Water Works System',
            'org_short_name' => 'MWS-IUMS',
            'org_address' => 'Malalag, Davao del Sur',
            'org_tagline' => 'Integrated Water Utility Management System',
            'logo' => 'WDMS/img/logo/malalag.png',
            'favicon' => 'WDMS/img/logo/malalag.png',
            'hero_image' => 'WDMS/img/logo/hero.jpeg',
        ];
    }

    /**
     * Branding setting keys stored in the settings table.
     */
    public static function brandingKeys(): array
    {
        return array_keys(static::brandingDefaults());
    }

    /**
     * Resolved branding for views (text + absolute asset URLs).
     */
    public static function branding(): array
    {
        $defaults = static::brandingDefaults();
        $values = [];

        try {
            $rows = static::whereIn('key', array_keys($defaults))->pluck('value', 'key');
            foreach ($defaults as $key => $default) {
                $stored = $rows[$key] ?? null;
                $values[$key] = ($stored !== null && $stored !== '') ? $stored : $default;
            }
        } catch (\Throwable $e) {
            $values = $defaults;
        }

        $logo = static::resolvePublicPath($values['logo'], $defaults['logo']);
        $favicon = static::resolvePublicPath($values['favicon'], $defaults['favicon']);
        $hero = static::resolvePublicPath($values['hero_image'], $defaults['hero_image']);

        return [
            'org_name' => $values['org_name'],
            'org_name_upper' => mb_strtoupper($values['org_name']),
            'org_short_name' => $values['org_short_name'],
            'org_address' => $values['org_address'],
            'org_tagline' => $values['org_tagline'],
            'logo' => $logo,
            'favicon' => $favicon,
            'hero_image' => $hero,
            'logo_url' => asset($logo),
            'favicon_url' => asset($favicon),
            'hero_url' => asset($hero),
        ];
    }

    /**
     * Ensure a stored path exists under public/; otherwise fall back.
     */
    protected static function resolvePublicPath(string $path, string $fallback): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if ($path !== '' && is_file(public_path($path))) {
            return $path;
        }

        $fallback = ltrim(str_replace('\\', '/', $fallback), '/');
        return $fallback;
    }

    /**
     * Get a setting value by key.
     */
    public static function get(string $key, $default = null)
    {
        $row = static::where('key', $key)->first();
        return $row ? $row->value : $default;
    }

    /**
     * Set a setting value by key.
     */
    public static function set(string $key, $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    /**
     * Check if the given PIN matches the stored consumer edit PIN.
     */
    public static function verifyConsumerEditPin(string $pin): bool
    {
        $stored = static::get('consumer_edit_pin');
        if ($stored === null || $stored === '') {
            return false;
        }
        return Hash::check($pin, $stored);
    }

    /**
     * Update the consumer edit PIN (store hashed).
     */
    public static function setConsumerEditPin(string $pin): void
    {
        static::set('consumer_edit_pin', Hash::make($pin));
    }
}
