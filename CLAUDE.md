# CLAUDE.md

## Documentação de Referência

Antes de qualquer tarefa, leia o seguinte documento na íntegra:
1. `.claude/docs/SAD.md` — arquitetura completa do sistema
Este documento é a fonte de verdade do projeto. Nunca tome decisões arquiteturais que contradigam o SAD sem registrar uma ADR.

## 1. Identidade do Projeto

Arkion é uma plataforma SaaS B2B para orquestração inteligente de marketing baseada em IA, construída sobre o conceito de Brand Memory, onde toda operação ocorre dentro de uma Brand (Project) ativa.

A aplicação utiliza Laravel 11, Livewire 3, PostgreSQL, Redis, Horizon, Reverb, Firebase Authentication, Stripe Cashier e n8n self-hosted como motor externo de orquestração de IA.

A arquitetura oficial é um monólito Laravel utilizando Blade + Livewire, executando em um Droplet dedicado, enquanto o n8n executa isoladamente em um segundo Droplet conectado por VPC privada. Toda comunicação entre ambos ocorre exclusivamente via Webhooks HTTP autenticados.

---

## 2. Stack

| Tecnologia | Versão | Papel |
|------------|---------|------|
| PHP | 8.3 | Runtime principal |
| Laravel | 11 | Backend, domínio, API e autenticação |
| Livewire | 3 | Componentes reativos da interface |
| Blade | Laravel 11 | Templates oficiais da aplicação |
| Alpine.js | 3.x | Interatividade client-side |
| Tailwind CSS | 3.x | Design System |
| PostgreSQL | [A DEFINIR] | Banco oficial do domínio |
| Redis | [A DEFINIR] | Filas, cache e Horizon |
| Laravel Horizon | Laravel 11 | Monitoramento de filas |
| Laravel Reverb | Laravel 11 | WebSockets em tempo real |
| Firebase Authentication | Latest | Autenticação dos usuários |
| Laravel Cashier | Laravel 11 | Assinaturas Stripe |
| Stripe | Latest | Billing |
| n8n | Self-hosted | Orquestração dos pipelines de IA |
| DigitalOcean | Ubuntu 24.04 LTS | Infraestrutura |

---

## 3. Regras Invioláveis

1. Laravel é o único serviço autorizado a acessar diretamente o PostgreSQL.
2. n8n nunca acessa o banco de dados e nunca conversa diretamente com o usuário.
3. Toda autenticação deve ocorrer via Firebase ID Token.
4. Toda autorização pertence ao Laravel utilizando Policies e regras de domínio.
5. Nunca validar dados em Controllers; utilizar sempre Form Requests.
6. Nunca implementar lógica de negócio em componentes Livewire; eles apenas apresentam dados e disparam ações.
7. Toda regra de domínio deve residir em Services, Actions, Jobs ou no próprio domínio.
8. `App\Actions` é utilizado exclusivamente para operações de sincronização de autenticação. Exemplo canônico: `App\Actions\Auth\SyncFirebaseUserAction` (upsert do usuário Firebase no banco local, injetado no AuthController e no VerifyFirebaseToken middleware). Novas Actions fora do domínio de Auth exigem ADR antes de serem criadas.
9. Nunca hardcode valores de consumo de créditos; utilizar sempre plan_limits cacheado.
10. Toda query de domínio é escopada pela organização ativa (`organization_id`) e pela Brand ativa (`project_id`), nunca por `user_id`. Não existe recurso pertencente diretamente a um usuário além da carteira pessoal de créditos.
11. Nunca carregar executions.result em listagens; utilizar select() explícito contendo apenas os campos necessários.
12. Nunca incluir executions.result em eventos de broadcast; transmitir apenas metadados da execução.
13. Nunca armazenar tokens do Firebase em cache; sua revogação é gerenciada pelo próprio Firebase.
14. DevelopmentSeeder somente pode ser executado quando APP_ENV=local.
15. Comparações do header X-N8N-Secret devem utilizar exclusivamente hash_equals(); nunca utilizar == ou ===.
16. Utilizar sempre $table->jsonb() nas migrations PostgreSQL; nunca utilizar $table->json().
17. Declarar sempre $fillable explicitamente em todos os Models; nunca utilizar protected $guarded = [].
18. Toda comunicação Laravel ↔ n8n deve ocorrer exclusivamente via Webhooks HTTP autenticados.
19. Toda execução de IA deve ser assíncrona utilizando Redis, Horizon e Queue Workers.
20. O modo Light é o padrão oficial; o elemento <html> inicia sem classe de tema e o modo Dark é aplicado exclusivamente pelo prefixo Tailwind `dark:`.
21. As fontes oficiais do sistema são Newsreader (`font-serif`, títulos H1–H5) e Inter (`font-sans`, corpo e UI); nenhuma outra tipografia pode ser introduzida sem uma ADR aprovada.
22. `users.is_admin` é um boolean na tabela `users`, não uma tabela separada. Reavaliar apenas via ADR se o sistema crescer para múltiplos funcionários com hierarquia de permissões. `users.is_admin` é staff da plataforma Arkion; papel em organização é `organization_user.role` (`owner`, `admin` ou `member`). Nunca use um no lugar do outro — um não implica o outro em nenhuma direção. O papel `admin` de organização não tem nenhuma relação com `users.is_admin`.
23. Rotas `/admin/*` nunca compartilham Controllers com rotas de Web ou de Api. Sempre em `App\Http\Controllers\Admin`.
24. Toda ação administrativa que altera dados de um usuário (créditos, plano, status de conta) deve ser registrada em `admin_action_logs` antes de retornar sucesso ao admin.
25. Toda tela voltada ao cliente final usa inglês. Painel administrativo (uso interno) pode usar português.
26. Nunca usar tokens de cor antigos (#111111, #FF5500) — usar exclusivamente a paleta documentada na Seção 10 do SAD.
27. Créditos têm três carteiras: `organizations.plan_credits_balance`, `organizations.purchased_credits_balance` e `users.purchased_credits_balance`. Nunca somar as três em uma única coluna. Ordem de débito: plano da org, comprado pela org, pessoal do usuário. Nunca inverta.
28. Ícones são SVG inline com `stroke="currentColor" stroke-width="0.3"` e tamanho `w-5 h-5` ou `w-4 h-4`; nenhum outro peso ou tamanho é permitido.
29. Um Project pode ter `parent_project_id` opcional apontando para outro Project da **mesma organização**; sub-brands compartilham o saldo da organização e nunca ampliam o escopo de consultas por `project_id`.
30. A Organização é a entidade de faturamento. Nenhuma Marca existe fora de uma Organização: `projects.organization_id` é NOT NULL, não há marca órfã e não há vínculo direto entre User e Project. `projects.user_id` é apenas auditoria de criação.
31. A carteira pessoal nunca é debitada sem `confirm_personal_credits = true` explícito na requisição. Sem a flag, a API responde 409 com o breakdown por carteira. O bloqueio é no backend, não apenas na UI, e o valor pessoal é recalculado dentro da transação de débito.
32. Toda organização tem exatamente um Owner, garantido por índice único parcial (`UNIQUE(organization_id) WHERE role = 'owner'`) e por verificação dentro da transação, sob lock. O Owner não pode ser removido, rebaixado nem sair da organização; só deixa de ser Owner por transferência de posse a um membro existente, e o Owner anterior vira Admin (destino fora da org = convidar primeiro). A organização Personal criada no primeiro login tem o usuário como Owner. (Substitui "uma organização nunca fica sem Admin" — ADR-010, SAD §20.4.)
33. `organizations.plan` é NULLABLE; `plan = null` significa sem assinatura ativa. A verificação de plano nulo (`NoActivePlanException`, 402) acontece ANTES do feature gating (`FeatureNotAvailableException`, 403), que acontece antes do saldo (`InsufficientCreditsException`, 402). Inverter essa ordem é falha de segurança, porque linha ausente em `plan_limits` significa habilitado. Nunca colapsar os três bloqueios em uma mensagem genérica.
34. O contexto de sessão tem duas variáveis: `active_organization_id` e `active_project_id`. A Brand ativa deve pertencer à organização ativa — validar em todo acesso.
35. Papel é `organization_user.role` em {`owner`, `admin`, `member`}. Nunca compare strings de papel: use o enum `OrganizationRole` e seus métodos `can*()`.
36. Matriz de papéis (SAD §20.4): faturamento (cartão, plano, assinatura), compra de créditos para a organização, atribuição de papéis, transferência de posse e exclusão da organização são exclusivos do Owner. Criar, editar e excluir Marcas e sub-brands (incluindo Brand Core) e ver relatórios de consumo são de Owner e Admin. O Owner convida e remove Admins e Members; o Admin convida e remove somente Members; o Member não convida nem remove. Todos usam as ferramentas de IA, veem os saldos e compram créditos pessoais. CTAs de assinar, fazer upgrade ou comprar para a org só aparecem para o Owner; os demais veem "ask your organization owner" (SAD §12.12).

## 4. Estrutura de Diretórios

Somente os diretórios abaixo fazem parte da arquitetura oficial do Arkion e podem ser criados ou modificados pelo agente.

```text
app/
├── Actions/
├── Auth/
│   ├── SyncFirebaseUserAction.php  # upsert Firebase→PostgreSQL
│   ├── Billing/
│   ├── Executions/
│   ├── Projects/
│   └── Webhooks/
│
├── Broadcasting/
│
├── Events/
│   ├── Execution/
│   ├── Notification/
│   └── Project/
│
├── Exceptions/
│
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   ├── AuthController.php
│   │   │   ├── ExecutionController.php
│   │   │   ├── NotificationController.php
│   │   │   ├── ProjectController.php
│   │   │   ├── SubscriptionController.php
│   │   │   └── WebhookController.php
│   │   │
│   │   └── Web/
│   │       └── AppController.php
│   │
│   ├── Middleware/
│   │   ├── EnsureActiveProject.php
│   │   ├── VerifyFirebaseToken.php
│   │   ├── CheckSubscription.php
│   │   └── VerifyN8nWebhook.php
│   │
│   └── Requests/
│       ├── Auth/
│       ├── Execution/
│       ├── Project/
│       ├── Subscription/
│       └── Webhook/
│
├── Jobs/
│   ├── DispatchPipelineJob.php
│   ├── ProcessBriefingJob.php
│   ├── ProcessBrandResearchJob.php
│   ├── ProcessImageGenerationJob.php
│   ├── ProcessSeoOptimizationJob.php
│   └── SendNotificationJob.php
│
├── Livewire/
│   ├── Dashboard/
│   ├── Executions/
│   ├── Notifications/
│   ├── Projects/
│   └── Shared/
│
├── Models/
│   ├── Execution.php
│   ├── Notification.php
│   ├── PlanLimit.php
│   ├── Project.php
│   ├── Subscription.php
│   ├── User.php
│   └── VideoReference.php
│
├── Policies/
│   ├── ExecutionPolicy.php
│   └── ProjectPolicy.php
│
├── Providers/
│
└── Services/
    ├── Firebase/
    │   └── FirebaseService.php
    │
    ├── N8nService.php
    │
    ├── Billing/
    │
    ├── Credits/
    │
    └── Projects/

database/
├── factories/
├── migrations/
├── seeders/
│   ├── DatabaseSeeder.php
│   ├── DevelopmentSeeder.php
│   └── PlanLimitSeeder.php
└── schema/

resources/
└── views/
    ├── layouts/
    │   ├── app.blade.php
    │   └── guest.blade.php
    │
    ├── livewire/
    │   ├── dashboard/
    │   ├── executions/
    │   ├── notifications/
    │   ├── projects/
    │   └── shared/
    │
    ├── auth/
    ├── dashboard/
    ├── projects/
    ├── executions/
    ├── notifications/
    └── components/

routes/
├── api.php
├── channels.php
├── console.php
└── web.php

public/
├── build/
├── favicon.ico
├── robots.txt
└── index.php

tests/
├── Feature/
│   ├── AuthTest.php
│   ├── ExecutionTest.php
│   ├── ProjectTest.php
│   ├── SubscriptionTest.php
│   └── WebhookTest.php
│
├── Unit/
│   ├── Services/
│   ├── Jobs/
│   ├── Policies/
│   └── Models/
│
├── Pest.php
└── TestCase.php

## 5. Rotas Principais

| Método | URI | Controller | Middleware | Descrição |
|--------|-----|------------|------------|-----------|
| POST | /api/auth/firebase | AuthController@firebase | — | Autentica usuário via Firebase ID Token e sincroniza usuário local. |
| GET | /api/auth/me | AuthController@me | VerifyFirebaseToken | Retorna usuário autenticado, assinatura, créditos e Brand ativa. |
| GET | /api/projects | ProjectController@index | VerifyFirebaseToken | Lista as Brands pertencentes ao usuário autenticado. |
| POST | /api/projects | ProjectController@store | VerifyFirebaseToken | Cria uma nova Brand (Project). |
| GET | /api/projects/{project} | ProjectController@show | VerifyFirebaseToken + ProjectPolicy | Exibe detalhes da Brand selecionada. |
| PUT | /api/projects/{project} | ProjectController@update | VerifyFirebaseToken + ProjectPolicy | Atualiza informações da Brand. |
| PATCH | /api/projects/{project} | ProjectController@update | VerifyFirebaseToken + ProjectPolicy | Atualização parcial da Brand. |
| DELETE | /api/projects/{project} | ProjectController@destroy | VerifyFirebaseToken + ProjectPolicy | Remove a Brand conforme políticas de domínio. |
| POST | /api/executions | ExecutionController@store | VerifyFirebaseToken (verificação de créditos via InsufficientCreditsException (ExecutionService); CheckSubscription pendente até Mês 4/Cashier) | Cria uma Execution, desconta créditos, grava status `queued` e despacha o Job para a fila. |
| GET | /api/executions/{execution} | ExecutionController@show | VerifyFirebaseToken + ExecutionPolicy | Retorna os detalhes completos de uma Execution. |
| GET | /api/dashboard/health | DashboardController@health | VerifyFirebaseToken | Retorna métricas de saúde da plataforma e das integrações. |
| POST | /api/webhook/n8n/result | WebhookController@result | VerifyN8nWebhook | Endpoint exclusivo para callback do n8n. Atualiza status da Execution, persiste o JSONB retornado e dispara o evento `ExecutionUpdated`. |
| GET | / | AppController@index | VerifyFirebaseToken | Dashboard principal da aplicação Blade + Livewire. |
| GET | /briefing | AppController@briefing | VerifyFirebaseToken | Tela de criação de Briefings. |
| GET | /result/{execution} | AppController@result | VerifyFirebaseToken + ExecutionPolicy | Tela de visualização do resultado da Execution. |
| GET | /plans | AppController@plans | VerifyFirebaseToken | Tela de planos e assinatura. |
| GET | /history | AppController@history | VerifyFirebaseToken | Histórico de execuções da Brand ativa. |

### Observações Operacionais

- Toda rota protegida deve validar o Firebase ID Token antes de qualquer acesso ao domínio.
- Toda rota relacionada a Projects deve aplicar `ProjectPolicy`.
- Toda rota relacionada a Executions deve aplicar `ExecutionPolicy`.
- Toda criação de Execution deve validar assinatura ativa e saldo suficiente antes de persistir qualquer operação.
- O callback do n8n nunca utiliza autenticação Firebase; sua autenticação ocorre exclusivamente pela validação do header `X-N8N-Secret` utilizando `hash_equals()`.
- O endpoint `/api/webhook/n8n/result` nunca deve possuir rate limiting baseado em IP; a segurança depende exclusivamente do segredo compartilhado.
- O callback do n8n é o único mecanismo autorizado para alterar o estado final (`completed` ou `failed`) de uma Execution.
- Nenhuma rota retorna dados pertencentes a outra Brand; todas as consultas devem utilizar obrigatoriamente o contexto da Brand ativa (`project_id`).
- Endpoints de listagem nunca devem carregar `executions.result`; utilizar sempre `select()` explícito para excluir o campo JSONB quando desnecessário.

## 6. Estado da Execution

```text
                    +----------------------+
                    |      queued          |
                    | (Laravel Controller) |
                    +----------+-----------+
                               |
                               | Dispatch Job
                               v
                    +----------------------+
                    |     processing       |
                    | (Queue Worker / n8n) |
                    +-----+-----------+----+
                          |           |
            sucesso       |           | erro
                          |           |
                          v           v
                +----------------+  +----------------+
                |   completed    |  |     failed     |
                | (Webhook n8n)  |  | (Webhook n8n)  |
                +----------------+  +----------------+
```

### Responsável por cada transição

| Transição | Responsável |
|-----------|-------------|
| queued → processing | Laravel Queue Worker (Job) |
| processing → completed | n8n via Webhook autenticado |
| processing → failed | n8n via Webhook autenticado |

### Regras Operacionais

- Apenas o Laravel pode criar uma Execution.
- Apenas Queue Workers podem iniciar o processamento.
- Apenas o Webhook autenticado do n8n pode finalizar uma Execution.
- Nenhum usuário pode alterar manualmente o status.
- Nunca atualizar diretamente o status pelo frontend.
- Toda alteração de estado deve gerar evento de domínio.
- Toda conclusão deve registrar timestamps consistentes.
- Falhas devem preservar informações de diagnóstico sem expor dados sensíveis ao usuário.
- O campo `result` somente pode ser preenchido após conclusão bem-sucedida.
- Estados válidos são exclusivamente: `queued`, `processing`, `completed` e `failed`.

---

## 7. Convenções de Nomenclatura — Referência Rápida

| Tipo | Padrão | Exemplo |
|------|---------|---------|
| Tabelas | snake_case plural | users |
| Colunas | snake_case | project_id |
| Foreign Keys | entidade_id | user_id |
| Booleanos | prefixo is_ ou has_ | is_active |
| Timestamps | Laravel padrão | created_at |
| Migrations | create_xxx_table | create_projects_table |
| Classes PHP | PascalCase | ProjectService |
| Interfaces | PascalCase | BillingGateway |
| Traits | PascalCase | HasCredits |
| Enums | PascalCase | ExecutionStatus |
| Métodos | camelCase | consumeCredits |
| Scopes | scopeCamelCase | scopeByProject |
| Form Requests | Verbo + Recurso + Request | StoreProjectRequest |
| Controllers | RecursoController | ProjectController |
| Services | RecursoService | FirebaseService |
| Actions | Verbo + Recurso + Action | CreateExecutionAction |
| Jobs | Verbo + Recurso + Job | DispatchPipelineJob |
| Events | Recurso + Verbo | ExecutionCompleted |
| Listeners | Verbo + Evento + Listener | NotifyExecutionCompletedListener |
| Policies | RecursoPolicy | ProjectPolicy |
| Exceptions | Descrição + Exception | InsufficientCreditsException |
| Seeders | RecursoSeeder | PlanLimitSeeder |
| Factories | RecursoFactory | ProjectFactory |
| Livewire | PascalCase | ProjectList |
| Views Blade | kebab-case | project-list.blade.php |
| Variáveis | camelCase | activeProject |
| Constantes | UPPER_SNAKE_CASE | DEFAULT_QUEUE |

### Convenções Gerais

- Utilizar inglês em nomes de classes, métodos, propriedades e arquivos.
- Utilizar inglês em todo texto apresentado ao cliente final (labels, botões, placeholders, mensagens de validação e de erro, estados vazios, notificações e e-mails). Ver regra 25 e SAD, Seção 10.1.1.
- Utilizar português apenas no painel administrativo (uso interno) e na documentação de arquitetura.
- Evitar abreviações que não sejam amplamente reconhecidas.
- Utilizar nomes descritivos e orientados ao domínio.
- Não utilizar prefixos como `tbl_`, `obj_`, `str_`, `tmp_` ou similares.
- Seguir rigorosamente os padrões PSR-12 e Laravel Pint.

---

## 8. Comandos Úteis

### Banco de Dados

```bash
php artisan migrate
```

Executa todas as migrations pendentes.

```bash
php artisan migrate:fresh --seed
```

Recria completamente o banco local e executa todos os Seeders.

```bash
php artisan db:seed
```

Executa o DatabaseSeeder.

```bash
php artisan db:seed --class=PlanLimitSeeder
```

Atualiza apenas os limites de planos e custos de créditos.

---

### Desenvolvimento

```bash
php artisan serve
```

Inicializa o servidor local.

```bash
php artisan optimize:clear
```

Limpa todos os caches da aplicação.

```bash
php artisan config:clear
```

Limpa cache de configuração.

```bash
php artisan cache:clear
```

Limpa cache da aplicação.

```bash
php artisan route:clear
```

Limpa cache de rotas.

```bash
php artisan view:clear
```

Limpa cache das views Blade.

---

### Geração de Código

```bash
php artisan make:livewire Projects/ProjectList
```

Cria um componente Livewire.

```bash
php artisan make:job DispatchPipelineJob
```

Cria um Job.

```bash
php artisan make:event ExecutionCompleted
```

Cria um Event.

```bash
php artisan make:listener NotifyExecutionCompletedListener
```

Cria um Listener.

```bash
php artisan make:policy ProjectPolicy --model=Project
```

Cria uma Policy.

```bash
php artisan make:request StoreProjectRequest
```

Cria um Form Request.

```bash
php artisan make:test ProjectTest
```

Cria um teste Feature.

---

### Filas

```bash
php artisan queue:work redis --queue=default --tries=3 --timeout=120
```

Worker da fila padrão.

```bash
php artisan queue:work redis --queue=viral-refs --tries=3 --timeout=900
```

Worker para pipelines longos.

```bash
php artisan queue:work redis --queue=notifications --tries=3 --timeout=60
```

Worker para notificações.

```bash
php artisan horizon
```

Inicializa o Horizon.

---

### Realtime

```bash
php artisan reverb:start
```

Inicializa o servidor Laravel Reverb.

---

### Testes

```bash
php artisan test
```

Executa toda a suíte de testes.

```bash
php artisan test --filter=AuthTest
```

Executa apenas os testes de autenticação.

```bash
php artisan test --filter=ProjectTest
```

Executa apenas os testes de Projects.

```bash
php artisan test --parallel
```

Executa os testes em paralelo.

---

### Regras Operacionais

- Sempre executar os testes relacionados antes de concluir uma implementação.
- Nunca executar `migrate:fresh` em ambientes que não sejam locais.
- Horizon deve ser utilizado como gerenciador oficial de filas.
- Reverb deve permanecer ativo durante o desenvolvimento de funcionalidades em tempo real.
- Queue Workers devem consumir apenas as filas para as quais foram configurados.
- Todo código novo deve ser formatado utilizando Laravel Pint antes do commit.
```

