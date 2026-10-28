<?php

use App\Http\Middleware\EnsureOrganization;
use App\Http\Middleware\EnsureSystemAdmin;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            // 'organization' exige uma organização selecionada e vínculo
            // ativo; com parâmetro — 'organization:company' — exige também
            // o tipo. Antes eram dois middlewares quase idênticos.
            'organization' => EnsureOrganization::class,
            'system' => EnsureSystemAdmin::class,
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

        // Erro de regra de negócio: a mensagem é escrita para ser lida pelo
        // usuário, então vai inteira.
        $exceptions->render(function (DomainException $exception, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            return response()->json(['message' => $exception->getMessage()], 409);
        });

        // Qualquer outra falha: o detalhe fica no log, não na tela. Antes cada
        // controller devolvia `$e->getMessage()` com status 500, o que
        // entregava SQL e caminho de arquivo ao navegador.
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            // As que o Laravel já traduz sozinho seguem o caminho dele:
            // 422 de validação, 401 de sessão, 404 de rota e afins.
            $handledByFramework = $exception instanceof HttpExceptionInterface
                || $exception instanceof ValidationException
                || $exception instanceof AuthenticationException;

            if ($handledByFramework) {
                return null;
            }

            report($exception);

            return response()->json([
                'message' => config('app.debug')
                    ? $exception->getMessage()
                    : 'Não foi possível concluir a operação. Tente novamente.',
            ], 500);
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
