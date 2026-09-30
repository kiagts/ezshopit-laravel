<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureShopper;
use App\Http\Middleware\EnsureVendorIsApproved;
use App\Http\Middleware\RedirectAuthenticatedUsersToDashboard;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetRequestLanguage;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            require base_path('routes/admin.php');
            require base_path('routes/vendor.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', SecurityHeaders::class);
        $middleware->appendToGroup('web', SetRequestLanguage::class);
        $middleware->validateCsrfTokens(except: [
            'webhooks/stripe',
        ]);

        $middleware->alias([
            'guest.dashboard' => RedirectAuthenticatedUsersToDashboard::class,
            'shopper' => EnsureShopper::class,
            'vendor.approved' => EnsureVendorIsApproved::class,
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
