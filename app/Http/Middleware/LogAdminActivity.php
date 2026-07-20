<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\ActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class LogAdminActivity
{
    /**
     * Routes already logged with richer detail elsewhere.
     *
     * @var list<string>
     */
    protected array $skipRouteNames = [
        'logout',
        'profile.update',
        'user.store',
        'user.update',
        'user.destroy',
        'activity-logs.index',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldLog($request, $response)) {
            ActivityLogger::logAdminRequest($request, $response->getStatusCode());
        }

        return $response;
    }

    protected function shouldLog(Request $request, Response $response): bool
    {
        $user = Auth::user();
        if (!$user instanceof User || $user->role !== 'admin') {
            return false;
        }

        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return false;
        }

        // Only successful (or redirect) outcomes — skip validation/auth failures
        if ($response->getStatusCode() >= 400) {
            return false;
        }

        $routeName = $request->route()?->getName();
        if ($routeName && in_array($routeName, $this->skipRouteNames, true)) {
            return false;
        }

        return true;
    }
}
