<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The local ngrok agent terminates HTTPS before forwarding to Artisan.
        $middleware->trustProxies(at: ['127.0.0.1', '::1'], headers: Request::HEADER_X_FORWARDED_PROTO);
        $middleware->alias(['recent-owner' => \App\Http\Middleware\RecentOwner::class, 'account-write' => \App\Http\Middleware\EnsureAccountWritable::class]);
        $middleware->web(append: [\App\Http\Middleware\ExpireDemoLogin::class]);
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->preventRequestForgery(except: ['doku/notification']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
    })->create();



