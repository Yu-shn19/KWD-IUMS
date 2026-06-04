<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * HTTP trigger for shared hosting (e.g. Hostinger) where SSH/artisan is not available.
 * Set a secret token in .env and call this URL from hPanel → Cron Jobs.
 */
class ConsumerActivationCronController extends Controller
{
    public function activatePending(Request $request): JsonResponse
    {
        $expected = (string) config('consumer.cron_token', '');
        $provided = (string) $request->query('token', '');

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $options = [];
        if ($request->boolean('backfill')) {
            $options['--backfill'] = true;
        }
        if ($request->boolean('dry_run')) {
            $options['--dry-run'] = true;
        }

        $exitCode = Artisan::call('consumers:activate-pending-after-install', $options);

        return response()->json([
            'success' => $exitCode === 0,
            'exit_code' => $exitCode,
            'output' => trim(Artisan::output()),
        ]);
    }
}
