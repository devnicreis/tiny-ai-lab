<?php

namespace App\Policies;

use App\Models\Execution;
use App\Models\Project;
use App\Models\User;

/**
 * Autorização sobre Executions (SAD 5.9 e 20.6).
 *
 * A Execution pertence à Brand, e a Brand à organização: quem é membro da
 * organização dona enxerga o trabalho produzido nela, inclusive o disparado por
 * outro membro. Isso é intencional — a Brand é da organização, e o resultado
 * também (SAD 20.6).
 *
 * > 🔁 `executions.user_id` não é comparado em lugar nenhum desta classe. A
 * > coluna registra **quem disparou** a execução, para auditoria e para o
 * > débito da carteira pessoal, e nunca decide acesso (SAD 20.6).
 *
 * O recorte da Brand ativa é responsabilidade das consultas (CLAUDE.md regra
 * 10): uma Execution de outra Brand da mesma organização continua abrindo por
 * link direto em `/result/{execution}`.
 */
class ExecutionPolicy
{
    /**
     * Criar uma Execution exige participação na organização dona da Brand.
     *
     * O Project chega como argumento adicional do Gate porque a Brand alvo vem
     * do corpo da requisição e não do usuário: sem ela não há o que autorizar.
     */
    public function create(User $user, ?Project $project = null): bool
    {
        return $project !== null && $this->isMemberOfOwner($user, $project);
    }

    public function view(User $user, Execution $execution): bool
    {
        return $this->viewProjectOf($user, $execution);
    }

    /**
     * Exportar o resultado segue exatamente o mesmo critério de leitura: quem
     * pode ver o resultado na tela pode levá-lo consigo.
     */
    public function export(User $user, Execution $execution): bool
    {
        return $this->viewProjectOf($user, $execution);
    }

    private function viewProjectOf(User $user, Execution $execution): bool
    {
        $project = $execution->project()->first();

        return $project !== null && $this->isMemberOfOwner($user, $project);
    }

    private function isMemberOfOwner(User $user, Project $project): bool
    {
        $organization = $project->organization()->first();

        return $organization !== null && $user->belongsToOrganization($organization);
    }
}
