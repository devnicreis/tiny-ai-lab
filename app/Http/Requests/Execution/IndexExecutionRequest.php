<?php

namespace App\Http\Requests\Execution;

use App\Models\Project;
use App\Services\OrganizationContext;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Contexto de Brand de toda listagem de Executions (SAD 5.12).
 *
 * Nenhuma consulta de domínio é escopada por `user_id` (SAD 20.6). A Brand vem
 * de `project_id` quando o cliente a informa e, na ausência dele, da Brand
 * marcada como ativa dentro da **organização ativa** — a Brand ativa precisa
 * pertencer a ela (CLAUDE.md regra 34).
 *
 * O middleware `EnsureActiveProject` previsto na CLAUDE.md §4 ainda não existe;
 * enquanto ele não centraliza esse contexto, a resolução vive aqui — jamais no
 * Controller, que continua sem regra de domínio.
 */
class IndexExecutionRequest extends FormRequest
{
    private const PER_PAGE = 15;

    private ?Project $brand = null;

    private bool $resolved = false;

    public function authorize(): bool
    {
        $brand = $this->resolveBrand();

        // Brand nula é decidida por rules()/after(): 422 quando o `project_id`
        // não existe, 422 também quando o usuário não possui Brand ativa.
        return $brand === null || $this->user()->can('view', $brand);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'project_id' => [
                'nullable',
                'integer',
                Rule::exists('projects', 'id')->whereNull('deleted_at'),
            ],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->resolveBrand() === null && ! $this->has('project_id')) {
                    $validator->errors()->add(
                        'project_id',
                        'Nenhuma Brand ativa encontrada. Informe a Brand da listagem.',
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'project_id.integer' => 'A Brand informada é inválida.',
            'project_id.exists' => 'A Brand informada não existe.',
            'per_page.integer' => 'A quantidade por página deve ser um número.',
            'per_page.max' => 'A quantidade por página deve ser no máximo 50.',
        ];
    }

    public function brandId(): int
    {
        return (int) $this->resolveBrand()?->getKey();
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? self::PER_PAGE);
    }

    private function resolveBrand(): ?Project
    {
        if ($this->resolved) {
            return $this->brand;
        }

        $this->resolved = true;

        $projectId = $this->input('project_id');

        $organization = app(OrganizationContext::class)->current();

        $this->brand = is_numeric($projectId)
            ? Project::query()->find((int) $projectId)
            : ($organization === null
                ? null
                : Project::query()
                    ->forOrganization($organization->id)
                    ->active()
                    ->latest('id')
                    ->first());

        return $this->brand;
    }
}
