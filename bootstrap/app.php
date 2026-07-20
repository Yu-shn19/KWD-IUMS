<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\ApiReaderAuth;
use App\Http\Middleware\LogAdminActivity;
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
          $middleware->use([
     //   App\Http\Middleware\Example::class,
        ]);
         // Register route middleware
        $middleware->alias([
            'role' => RoleMiddleware::class, // ✅ your role-based middleware
            'api.reader' => ApiReaderAuth::class, // ✅ API authentication for readers
            'log.activity' => LogAdminActivity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
