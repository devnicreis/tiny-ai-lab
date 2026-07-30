<?php

namespace App\Policies;

use App\Models\Execution;
use App\Models\Project;
use App\Models\User;

/**
 * Autorização sobre Executions (SAD 5.9).
 *
 * A Policy responde por *ownership*. O recorte da Brand ativa é
 * responsabilidade das consultas — toda listagem escopa por `project_id`
 * (CLAUDE.md regra 10) — porque uma Execution pertencente a outra Brand do
 * mesmo usuário continua sendo dele e não pode virar 403 ao ser aberta pelo
 * link direto de `/result/{execution}`.
 */
class ExecutionPolicy
{
    /**
     * Criar uma Execution exige ser dono da Brand de destino.
     *
     * O Project chega como argumento adicional do Gate porque a Brand alvo vem
     * do corpo da requisição e não do usuário: sem ela não há o que autorizar.
     */
    public function create(User $user, ?Project $project = null): bool
    {
        return $project !== null && $project->user_id === $user->id;
    }

    public function view(User $user, Execution $execution): bool
    {
        return $execution->user_id === $user->id;
    }
}
