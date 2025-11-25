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
    ->withMiddleware(function (Middleware $middleware): void {
        // Añadir el middleware de Sanctum para las peticiones API 
        // Asegura que las cookies de sesión se manejen correctamente
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // Las rutas que coincidan con estos patrones no requerirán un token CSRF (Cross-Site Request Forgery)
        $middleware->validateCsrfTokens(except: [
            'http://localhost:8000/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

    })->create();