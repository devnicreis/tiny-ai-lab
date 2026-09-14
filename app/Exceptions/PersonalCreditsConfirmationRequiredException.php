<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * O débito tocaria a carteira pessoal sem confirmação explícita (SAD 12.19).
 *
 * Não é um bloqueio, e sim uma pergunta: a resposta HTTP é 409 carregando o
 * breakdown do custo por carteira, para que o usuário decida com contexto. Nada
 * foi debitado e nenhuma Execution foi criada quando esta exception sobe.
 *
 * O bloqueio vive no serviço de crédito, nunca apenas na interface: uma chamada
 * direta à API que resultaria em débito pessoal e não traz
 * `confirm_personal_credits = true` é recusada aqui, sem passar por modal algum.
 *
 * O breakdown é **informativo**. A confirmação autoriza a carteira, não um
 * valor: entre o aviso e a confirmação outro membro da organização pode ter
 * gasto — ou um Admin pode ter comprado — créditos da org, de modo que o valor
 * realmente retirado da carteira pessoal é recalculado dentro da transação de
 * débito e pode ser maior, menor ou nenhum (SAD 12.19).
 *
 * Estende `RuntimeException` — que é uma `Exception` — para acompanhar as
 * demais exceptions de domínio deste diretório.
 */
class PersonalCreditsConfirmationRequiredException extends RuntimeException
{
    /**
     * @param  array{total_cost: int, organization_plan_credits: int, organization_purchased_credits: int, personal_credits: int}  $breakdown
     */
    public function __construct(
        public readonly array $breakdown,
    ) {
        parent::__construct('This action will use your personal credits.');
    }

    /**
     * Constrói a exception a partir da divisão do custo pelas três carteiras.
     *
     * As chaves do breakdown são as do contrato HTTP da Seção 12.19 e não as do
     * array interno do `CreditService`: quem lê o 409 é o frontend, não o
     * domínio.
     *
     * @param  array{cost: int, org_plan: int, org_purchased: int, user_personal: int}  $split
     */
    public static function for(array $split): self
    {
        return new self([
            'total_cost' => $split['cost'],
            'organization_plan_credits' => $split['org_plan'],
            'organization_purchased_credits' => $split['org_purchased'],
            'personal_credits' => $split['user_personal'],
        ]);
    }

    /**
     * Contexto anexado automaticamente pelo Laravel ao reportar a exception.
     *
     * @return array<string, int>
     */
    public function context(): array
    {
        return $this->breakdown;
    }
}
