<?php

namespace App\Services;

use App\Exceptions\FeatureNotAvailableException;
use App\Exceptions\InsufficientCreditsException;
use App\Exceptions\NoActivePlanException;
use App\Exceptions\PersonalCreditsConfirmationRequiredException;
use App\Jobs\ProcessBriefingJob;
use App\Models\Execution;
use App\Models\Organization;
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
     * Quem paga é a **Organização dona da Brand** (SAD 12.1). A organização é
     * derivada da Brand alvo, e não recebida do chamador, porque é a Brand que
     * o usuário escolheu e é `projects.organization_id` que diz de quem ela é:
     * aceitar uma organização pelo parâmetro permitiria cobrar a execução de
     * uma Brand no saldo de outra organização. Quando o contexto de sessão da
     * Seção 20.5 existir, ele continuará apenas *validando* que a Brand ativa
     * pertence à organização ativa (CLAUDE.md regra 34) — nunca substituindo
     * esta derivação.
     *
     * O débito e a criação compartilham uma transação porque a corrida entre
     * duas solicitações simultâneas só é resolvida dentro de `deduct()`, sob
     * lock: se o saldo real não cobrir o custo, a Execution criada instantes
     * antes precisa desaparecer junto — SAD 12.12 exige que saldo insuficiente
     * não deixe execução nenhuma para trás. O mesmo vale para o 409 da carteira
     * pessoal, que é pergunta e não débito.
     *
     * O Job é despachado apenas após o commit: um Worker que lesse a Execution
     * antes dela existir no banco falharia em `findOrFail`.
     *
     * `$activeOrganization` é o contexto de sessão e serve apenas para
     * *validar* que a Brand escolhida pertence a ele (CLAUDE.md regra 34).
     * Jamais substitui a derivação acima: quem paga continua sendo a
     * organização dona da Brand, nunca a que o chamador informou.
     *
     * `$confirmPersonalCredits` precisa vir da requisição originada pela
     * confirmação do usuário. Nenhum chamador pode injetá-la por conta própria
     * (SAD 12.19).
     *
     * @param  array<string, mixed>  $config
     *
     * @throws AuthorizationException se o usuário não for membro da organização
     *                                dona da Brand, ou se a Brand não pertencer
     *                                à organização ativa
     * @throws NoActivePlanException se a organização da Brand não tem assinatura ativa
     * @throws FeatureNotAvailableException se o plano da organização não libera o pipeline
     * @throws InsufficientCreditsException se o saldo não cobrir o custo do pipeline
     * @throws PersonalCreditsConfirmationRequiredException se o custo tocar a carteira pessoal sem confirmação
     */
    public function create(
        User $user,
        Project $brand,
        string $pipeline,
        array $config = [],
        bool $confirmPersonalCredits = false,
        ?Organization $activeOrganization = null,
    ): Execution {
        // A autorização vive na ExecutionPolicy e é avaliada aqui para valer
        // igualmente às duas portas de entrada — o Gate é a única autoridade,
        // nunca o chamador.
        Gate::forUser($user)->authorize('create', [Execution::class, $brand]);

        $organization = $brand->organization()->firstOrFail();

        // A Brand precisa pertencer à organização ativa da sessão
        // (SAD 20.5, CLAUDE.md regra 34). O estado inválido é rejeitado, nunca
        // corrigido em silêncio: um cliente que envie a Brand de outra
        // organização recebe 403 ainda que seja membro das duas.
        if ($activeOrganization !== null
            && (int) $activeOrganization->getKey() !== (int) $organization->getKey()) {
            throw new AuthorizationException(
                'The selected Brand does not belong to the active organization.',
            );
        }

        // Os quatro desfechos negativos sobem como exceptions distintas, cada
        // uma com seu código HTTP e sua CTA (SAD 12.12); não há booleano a
        // testar aqui.
        $this->credits->canExecute($organization, $user, $pipeline, $confirmPersonalCredits);

        $execution = DB::transaction(function () use ($organization, $user, $brand, $pipeline, $config, $confirmPersonalCredits): Execution {
            $execution = Execution::create([
                'project_id' => $brand->id,
                'user_id' => $user->id,
                'pipeline_type' => $pipeline,
                'status' => 'queued',
                'config' => $config,
                'credits_consumed' => $this->credits->costFor($pipeline),
            ]);

            $this->credits->deduct($organization, $user, $pipeline, $confirmPersonalCredits);

            return $execution;
        });

        ProcessBriefingJob::dispatch($execution->id);

        return $execution;
    }
}
