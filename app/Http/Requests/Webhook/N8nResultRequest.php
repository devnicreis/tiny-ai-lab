<?php

namespace App\Http\Requests\Webhook;

use App\Models\Execution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Payload de callback do n8n (SAD 7.4).
 *
 * As regras são deliberadamente permissivas nos campos opcionais: recusar um
 * callback por um campo acessório significa perder o resultado de um pipeline
 * já processado e já cobrado do usuário. Obrigatórios apenas `execution_id` e
 * `status`, sem os quais não há o que atualizar.
 *
 * As mensagens permanecem em português por consistência com o restante do
 * domínio, ainda que o destinatário aqui seja o n8n e não o usuário final.
 */
class N8nResultRequest extends FormRequest
{
    /**
     * Exceção consciente à regra de authorize() por Policy (SAD L.4).
     *
     * Este fluxo não possui usuário: o n8n nunca autentica ninguém (SAD 7.10 —
     * Regra 2) e não há sujeito sobre o qual uma Policy possa decidir. A
     * autenticação inteira do endpoint é o middleware `verify.n8n`, que já
     * validou o segredo compartilhado com `hash_equals()` antes desta classe
     * ser instanciada.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'execution_id' => [
                'required',
                'integer',
                Rule::exists('executions', 'id')->whereNull('deleted_at'),
            ],
            // Só o n8n encerra uma Execution, e só nestes dois estados
            // (SAD 7.4 — Status Permitidos).
            'status' => ['required', 'string', Rule::in(Execution::TERMINAL_STATUSES)],
            'pipeline_type' => ['sometimes', 'string'],
            'tokens_used' => ['sometimes', 'integer', 'min:0'],
            'result' => ['sometimes', 'nullable', 'array'],
            'finished_at' => ['sometimes', 'nullable', 'date'],
            'error' => ['sometimes', 'nullable', 'array'],
            'error.message' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'execution_id.required' => 'O callback deve informar a execução correspondente.',
            'execution_id.exists' => 'A execução informada não existe.',
            'status.required' => 'O callback deve informar o estado final da execução.',
            'status.in' => 'O n8n só pode finalizar uma execução como completed ou failed.',
            'result.array' => 'O resultado deve ser um objeto JSON.',
            'finished_at.date' => 'A data de conclusão deve estar em formato ISO8601.',
        ];
    }
}
