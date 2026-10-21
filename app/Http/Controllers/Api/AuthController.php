<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\SyncFirebaseUserAction;
use App\Enums\OrganizationRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AuthenticateFirebaseRequest;
use App\Models\Organization;
use App\Models\User;
use App\Services\FirebaseService;
use App\Services\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly SyncFirebaseUserAction $syncUser,
        private readonly OrganizationContext $context,
    ) {}

    /**
     * Autentica o usuário a partir de um Firebase ID Token e sincroniza o
     * registro local do domínio.
     */
    public function firebase(AuthenticateFirebaseRequest $request): JsonResponse
    {
        try {
            $claims = app(FirebaseService::class)
                ->verifyIdToken($request->string('token')->toString());

            $user = $this->syncUser->handle($claims);
        } catch (InvalidArgumentException) {
            return response()->json(['error' => 'unauthenticated'], Response::HTTP_UNAUTHORIZED);
        }

        // A rota não passa pelo `auth.firebase` — é ela que estabelece a
        // identidade. Fixar o usuário para esta requisição permite que a
        // OrganizationContext resolva o contexto pelo mesmo caminho de todas as
        // demais rotas, em vez de reimplementá-lo aqui.
        Auth::setUser($user);

        return response()->json($this->toPayload($user));
    }

    /**
     * Retorna o usuário autenticado pelo middleware `auth.firebase`.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($this->toPayload($user));
    }

    /**
     * Identidade, participações e contexto ativo — os três separados (SAD 20).
     *
     * `user` carrega apenas o que é do usuário: a identidade, o eixo de staff da
     * plataforma (`is_admin`, SAD 20.7) e a **carteira pessoal**, único recurso
     * que lhe pertence diretamente. Plano e carteiras da organização vivem em
     * `active_organization`, porque a entidade de faturamento é ela (SAD 12.1).
     *
     * As três carteiras viajam em campos distintos e jamais somadas: crédito do
     * plano expira, crédito comprado não, e crédito pessoal é de outra pessoa
     * jurídica do modelo (CLAUDE.md regra 27).
     *
     * `active_organization` é `null` quando não há contexto — usuário sem
     * organização ou com mais de uma e nenhuma escolhida. `plan` nulo dentro
     * dele significa organização sem assinatura ativa, que é o estado inicial
     * normal e não um erro (SAD 12.18).
     *
     * O papel viaja como um dos três valores de `OrganizationRole` — `owner`,
     * `admin` ou `member` (ADR-010) — com o rótulo de interface ao lado. Na
     * organização ativa vão também as `capabilities` já resolvidas: a interface
     * decide quais CTAs mostrar a partir delas e nunca deduz a capacidade pelo
     * valor do papel (SAD 12.12).
     *
     * @return array{
     *     user: array{id: int, email: string, name: string, is_admin: bool, purchased_credits_balance: int},
     *     organizations: array<int, array{id: int, name: string, slug: string, role: string, role_label: string}>,
     *     active_organization: array{id: int, name: string, plan: string|null, plan_credits_balance: int, purchased_credits_balance: int, role: string|null, role_label: string|null, capabilities: array<string, bool>}|null
     * }
     */
    private function toPayload(User $user): array
    {
        return [
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'is_admin' => $user->isPlatformStaff(),
                'purchased_credits_balance' => (int) $user->purchased_credits_balance,
            ],
            'organizations' => $user->organizations()
                ->get(['organizations.id', 'organizations.name', 'organizations.slug'])
                ->map(function (Organization $organization): array {
                    // O papel vem da pivot: é por organização, e nunca se
                    // confunde com `is_admin` (SAD 20.7).
                    $role = OrganizationRole::from($organization->pivot->role);

                    return [
                        'id' => $organization->id,
                        'name' => $organization->name,
                        'slug' => $organization->slug,
                        'role' => $role->value,
                        'role_label' => $role->label(),
                    ];
                })
                ->all(),
            'active_organization' => $this->activeOrganization(),
        ];
    }

    /**
     * @return array{id: int, name: string, plan: string|null, plan_credits_balance: int, purchased_credits_balance: int, role: string|null, role_label: string|null, capabilities: array<string, bool>}|null
     */
    private function activeOrganization(): ?array
    {
        $organization = $this->context->resolve();

        if ($organization === null) {
            return null;
        }

        $role = $this->context->currentRole();

        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'plan' => $organization->plan,
            'plan_credits_balance' => (int) $organization->plan_credits_balance,
            'purchased_credits_balance' => (int) $organization->purchased_credits_balance,
            'role' => $role?->value,
            'role_label' => $role?->label(),
            // Sem papel não há capacidade: a interface não mostra CTA algum.
            'capabilities' => $role?->capabilities() ?? [],
        ];
    }
}
