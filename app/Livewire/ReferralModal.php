<?php

namespace App\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Modal de indicação (mockup `modal_referral.html`), atrás de
 * `config('arkion.features.referral')`.
 *
 * Só apresentação, e sem dado algum: não existe código de indicação, link,
 * histórico nem valor de recompensa. O mockup traz tudo isso como exemplo —
 * e se contradiz (100 créditos no botão da Sidebar, 30 no modal) —, e o
 * programa depende de decisão sobre códigos, atribuição, anti-fraude e
 * carteira de destino (SAD 10.16). Até lá a interface mostra estado vazio.
 *
 * O layout só monta o componente com a flag ligada; a verificação aqui
 * garante o mesmo para quem o montar por outro caminho.
 */
class ReferralModal extends Component
{
    #[Computed]
    public function enabled(): bool
    {
        return (bool) config('arkion.features.referral');
    }

    public function render()
    {
        return view('livewire.referral-modal');
    }
}
