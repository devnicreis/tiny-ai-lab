# Validação do Mês 2 — Pipeline de Execução

**Data:** 2026-07-30
**Escopo:** sessões s09 a s13 (N8nService, ProcessBriefingJob, middleware + webhook, Livewire, teste de ponta a ponta)
**Referência:** `.claude/docs/SAD.md` — Seção 6 (Pipeline), Seção 7 (Integração n8n), Seção 8 (Filas), Seção 9 (Realtime), Seção 12 (Créditos)

Este documento descreve o pipeline **como ele está implementado hoje**, não como ele será. Onde a implementação difere da tabela de rotas da `CLAUDE.md`, a diferença está registrada explicitamente na seção 6.

---

## 1. Endpoints do pipeline e seus middlewares

Middlewares abaixo conforme `php artisan route:list` — são os efetivos, não os planejados.

### 1.1 Entrada — criação da Execution

| Método | URI | Ação | Middleware | Form Request | Autorização | Resposta |
|--------|-----|------|------------|--------------|-------------|----------|
| POST | `/api/executions` | `Api\ExecutionController@store` | `api`, `VerifyFirebaseToken` | `StoreBriefingRequest` | `ExecutionPolicy@create` (no `authorize()` do Request e novamente no `ExecutionService` via `Gate::forUser`) | `202 Accepted` + `{execution_id, status: "queued"}` |

Respostas de recusa deste endpoint:

| Situação | Status | Corpo |
|----------|-------:|-------|
| `pipeline_type` fora de `Execution::PIPELINE_TYPES` | 422 | erros de validação |
| `project_id` ausente ou inexistente | 422 | erros de validação |
| Brand de outro usuário | 403 | — (Brand inexistente devolve o mesmo 403, para a API não servir de oráculo de IDs) |
| Saldo insuficiente | 402 | `{error: "insufficient_credits", required, available, cta: "Buy AI Credits"}` |

O 402 é renderizado pelo handler de `InsufficientCreditsException` em `bootstrap/app.php`, não por middleware.

### 1.2 Leitura

| Método | URI | Ação | Middleware | Observação |
|--------|-----|------|------------|------------|
| GET | `/api/executions` | `Api\ExecutionController@index` | `api`, `VerifyFirebaseToken` | Escopado por `user_id` + `project_id` (`IndexExecutionRequest` cai na Brand ativa quando o parâmetro é omitido). `select()` explícito — nunca carrega `result` nem `config` (CLAUDE.md regra 11) |
| GET | `/api/executions/{execution}` | `Api\ExecutionController@show` | `api`, `VerifyFirebaseToken` | `ExecutionPolicy@view`. **Único** endpoint autorizado a devolver `result` |

### 1.3 Saída — callback do n8n

| Método | URI | Ação | Middleware | Form Request |
|--------|-----|------|------------|--------------|
| POST | `/api/webhook/n8n/result` | `Api\WebhookController@result` | `api`, `VerifyN8nSecret` (alias `verify.n8n`) | `N8nResultRequest` |

- Sem `VerifyFirebaseToken`: o n8n não é um usuário e nunca autentica ninguém (SAD 7.10 — Regra 2).
- Credencial única: header `X-N8N-Secret`, comparado com `hash_equals()` (CLAUDE.md regra 15). Segredo errado ou ausente ⇒ `403 {error: "invalid_secret"}`.
- **Sem rate limiting por IP** — `throttleApi()` está deliberadamente ausente do grupo `api` em `bootstrap/app.php` (CLAUDE.md §5). Um limite por origem descartaria resultados já processados e já cobrados.
- Idempotente: callback repetido sobre Execution já terminal responde `200 {ignored: true}` e não sobrescreve nada.
- `credits_consumed` do payload é **ignorado**: a contabilidade é do Laravel, a partir de `plan_limits`.

### 1.4 Telas (Blade + Livewire)

| Método | URI | Ação | Middleware | Componente |
|--------|-----|------|------------|------------|
| GET | `/` | `Web\AppController@index` | `web` | — |
| GET | `/briefing` | `Web\AppController@briefing` | `web`, `VerifyFirebaseToken` | `BriefingForm` |
| GET | `/result/{execution}` | `Web\AppController@result` | `web`, `VerifyFirebaseToken` | `ExecutionStatus`, `ResultDashboard` (`ExecutionPolicy@view` no Controller) |
| POST | `/livewire/update` | `HandleRequests@handleUpdate` | `web` | Ações dos componentes |
| GET/POST | `/broadcasting/auth` | `BroadcastController@authenticate` | `VerifyFirebaseToken` | Autoriza os canais privados de `routes/channels.php` |

O `BriefingForm` é a **segunda porta de entrada** do mesmo fluxo: ele não faz `Http::post()` para a própria API — chama `ExecutionService::create()` diretamente, que é onde vivem autorização, débito e despacho. Por isso o componente não conhece custo em créditos nem decide sobre saldo.

### 1.5 Canais de broadcast

| Canal | Autorização |
|-------|-------------|
| `private-execution.{executionId}` | `$user->executions()->whereKey($executionId)->exists()` |
| `private-App.Models.User.{id}` | `$user->id === $id` |

Eventos trafegam **apenas metadados**: `ExecutionUpdated` (`execution_id`, `status`, `pipeline_type`, `updated_at`) e `ExecutionFailed` (`execution_id`, `status`, `pipeline_type`, `error_message`, `failed_at`). `result` e `config` nunca entram no payload (CLAUDE.md regra 12).

---

## 2. Fluxo de estados da Execution

```text
   POST /api/executions              (HTTP — usuário)
   BriefingForm::submit()
            │
            │  ExecutionService::create()
            │    ├── Gate: ExecutionPolicy@create
            │    ├── CreditService::canExecute()      → 402 se não passa
            │    └── DB::transaction:
            │          Execution::create(status: queued)
            │          CreditService::deduct()        (lockForUpdate)
            │        commit → ProcessBriefingJob::dispatch(id)
            ▼
      ┌──────────┐
      │  queued  │   credits_consumed já debitado; result NULL
      └────┬─────┘
           │  Queue Worker (Redis, fila `default`)
           │  ProcessBriefingJob::handle()
           │    ├── update(status: processing)
           │    ├── ExecutionUpdated::dispatch()      (persistência → commit → broadcast)
           │    └── N8nService::dispatch()  ── HTTP ──▶ n8n (timeout 10s)
           ▼
    ┌──────────────┐
    │  processing  │   result NULL; nenhum timestamp final
    └───┬──────┬───┘
        │      │
        │      └──── falha de transporte ──▶ N8nConnectionException sobe
        │                                     retry (3 tentativas: 60s, 300s, 900s)
        │                                     esgotado ⇒ Job::failed()
        │                                        update(status: failed, error_message genérica, failed_at)
        │                                        ExecutionFailed::dispatch()
        │
        │  POST /api/webhook/n8n/result  (X-N8N-Secret)
        ▼
  ┌─────────────┐                            ┌──────────┐
  │  completed  │                            │  failed  │
  └─────────────┘                            └──────────┘
   result JSONB salvo                         error_message tratada (≤500 chars)
   tokens_used atualizado                     failed_at gravado
   completed_at gravado                       result permanece NULL
   error_message = NULL                       objeto `error` completo só no log
   ExecutionUpdated::dispatch()               ExecutionFailed::dispatch()
```

### 2.1 Responsável por cada transição

| Transição | Responsável | Código |
|-----------|-------------|--------|
| — → `queued` | Laravel (HTTP ou Livewire) | `ExecutionService::create()` |
| `queued` → `processing` | Queue Worker | `ProcessBriefingJob::handle()` |
| `processing` → `completed` | Callback autenticado do n8n | `WebhookController::complete()` |
| `processing` → `failed` | Callback autenticado do n8n | `WebhookController::fail()` |
| `processing` → `failed` (transporte) | Worker, após esgotar os retries | `ProcessBriefingJob::failed()` |

Estados válidos são exclusivamente `queued`, `processing`, `completed` e `failed` (`Execution::TERMINAL_STATUSES = ['completed', 'failed']`).

### 2.2 Invariantes garantidas por teste

- Nenhum usuário altera status manualmente; não existe endpoint de escrita de status.
- Estado terminal é **imutável**: tanto o callback duplicado quanto o `failed()` do Job desistem quando encontram `completed`/`failed` (o diagnóstico do n8n nunca é sobrescrito pelo da camada de transporte).
- `result` só é preenchido em conclusão bem-sucedida.
- Broadcast sempre **após** a persistência (SAD 9.11).
- `error_message` é sempre mensagem tratada, nunca stack trace, URL do Droplet 2 ou segredo compartilhado.
- Débito ocorre uma única vez, na criação, a partir de `plan_limits`.

### 2.3 Política de fila

| Parâmetro | Valor | Origem |
|-----------|------:|--------|
| Fila | `default` | `ProcessBriefingJob::QUEUE` |
| `tries` | 3 | `ProcessBriefingJob` |
| `backoff` | 60s, 300s, 900s | `ProcessBriefingJob` |
| `timeout` do Job | 30s | `ProcessBriefingJob` |
| `timeout` HTTP ao n8n | 10s | `N8nService::TIMEOUT_SECONDS` |

---

## 3. Cobertura automatizada

`php artisan test` — **81 testes, 342 asserções, verde** (2026-07-30).

| Arquivo | Cobre |
|---------|-------|
| `tests/Feature/PipelineEndToEndTest.php` | O trajeto completo HTTP → fila → n8n → callback → leitura do resultado; n8n offline com retries esgotados; bloqueio por saldo insuficiente |
| `tests/Feature/ExecutionTest.php` | API de executions, payload dos eventos de broadcast, escopo por Brand, 402, 403 |
| `tests/Feature/ProcessBriefingJobTest.php` | `queued → processing`, despacho ao n8n, retry, `failed()`, sanitização de log |
| `tests/Feature/WebhookTest.php` | Callback: sucesso, falha, segredo inválido/ausente, idempotência, ausência de throttle |
| `tests/Feature/LivewirePipelineTest.php` | `BriefingForm`, `ExecutionStatus`, `ResultDashboard` |
| `tests/Feature/BroadcastAuthTest.php` | Autorização dos canais privados |
| `tests/Unit/Services/N8nServiceTest.php` | Payload enviado ao n8n e sanitização de erros |
| `tests/Unit/Services/CreditServiceTest.php` | Custo por `plan_limits`, débito sob lock, plano ilimitado |

O n8n real nunca é chamado: `Http::fake()` na ida, requisição ao próprio endpoint de callback na volta, e `Http::preventStrayRequests()` para garantir que nada escape para a rede.

Rodar apenas o teste de ponta a ponta:

```bash
php artisan test --filter=PipelineEndToEndTest
```

---

## 4. Como testar manualmente com o n8n real

A executar quando o Erich concluir os workflows no Droplet 2.

### 4.1 Contrato que o n8n precisa cumprir

**Entrada** — o Laravel faz `POST` em `N8N_WEBHOOK_URL` (URL única para todos os pipelines; o roteamento por tipo é interno ao n8n), com headers `X-N8N-Secret` e `User-Agent: TinyAILab/Laravel`:

```json
{
  "execution_id": 154,
  "pipeline_type": "briefing_generation",
  "user_id": 12,
  "project_id": 8,
  "credits_cost": 5,
  "locale": "pt-BR",
  "brand": {
    "id": 8,
    "name": "Acme",
    "website": "https://acme.com",
    "description": "...",
    "segment": "...",
    "competitors": [],
    "channels": []
  },
  "config": { "language": "pt-BR", "objective": "..." },
  "webhook_callback": "https://app.tinyailab.com/api/webhook/n8n/result",
  "requested_at": "2026-07-30T12:00:00+00:00"
}
```

O `brand` contém apenas campos descritivos: o n8n nunca recebe dados de billing, créditos ou autenticação.

**Saída** — o n8n devolve o resultado em `webhook_callback`, com o mesmo `X-N8N-Secret`:

```json
{
  "execution_id": 154,
  "status": "completed",
  "pipeline_type": "briefing_generation",
  "tokens_used": 18432,
  "result": { "diagnosis": {}, "calendar": [], "copies": [] },
  "finished_at": "2026-07-30T12:03:11Z"
}
```

Em falha:

```json
{
  "execution_id": 154,
  "status": "failed",
  "error": { "message": "OpenAI rate limit exceeded" }
}
```

Obrigatórios apenas `execution_id` e `status` (`completed` ou `failed`). `status: "processing"` é recusado com 422 — o n8n não anuncia progresso.

### 4.2 Preparação do ambiente

```bash
# .env — Droplet 1 (Laravel)
APP_URL=https://app.tinyailab.com          # base do webhook_callback; nunca vem do header da requisição
N8N_WEBHOOK_URL=http://10.0.0.2:5678/webhook/pipeline   # IP privado da VPC
N8N_SHARED_SECRET=<mesmo valor configurado no n8n>
QUEUE_CONNECTION=redis
BROADCAST_CONNECTION=reverb
```

O mesmo `N8N_SHARED_SECRET` vale nos dois sentidos (SAD 7.5). Depois de alterar o `.env`:

```bash
php artisan config:clear
php artisan queue:restart     # workers carregam a config antiga até serem reiniciados
```

Processos que precisam estar de pé:

```bash
php artisan horizon        # ou: php artisan queue:work redis --queue=default --tries=3 --timeout=120
php artisan reverb:start   # para ver a tela reagir sem refresh
```

### 4.3 Roteiro — caminho felizardo

1. **Criar a Execution** (o `<ID_TOKEN>` sai do login Firebase no navegador — DevTools → Application → o token da sessão):

   ```bash
   curl -i -X POST https://app.tinyailab.com/api/executions \
     -H "Authorization: Bearer <ID_TOKEN>" \
     -H "Content-Type: application/json" \
     -H "Accept: application/json" \
     -d '{
           "project_id": 8,
           "pipeline_type": "briefing_generation",
           "config": {"language": "pt-BR", "objective": "lançamento da linha de verão"}
         }'
   ```

   Esperado: `202` com `{"execution_id": N, "status": "queued"}`.

2. **Conferir o débito** — `GET /api/auth/me`: `credits_balance` caiu exatamente o valor de `plan_limits` para `briefing_generation` (5 no seeder). O `credits_used_this_month` não é exposto pela API; conferir no banco:

   ```bash
   php artisan tinker --execute="dump(App\Models\User::find(12)->only('credits_balance','credits_used_this_month'));"
   ```

3. **Acompanhar a fila** — Horizon (`/horizon`, restrito a `HORIZON_ALLOWED_EMAILS`): o `ProcessBriefingJob` aparece em `pending` e some em segundos. Se ficar preso, o worker não está rodando.

4. **Confirmar a chegada no n8n** — na UI do n8n, o workflow deve constar em *Executions* com o payload da seção 4.1. Se não chegou, ver 4.6.

5. **`processing`** — `GET /api/executions/{id}` devolve `status: "processing"` e `result: null`. Com a tela `/result/{id}` aberta, o status muda **sem refresh** (evento `ExecutionUpdated` no canal `private-execution.{id}`).

6. **Aguardar o callback** — ao fim do workflow, `GET /api/executions/{id}` devolve `status: "completed"`, o `result` preenchido, `tokens_used` e `completed_at`. A tela troca para o `ResultDashboard` sozinha.

7. **Verificar o que a listagem devolve** — `GET /api/executions?project_id=8` **não** traz `result` nem `config`. Se trouxer, a regra 11 foi violada.

### 4.4 Roteiro — falhas

| Cenário | Como provocar | Esperado |
|---------|---------------|----------|
| n8n fora do ar | `systemctl stop n8n` no Droplet 2 e criar uma Execution | 3 tentativas (60s, 300s, 900s) visíveis no Horizon; ao esgotar, `failed` com mensagem genérica; `ExecutionFailed` chega na tela; **crédito não é estornado** (ver 6.1) |
| Falha dentro do workflow | Nó de erro proposital no n8n devolvendo `status: "failed"` | `failed` com o `error.message` do n8n (truncado em 500 chars); objeto `error` completo apenas em `storage/logs` |
| Segredo divergente | `curl` no callback com `X-N8N-Secret` errado | `403 {"error":"invalid_secret"}` e Execution intocada |
| Callback duplicado | Repetir o `curl` de callback já aceito | `200 {"ignored": true}`; `result` original preservado; nenhum broadcast novo |
| Saldo insuficiente | Zerar `credits_balance` do usuário e criar uma Execution | `402` com CTA `Buy AI Credits`; nenhuma Execution criada; nada enfileirado |
| Brand de outro usuário | `project_id` alheio | `403`; nada criado |

Simular o callback à mão (útil antes de os workflows existirem):

```bash
curl -i -X POST https://app.tinyailab.com/api/webhook/n8n/result \
  -H "X-N8N-Secret: $N8N_SHARED_SECRET" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
        "execution_id": 154,
        "status": "completed",
        "tokens_used": 18432,
        "result": {"diagnosis": {"posicionamento": "premium"}},
        "finished_at": "2026-07-30T12:03:11Z"
      }'
```

### 4.5 Checklist de aceitação

- [ ] `202` na criação, com `status: "queued"`
- [ ] Crédito debitado uma única vez, no valor de `plan_limits`
- [ ] Job processado pelo Horizon na fila `default`
- [ ] Payload da seção 4.1 recebido pelo n8n, com `webhook_callback` absoluto e correto
- [ ] `processing` refletido na tela sem refresh
- [ ] Callback aceito com `200`; `result` persistido em JSONB
- [ ] `completed` refletido na tela sem refresh
- [ ] Listagem sem `result`/`config`
- [ ] Callback duplicado devolve `ignored: true` sem alterar nada
- [ ] Segredo inválido devolve `403`
- [ ] n8n fora do ar termina em `failed` com mensagem genérica
- [ ] Nenhum log contém `N8N_SHARED_SECRET` ou a URL do webhook: `grep -ri "$N8N_SHARED_SECRET" storage/logs/` deve sair vazio

### 4.6 Diagnóstico rápido

| Sintoma | Verificar |
|---------|-----------|
| Fica em `queued` | Worker/Horizon rodando? `QUEUE_CONNECTION=redis`? Redis acessível? |
| Fica em `processing` para sempre | Callback nunca chegou: `webhook_callback` alcançável do Droplet 2? `APP_URL` correto? Nó final do workflow configurado? |
| `failed` imediato, sem retry visível | `N8N_WEBHOOK_URL`/`N8N_SHARED_SECRET` vazios ⇒ `N8nConnectionException::notConfigured` na primeira tentativa |
| Callback com `403` | Segredo divergente entre os dois Droplets, ou header ausente. Comparar os valores; lembrar de `config:clear` |
| Callback com `422` | Payload sem `execution_id`/`status`, ou `status` diferente de `completed`/`failed` |
| Tela não atualiza, mas o banco atualiza | Reverb no ar? `BROADCAST_CONNECTION=reverb`? `/broadcasting/auth` recebendo o ID Token? |
| `curl` no callback funciona, n8n não | Rota de saída do Droplet 2 pela VPC / firewall |

---

## 5. Divergências entre a implementação e a `CLAUDE.md` §5

Nenhum bug encontrado pelos testes desta sessão. Há, porém, duas diferenças entre a tabela de rotas documentada e o código:

1. **`CheckSubscription` e `EnsureCreditBalance` não existem como middleware.** A `CLAUDE.md` §5 os lista em `POST /api/executions`. Os middlewares implementados são apenas `VerifyFirebaseToken` e `VerifyN8nSecret`. A verificação de crédito está no `ExecutionService`/`CreditService`, com o 402 renderizado pelo handler de `InsufficientCreditsException` — comportamento equivalente e coberto por teste. A verificação de assinatura **não existe ainda** e depende do Cashier (mês 3).
2. **`GET /api/dashboard/health`, `/plans` e `/history` ainda não foram implementados** — constam da tabela de rotas como escopo futuro.

---

## 6. Lacunas conhecidas ([A DEFINIR] no SAD)

### 6.1 Estorno de créditos em falha (SAD 6.2 e 6.9)

Hoje o crédito é debitado na criação e **não é estornado** quando o pipeline falha. O `PipelineEndToEndTest::test_pipeline_handles_n8n_failure()` fixa esse comportamento por assert deliberado: quando a política de estorno for decidida, o teste quebra em vez de a mudança passar despercebida. Decidir antes do lançamento comercial.

### 6.2 Reexecução pelo usuário (SAD 6.10)

Não existe endpoint de retry. Uma Execution `failed` é definitiva; o usuário precisa criar outra — e pagar de novo, dado 6.1.

### 6.3 Um único Job para todos os pipelines

`ExecutionService::create()` despacha `ProcessBriefingJob` para qualquer `pipeline_type`. A estrutura de diretórios da `CLAUDE.md` §4 prevê `ProcessBrandResearchJob`, `ProcessImageGenerationJob` e `ProcessSeoOptimizationJob`. O Job atual é agnóstico ao tipo (só transporta a Execution até o n8n), então a divisão só se justifica quando algum pipeline exigir fila ou timeout próprios — `brand_research` é o candidato natural (fila `viral-refs`, timeout 900s, conforme a `CLAUDE.md` §8). Enquanto isso, um Job para todos evita quatro classes idênticas.

### 6.4 Estrutura de `config` e de `result` por pipeline

Permanece `[A DEFINIR]` no SAD para a maioria dos pipelines. O domínio só garante que `config` é um objeto (possivelmente vazio) e que `result` é JSONB. Fechar com o Erich junto dos workflows.

### 6.5 Múltiplas tentativas reemitem `ExecutionUpdated`

Cada retry do Job reanuncia `processing` no canal privado. Inofensivo — o evento carrega só metadados de estado e a interface é idempotente —, mas é comportamento observável e está registrado como asserção no teste de falha.
