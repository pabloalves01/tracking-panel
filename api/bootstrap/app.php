<?php

use App\Http\Middleware\EnsureCompanySelected;
use App\Http\Middleware\EnsureFuelStationSelected;
use App\Http\Middleware\EnsureSystemAdmin;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'ensure.company' => EnsureCompanySelected::class, // Verifica se a empresa está selecionada
            'ensure.station' => EnsureFuelStationSelected::class, // Verifica se o posto está selecionado
            'ensure.system' => EnsureSystemAdmin::class, // Verifica se o usuário é um administrador do sistema
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (AuthorizationException|AccessDeniedHttpException $exception, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            $message = $exception->getMessage();
            $english = $message === ''
                || $message === 'This action is unauthorized.'
                || $message === 'This action is unauthorized'
                || $message === 'Forbidden';

            return response()->json([
                'message' => $english ? 'Você não tem permissão para esta ação.' : $message,
            ], 403);
        });

        $exceptions->render(function (ThrottleRequestsException $exception, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $seconds = (int) ($exception->getHeaders()['Retry-After'] ?? 0);
                $message = $seconds > 0
                    ? "Muitas tentativas. Tente novamente em {$seconds} segundos."
                    : 'Muitas tentativas. Tente novamente em instantes';

                return response()->json([
                    'message' => $message,
                    'retry_after' => $seconds,
                ], 429, $exception->getHeaders());
            }

            return null;
        });
    })->create();
