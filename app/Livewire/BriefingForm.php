<?php

namespace App\Livewire;

use App\Exceptions\FeatureNotAvailableException;
use App\Exceptions\InsufficientCreditsException;
use App\Exceptions\NoActivePlanException;
use App\Exceptions\PersonalCreditsConfirmationRequiredException;
use App\Models\Execution;
use App\Models\Organization;
use App\Models\Project;
use App\Services\ExecutionService;
use App\Services\OrganizationContext;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Formulário de solicitação de pipeline (SAD 6.3 e M.2).
 *
 * O componente é apresentação: coleta os campos, valida a forma da entrada e
 * entrega o pedido ao ExecutionService. Crédito, autorização, transação e fila
 * pertencem inteiramente ao backend (SAD M.9) — aqui não existe `Http::post()`,
 * não existe custo em créditos e não existe decisão sobre saldo.
 */
class BriefingForm extends Component
{
    /**
     * Brand alvo da execução.
     *
     * Chega por `mount()` (nunca de `request()`, SAD M.9) e é reconferida na
     * validação contra as Brands do próprio usuário.
     */
    public ?int $project_id = null;

    public string $pipeline_type = 'briefing_generation';

    public string $objective = '';

    public string $target_audience = '';

    public string $tone = '';

    public string $language = 'pt-BR';

    public string $notes = '';

    /**
     * Mensagem de saldo insuficiente, com a CTA de upgrade (SAD 12.7).
     *
     * Vive no componente, e não em `session()`, porque é estado visual
     * (SAD M.9).
     */
    public ?string $upgradeMessage = null;

    public function mount(?int $projectId = null): void
    {
        // Sem Brand informada, assume a Brand ativa do usuário — o mesmo
        // critério do contexto de listagem (`projects.is_active`).
        $this->project_id = $projectId ?? $this->brands->firstWhere('is_active', true)?->id
            ?? $this->brands->first()?->id;
    }

    /**
     * Brands da organização ativa, para o seletor do formulário (SAD 20.6).
     *
     * @return Collection<int, Project>
     */
    #[Computed]
    public function brands(): Collection
    {
        $organization = $this->activeOrganization();

        if ($organization === null) {
            return new Collection;
        }

        return Project::query()
            ->select(['id', 'brand_name', 'is_active'])
            ->forOrganization((int) $organization->getKey())
            ->orderBy('brand_name')
            ->get();
    }

    /**
     * A organização ativa da sessão (SAD 20.5).
     *
     * O componente não decide contexto — apenas o lê. A seleção é do middleware
     * `organization`, que protege a rota desta tela.
     */
    private function activeOrganization(): ?Organization
    {
        return app(OrganizationContext::class)->resolve();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            // O `exists` é escopado à organização ativa: o formulário nunca
            // oferece nem aceita Brand de outra organização. A autorização
            // definitiva continua sendo da ExecutionPolicy, aplicada pelo
            // Service.
            'project_id' => [
                'required',
                'integer',
                Rule::exists('projects', 'id')
                    ->where('organization_id', $this->activeOrganization()?->getKey())
                    ->whereNull('deleted_at'),
            ],
            'pipeline_type' => ['required', 'string', Rule::in(Execution::PIPELINE_TYPES)],
            'objective' => ['required', 'string', 'min:10', 'max:2000'],
            'target_audience' => ['nullable', 'string', 'max:1000'],
            'tone' => ['nullable', 'string', 'max:255'],
            'language' => ['required', 'string', 'max:10'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'project_id.required' => 'Selecione a Brand da execução.',
            'project_id.exists' => 'A Brand selecionada não está disponível.',
            'pipeline_type.in' => 'Tipo de pipeline inválido.',
            'objective.required' => 'Descreva o objetivo do briefing.',
            'objective.min' => 'Descreva o objetivo com pelo menos 10 caracteres.',
        ];
    }

    /**
     * Submete o briefing e leva o usuário à tela de acompanhamento.
     *
     * O redirecionamento é imediato porque o botão deixa de representar o
     * progresso assim que o Job é despachado: quem acompanha a execução é o
     * ExecutionStatus, via Reverb (SAD M.4).
     */
    public function submit(ExecutionService $executions)
    {
        $this->upgradeMessage = null;

        $validated = $this->validate();

        try {
            $execution = $executions->create(
                user: auth()->user(),
                // Model completo, e não a projeção do seletor: a Policy avalia
                // a organização dona da Brand, coluna que o seletor não carrega.
                brand: Project::query()->findOrFail($validated['project_id']),
                pipeline: $validated['pipeline_type'],
                config: $this->config($validated),
                activeOrganization: $this->activeOrganization(),
            );
        } catch (NoActivePlanException) {
            // Organização sem assinatura: nem upgrade nem compra de créditos
            // resolvem, e a mensagem não pode citar saldo (SAD 12.12 e 12.18).
            // Assinar é ação de Admin da organização.
            $this->upgradeMessage = 'This organization has no active plan. An organization Admin needs to subscribe before running pipelines.';

            return null;
        } catch (PersonalCreditsConfirmationRequiredException $e) {
            // O custo ultrapassa as carteiras da organização e tocaria a
            // carteira pessoal. O modal de confirmação da Seção 12.19 é trabalho
            // de interface ainda pendente; até lá o componente apenas expõe o
            // breakdown, sem jamais reenviar a confirmação por conta própria —
            // a flag precisa vir de um ato explícito do usuário.
            $this->upgradeMessage = sprintf(
                'This action needs %d credit(s) from your personal wallet — your organization covers only %d of the %d required.',
                $e->breakdown['personal_credits'],
                $e->breakdown['organization_plan_credits'] + $e->breakdown['organization_purchased_credits'],
                $e->breakdown['total_cost'],
            );

            return null;
        } catch (FeatureNotAvailableException) {
            // Pipeline fora do plano: comprar créditos não destravaria a ação,
            // então a mensagem aponta para upgrade e nunca cita saldo (SAD 12.5).
            $this->upgradeMessage = 'This pipeline is not available on your current plan. Upgrade to unlock it.';

            return null;
        } catch (InsufficientCreditsException $e) {
            // Saldo insuficiente não é erro de formulário: é convite a upgrade.
            // O componente só exibe o que o domínio já decidiu.
            $this->upgradeMessage = sprintf(
                'Insufficient credits: this execution requires %d credit(s) and you have %d. Upgrade your plan to continue.',
                $e->required,
                $e->available,
            );

            return null;
        }

        return redirect()->route('result', $execution);
    }

    /**
     * Payload de configuração do pipeline (SAD 4.5 — campo `config`).
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function config(array $validated): array
    {
        return [
            'language' => $validated['language'],
            'inputs' => [
                'objective' => $validated['objective'],
                'target_audience' => $validated['target_audience'] ?? '',
                'tone' => $validated['tone'] ?? '',
                'notes' => $validated['notes'] ?? '',
            ],
        ];
    }

    public function render()
    {
        return view('livewire.briefing-form');
    }
}
