<?php

namespace App\Http\Requests\Execution;

use App\Models\Execution;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Entrada de toda solicitação de pipeline (SAD 6 e L.4).
 *
 * A Brand alvo é resolvida e autorizada aqui, antes de qualquer regra de
 * negócio: o Controller recebe um Project já comprovadamente do usuário e não
 * repete a consulta.
 */
class StoreBriefingRequest extends FormRequest
{
    private ?Project $brand = null;

    public function authorize(): bool
    {
        $projectId = $this->input('project_id');

        // Sem uma Brand sequer identificável não existe autorização a avaliar:
        // a ausência é erro de validação (422), tratado por rules().
        if (! is_numeric($projectId)) {
            return true;
        }

        $this->brand = Project::query()->find((int) $projectId);

        // Brand inexistente e Brand de outro usuário produzem deliberadamente a
        // mesma resposta (403), para que a API não sirva de oráculo de IDs.
        return $this->brand !== null
            && $this->user()->can('create', [Execution::class, $this->brand]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'project_id' => [
                'required',
                'integer',
                Rule::exists('projects', 'id')->whereNull('deleted_at'),
            ],
            'pipeline_type' => ['required', 'string', Rule::in(Execution::PIPELINE_TYPES)],
            'config' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'project_id.required' => 'Informe a Brand em que a execução será criada.',
            'project_id.integer' => 'A Brand informada é inválida.',
            'project_id.exists' => 'A Brand informada não existe.',
            'pipeline_type.required' => 'Informe o tipo de pipeline.',
            'pipeline_type.in' => 'Tipo de pipeline inválido.',
            'config.array' => 'A configuração da execução deve ser um objeto.',
        ];
    }

    /**
     * Brand alvo da execução, já autorizada em authorize().
     */
    public function brand(): Project
    {
        return $this->brand ??= Project::query()->findOrFail($this->integer('project_id'));
    }

    /**
     * Configuração específica do pipeline (SAD 7.3).
     *
     * A estrutura interna varia por pipeline e permanece [A DEFINIR] na
     * documentação; o domínio apenas garante que a coluna NOT NULL receba um
     * objeto, ainda que vazio.
     *
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->validated('config') ?? [];
    }
}
