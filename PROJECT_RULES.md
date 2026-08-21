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
- [ ] Queries limitadas ao `project_id`.

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

- [ ] `plan_credits_balance` e `purchased_credits_balance` permanecem colunas separadas.
- [ ] O débito consome o saldo do plano antes do saldo comprado.
- [ ] A renovação mensal **atribui** `plan_credits_balance` e nunca o incrementa.
- [ ] `purchased_credits_balance` não é zerado por nenhuma rotina de ciclo.
- [ ] Pipeline fora do plano retorna 403; saldo insuficiente retorna 402.

## Hierarquia de Marcas

- [ ] `parent_project_id` aponta para um Project do mesmo `user_id`.
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

Executar queries sem filtro por `project_id`.

Motivo: viola isolamento entre Brands.

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

Somar os dois saldos de crédito em uma única coluna.

```php
$user->credits_balance = $plan + $purchased;   // PROIBIDO
```

Motivo: `plan_credits_balance` expira na virada do ciclo e `purchased_credits_balance` nunca expira. Consolidá-los destrói a informação que decide o que pode ser cobrado do usuário.

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

Motivo: a hierarquia de marcas organiza a apresentação, nunca o isolamento de dados. Cada Brand — inclusive sub-brand — permanece escopada pelo seu próprio `project_id`.

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
| Escopo obrigatório por `project_id` | Dados globais | Sim |
| Modo Light como padrão, Dark como variante `dark:` | Dark First | Sim |
| Newsreader (títulos) + Inter (corpo e UI) | Tipografia única | Sim |
| Paleta `#d2691e` / `#9e6136` / `#6b4e3a` | Paleta `#111111` / `#FF5500` | Sim |
| Interface do cliente final em inglês | Interface em português | Sim |
| Dois saldos de crédito separados | Saldo único consolidado | Sim |
| Feature gating de pipelines por plano | Diferenciação apenas por volume de créditos | Sim |
| Sub-brands compartilham o saldo do usuário | Saldo por Brand | Sim |
| `is_admin` boolean em `users` | Tabela de papéis / RBAC | Sim |
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
