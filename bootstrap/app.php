<?php

use App\Exceptions\FeatureNotAvailableException;
use App\Exceptions\InsufficientCreditsException;
use App\Exceptions\N8nConnectionException;
use App\Http\Middleware\VerifyFirebaseToken;
use App\Http\Middleware\VerifyN8nSecret;
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
            'verify.n8n' => VerifyN8nSecret::class,
        ]);

        // `throttleApi()` permanece deliberadamente ausente: o grupo `api` não
        // pode carregar rate limiting por IP, sob pena de descartar callbacks
        // legítimos do n8n (SAD 7 e CLAUDE.md §5).
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

        // Pipeline fora do plano é 403, nunca 402: o usuário precisa de
        // upgrade, não de créditos. Códigos e mensagens permanecem distintos
        // por exigência da Seção 12.5 (SAD 12.5 e L.6).
        $exceptions->render(function (FeatureNotAvailableException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => 'This feature is not available on your current plan.',
                'error' => 'feature_not_available',
                'cta' => 'Upgrade plan',
            ], 403);
        });
    })->create();
