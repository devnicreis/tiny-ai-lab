<?php

namespace App\Http\Middleware;

use App\Actions\Auth\SyncFirebaseUserAction;
use App\Services\FirebaseService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fronteira de autenticação entre o Firebase e o Laravel.
 *
 * Valida o Firebase ID Token de toda requisição protegida, sincroniza o usuário
 * local e o injeta no contexto da request. Nenhuma regra de autorização,
 * assinatura ou crédito é avaliada aqui.
 */
class VerifyFirebaseToken
{
    public function __construct(
        private readonly SyncFirebaseUserAction $syncUser,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token === null || $token === '') {
            return $this->unauthenticated();
        }

        try {
            // Resolvido sob demanda: requisições sem token não precisam
            // inicializar o Admin SDK.
            $claims = app(FirebaseService::class)->verifyIdToken($token);

            $user = $this->syncUser->handle($claims);
        } catch (InvalidArgumentException) {
            return $this->unauthenticated();
        }

        Auth::login($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }

    private function unauthenticated(): Response
    {
        return response()->json(['error' => 'unauthenticated'], Response::HTTP_UNAUTHORIZED);
    }
}
