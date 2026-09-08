# PROJECT_RULES.md

> Checklist operacional de arquitetura do Arkion.
> Este documento deve ser utilizado como contexto adicional do Codex sempre que houver geração de código.
> Nenhuma regra abaixo pode ser flexibilizada sem aprovação por ADR.

---

# 1. Fronteiras de Camada

| Camadas | Permitido | Proibido |
|----------|-----------|-----------|
| Livewire ↔ Service | Livewire chama Services para executar casos de uso e recebe DTOs, Collections ou Models já preparados para apresentação. | Livewire acessar Models diretamente para lógica de negócio, executar consultas complexas, consumir APIs externas ou acessar Redis. |
| Controller ↔ Model | Controller apenas recebe Request, delega ao Service e retorna Response. | Controller contendo regras de negócio, queries complexas, consumo de APIs externas ou cálculos de domínio. |
| Job ↔ Service | Jobs apenas orquestram processamento assíncrono chamando Services. | Jobs contendo lógica de negócio extensa, validações ou acesso direto ao frontend. |
| Service ↔ n8n | Services enviam Webhooks autenticados ao n8n e interpretam respostas. | Services compartilhar banco de dados, Redis ou qualquer estado interno com o n8n. |
| n8n ↔ Banco | Nenhuma comunicação permitida. | n8n acessar PostgreSQL, Redis, Eloquent, Models ou qualquer recurso interno do Laravel. |

---

# 2. Checklist de Pull Request

Antes de aceitar qualquer código gerado pelo Codex, verificar obrigatoriamente:

## Banco de Dados

- [ ] Toda migration utiliza `jsonb()` quando necessário.
- [ ] Nenhuma migration utiliza `json()`.
- [ ] Todas as Foreign Keys possuem política explícita (`cascade`, `restrict` ou `nullOnDelete`).
- [ ] Índices necessários foram criados.
- [ ] Não existem colunas sem propósito definido.
- [ ] Todas as migrations são reversíveis.

## Models

- [ ] `$fillable` declarado explicitamente.
- [ ] Nunca utilizar `$guarded = []`.
- [ ] `$casts` configurado corretamente.
- [ ] Relacionamentos implementados.
- [ ] Scopes reutilizáveis criados quando necessário.
- [ ] Nenhuma regra de negócio complexa implementada no Model.

## Controllers

- [ ] Controller contém apenas orquestração.
- [ ] Utiliza Form Requests.
- [ ] Não realiza validação manual.
- [ ] Não realiza consultas complexas.
- [ ] Não consome APIs externas.
- [ ] Não contém regras de crédito.

## Form Requests

- [ ] Método `authorize()` implementa autorização real.
- [ ] Nunca retornar `true` indiscriminadamente.
- [ ] Todas as regras de validação estão centralizadas.

## Services

- [ ] Toda lógica de negócio está concentrada em Services.
- [ ] Nenhum Service depende do frontend.
- [ ] Toda integração externa está encapsulada.

## Jobs

- [ ] Job apenas coordena processamento assíncrono.
- [ ] Nenhum Job realiza renderização.
- [ ] Filas corretas foram atribuídas.

## Livewire

- [ ] Nenhuma regra de negócio implementada.
- [ ] Nenhum acesso HTTP externo.
- [ ] Nenhuma query complexa.
- [ ] Apenas apresentação e interação.

## Segurança

- [ ] Firebase Token validado.
- [ ] Policies utilizadas.
- [ ] Webhook protegido por `hash_equals()`.
- [ ] Nenhum segredo exposto.
- [ ] Queries escopadas por `organization_id` (organização ativa) **e** `project_id` (Brand ativa).
- [ ] Nenhuma query usa `user_id` como critério de isolamento.
- [ ] A Brand ativa foi validada como pertencente à organização ativa.
- [ ] O papel do usuário na organização foi verificado por Policy, não pela UI.

## Cache

- [ ] TTL explícito definido.
- [ ] Tokens Firebase não são cacheados.
- [ ] `plan_limits` cacheado.

## Performance

- [ ] Listagens utilizam `select()`.
- [ ] Campo `executions.result` não é carregado em listagens.
- [ ] Não existe N+1 Query.

## Broadcast

- [ ] Eventos não enviam JSON completo da Execution.
- [ ] Apenas metadados são transmitidos.

## Design System e Idioma

- [ ] Todo texto de cliente final está em inglês.
- [ ] Nenhum token de cor antigo (`#111111`, `#FF5500`, `#E64D00`, escala `orange.*`) foi utilizado.
- [ ] Apenas os tokens da Seção 10.4 do `SAD.md` foram utilizados.
- [ ] Títulos `H1`–`H5` usam `font-serif` (Newsreader); corpo e UI usam `font-sans` (Inter).
- [ ] Ícones SVG usam `stroke="currentColor" stroke-width="0.3"` e `w-5 h-5` ou `w-4 h-4`.
- [ ] Toda superfície usa o par light/dark da Seção 10.4 (`bg-gray-50` / `dark:bg-[#121214]`, `bg-white` / `dark:bg-[#1c1c1c]`, `border-gray-200` / `dark:border-[#6b4e3a]/30`).
- [ ] O hover do CTA primário usa `hover:brightness-90`, nunca um novo hexadecimal.
- [ ] O tema escuro é aplicado apenas por `dark:`; o `<html>` não inicia com a classe `dark`.

## Créditos

- [ ] As três carteiras permanecem colunas separadas: `organizations.plan_credits_balance`, `organizations.purchased_credits_balance`, `users.purchased_credits_balance`.
- [ ] O débito segue a ordem plano da org → comprado pela org → pessoal do usuário, sem inversão.
- [ ] A renovação mensal **atribui** `organizations.plan_credits_balance` e nunca o incrementa.
- [ ] Nenhum `purchased_credits_balance` é zerado por rotina de ciclo.
- [ ] Nenhuma coluna de saldo de plano foi adicionada a `users`.
- [ ] A carteira pessoal só é debitada com `confirm_personal_credits = true` na requisição.
- [ ] A ausência da flag retorna **409** com breakdown por carteira, sem criar Execution nem debitar nada.
- [ ] O valor debitado da carteira pessoal é recalculado dentro da transação, nunca reaproveitado do 409.
- [ ] Organização sem plano retorna 402 (`NoActivePlanException`) **antes** de qualquer verificação de gating.
- [ ] Pipeline fora do plano retorna 403 (`FeatureNotAvailableException`); saldo insuficiente retorna 402 (`InsufficientCreditsException`).
- [ ] Os três bloqueios têm mensagens e CTAs distintos, nunca uma mensagem genérica.

## Organizações

- [ ] `projects.organization_id` é NOT NULL; nenhuma Marca foi criada fora de uma Organização.
- [ ] Nenhuma relação de propriedade direta entre `User` e `Project` foi criada ou usada.
- [ ] `users.is_admin` (staff da plataforma) e `organization_user.role` (papel na org) não foram confundidos.
- [ ] O invariante "a organização nunca fica sem Admin" é verificado dentro da transação, sob lock.
- [ ] O último Admin não pode ser removido, rebaixado, nem sair da organização.
- [ ] Ações exclusivas de Admin (faturamento, créditos da org, membros, exclusão da org) estão protegidas por Policy.
- [ ] O usuário novo recebe automaticamente a organização "Personal" com ele como Admin.
- [ ] O seletor de organização fica oculto quando o usuário pertence a apenas uma organização.

## Hierarquia de Marcas

- [ ] `parent_project_id` aponta para um Project da mesma `organization_id`.
- [ ] Nenhuma consulta usa `parent_project_id` para agregar dados entre Brands.
- [ ] Nenhuma coluna de crédito foi adicionada a `projects`.

## Testes

- [ ] Feature Tests criados.
- [ ] Casos negativos cobertos.
- [ ] Policies testadas.
- [ ] Services testados quando necessário.
- [ ] Todos os testes passam.

---

# 3. Antipatterns Proibidos

### 1.

```php
User::all();
```

Motivo: risco de consumo excessivo de memória e vazamento de dados.

---

### 2.

```php
Http::post(...)
```

dentro de Livewire.

Motivo: integrações externas pertencem aos Services.

---

### 3.

```php
cache()->remember(...)
```

sem TTL explícito.

Motivo: impede controle adequado de invalidação.

---

### 4.

```php
$table->json(...)
```

Motivo: PostgreSQL deve utilizar exclusivamente JSONB.

---

### 5.

```php
return true;
```

em `authorize()`.

Motivo: elimina completamente a camada de autorização.

---

### 6.

Comparar Webhook Secret usando

```php
==
```

ou

```php
===
```

Motivo: suscetível a timing attacks.

---

### 7.

Consumir Redis diretamente em Controllers.

Motivo: viola separação de responsabilidades.

---

### 8.

Criar lógica de crédito em Controllers.

Motivo: regras pertencem ao domínio.

---

### 9.

Acessar banco diretamente pelo n8n.

Motivo: quebra completamente a arquitetura.

---

### 10.

Executar lógica de domínio em componentes Livewire.

Motivo: Livewire representa apenas a camada de apresentação.

---

### 11.

Utilizar valores fixos de créditos.

Motivo: todos os custos vêm da tabela `plan_limits`.

---

### 12.

Executar queries sem filtro por `organization_id` e `project_id`, ou usar `user_id` como critério de isolamento.

```php
Project::where('user_id', auth()->id());                       // PROIBIDO
Execution::where('user_id', auth()->id())->get();              // PROIBIDO

Project::where('organization_id', $activeOrganization->id);    // correto
Execution::where('project_id', $activeProject->id);            // correto
```

Motivo: viola o isolamento entre Organizações e entre Brands. `user_id` deixou de ser critério de propriedade — o único recurso que pertence diretamente a um usuário é a carteira pessoal de créditos. Ver `SAD.md`, Seções 20.2 e 20.6.

---

### 13.

Selecionar `executions.result` em listagens.

Motivo: aumenta consumo de memória e largura de banda.

---

### 14.

Enviar `result` completo em Broadcast.

Motivo: payload excessivo e exposição desnecessária de dados.

---

### 15.

Executar `DevelopmentSeeder` fora do ambiente local.

Motivo: risco de contaminação dos ambientes de homologação e produção.

---

### 16.

Texto em português em telas do cliente final.

```blade
<button>Gerar briefing</button>          {{-- PROIBIDO --}}
<button>Generate briefing</button>       {{-- correto --}}
```

Motivo: toda superfície voltada ao cliente final é em inglês. O painel administrativo (`/admin/*`, uso interno) é a única exceção. Ver `SAD.md`, Seção 10.1.1.

---

### 17.

Utilizar tokens de cor do Design System anterior.

```html
<div class="bg-[#111111] text-[#FF5500]">   <!-- PROIBIDO -->
```

Motivo: a paleta antiga foi integralmente substituída. Usar exclusivamente os tokens da Seção 10.4 do `SAD.md` — `primary`, `secondary`, `accent`, `app-bg`, `app-bg-dark`, `app-card-dark`.

---

### 18.

Somar as três carteiras de crédito em uma única coluna.

```php
$user->credits_balance = $orgPlan + $orgPurchased + $personal;   // PROIBIDO
```

Motivo: `organizations.plan_credits_balance` expira na virada do ciclo, `organizations.purchased_credits_balance` não expira e `users.purchased_credits_balance` é pessoal e portátil entre organizações. Consolidá-las destrói a informação que decide o que pode ser cobrado, de quem, e com qual autorização. Ver `SAD.md`, Seção 12.2.

---

### 19.

Ícones com `stroke-width` diferente de `0.3` ou tamanho fora de `w-5 h-5` / `w-4 h-4`.

```html
<svg class="w-6 h-6" stroke-width="1.5">    <!-- PROIBIDO -->
<svg class="w-5 h-5" stroke="currentColor" stroke-width="0.3">   <!-- correto -->
```

Motivo: o traço fino é parte da identidade visual do produto; os padrões de Heroicons e Lucide não se aplicam.

---

### 20.

Inicializar a aplicação com `<html class="dark">` fixo.

Motivo: o Light mode é o tema padrão. O Dark mode é variante aplicada pelo prefixo `dark:` e ativada pelo `theme-toggle-btn`.

---

### 21.

Ampliar o escopo de uma consulta através de `parent_project_id`.

```php
Execution::whereIn('project_id', $brand->children->pluck('id'));   // PROIBIDO
```

Motivo: a hierarquia de marcas organiza a apresentação, nunca o isolamento de dados. Cada Brand — inclusive sub-brand — permanece escopada pelo seu próprio `project_id`, dentro da organização ativa.

---

### 22.

Inverter a ordem de débito das carteiras de crédito.

```php
$user->purchased_credits_balance -= $cost;      // PROIBIDO — pessoal antes da org
```

Motivo: a ordem é plano da org → comprado pela org → pessoal do usuário. Gastar crédito pessoal antes do crédito da organização cobra do bolso errado. Ver `SAD.md`, Seção 12.3.

---

### 23.

Debitar a carteira pessoal sem `confirm_personal_credits` explícito na requisição.

```php
// PROIBIDO — débito pessoal sem confirmação
$this->deductFromPersonalWallet($user, $remaining);

// correto — sem a flag, 409 com breakdown, nada é debitado
if ($touchesPersonalWallet && ! $request->boolean('confirm_personal_credits')) {
    throw new PersonalCreditsConfirmationRequiredException($breakdown);   // 409
}
```

Motivo: a carteira pessoal nunca é debitada sem autorização explícita do usuário. O bloqueio é do backend: uma chamada direta à API, sem passar pelo modal, também é rejeitada. Ver `SAD.md`, Seção 12.19.

---

### 24.

Debitar da carteira pessoal exatamente o valor informado no breakdown do 409.

```php
$personal = $request->input('breakdown.personal_credits');   // PROIBIDO
$user->purchased_credits_balance -= $personal;
```

Motivo: a confirmação autoriza **usar a carteira pessoal nesta ação**, não um valor. Outro membro da organização pode ter gasto — ou um Admin pode ter comprado — créditos entre o aviso e a confirmação. O valor real é recalculado dentro da transação de débito. Ver `SAD.md`, Seção 12.19.

---

### 25.

Verificar feature gating antes de verificar se a organização tem plano.

```php
// PROIBIDO — gating antes do plano nulo
if ($this->planAllows($org->plan, $pipeline)) { ... }
if (is_null($org->plan)) { ... }
```

Motivo: **falha de segurança**, não questão de estilo. A convenção "linha ausente em `plan_limits` significa habilitado" faria o gating liberar *todos* os pipelines para uma organização sem assinatura. A verificação de `plan === null` vem sempre primeiro. Ver `SAD.md`, Seção 12.18.

---

### 26.

Colapsar os três estados de bloqueio em uma mensagem genérica.

```blade
{{-- PROIBIDO --}}
<p>You cannot run this action.</p>
```

Motivo: sem plano, feature fora do plano e saldo insuficiente levam o usuário a três telas diferentes. Cada um tem exceção, código HTTP, mensagem e CTA próprios: `NoActivePlanException` (402, "Subscribe to a plan"), `FeatureNotAvailableException` (403, "Upgrade your plan"), `InsufficientCreditsException` (402, "Buy credits"). Ver `SAD.md`, Seção 12.12.

---

### 27.

Usar `users.is_admin` como papel de organização, ou o contrário.

```php
if ($user->is_admin) { $org->subscribeToPlan(...); }        // PROIBIDO
if ($org->isAdmin($user)) { return view('admin.users'); }   // PROIBIDO
```

Motivo: são dois eixos independentes. `users.is_admin` é staff da plataforma Arkion; `organization_user.role` é papel dentro de uma organização. Um não implica o outro em nenhuma direção. Ver `SAD.md`, Seção 20.7.

---

### 28.

Deixar uma organização sem nenhum Admin.

```php
$org->members()->detach($lastAdmin->id);                        // PROIBIDO
$org->members()->updateExistingPivot($lastAdmin->id, [
    'role' => 'member',
]);                                                             // PROIBIDO
```

Motivo: uma organização sem Admin é estado irrecuperável — ninguém mais pode assinar plano, comprar créditos para a org, gerenciar membros ou excluí-la. O último Admin não pode ser removido nem rebaixado, e a verificação ocorre dentro da transação, sob lock. Ver `SAD.md`, Seção 20.4.

---

### 29.

Criar uma Marca sem organização, ou tratar `projects.user_id` como propriedade.

```php
Project::create(['user_id' => auth()->id(), 'name' => $name]);   // PROIBIDO
$this->authorize($project->user_id === auth()->id());            // PROIBIDO
```

Motivo: nenhuma Marca existe fora de uma Organização. `projects.organization_id` é NOT NULL e é a coluna de propriedade; `projects.user_id` é apenas auditoria de quem criou. Ver `SAD.md`, Seção 20.2.

---

### 30.

Aceitar uma Brand ativa que não pertence à organização ativa.

```php
session(['active_project_id' => $request->project_id]);   // PROIBIDO sem validar a org
```

Motivo: o contexto de sessão tem duas variáveis (`active_organization_id` e `active_project_id`) e a segunda precisa pertencer à primeira. A validação ocorre em todo acesso, não só na troca, e um par inválido é rejeitado — nunca corrigido silenciosamente. Ver `SAD.md`, Seção 20.5.

---

# 4. Decisões Arquiteturais Fixadas

| Decisão | Alternativa Rejeitada | Nunca Reverter Sem ADR |
|----------|-----------------------|-------------------------|
| Monólito Laravel 11 | Microservices | Sim |
| Livewire + Blade | SPA Vue/React | Sim |
| PostgreSQL | MySQL | Sim |
| JSONB | JSON | Sim |
| Redis para filas e cache | Database Queue | Sim |
| Horizon como gerenciador de filas | Queue Worker puro | Sim |
| Reverb para WebSockets | Pusher | Sim |
| Firebase Authentication | Laravel Breeze / Sanctum | Sim |
| Stripe Cashier | Implementação própria | Sim |
| n8n em Droplet separado | n8n na mesma instância do Laravel | Sim |
| Comunicação Laravel ↔ n8n via Webhooks | Banco compartilhado | Sim |
| Apenas Laravel acessa PostgreSQL | n8n acessando banco diretamente | Sim |
| Services concentram regras de negócio | Lógica em Controllers | Sim |
| Form Requests para validação | Validação inline | Sim |
| Policies para autorização | Verificações espalhadas | Sim |
| Queue para toda execução de IA | Processamento síncrono | Sim |
| Custos de créditos em `plan_limits` | Valores hardcoded | Sim |
| Organização como entidade de faturamento | Usuário como entidade de faturamento | Sim |
| Marca sempre pertencente a uma Organização | Marca pertencente ao usuário | Sim |
| Dois papéis por organização: Admin e Member | RBAC granular por organização | Sim |
| Escopo obrigatório por `organization_id` + `project_id` | Escopo por `user_id` / dados globais | Sim |
| Modo Light como padrão, Dark como variante `dark:` | Dark First | Sim |
| Newsreader (títulos) + Inter (corpo e UI) | Tipografia única | Sim |
| Paleta `#d2691e` / `#9e6136` / `#6b4e3a` | Paleta `#111111` / `#FF5500` | Sim |
| Interface do cliente final em inglês | Interface em português | Sim |
| Três carteiras de crédito separadas | Saldo único consolidado / dois saldos no usuário | Sim |
| Carteira pessoal só com `confirm_personal_credits` | Débito pessoal silencioso | Sim |
| `organizations.plan` NULLABLE como estado inicial | Plano free permanente / trial automático | Sim |
| Verificação de plano nulo antes do feature gating | Gating primeiro | Sim |
| Feature gating de pipelines por plano | Diferenciação apenas por volume de créditos | Sim |
| Sub-brands compartilham o saldo da organização | Saldo por Brand / saldo do usuário | Sim |
| `users.is_admin` boolean em `users` (staff da plataforma) | Tabela de papéis / RBAC | Sim |
| Controllers administrativos isolados em `Admin` | Reuso de Controllers Web ou Api | Sim |
| Auditoria obrigatória em `admin_action_logs` | Ação administrativa sem rastro | Sim |

---

# 5. Regras Invioláveis do Painel Administrativo

Aplicáveis a partir do Mês 4. Ver `SAD.md`, Seção 14 — Painel Administrativo.

1. `is_admin` é um boolean na tabela `users`, não uma tabela separada. Reavaliar apenas via ADR se o sistema crescer para múltiplos funcionários com hierarquia de permissões.
2. Rotas `/admin/*` nunca compartilham Controllers com rotas de Web ou de Api. Sempre em `App\Http\Controllers\Admin`.
3. Toda ação administrativa que altera dados de um usuário (créditos, plano, status de conta) deve ser registrada em `admin_action_logs` antes de retornar sucesso ao admin.

## Checklist de Pull Request — Painel Administrativo

- [ ] A rota está sob o middleware `EnsureIsAdmin`.
- [ ] O Controller reside em `App\Http\Controllers\Admin`.
- [ ] Nenhum Controller de `Web` ou `Api` foi reutilizado.
- [ ] A ação de escrita chama `CreditService` ou `PlanService`; nenhuma regra de negócio nova foi criada.
- [ ] A ação foi registrada em `admin_action_logs` antes do retorno de sucesso.
- [ ] O componente Livewire apenas apresenta dados e dispara ações.
- [ ] O acesso de um usuário sem `is_admin` foi testado e retorna 403.

---

# Regra Final

Sempre que houver dúvida durante a geração de código:

1. Consulte este documento.
2. Consulte o AGENTS.md.
3. Consulte o SAD.
4. Se ainda houver ambiguidade, **não invente arquitetura**. Mantenha o marcador **[A DEFINIR]** e aguarde uma ADR.
