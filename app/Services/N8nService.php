<?php

namespace App\Services;

use App\Exceptions\N8nConnectionException;
use App\Models\Execution;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fronteira de saída entre o Laravel e o n8n (SAD 7).
 *
 * Responsável exclusivamente por transportar uma Execution já persistida até o
 * webhook do Droplet 2. Nenhuma regra de domínio vive aqui: o Service não cria,
 * não altera estado e não interpreta resultado. A transição para `completed` ou
 * `failed` pertence unicamente ao callback autenticado do n8n (SAD 7.7).
 *
 * O Service é stateless e registrado como singleton no AppServiceProvider.
 */
class N8nService
{
    /**
     * Caminho do callback do n8n (CLAUDE.md §5), registrado em routes/api.php
     * pelo WebhookController. Enviado no payload para que o n8n saiba para onde
     * devolver o resultado.
     */
    private const CALLBACK_PATH = '/api/webhook/n8n/result';

    /**
     * Teto documentado para qualquer chamada HTTP iniciada pelo Laravel
     * (SAD 7.6). O tempo real do pipeline é assíncrono e não conta aqui.
     */
    private const TIMEOUT_SECONDS = 10;

    /**
     * Envia a Execution para o pipeline correspondente no n8n.
     *
     * @throws N8nConnectionException se o n8n estiver inacessível, recusar o
     *                                despacho (status >= 400) ou não estiver configurado
     */
    public function dispatch(Execution $execution): void
    {
        $url = (string) config('services.n8n.url');
        $secret = (string) config('services.n8n.secret');

        if ($url === '' || $secret === '') {
            throw N8nConnectionException::notConfigured($execution->id);
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders([
                    'X-N8N-Secret' => $secret,
                    'User-Agent' => 'TinyAILab/Laravel',
                ])
                ->acceptJson()
                ->asJson()
                ->post($url, $this->payload($execution));
        } catch (ConnectionException $e) {
            // A mensagem do cliente HTTP contém a URL completa do Droplet 2;
            // apenas a classe do erro é preservada.
            throw N8nConnectionException::unreachable($execution->id, $e::class);
        }

        if ($response->failed()) {
            throw N8nConnectionException::rejected($execution->id, $response->status());
        }

        Log::info('Execution dispatched to n8n', ['execution_id' => $execution->id]);
    }

    /**
     * Payload documentado em SAD 7.3, acrescido do contexto da Brand e do
     * endereço de callback exigidos pelos pipelines.
     *
     * @return array<string, mixed>
     */
    private function payload(Execution $execution): array
    {
        $execution->loadMissing('project');

        return [
            'execution_id' => $execution->id,
            'pipeline_type' => $execution->pipeline_type,
            'user_id' => $execution->user_id,
            'project_id' => $execution->project_id,
            'credits_cost' => $execution->credits_consumed,
            'locale' => $execution->config['language'] ?? config('app.locale'),
            'brand' => $this->brand($execution),
            'config' => $execution->config,
            'webhook_callback' => $this->callbackUrl(),
            'requested_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Endereço absoluto do callback.
     *
     * Derivado sempre de `app.url`, nunca do host da requisição: o despacho
     * ocorre em um Queue Worker, fora de qualquer contexto HTTP, e o endereço
     * de retorno não pode depender de um header enviado pelo cliente.
     */
    private function callbackUrl(): string
    {
        return rtrim((string) config('app.url'), '/').self::CALLBACK_PATH;
    }

    /**
     * Contexto da Brand ativa. Somente campos descritivos: o n8n não recebe
     * identificadores de billing, créditos nem dados de autenticação.
     *
     * @return array<string, mixed>
     */
    private function brand(Execution $execution): array
    {
        $project = $execution->project;

        return [
            'id' => $project?->id,
            'name' => $project?->brand_name,
            'website' => $project?->website,
            'description' => $project?->description,
            'segment' => $project?->segment,
            'competitors' => $project?->competitors ?? [],
            'channels' => $project?->channels ?? [],
        ];
    }
}
