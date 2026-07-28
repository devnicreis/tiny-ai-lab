<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Falha no despacho de uma Execution para o n8n (SAD 7).
 *
 * A mensagem e o contexto desta exception são deliberadamente pobres: nem o
 * segredo compartilhado nem a URL do webhook podem chegar ao log. Apenas o
 * `execution_id`, o status HTTP e a classe do erro de transporte são
 * considerados seguros para diagnóstico.
 *
 * Por esse motivo a exception original do cliente HTTP nunca é encadeada como
 * `previous`: a mensagem do cURL carrega a URL completa do Droplet 2.
 */
class N8nConnectionException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $executionId,
        public readonly ?int $statusCode = null,
        public readonly ?string $reason = null,
    ) {
        parent::__construct($message);
    }

    /**
     * O n8n não respondeu — DNS, VPC, timeout de 10s ou serviço fora do ar.
     */
    public static function unreachable(int $executionId, string $reason): self
    {
        return new self(
            'Failed to reach the n8n webhook.',
            executionId: $executionId,
            reason: $reason,
        );
    }

    /**
     * O n8n respondeu, mas recusou o despacho (status >= 400).
     */
    public static function rejected(int $executionId, int $statusCode): self
    {
        return new self(
            'The n8n webhook rejected the dispatch.',
            executionId: $executionId,
            statusCode: $statusCode,
        );
    }

    /**
     * URL ou segredo ausentes na configuração do ambiente.
     */
    public static function notConfigured(int $executionId): self
    {
        return new self(
            'The n8n webhook is not configured.',
            executionId: $executionId,
        );
    }

    /**
     * Contexto anexado automaticamente pelo Laravel ao reportar a exception.
     *
     * @return array<string, int|string|null>
     */
    public function context(): array
    {
        return array_filter([
            'execution_id' => $this->executionId,
            'status' => $this->statusCode,
            'reason' => $this->reason,
        ], fn (int|string|null $value) => $value !== null);
    }
}
