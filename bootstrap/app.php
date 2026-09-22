<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'exclude.notifications' => \App\Http\Middleware\ExcludeNotifications::class,
            'security.headers' => \App\Http\Middleware\SecurityHeaders::class,
            'rate.limiting' => \App\Http\Middleware\RateLimiting::class,
            'input.sanitization' => \App\Http\Middleware\InputSanitization::class,
        ]);
        
        // Apply security middleware globally
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->append(\App\Http\Middleware\InputSanitization::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
