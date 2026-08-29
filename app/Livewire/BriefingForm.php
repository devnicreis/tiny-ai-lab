<?php

namespace App\Livewire;

use App\Exceptions\FeatureNotAvailableException;
use App\Exceptions\InsufficientCreditsException;
use App\Models\Execution;
use App\Models\Project;
use App\Services\ExecutionService;
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
     * Brands do usuário autenticado, para o seletor do formulário.
     *
     * @return Collection<int, Project>
     */
    #[Computed]
    public function brands(): Collection
    {
        return Project::query()
            ->select(['id', 'brand_name', 'is_active'])
            ->forUser((int) auth()->id())
            ->orderBy('brand_name')
            ->get();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            // O `exists` é escopado ao usuário autenticado: o formulário nunca
            // oferece nem aceita Brand de terceiro. A autorização definitiva
            // continua sendo da ExecutionPolicy, aplicada pelo Service.
            'project_id' => [
                'required',
                'integer',
                Rule::exists('projects', 'id')
                    ->where('user_id', auth()->id())
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
                // `user_id`, coluna que o seletor não carrega.
                brand: Project::query()->findOrFail($validated['project_id']),
                pipeline: $validated['pipeline_type'],
                config: $this->config($validated),
            );
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
