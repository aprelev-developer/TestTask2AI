<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // backend-conventions → Domain invariants: "never normalize, trim,
        // ... an observed value" — Laravel's global TrimStrings middleware
        // would otherwise silently strip leading/trailing whitespace from
        // these fields before the comparison ever sees them.
        $middleware->trimStrings(except: [
            'displayed_address',
            'qr_address',
            'copy_button_value',
            'address_after_watch_window',
            'displayed_amount',
            'qr_amount',
            'displayed_network',
            'qr_network',
            'page_scripts',
            'page_scripts.*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
