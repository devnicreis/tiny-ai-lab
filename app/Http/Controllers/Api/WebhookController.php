<?php

namespace App\Http\Controllers\Api;

use App\Events\ExecutionFailed;
use App\Events\ExecutionUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Webhook\N8nResultRequest;
use App\Models\Execution;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Saída do pipeline (SAD 5.8 e 7.4).
 *
 * Este é o único mecanismo autorizado a levar uma Execution a `completed` ou
 * `failed`. Não há usuário autenticado aqui: a credencial do endpoint é
 * exclusivamente o header `X-N8N-Secret`, já validado pelo middleware
 * `verify.n8n`.
 */
class WebhookController extends Controller
{
    /**
     * Limite do diagnóstico persistido em `error_message`.
     *
     * A coluna é lida pelo `ExecutionFailed` e chega à interface; um stack
     * trace inteiro devolvido pelo n8n não tem lugar na tela do usuário.
     */
    private const ERROR_MESSAGE_LIMIT = 500;

    /**
     * Mensagem usada quando o n8n reporta falha sem `error.message`.
     */
    private const UNKNOWN_ERROR = 'O pipeline falhou durante o processamento. Tente novamente em alguns minutos.';

    public function result(N8nResultRequest $request): JsonResponse
    {
        $execution = Execution::query()->findOrFail($request->integer('execution_id'));

        // Idempotência (SAD 7.8 — lacuna [A DEFINIR]): um callback repetido é
        // reconhecido com 200 e descartado. Reaplicá-lo reemitiria o broadcast
        // e poderia sobrescrever com um retry vazio o resultado já persistido.
        if (in_array($execution->status, Execution::TERMINAL_STATUSES, true)) {
            Log::info('Duplicate n8n callback ignored', [
                'execution_id' => $execution->id,
                'status' => $execution->status,
            ]);

            return $this->acknowledge($execution, ignored: true);
        }

        $request->validated('status') === 'completed'
            ? $this->complete($execution, $request)
            : $this->fail($execution, $request);

        return $this->acknowledge($execution);
    }

    /**
     * Conclusão bem-sucedida (SAD 7.4 — Callback de Sucesso).
     *
     * `credits_consumed` chega no payload mas é deliberadamente ignorado: o
     * débito já ocorreu na criação da Execution, a partir de `plan_limits`.
     * Aceitar o valor do n8n deixaria o motor externo reescrever a
     * contabilidade do domínio, contra a regra 9 da CLAUDE.md e a Regra 4 da
     * Seção 7.10.
     */
    private function complete(Execution $execution, N8nResultRequest $request): void
    {
        $execution->update([
            'status' => 'completed',
            'result' => $request->validated('result') ?? [],
            'tokens_used' => $request->has('tokens_used')
                ? $request->integer('tokens_used')
                : $execution->tokens_used,
            'completed_at' => $this->finishedAt($request),
            'error_message' => null,
        ]);

        // Persistência → commit → broadcast (SAD 9.11).
        ExecutionUpdated::dispatch($execution);
    }

    /**
     * Falha reportada pelo n8n (SAD 7.4 — Callback de Erro).
     *
     * O objeto `error` completo vai para o log, que é o canal de auditoria; a
     * coluna guarda apenas a mensagem, já que ela trafega no broadcast até a
     * interface (SAD 6 — preservar diagnóstico sem expor dados sensíveis).
     * O Laravel nunca interpreta o erro das APIs externas.
     */
    private function fail(Execution $execution, N8nResultRequest $request): void
    {
        Log::warning('n8n reported a pipeline failure', [
            'execution_id' => $execution->id,
            'error' => $request->validated('error'),
        ]);

        $execution->update([
            'status' => 'failed',
            'error_message' => $this->errorMessage($request),
            'failed_at' => $this->finishedAt($request),
        ]);

        ExecutionFailed::dispatch($execution);
    }

    private function errorMessage(N8nResultRequest $request): string
    {
        $message = $request->validated('error.message');

        return is_string($message) && trim($message) !== ''
            ? Str::limit(trim($message), self::ERROR_MESSAGE_LIMIT)
            : self::UNKNOWN_ERROR;
    }

    /**
     * `finished_at` do payload mapeado para as colunas do domínio.
     *
     * Na ausência do campo o instante do callback é a melhor aproximação
     * disponível — a coluna nunca pode ficar nula em um estado final.
     */
    private function finishedAt(N8nResultRequest $request): Carbon
    {
        $finishedAt = $request->validated('finished_at');

        return is_string($finishedAt) ? Carbon::parse($finishedAt) : now();
    }

    /**
     * Confirmação imediata ao n8n. O corpo é mínimo por princípio: o Droplet 2
     * não precisa conhecer nada do domínio para saber que entregou o resultado.
     */
    private function acknowledge(Execution $execution, bool $ignored = false): JsonResponse
    {
        return response()->json([
            'execution_id' => $execution->id,
            'status' => $execution->status,
            'ignored' => $ignored,
        ]);
    }
}
