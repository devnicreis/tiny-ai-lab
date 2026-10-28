<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\Credits\CreditSummary;
use App\Services\Credits\CreditSummaryService;
use App\Services\OrganizationContext;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Sidebar do shell autenticado (SAD 10.9 e 10.12; mockup `sidebar_v3.html`).
 *
 * Apresentação pura: navegação, indicador de créditos e menus. Colapso,
 * overlay mobile, submenu de ajuda e tema são estado visual e vivem no Alpine
 * (`appSidebar`), sem ida ao servidor. A troca de Brand saiu da Sidebar e foi
 * para o menu da marca no Header.
 */
class Sidebar extends Component
{
    /**
     * Rota corrente, para marcar o item ativo.
     *
     * Chega do layout por `mount()` — o componente nunca lê `request()`
     * (SAD M.9) — e é travada: não é estado que o cliente possa alterar.
     */
    #[Locked]
    public ?string $currentRoute = null;

    public function mount(?string $currentRoute = null): void
    {
        $this->currentRoute = $currentRoute;
    }

    /**
     * Resumo de créditos da organização ativa, ou `null` sem contexto.
     */
    #[Computed]
    public function credits(): ?CreditSummary
    {
        $user = Auth::user();
        $organization = app(OrganizationContext::class)->current();

        if (! $user instanceof User || $organization === null) {
            return null;
        }

        return app(CreditSummaryService::class)->for($user, $organization);
    }

    /**
     * Programa de indicação: com a flag desligada, nem o botão é renderizado.
     */
    #[Computed]
    public function referralEnabled(): bool
    {
        return (bool) config('arkion.features.referral');
    }

    public function render()
    {
        return view('livewire.sidebar');
    }
}
