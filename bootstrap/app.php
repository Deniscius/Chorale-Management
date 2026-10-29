<?php

use App\Http\Middleware\AuthenticateChoraleSession;
use App\Http\Middleware\EnsureModuleAccess;
use App\Http\Middleware\EstablishTenant;
use App\Http\Middleware\PlatformAccess;
use App\Http\Middleware\RecordPlatformUsage;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrustDeploymentProxies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->replace(TrustProxies::class, TrustDeploymentProxies::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->web(append: [EstablishTenant::class, RecordPlatformUsage::class]);
        $middleware->validateCsrfTokens(except: ['internal/reminders/run']);
        $middleware->prependToPriorityList(SubstituteBindings::class, EstablishTenant::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, PlatformAccess::class);
        $middleware->alias([
            'module' => EnsureModuleAccess::class,
            'platform.auth' => PlatformAccess::class,
            'chorale.session' => AuthenticateChoraleSession::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontFlash(['license_key', 'code', 'current_password', 'setup_token']);
    })->create();
