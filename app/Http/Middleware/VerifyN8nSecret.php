<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fronteira de entrada do callback do n8n (SAD 5.6 e 7.10 — Regra 7).
 *
 * O callback do Droplet 2 não possui usuário: nenhum Firebase ID Token existe
 * nesse fluxo. A única credencial é o segredo compartilhado transportado no
 * header `X-N8N-Secret`, validado aqui antes de qualquer acesso ao domínio.
 *
 * Por esse motivo o endpoint também não recebe rate limiting por IP: o n8n vive
 * atrás da VPC privada e um limite por origem apenas transformaria o callback
 * legítimo — que pode chegar em rajada ao fim de vários pipelines — em perda de
 * resultado já processado e já cobrado do usuário.
 */
class VerifyN8nSecret
{
    private const HEADER = 'X-N8N-Secret';

    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.n8n.secret');
        $provided = (string) $request->header(self::HEADER, '');

        // Segredo ausente na configuração invalida o endpoint inteiro: sem ele
        // `hash_equals('', '')` autenticaria qualquer requisição sem header.
        if ($expected === '' || ! hash_equals($expected, $provided)) {
            return $this->rejected($request);
        }

        return $next($request);
    }

    /**
     * Comparação recusada (SAD 5.6 — impedir callbacks falsificados).
     *
     * O log registra a origem da tentativa e nada além disso: nem o segredo
     * esperado nem o segredo recebido podem chegar a um arquivo de log, sob
     * pena de vazar por outro canal aquilo que este middleware protege.
     */
    private function rejected(Request $request): Response
    {
        Log::warning('Rejected n8n callback: invalid shared secret', [
            'ip' => $request->ip(),
            'path' => $request->path(),
            'has_header' => $request->hasHeader(self::HEADER),
        ]);

        return response()->json(['error' => 'invalid_secret'], Response::HTTP_FORBIDDEN);
    }
}
