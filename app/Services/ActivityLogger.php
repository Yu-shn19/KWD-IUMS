<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ActivityLogger
{
    /**
     * Sensitive request keys that must never be stored in activity logs.
     *
     * @var list<string>
     */
    protected static array $sensitiveKeys = [
        'password',
        'password_confirmation',
        'current_password',
        'current_pin',
        'new_pin',
        'new_pin_confirmation',
        'pin',
        'token',
        '_token',
    ];

    /**
     * Record a user / system activity.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function log(
        string $action,
        string $description,
        ?User $user = null,
        array $properties = []
    ): ActivityLog {
        $user = $user ?? Auth::user();
        $userId = $user instanceof User ? $user->id : null;

        return ActivityLog::create([
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'properties' => $properties ?: null,
            'created_at' => now(),
        ]);
    }

    /**
     * Log an admin HTTP request as a system action.
     */
    public static function logAdminRequest(Request $request, int $statusCode): ActivityLog
    {
        /** @var User|null $user */
        $user = Auth::user();
        $routeName = $request->route()?->getName();
        $method = strtoupper($request->method());
        $path = '/' . ltrim($request->path(), '/');

        $action = $routeName
            ? 'admin.' . $routeName
            : 'admin.' . strtolower($method) . '.' . Str::slug($path, '.');

        $label = $routeName
            ? str_replace(['.', '-', '_'], ' ', $routeName)
            : $path;

        $description = sprintf(
            '%s %s %s',
            $user?->name ?? 'Admin',
            self::methodVerb($method),
            trim($label)
        );

        return self::log($action, $description, $user instanceof User ? $user : null, [
            'method' => $method,
            'path' => $path,
            'route' => $routeName,
            'status' => $statusCode,
            'input' => self::sanitizeInput($request),
        ]);
    }

    protected static function methodVerb(string $method): string
    {
        return match ($method) {
            'POST' => 'created/submitted',
            'PUT', 'PATCH' => 'updated',
            'DELETE' => 'deleted',
            default => 'performed',
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected static function sanitizeInput(Request $request): array
    {
        $input = $request->except(self::$sensitiveKeys);

        // Drop large file payloads; keep filenames only
        foreach ($request->allFiles() as $key => $file) {
            if (is_array($file)) {
                $input[$key] = collect($file)->map(fn ($f) => $f?->getClientOriginalName())->filter()->values()->all();
            } else {
                $input[$key] = $file?->getClientOriginalName();
            }
        }

        // Keep logs compact
        $encoded = json_encode($input);
        if ($encoded !== false && strlen($encoded) > 4000) {
            return ['_truncated' => true, 'keys' => array_keys($input)];
        }

        return $input;
    }
}
