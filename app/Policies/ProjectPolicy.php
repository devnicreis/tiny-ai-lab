<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Qualquer usuário autenticado pode criar uma Brand.
     *
     * Os limites de plano serão avaliados posteriormente.
     */
    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return $project->user_id === $user->id;
    }

    public function update(User $user, Project $project): bool
    {
        return $project->user_id === $user->id;
    }

    public function delete(User $user, Project $project): bool
    {
        return $project->user_id === $user->id;
    }
}
