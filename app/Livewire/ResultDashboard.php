<?php

namespace App\Livewire;

use App\Models\Execution;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Renderiza o `result` de uma Execution concluída (SAD 4.5 e M.7).
 *
 * É componente lazy por definição: `result` é JSONB que passa facilmente de
 * dezenas de kilobytes e nunca pode entrar na renderização inicial da página
 * (SAD M.9). A consulta acontece uma única vez, na propriedade computada, e
 * somente quando a Execution já está concluída.
 */
class ResultDashboard extends Component
{
    /**
     * Chaves conhecidas do resultado, na ordem de exibição. As demais chaves
     * continuam sendo renderizadas ao final: o contrato do JSON pertence ao
     * pipeline e pode crescer sem alteração aqui.
     *
     * @var array<int, string>
     */
    public const SECTIONS = ['diagnosis', 'calendar', 'copies', 'scripts'];

    public int $executionId;

    /**
     * Seção visível. Estado puramente visual, logo pertence ao componente e
     * nunca à sessão (SAD M.9).
     */
    public string $activeSection = '';

    public function mount(int $executionId): void
    {
        $this->executionId = $executionId;
    }

    /**
     * Área ocupada durante o carregamento lazy (SAD M.7).
     */
    public function placeholder(): View
    {
        return view('livewire.placeholders.result-dashboard');
    }

    /**
     * Recarrega o resultado quando a Execution atinge estado final.
     *
     * O aviso vem do ExecutionStatus por evento Livewire (SAD M.10); o conteúdo
     * em si sempre chega por consulta autorizada ao Laravel, nunca pelo
     * broadcast.
     */
    #[On('execution-finished')]
    public function onExecutionFinished(int $executionId): void
    {
        if ($executionId !== $this->executionId) {
            return;
        }

        unset($this->execution, $this->result, $this->sections);
    }

    /**
     * A Execution, com `result`, escopada às organizações do usuário.
     *
     * O recorte por participação não substitui a ExecutionPolicy — aplicada na
     * rota `/result/{execution}` — apenas garante que uma requisição Livewire
     * forjada com outro identificador não devolva conteúdo algum. O critério é
     * o mesmo da Policy: membro da organização dona da Brand (SAD 20.6).
     */
    #[Computed]
    public function execution(): Execution
    {
        return Execution::query()
            ->whereKey($this->executionId)
            ->visibleTo(auth()->user())
            ->firstOrFail();
    }

    /**
     * O JSONB decodificado uma única vez por request (SAD M.1 — nunca
     * recalcular no Blade).
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function result(): array
    {
        // `result` só existe depois da conclusão bem-sucedida (CLAUDE.md §6):
        // enquanto isso não há o que decodificar.
        if ($this->execution->status !== 'completed') {
            return [];
        }

        return $this->execution->result ?? [];
    }

    /**
     * Chaves efetivamente presentes no resultado, na ordem de exibição.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function sections(): array
    {
        $present = array_keys($this->result);

        $known = array_values(array_intersect(self::SECTIONS, $present));
        $extra = array_values(array_diff($present, self::SECTIONS));

        return [...$known, ...$extra];
    }

    /**
     * Seção realmente exibida.
     *
     * A escolha do usuário só vale se a chave existir no resultado corrente: a
     * primeira renderização acontece antes de qualquer seleção e, quando a
     * Execution termina, o conjunto de chaves passa a existir de uma vez.
     */
    #[Computed]
    public function currentSection(): string
    {
        return in_array($this->activeSection, $this->sections, true)
            ? $this->activeSection
            : ($this->sections[0] ?? '');
    }

    public function selectSection(string $section): void
    {
        if (in_array($section, $this->sections, true)) {
            $this->activeSection = $section;
        }
    }

    public function render()
    {
        return view('livewire.result-dashboard');
    }
}
