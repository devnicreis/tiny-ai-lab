<?php

use App\Exceptions\InsufficientCreditsException;
use App\Exceptions\N8nConnectionException;
use App\Http\Middleware\VerifyFirebaseToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // A rota /broadcasting/auth não usa sessão: a identidade vem do Firebase
    // ID Token, exatamente como nas demais rotas protegidas (SAD 9.5).
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['auth.firebase']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.firebase' => VerifyFirebaseToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // A falha de comunicação com o n8n é registrada com contexto mínimo e
        // controlado: a URL do webhook e o segredo compartilhado nunca podem
        // chegar ao log. O `false` interrompe o report padrão, que imprimiria o
        // trace completo. Sentry substituirá este canal futuramente.
        $exceptions->report(function (N8nConnectionException $e): bool {
            Log::error($e->getMessage(), $e->context());

            return false;
        });

        // Saldo insuficiente é decisão de negócio, não erro de aplicação:
        // responde 402 com a CTA "Buy AI Credits" (SAD 12.7 e L.6).
        $exceptions->render(function (InsufficientCreditsException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => 'Créditos insuficientes para executar esta ação.',
                'error' => 'insufficient_credits',
                'required' => $e->required,
                'available' => $e->available,
                'cta' => 'Buy AI Credits',
            ], 402);
        });
    })->create();
