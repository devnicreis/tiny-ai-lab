<?php

namespace App\Http\Middleware;

use App\Services\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garante o contexto de organização de toda rota que depende dele (SAD 20.5).
 *
 * Roda sempre depois de `auth.firebase`: a identidade é pré-condição da
 * participação. É o único lugar onde a organização ativa é *escolhida* — a
 * `OrganizationContext` apenas lê, valida e guarda.
 *
 * Divisão com `EnsureActiveProject` (SAD 20.5, item [A DEFINIR]): este
 * middleware responde exclusivamente pela organização ativa e pela participação
 * do usuário nela. A validação de que a Brand ativa pertence à organização
 * ativa pertence ao middleware de Brand, que roda depois — a verificação da
 * organização antecede qualquer verificação de Brand.
 *
 * A reconferência de participação acontece a cada requisição, não apenas na
 * troca: um membro removido perde o acesso na requisição seguinte, sem esperar
 * novo login.
 */
class EnsureActiveOrganization
{
    public function __construct(
        private readonly OrganizationContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['error' => 'unauthenticated'], Response::HTTP_UNAUTHORIZED);
        }

        // `resolve()` valida a sessão e, quando o usuário pertence a exatamente
        // uma organização, a seleciona sozinho: sem ambiguidade não há escolha
        // a pedir (SAD 20.3).
        if ($this->context->resolve() !== null) {
            return $next($request);
        }

        return $user->organizations()->exists()
            ? $this->needsSelection($request)
            : $this->needsOrganization($request);
    }

    /**
     * Várias organizações e nenhuma ativa: o usuário precisa escolher.
     *
     * Escolher por ele decidiria de qual carteira sairia o crédito da próxima
     * execução — decisão do usuário, nunca do backend (SAD 20.5).
     */
    private function needsSelection(Request $request): Response
    {
        return $this->wantsJson($request)
            ? response()->json([
                'message' => 'Select an organization to continue.',
                'error' => 'organization_selection_required',
            ], Response::HTTP_CONFLICT)
            : redirect()->guest(route('organizations.select'));
    }

    /**
     * Nenhuma organização: nada do domínio existe fora de uma (SAD 20.2).
     */
    private function needsOrganization(Request $request): Response
    {
        return $this->wantsJson($request)
            ? response()->json([
                'message' => 'Create an organization to continue.',
                'error' => 'organization_required',
            ], Response::HTTP_CONFLICT)
            : redirect()->guest(route('organizations.create'));
    }

    private function wantsJson(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }
}
