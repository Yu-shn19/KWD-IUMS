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
     * Human-readable labels for known actions / route names.
     *
     * @var array<string, string>
     */
    protected static array $actionLabels = [
        // Account
        'login' => 'Logged In',
        'login.failed' => 'Failed Login',
        'logout' => 'Logged Out',
        'register' => 'Registered Account',
        'profile.updated' => 'Updated Profile',
        'user.created' => 'Created User Account',
        'user.updated' => 'Updated User Account',
        'user.deleted' => 'Deleted User Account',

        // Consumers
        'consumer.store' => 'Added New Consumer',
        'consumer.update' => 'Updated Consumer',
        'consumer.destroy' => 'Deleted Consumer',
        'consumer.import.store' => 'Imported Consumers',

        // Billing processes
        'billing-processes.prepare-meter-reading' => 'Prepared Meter Reading',
        'billing-processes.save-schedules' => 'Saved Meter Reading Schedules',
        'billing-processes.assign-to-reader' => 'Assigned Schedules to Reader',
        'billing-processes.delete-schedules' => 'Deleted Meter Reading Schedules',
        'billing-processes.update-schedule-batch' => 'Updated Schedule Batch',
        'billing-processes.search' => 'Searched Billing Records',
        'billing-processes.export' => 'Exported Billing Data',
        'billing-processes.get-downloaded-readings' => 'Loaded Downloaded Readings',
        'billing-processes.surcharge-candidates' => 'Viewed Surcharge Candidates',
        'billing-processes.apply-surcharge' => 'Applied Surcharge / Penalty',
        'billing-processes.mark-paid' => 'Marked Bill as Paid',
        'billing-processes.single-penalty-candidate' => 'Checked Penalty Candidate',

        // Consumer master list / DM
        'consumer-master-list.bulk-dm' => 'Saved Bulk Debit Memo',
        'consumer-master-list.store-dm' => 'Saved Debit Memo',
        'consumer-master-list.import-dm' => 'Imported Debit Memo',

        // Meter reading
        'meter-reading.assign' => 'Assigned Meter Reading',
        'meter-reading.unassign' => 'Unassigned Meter Reading',
        'meter-reading.upload-previous-reading' => 'Uploaded Previous Reading',

        // Billing adjustment
        'billing-adjustment.store' => 'Created Billing Adjustment',
        'billing-adjustment.update' => 'Updated Billing Adjustment',
        'billing-adjustment.lro.update' => 'Updated LRO Adjustment',
        'billing-adjustment.lro.destroy' => 'Deleted LRO Adjustment',
        'billing-adjustment.ar.update' => 'Updated AR Adjustment',
        'billing-adjustment.ar.destroy' => 'Deleted AR Adjustment',

        // Payments
        'billing-payment.cancelled-or' => 'Cancelled Official Receipt',
        'billing-payment.delete' => 'Deleted Payment',

        // Penalty
        'penalty.update' => 'Updated Penalty',

        // Disconnection
        'disconnection.store' => 'Created Disconnection Order',
        'disconnection.update' => 'Updated Disconnection Order',
        'disconnection.destroy' => 'Deleted Disconnection Order',

        // Collection / imports
        'collection.import.store' => 'Imported Collection',
        'ledger.import.store' => 'Imported Ledger',
        'lro-ledger.import.store' => 'Imported LRO Ledger',

        // Settings / pricing
        'settings.consumer-edit-pin.update' => 'Changed Consumer Edit PIN',
        'pricing-tiers.store' => 'Created Pricing Tier',
        'pricing-tiers.update' => 'Updated Pricing Tier',
        'pricing-tiers.destroy' => 'Deleted Pricing Tier',
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
            'action' => self::labelFor($action),
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
        $input = self::sanitizeInput($request);

        $actionKey = $routeName
            ? $routeName
            : strtolower($method) . '.' . Str::slug($path, '.');

        $actionLabel = self::labelFor($actionKey, $method);
        $description = self::buildAdminDescription($user, $actionLabel, $input, $routeName);

        return self::log($actionLabel, $description, $user instanceof User ? $user : null, [
            'method' => $method,
            'path' => $path,
            'route' => $routeName,
            'status' => $statusCode,
            'input' => $input,
        ]);
    }

    /**
     * Convert a technical action / route name into a plain-language label.
     */
    public static function labelFor(string $action, ?string $method = null): string
    {
        // Already human-readable (no dots / admin prefix)
        if (!str_contains($action, '.') && !str_contains($action, '_') && !str_contains($action, '-')) {
            // Could still be camelCase technical — but most friendly labels have spaces
            if (preg_match('/^[A-Z]/', $action) || str_contains($action, ' ')) {
                return $action;
            }
        }

        $normalized = Str::lower(preg_replace('/^admin\./', '', $action) ?? $action);

        if (isset(self::$actionLabels[$normalized])) {
            return self::$actionLabels[$normalized];
        }

        // Fallback: turn consumer.store → "Consumer Store" then improve verbs
        $parts = preg_split('/[.\-_]+/', $normalized) ?: [];
        $parts = array_values(array_filter($parts, fn ($p) => $p !== '' && $p !== 'admin'));

        $verbMap = [
            'store' => 'Added',
            'create' => 'Created',
            'update' => 'Updated',
            'destroy' => 'Deleted',
            'delete' => 'Deleted',
            'import' => 'Imported',
            'export' => 'Exported',
            'assign' => 'Assigned',
            'unassign' => 'Unassigned',
            'upload' => 'Uploaded',
            'apply' => 'Applied',
            'mark' => 'Marked',
            'save' => 'Saved',
            'submit' => 'Submitted',
            'cancel' => 'Cancelled',
            'prepare' => 'Prepared',
        ];

        $last = $parts[count($parts) - 1] ?? '';
        if (isset($verbMap[$last])) {
            array_pop($parts);
            $subject = collect($parts)
                ->map(fn ($p) => Str::title(str_replace(['_', '-'], ' ', $p)))
                ->implode(' ');

            return trim($verbMap[$last] . ' ' . $subject) ?: self::methodFallback($method);
        }

        if ($method) {
            $subject = collect($parts)
                ->map(fn ($p) => Str::title(str_replace(['_', '-'], ' ', $p)))
                ->implode(' ');

            return trim(self::methodVerb($method) . ' ' . $subject) ?: self::methodFallback($method);
        }

        return collect($parts)
            ->map(fn ($p) => Str::title(str_replace(['_', '-'], ' ', $p)))
            ->implode(' ') ?: $action;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    protected static function buildAdminDescription(
        ?User $user,
        string $actionLabel,
        array $input,
        ?string $routeName
    ): string {
        $name = $user?->name ?? 'Admin';
        $details = [];

        foreach (['account_no', 'account_number', 'meter_number', 'or_number', 'zone', 'bill_month'] as $key) {
            if (!empty($input[$key]) && is_scalar($input[$key])) {
                $label = match ($key) {
                    'account_no', 'account_number' => 'Account No.',
                    'meter_number' => 'Meter No.',
                    'or_number' => 'OR No.',
                    'zone' => 'Zone',
                    'bill_month' => 'Bill Month',
                    default => Str::title(str_replace('_', ' ', $key)),
                };
                $details[] = $label . ' ' . $input[$key];
            }
        }

        if (!empty($input['last_name']) || !empty($input['first_name'])) {
            $consumerName = trim(($input['last_name'] ?? '') . ', ' . ($input['first_name'] ?? ''), ' ,');
            if ($consumerName !== '') {
                $details[] = 'Name: ' . $consumerName;
            }
        } elseif (!empty($input['account_name']) && is_scalar($input['account_name'])) {
            $details[] = 'Name: ' . $input['account_name'];
        }

        $suffix = $details ? ' (' . implode(', ', $details) . ')' : '';

        return $name . ' — ' . $actionLabel . $suffix;
    }

    protected static function methodVerb(string $method): string
    {
        return match (strtoupper($method)) {
            'POST' => 'Submitted',
            'PUT', 'PATCH' => 'Updated',
            'DELETE' => 'Deleted',
            default => 'Performed',
        };
    }

    protected static function methodFallback(?string $method): string
    {
        return match (strtoupper((string) $method)) {
            'POST' => 'Submitted Form',
            'PUT', 'PATCH' => 'Updated Record',
            'DELETE' => 'Deleted Record',
            default => 'System Action',
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected static function sanitizeInput(Request $request): array
    {
        $input = $request->except(self::$sensitiveKeys);

        foreach ($request->allFiles() as $key => $file) {
            if (is_array($file)) {
                $input[$key] = collect($file)->map(fn ($f) => $f?->getClientOriginalName())->filter()->values()->all();
            } else {
                $input[$key] = $file?->getClientOriginalName();
            }
        }

        $encoded = json_encode($input);
        if ($encoded !== false && strlen($encoded) > 4000) {
            return ['_truncated' => true, 'keys' => array_keys($input)];
        }

        return $input;
    }
}
