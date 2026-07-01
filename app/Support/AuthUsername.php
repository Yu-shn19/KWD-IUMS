<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

class AuthUsername
{
    /**
     * Formatted name for ledger/audit fields: LAST_NAME, FIRST_NAME M. EXT
     */
    public static function formatted(): string
    {
        $user = Auth::user();

        if (!$user) {
            return 'SYSTEM';
        }

        $formattedName = strtoupper($user->last_name ?? '') . ', ' . strtoupper($user->first_name ?? '');

        if (!empty($user->middle_name)) {
            $formattedName .= ' ' . strtoupper(substr($user->middle_name, 0, 1)) . '.';
        }

        if (!empty($user->extension)) {
            $formattedName .= ' ' . strtoupper($user->extension);
        }

        $formattedName = trim($formattedName);

        return $formattedName !== '' ? $formattedName : ($user->name ?? 'SYSTEM');
    }

    /**
     * First name for display (e.g. "DELA CRUZ, JUAN M." -> "JUAN").
     */
    public static function displayFirstName(?string $formattedName): string
    {
        $formattedName = trim((string) $formattedName);

        if ($formattedName === '') {
            return '';
        }

        $parts = explode(',', $formattedName);

        if (count($parts) < 2) {
            $words = explode(' ', $formattedName);

            return !empty($words[0]) ? trim($words[0]) : '';
        }

        $nameWords = explode(' ', trim($parts[1]));

        return !empty($nameWords[0]) ? trim($nameWords[0]) : '';
    }
}
