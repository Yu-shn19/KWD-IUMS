<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

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
