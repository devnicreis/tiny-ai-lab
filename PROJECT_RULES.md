# PROJECT_RULES.md

> Checklist operacional de arquitetura do Tiny AI Lab.
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
| Modo Dark como padrão | Light como padrão | Sim |
| Fonte oficial Inter | Troca de tipografia sem ADR | Sim |

---
## Regra Final

Sempre que houver dúvida durante a geração de código:

1. Consulte este documento.
2. Consulte o AGENTS.md.
3. Consulte o SAD.
4. Se ainda houver ambiguidade, **não invente arquitetura**. Mantenha o marcador **[A DEFINIR]** e aguarde uma ADR.
