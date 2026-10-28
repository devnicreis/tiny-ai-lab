<?php

namespace App\Livewire;

use Livewire\Component;

/**
 * Confirmação de logout (SAD 10.13).
 *
 * Sem estado de servidor: abrir e fechar são do Alpine (`open-logout-modal`),
 * e a saída é o `POST /logout` convencional do `Web\AuthController`, por
 * formulário (SAD M.3). O componente existe para ser o único modal de logout
 * do shell, aberto tanto pela Sidebar quanto pelo menu do perfil (SAD M.12).
 */
class LogoutModal extends Component
{
    public function render()
    {
        return view('livewire.logout-modal');
    }
}
