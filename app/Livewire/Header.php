<?php

namespace App\Livewire;

use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Services\BrandContext;
use App\Services\Credits\CreditSummary;
use App\Services\Credits\CreditSummaryService;
use App\Services\OrganizationContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Header do shell autenticado (SAD 10.10; mockup `header_3.0.html`).
 *
 * Mostra a organização e a Brand ativas, a lista de Brands da organização, as
 * notificações e o menu do perfil. Busca, filtros, abas e menus são estado
 * visual do Alpine (`appHeader`) — a busca não faz requisição alguma.
 *
 * A única ação de domínio é trocar a Brand ativa, e ela é inteira do
 * `BrandContext`, que consulta a `ProjectPolicy` e o pertencimento à
 * organização ativa. O componente só repassa o pedido e avisa os demais
 * com `brand-switched` (SAD M.8).
 */
class Header extends Component
{
    private const NOTIFICATIONS_LIMIT = 10;

    #[Computed]
    public function user(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    #[Computed]
    public function organization(): ?Organization
    {
        return app(OrganizationContext::class)->current();
    }

    #[Computed]
    public function activeBrand(): ?Project
    {
        return app(BrandContext::class)->resolve();
    }

    /**
     * Brands da organização ativa, em lista plana por nome.
     *
     * Sub-brands levam a legenda "Sub-brand of X" em vez de recuo: a
     * profundidade da hierarquia é [A DEFINIR] e a apresentação não pode
     * presumir nenhuma (SAD 19.2). A relação `parent` só rotula — nunca amplia
     * o escopo da consulta (SAD 19.4).
     *
     * @return array<int, array{id: int, name: string, initials: string, parent: ?string, active: bool}>
     */
    #[Computed]
    public function brands(): array
    {
        $organization = $this->organization;

        if ($organization === null) {
            return [];
        }

        $activeId = $this->activeBrand?->getKey();

        return Project::query()
            ->select(['id', 'organization_id', 'parent_project_id', 'brand_name'])
            ->forOrganization((int) $organization->getKey())
            ->with('parent:id,brand_name')
            ->orderBy('brand_name')
            ->get()
            ->map(fn (Project $project) => [
                'id' => (int) $project->getKey(),
                'name' => $project->brand_name,
                'initials' => $project->initials(),
                'parent' => $project->parent?->brand_name,
                'active' => $project->getKey() === $activeId,
            ])
            ->all();
    }

    /**
     * "Add new Brand" aparece só para quem a `ProjectPolicy` deixa criar —
     * Owner e Admin. Esconder é conveniência; a recusa real é a Policy.
     */
    #[Computed]
    public function canCreateBrand(): bool
    {
        return Gate::allows('create', Project::class);
    }

    #[Computed]
    public function credits(): ?CreditSummary
    {
        $organization = $this->organization;

        if ($this->user === null || $organization === null) {
            return null;
        }

        return app(CreditSummaryService::class)->for($this->user, $organization);
    }

    /**
     * Notificações do próprio usuário — o `notifiable` da tabela padrão do
     * Laravel (SAD 4.9). São endereçadas à pessoa, não à Brand.
     *
     * @return array<int, array{title: string, resource: ?string, time: string, unread: bool}>
     */
    #[Computed]
    public function notifications(): array
    {
        if ($this->user === null) {
            return [];
        }

        return $this->user->notifications()
            ->latest()
            ->limit(self::NOTIFICATIONS_LIMIT)
            ->get(['id', 'data', 'read_at', 'created_at'])
            ->map(fn ($notification) => [
                'title' => (string) ($notification->data['title'] ?? $notification->data['message'] ?? 'Notification'),
                'resource' => isset($notification->data['resource']) ? (string) $notification->data['resource'] : null,
                'time' => $notification->created_at->diffForHumans(),
                'unread' => $notification->read_at === null,
            ])
            ->all();
    }

    #[Computed]
    public function unreadNotificationsCount(): int
    {
        return $this->user?->unreadNotifications()->count() ?? 0;
    }

    /**
     * Troca a Brand ativa da sessão.
     *
     * @throws AuthorizationException quando a Brand não pertence à
     *                                organização ativa ou o usuário não é
     *                                membro da organização dona
     */
    public function switchBrand(int $projectId): void
    {
        $project = Project::query()->find($projectId);

        if ($project === null) {
            throw new AuthorizationException('The brand does not belong to the active organization.');
        }

        app(BrandContext::class)->set($project);

        unset($this->activeBrand, $this->brands);

        $this->dispatch('brand-switched', projectId: (int) $project->getKey());
    }

    public function render()
    {
        return view('livewire.header');
    }
}
