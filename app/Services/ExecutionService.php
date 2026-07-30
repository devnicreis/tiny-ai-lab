<?php

namespace App\Services;

use App\Exceptions\InsufficientCreditsException;
use App\Jobs\ProcessBriefingJob;
use App\Models\Execution;
use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Dono do fluxo de criação de uma Execution (SAD 6.1).
 *
 * Existe porque o mesmo fluxo tem duas portas de entrada — o endpoint
 * `POST /api/executions` e o componente Livewire `BriefingForm` — e nenhuma das
 * duas pode conhecer a regra: Controllers não implementam domínio e componentes
 * Livewire pertencem exclusivamente à apresentação (SAD M.9). Duplicar a
 * transação de débito nos dois lugares criaria duas verdades sobre crédito.
 *
 * O Service é stateless e registrado como singleton no AppServiceProvider.
 */
class ExecutionService
{
    public function __construct(
        private readonly CreditService $credits,
    ) {}

    /**
     * Cria a Execution, debita o crédito e enfileira o pipeline.
     *
     * O débito e a criação compartilham uma transação porque a corrida entre
     * duas solicitações simultâneas só é resolvida dentro de `deduct()`, sob
     * lock: se o saldo real não cobrir o custo, a Execution criada instantes
     * antes precisa desaparecer junto — SAD 12.7 exige que saldo insuficiente
     * não deixe execução nenhuma para trás.
     *
     * O Job é despachado apenas após o commit: um Worker que lesse a Execution
     * antes dela existir no banco falharia em `findOrFail`.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws AuthorizationException se a Brand não pertencer ao usuário
     * @throws InsufficientCreditsException se o saldo não cobrir o custo do pipeline
     */
    public function create(User $user, Project $brand, string $pipeline, array $config = []): Execution
    {
        // A autorização vive na ExecutionPolicy e é avaliada aqui para valer
        // igualmente às duas portas de entrada — o Gate é a única autoridade,
        // nunca o chamador.
        Gate::forUser($user)->authorize('create', [Execution::class, $brand]);

        if (! $this->credits->canExecute($user, $pipeline)) {
            throw InsufficientCreditsException::for($user, $pipeline, $this->credits->costFor($pipeline));
        }

        $execution = DB::transaction(function () use ($user, $brand, $pipeline, $config): Execution {
            $execution = Execution::create([
                'project_id' => $brand->id,
                'user_id' => $user->id,
                'pipeline_type' => $pipeline,
                'status' => 'queued',
                'config' => $config,
                'credits_consumed' => $this->credits->costFor($pipeline),
            ]);

            $this->credits->deduct($user, $pipeline);

            return $execution;
        });

        ProcessBriefingJob::dispatch($execution->id);

        return $execution;
    }
}
