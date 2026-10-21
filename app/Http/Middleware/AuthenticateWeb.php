<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fronteira de autenticação das rotas web (SAD 5, Sessão 3.1).
 *
 * Verifica a sessão Laravel convencional estabelecida por
 * `App\Http\Controllers\Web\AuthController::firebase()`. Nunca valida o
 * Firebase ID Token: essa validação acontece uma única vez, no login. Nunca
 * se mistura com `auth.firebase` (Bearer) na mesma rota.
 */
class AuthenticateWeb
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->guest(route('login'));
        }

        return $next($request);
    }
}
