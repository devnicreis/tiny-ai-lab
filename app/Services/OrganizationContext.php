<?php

namespace App\Services;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * A organização ativa da sessão (SAD 20.5).
 *
 * O contexto do Arkion tem duas variáveis — `active_organization_id` e
 * `active_project_id` — e esta classe é a única autoridade sobre a primeira.
 * Nenhum Controller, componente Livewire ou Policy lê `session()` diretamente:
 * todos perguntam aqui, para que a validação de participação aconteça uma única
 * vez e em um único lugar.
 *
 * ## Por que `scoped` e não `singleton`
 *
 * O contexto é por requisição, não por processo. Um Worker do Horizon ou o
 * Octane manteriam um singleton vivo entre requisições de **usuários
 * diferentes**, e a organização ativa de um vazaria para o próximo — que é
 * exatamente a falha de isolamento que a Seção 20.6 existe para impedir. O
 * cache interno é o motivo de a classe ter estado: sem ele, cada Policy
 * consultada em uma listagem repetiria a mesma consulta de participação.
 *
 * O cache é amarrado à instância da `Request` corrente, e não apenas ao ciclo
 * de vida do container. Em um container que sobrevive a mais de uma requisição
 * — a suíte de testes, o Octane — a instância `scoped` continuaria viva e
 * serviria o contexto da requisição anterior, inclusive **depois de o usuário
 * ter sido removido da organização**. Trocou a Request, o cache morre.
 *
 * ## A participação é reconferida a cada acesso
 *
 * `current()` nunca confia no identificador da sessão. O usuário pode ter sido
 * removido da organização entre duas requisições, e a sessão continuaria
 * apontando para ela: a leitura valida a participação e **limpa** o contexto
 * quando ela não existe mais (SAD 20.5). Perder acesso é imediato, não vale até
 * o próximo login.
 */
class OrganizationContext
{
    public const SESSION_KEY = 'active_organization_id';

    public const BRAND_SESSION_KEY = 'active_project_id';

    private ?Organization $resolved = null;

    private bool $resolvedCached = false;

    /**
     * A Request que preencheu o cache — a identidade da requisição corrente.
     */
    private ?Request $cachedFor = null;

    /**
     * A organização ativa da sessão, ou `null` quando não há contexto válido.
     *
     * Nunca seleciona nada por conta própria: a escolha automática pertence ao
     * middleware `EnsureActiveOrganization`, que também sabe para onde mandar o
     * usuário quando ela não é possível.
     */
    public function current(): ?Organization
    {
        $this->discardCacheOfPreviousRequest();

        if ($this->resolvedCached) {
            return $this->resolved;
        }

        $this->rememberCacheOwner();
        $this->resolved = null;

        $user = $this->user();
        $organizationId = session(self::SESSION_KEY);

        if ($user === null || ! is_numeric($organizationId)) {
            return null;
        }

        $organization = Organization::query()->find((int) $organizationId);

        // Organização excluída ou participação revogada: o contexto é inválido
        // e desaparece em vez de ser "quase certo" (SAD 20.5).
        if ($organization === null || ! $user->belongsToOrganization($organization)) {
            $this->forgetSession();

            return null;
        }

        return $this->resolved = $organization;
    }

    /**
     * O contexto atual ou a única organização do usuário.
     *
     * A seleção automática só acontece quando não há ambiguidade — uma
     * organização apenas. Com duas ou mais, escolher por ele seria decidir de
     * qual carteira sairia o crédito da próxima execução (SAD 20.3).
     */
    public function resolve(): ?Organization
    {
        $current = $this->current();

        if ($current !== null) {
            return $current;
        }

        $user = $this->user();

        if ($user === null) {
            return null;
        }

        $organizations = $user->organizations()->limit(2)->get();

        if ($organizations->count() !== 1) {
            return null;
        }

        $this->set($organizations->first());

        return $this->resolved;
    }

    /**
     * A organização ativa, exigida.
     *
     * Usada por quem já roda atrás do middleware `organization`: ali o contexto
     * é pré-condição da rota, e sua ausência é defeito de configuração, não
     * fluxo de usuário.
     *
     * @throws AuthorizationException
     */
    public function currentOrFail(): Organization
    {
        $organization = $this->current();

        if ($organization === null) {
            throw new AuthorizationException('No active organization in the current session.');
        }

        return $organization;
    }

    /**
     * Define a organização ativa, validando a participação antes.
     *
     * Trocar de organização **invalida a Brand ativa** (SAD 20.5): a Brand
     * pertence à organização anterior e um `active_project_id` fora da
     * organização ativa é estado inválido. O contexto de Brand fica vazio até
     * que o usuário escolha uma da nova organização.
     *
     * @throws AuthorizationException se o usuário não for membro
     */
    public function set(Organization $organization): void
    {
        $user = $this->user();

        if ($user === null || ! $user->belongsToOrganization($organization)) {
            throw new AuthorizationException('The user does not belong to this organization.');
        }

        if ((int) session(self::SESSION_KEY) !== (int) $organization->getKey()) {
            session()->forget(self::BRAND_SESSION_KEY);
        }

        session([self::SESSION_KEY => $organization->getKey()]);

        $this->resolved = $organization;
        $this->rememberCacheOwner();
    }

    /**
     * O papel do usuário autenticado na organização ativa (SAD 20.4).
     */
    public function currentRole(): ?OrganizationRole
    {
        $organization = $this->current();
        $user = $this->user();

        if ($organization === null || $user === null) {
            return null;
        }

        return $user->roleIn($organization);
    }

    public function clear(): void
    {
        $this->forgetSession();

        $this->resolved = null;
        $this->rememberCacheOwner();
    }

    /**
     * Invalida o cache quando a requisição corrente não é a que o preencheu.
     */
    private function discardCacheOfPreviousRequest(): void
    {
        if ($this->resolvedCached && $this->cachedFor !== $this->currentRequest()) {
            $this->resolved = null;
            $this->resolvedCached = false;
            $this->cachedFor = null;
        }
    }

    private function rememberCacheOwner(): void
    {
        $this->resolvedCached = true;
        $this->cachedFor = $this->currentRequest();
    }

    private function currentRequest(): ?Request
    {
        return app()->bound('request') ? app('request') : null;
    }

    private function forgetSession(): void
    {
        session()->forget([self::SESSION_KEY, self::BRAND_SESSION_KEY]);
    }

    private function user(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
