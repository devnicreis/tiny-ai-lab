<?php

namespace App\Livewire;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Seletor de organização (SAD 20.3; mockup `switch-organization-modal.html`).
 *
 * Duas apresentações da mesma lista: o modal aberto pelo menu da marca no
 * Header e, com `inline`, o conteúdo da página `/organizations/select`. A
 * lista e a ação de troca existem uma vez só.
 *
 * Trocar é `OrganizationContext::set()`: ele valida a participação, limpa
 * `active_project_id` e só então grava a nova organização. O componente nunca
 * decide pertencimento — o id recebido do cliente vale tanto quanto qualquer
 * outro e passa pela mesma validação.
 */
class SwitchOrganizationModal extends Component
{
    #[Locked]
    public bool $inline = false;

    public function mount(bool $inline = false): void
    {
        $this->inline = $inline;
    }

    /**
     * Organizações do usuário, com papel e contagem de Brands.
     *
     * @return array<int, array{id: int, name: string, initials: string, role: string, brands: int, current: bool}>
     */
    #[Computed]
    public function organizations(): array
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return [];
        }

        $currentId = app(OrganizationContext::class)->current()?->getKey();

        return $user->organizations()
            ->withCount('projects')
            ->orderBy('organizations.name')
            ->get(['organizations.id', 'organizations.name'])
            ->map(fn (Organization $organization) => [
                'id' => (int) $organization->getKey(),
                'name' => $organization->name,
                'initials' => $organization->initials(),
                'role' => OrganizationRole::from($organization->pivot->role)->label(),
                'brands' => (int) $organization->projects_count,
                'current' => $organization->getKey() === $currentId,
            ])
            ->all();
    }

    /**
     * @throws AuthorizationException quando o usuário não é membro
     */
    public function switchTo(int $organizationId)
    {
        $organization = Organization::query()->find($organizationId);

        // Organização inexistente responde igual à alheia: o id não revela se
        // a organização existe.
        if ($organization === null) {
            throw new AuthorizationException('The user does not belong to this organization.');
        }

        app(OrganizationContext::class)->set($organization);

        return $this->redirectRoute('dashboard');
    }

    public function render()
    {
        return view('livewire.switch-organization-modal');
    }
}
