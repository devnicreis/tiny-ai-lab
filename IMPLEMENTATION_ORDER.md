# IMPLEMENTATION_ORDER.md

> Ordem oficial de implementação do Arkion.
>
> Este documento responde à pergunta:
>
> **"Codex, o que devo criar agora?"**
>
> Nenhuma etapa deve ser antecipada. Cada fase pressupõe que a anterior esteja concluída, validada e com testes passando.

---

# FASE 0 — PRÉ-CÓDIGO (NÃO UTILIZAR O CODEX)

Esta fase consiste exclusivamente em atividades de infraestrutura e configuração manual.

Nenhuma geração automática de código deve ocorrer antes da conclusão desta fase.

---

## 0.1 Criar a infraestrutura na DigitalOcean

Provisionar exatamente **dois Droplets Ubuntu LTS**.

### Droplet 01 — Aplicação

Responsável por executar:

- Laravel 11
- PHP 8.3
- Nginx
- PostgreSQL
- Redis
- Horizon
- Reverb
- Supervisor

Este servidor representa toda a camada de domínio da aplicação.

---

### Droplet 02 — IA

Responsável exclusivamente por executar:

- n8n Self Hosted

Nenhum outro serviço da aplicação deve residir neste servidor.

O n8n não possui acesso direto ao banco de dados.

---

## 0.2 Configurar VPC privada

Criar uma VPC entre os dois Droplets.

Toda comunicação Laravel → n8n deve ocorrer pela rede privada.

Nunca utilizar IP público para Webhooks internos.

---

## 0.3 Configurar DNS

Criar os registros necessários para:

- aplicação principal
- webhook do n8n
- ambiente de produção

Configurar HTTPS utilizando certificados válidos.

---

## 0.4 Instalar Laravel

No Droplet da aplicação:

- instalar PHP 8.3
- instalar Composer
- instalar Node.js
- criar projeto Laravel 11
- instalar dependências oficiais
- executar build inicial

Não instalar bibliotecas adicionais antes da conclusão da Fase 1.

---

## 0.5 Configurar repositório Git

Criar o repositório oficial.

Configurar:

- branch main
- branch develop

Adicionar:

- .gitignore
- LICENSE
- README
- AGENTS.md
- PROJECT_RULES.md
- IMPLEMENTATION_ORDER.md

---

## 0.6 Configurar arquivo .env

Copiar:

```
.env.example
```

para

```
.env
```

Preencher todas as variáveis antes da primeira execução da aplicação.

Não utilizar valores temporários em produção.

---

## 0.7 Criar banco PostgreSQL

Criar:

- database
- usuário
- senha

Configurar permissões mínimas necessárias.

Validar conexão via Artisan.

---

## 0.8 Configurar Redis

Criar databases separados conforme arquitetura.

Obrigatório:

- Queue DB
- Cache DB

Nunca compartilhar filas e cache na mesma database Redis.

---

## 0.9 Criar projeto Firebase

No Firebase Console:

- criar projeto
- habilitar Authentication
- habilitar Login por Google
- habilitar Email/Senha (caso aprovado)
- obter Project ID

---

## 0.10 Baixar Service Account

Gerar a Service Account JSON.

Salvar fora do repositório Git.

Nunca versionar este arquivo.

Configurar o caminho no `.env`.

---

## 0.11 Configurar Stripe

Criar conta.

Obter:

- Publishable Key
- Secret Key
- Webhook Secret

Configurar produtos e planos definidos no projeto.

---

## 0.12 Configurar n8n

No segundo Droplet:

- instalar n8n
- configurar HTTPS
- configurar autenticação
- configurar credenciais OpenAI
- configurar credenciais Google
- configurar variável compartilhada do webhook secret

Nenhum workflow deve ser criado nesta etapa.

---

## 0.13 Configurar Supervisor

Criar processos para:

- Horizon
- Queue Workers
- Reverb

Validar reinício automático.

---

## 0.14 Instalar dependências Laravel

Instalar apenas dependências aprovadas no SAD.

Entre elas:

- Livewire
- Cashier
- Firebase SDK
- Horizon
- Reverb

Não instalar pacotes alternativos sem ADR.

---

## 0.15 Executar instalação inicial

Executar:

- composer install
- npm install
- npm run build

Sem gerar migrations.

Sem gerar Models.

Sem criar Controllers.

---

## 0.16 Validar ambiente

Confirmar:

- Laravel inicia corretamente
- PostgreSQL conecta
- Redis conecta
- Horizon inicia
- Reverb inicia
- Firebase configurado
- Stripe configurado
- n8n acessível via HTTPS privado

---

# Critério de Conclusão da Fase 0

A Fase 0 somente é considerada concluída quando:

- [ ] Dois Droplets operacionais
- [ ] VPC configurada
- [ ] HTTPS funcionando
- [ ] Laravel iniciado
- [ ] PostgreSQL conectado
- [ ] Redis conectado
- [ ] Horizon operacional
- [ ] Reverb operacional
- [ ] Firebase configurado
- [ ] Stripe configurado
- [ ] n8n operacional
- [ ] Repositório Git inicializado
- [ ] Arquivos AGENTS.md, PROJECT_RULES.md e IMPLEMENTATION_ORDER.md presentes na raiz
- [ ] Nenhuma linha de código de domínio foi escrita

---

## Próxima Etapa

Após todos os itens acima concluídos, iniciar **FASE 1 — Banco de Dados**, respeitando rigorosamente a ordem oficial das migrations.


# FASE 1 — BANCO DE DADOS

Objetivo desta fase:

Construir toda a fundação persistente da aplicação antes da criação de qualquer regra de negócio.

Nesta fase somente podem ser criados:

- Migrations
- Índices
- Foreign Keys
- Constraints
- Enumerações necessárias
- Seeders estruturais

Não criar Models antes da conclusão de todas as migrations.

Não criar Controllers.

Não criar Services.

Não criar Livewire.

---

# Ordem Oficial das Migrations

A sequência abaixo é obrigatória.

Nunca alterar a ordem sem uma ADR aprovada.

---

## 1. create_users_table

### Dependências

Nenhuma.

### Objetivo

Persistir os usuários autenticados via Firebase.

### Colunas obrigatórias

- id
- firebase_uid (unique)
- name
- email (unique)
- avatar_url (nullable)
- plan
- monthly_credit_limit
- monthly_credit_balance
- email_verified_at
- remember_token
- timestamps

### Índices obrigatórios

- unique(firebase_uid)
- unique(email)

### Foreign Keys

Nenhuma.

### Observações

- O Firebase é a autoridade de identidade.
- Laravel apenas sincroniza os dados necessários.
- Nunca armazenar tokens do Firebase.

---

## 2. create_projects_table

### Dependências

- users

### Objetivo

Representar cada Brand (Projeto) do Arkion.

Toda operação do sistema pertence exatamente a um Project.

### Colunas obrigatórias

- id
- user_id
- name
- slug
- description (nullable)
- logo_url (nullable)
- settings (jsonb)
- is_active
- timestamps

### Índices obrigatórios

- index(user_id)
- unique(slug)

### Foreign Keys

user_id

- onDelete(cascade)

### Observações

Toda consulta de domínio deve utilizar project_id como escopo.

---

## 3. create_plan_limits_table

### Dependências

Nenhuma.

### Objetivo

Centralizar todos os custos de créditos e limites dos planos.

Nenhum valor de crédito poderá existir no código.

### Colunas obrigatórias

- id
- action
- plan
- credit_cost
- monthly_limit (nullable)
- metadata (jsonb)
- timestamps

### Índices obrigatórios

- unique(action, plan)

### Foreign Keys

Nenhuma.

### Observações

Esta tabela deverá ser carregada em cache.

---

## 4. create_executions_table

### Dependências

- projects

### Objetivo

Registrar todas as execuções realizadas pela plataforma.

Representa o principal agregado do domínio.

### Colunas obrigatórias

- id
- project_id
- type
- status
- prompt
- input (jsonb)
- result (jsonb nullable)
- credits_consumed
- started_at (nullable)
- finished_at (nullable)
- timestamps

### Índices obrigatórios

- index(project_id)
- index(status)
- index(type)
- index(created_at)

### Foreign Keys

project_id

- onDelete(cascade)

### Observações

- result nunca deve ser carregado em listagens.
- status inicial obrigatório: queued.

---

## 5. create_video_references_table

### Dependências

Nenhuma.

### Objetivo

Persistir referências de vídeos utilizadas pelos pipelines de IA.

### Colunas obrigatórias

- id
- youtube_id
- title
- channel_name
- metadata (jsonb)
- timestamps

### Índices obrigatórios

- unique(youtube_id)

### Foreign Keys

Nenhuma.

### Observações

Todos os metadados variáveis devem permanecer em JSONB.

---

## 6. add_cashier_columns_to_users_table

### Dependências

- users

### Objetivo

Adicionar suporte ao Laravel Cashier.

### Colunas obrigatórias

As colunas oficiais exigidas pelo Cashier.

### Índices obrigatórios

Conforme documentação oficial do Laravel Cashier.

### Foreign Keys

Nenhuma.

### Observações

Nunca alterar os nomes esperados pelo Cashier.

---

## 7. create_subscriptions_table

### Dependências

- users

### Objetivo

Persistir informações complementares das assinaturas.

### Colunas obrigatórias

- id
- user_id
- stripe_subscription_id
- plan
- status
- renews_at
- ends_at (nullable)
- timestamps

### Índices obrigatórios

- unique(stripe_subscription_id)
- index(user_id)

### Foreign Keys

user_id

- onDelete(cascade)

### Observações

O Stripe permanece como fonte oficial de cobrança.

---

## 8. create_notifications_table

### Dependências

Nenhuma.

### Objetivo

Persistir notificações exibidas ao usuário.

### Colunas obrigatórias

- id
- user_id
- title
- body
- type
- is_read
- metadata (jsonb)
- read_at (nullable)
- timestamps

### Índices obrigatórios

- index(user_id)
- index(is_read)
- index(created_at)

### Foreign Keys

user_id

- onDelete(cascade)

### Observações

As notificações devem suportar futura integração com Reverb.

---

# Ordem de Execução

Executar exatamente nesta sequência:

1. create_users_table
2. create_projects_table
3. create_plan_limits_table
4. create_executions_table
5. create_video_references_table
6. add_cashier_columns_to_users_table
7. create_subscriptions_table
8. create_notifications_table

Nunca inverter esta ordem.

---

# Regras Gerais das Migrations

- Utilizar sempre PostgreSQL.
- Utilizar `jsonb()` para todos os documentos JSON.
- Nunca utilizar `json()`.
- Declarar índices explicitamente.
- Declarar Foreign Keys explicitamente.
- Declarar política de remoção (`cascade`, `restrict` ou `nullOnDelete`) em todas as FKs.
- Utilizar `timestamps()` padrão do Laravel.
- Utilizar `softDeletes()` apenas quando previsto pelo domínio.
- Nunca inserir dados nas migrations.
- Dados iniciais pertencem exclusivamente aos Seeders.
- Toda migration deve possuir método `down()` funcional.
- Nunca utilizar SQL bruto quando houver equivalente no Schema Builder.

---

# Critério de Conclusão da Fase 1

A Fase 1 somente estará concluída quando:

- [ ] Todas as oito migrations forem criadas.
- [ ] Todas executarem sem erro.
- [ ] Todas forem reversíveis.
- [ ] Todas utilizarem JSONB quando aplicável.
- [ ] Todas as Foreign Keys estiverem funcionais.
- [ ] Todos os índices obrigatórios existirem.
- [ ] `php artisan migrate:fresh` executar sem falhas.
- [ ] O banco estiver pronto para a criação dos Models.

---

## Próxima Etapa

Com todas as migrations validadas, iniciar a **FASE 2 — Models**, criando cada Model na ordem oficial e somente após a estrutura do banco estar completamente estabilizada.


# FASE 2 — MODELS

Objetivo desta fase:

Criar todos os Models oficiais do domínio após a conclusão integral das migrations.

Nesta fase somente podem ser criados:

- Models
- Relacionamentos
- Casts
- Fillable
- Scopes
- Accessors simples
- Mutators simples

Não criar Controllers.

Não criar Services.

Não criar Jobs.

Não criar Livewire.

---

# Ordem Oficial dos Models

A ordem abaixo deve ser respeitada.

---

## 1. User

### Depende de

- create_users_table
- add_cashier_columns_to_users_table

### Responsabilidade

Representar o usuário autenticado via Firebase.

### Traits obrigatórias

- HasFactory
- Notifiable
- Billable (Laravel Cashier)

### Fillable obrigatório

- firebase_uid
- name
- email
- avatar_url
- plan
- monthly_credit_limit
- monthly_credit_balance

### Casts obrigatórios

- email_verified_at => datetime

### Relacionamentos

projects()

subscriptions()

notifications()

### Scopes mínimos

Nenhum obrigatório.

### Observações

Nunca armazenar senha.

Nunca armazenar token Firebase.

Nunca implementar regras de crédito neste Model.

---

## 2. Project

### Depende de

create_projects_table

### Responsabilidade

Representar uma Brand da plataforma.

Todo o domínio é escopado por Project.

### Fillable obrigatório

- user_id
- name
- slug
- description
- logo_url
- settings
- is_active

### Casts obrigatórios

- settings => array
- is_active => boolean

### Relacionamentos

user()

executions()

### Scopes mínimos

scopeActive()

scopeOwnedBy()

### Observações

Todo acesso ao domínio deve passar pelo Project.

Nunca criar consultas globais ignorando project_id.

---

## 3. PlanLimit

### Depende de

create_plan_limits_table

### Responsabilidade

Representar custos e limites dos planos.

### Fillable obrigatório

- action
- plan
- credit_cost
- monthly_limit
- metadata

### Casts obrigatórios

- metadata => array

### Relacionamentos

Nenhum.

### Scopes mínimos

scopeByPlan()

scopeByAction()

### Observações

Esta tabela será cacheada.

Nunca hardcode valores de créditos.

---

## 4. Execution

### Depende de

create_executions_table

### Responsabilidade

Representar uma execução de IA.

É o principal agregado do domínio.

### Fillable obrigatório

- project_id
- type
- status
- prompt
- input
- result
- credits_consumed
- started_at
- finished_at

### Casts obrigatórios

- input => array
- result => array
- started_at => datetime
- finished_at => datetime

### Relacionamentos

project()

### Scopes mínimos

scopeByProject()

scopeQueued()

scopeProcessing()

scopeCompleted()

scopeFailed()

### Observações

Nunca selecionar result em consultas de listagem.

Nunca alterar status diretamente pelo frontend.

---

## 5. VideoReference

### Depende de

create_video_references_table

### Responsabilidade

Persistir referências utilizadas pelos pipelines de IA.

### Fillable obrigatório

- youtube_id
- title
- channel_name
- metadata

### Casts obrigatórios

- metadata => array

### Relacionamentos

Nenhum.

### Scopes mínimos

scopeYoutube()

### Observações

Todo conteúdo variável deve permanecer em JSONB.

---

## 6. Subscription

### Depende de

create_subscriptions_table

### Responsabilidade

Representar informações complementares das assinaturas.

### Fillable obrigatório

- user_id
- stripe_subscription_id
- plan
- status
- renews_at
- ends_at

### Casts obrigatórios

- renews_at => datetime
- ends_at => datetime

### Relacionamentos

user()

### Scopes mínimos

scopeActive()

scopeExpired()

### Observações

A autoridade da cobrança continua sendo o Stripe.

---

## 7. Notification

### Depende de

create_notifications_table

### Responsabilidade

Persistir notificações da aplicação.

### Fillable obrigatório

- user_id
- title
- body
- type
- is_read
- metadata
- read_at

### Casts obrigatórios

- metadata => array
- is_read => boolean
- read_at => datetime

### Relacionamentos

user()

### Scopes mínimos

scopeUnread()

scopeRead()

### Observações

Preparado para integração futura com Laravel Reverb.

---

# Regras Obrigatórias para Todos os Models

- Declarar `$fillable` explicitamente.
- Nunca utilizar `$guarded = []`.
- Declarar `$casts` sempre que necessário.
- Declarar relacionamentos utilizando Eloquent.
- Declarar Scopes reutilizáveis para consultas frequentes.
- Utilizar nomes consistentes com o domínio.
- Não implementar regras de negócio complexas.
- Não consumir APIs externas.
- Não acessar Redis.
- Não acessar n8n.
- Não executar validações.
- Não disparar Jobs.
- Não utilizar consultas SQL brutas quando Eloquent for suficiente.

---

# Estrutura Esperada

Cada Model deverá conter, nesta ordem:

1. Namespace
2. Imports
3. Traits
4. Fillable
5. Casts
6. Relacionamentos
7. Scopes
8. Accessors (quando necessários)
9. Mutators (quando necessários)

Manter esta organização em todos os Models.

---

# Critério de Conclusão da Fase 2

A Fase 2 somente estará concluída quando:

- [ ] Todos os sete Models existirem.
- [ ] Todos declararem `$fillable`.
- [ ] Nenhum utilizar `$guarded`.
- [ ] Todos declararem os `$casts` necessários.
- [ ] Todos os relacionamentos funcionarem.
- [ ] Todos os scopes mínimos estiverem implementados.
- [ ] Nenhum Model possuir lógica de negócio complexa.
- [ ] Nenhum Model acessar serviços externos.
- [ ] Nenhum Model acessar Redis.
- [ ] Nenhum Model acessar n8n.
- [ ] Todos os testes de relacionamento passarem.

---

## Próxima Etapa

Após a validação completa dos Models, iniciar a **FASE 3 — Autenticação**, implementando a integração com Firebase Authentication, middleware de autenticação, rotas de login e testes de autenticação.


# FASE 3 — AUTENTICAÇÃO

Objetivo desta fase:

Implementar toda a infraestrutura de autenticação baseada em Firebase Authentication, garantindo que o Laravel seja a autoridade de autorização enquanto o Firebase permanece como autoridade de identidade.

Nesta fase podem ser criados:

- Services
- Middleware
- Controllers de autenticação
- Rotas de autenticação
- Testes

Não criar funcionalidades de domínio.

Não criar CRUDs.

Não criar Livewire.

---

# Ordem Oficial de Implementação

A sequência abaixo deve ser seguida rigorosamente.

---

## 3.1 FirebaseService

### Arquivo

```
app/Services/Firebase/FirebaseService.php
```

### Responsabilidade

Centralizar toda comunicação com o Firebase Admin SDK.

Nenhuma outra classe deve acessar diretamente o SDK.

### Métodos mínimos

verifyIdToken()

getUser()

syncUser()

### Regras obrigatórias

- Registrar como Singleton.
- Nunca armazenar tokens.
- Nunca utilizar cache para tokens.
- Sempre lançar Exceptions específicas em caso de falha.
- Nunca retornar arrays sem tipagem definida.
- Centralizar toda configuração do Firebase.

---

## 3.2 Registrar o Singleton

Registrar o FirebaseService no Service Provider apropriado.

Objetivos:

- apenas uma instância por request;
- facilitar injeção de dependência;
- centralizar configuração do SDK.

---

## 3.3 VerifyFirebaseToken Middleware

### Arquivo

```
app/Http/Middleware/VerifyFirebaseToken.php
```

### Responsabilidade

Validar o Firebase ID Token enviado pelo cliente.

### Fluxo esperado

1. Ler Authorization Bearer Token.
2. Validar assinatura.
3. Validar expiração.
4. Validar revogação.
5. Obter Firebase UID.
6. Sincronizar usuário local.
7. Autenticar usuário no Laravel.
8. Prosseguir para a próxima camada.

### Regras obrigatórias

- Nunca confiar em dados enviados pelo frontend.
- Nunca aceitar tokens expirados.
- Nunca aceitar tokens revogados.
- Nunca criar usuário manualmente fora do FirebaseService.
- Todas as falhas devem retornar HTTP 401.

---

## 3.4 Registrar Middleware

Registrar o middleware no:

```
bootstrap/app.php
```

Criar alias oficial:

```
firebase.auth
```

Todas as rotas protegidas utilizarão este alias.

---

## 3.5 AuthController

### Arquivo

```
app/Http/Controllers/Api/AuthController.php
```

Este Controller deve possuir apenas dois endpoints.

---

### firebase()

Responsabilidade

Realizar login utilizando Firebase.

Fluxo esperado

1. Receber ID Token.
2. Delegar validação ao FirebaseService.
3. Sincronizar usuário.
4. Retornar usuário autenticado.
5. Retornar plano.
6. Retornar créditos disponíveis.
7. Retornar Brand ativa (quando existir).

Nunca implementar regras de negócio neste método.

---

### me()

Responsabilidade

Retornar o usuário autenticado.

Dados mínimos

- usuário
- plano
- assinatura
- créditos
- Brand ativa

Nunca consultar Firebase novamente.

Utilizar apenas o usuário autenticado pelo middleware.

---

## 3.6 Rotas

Criar em:

```
routes/api.php
```

Rotas obrigatórias

POST

```
/api/auth/firebase
```

GET

```
/api/auth/me
```

Proteção

- firebase() → pública
- me() → firebase.auth

---

## 3.7 Exceptions

Criar Exceptions específicas para autenticação.

Exemplos

- InvalidFirebaseTokenException
- RevokedFirebaseTokenException
- ExpiredFirebaseTokenException

Nunca utilizar Exception genérica.

---

## 3.8 Responses

Padronizar Responses JSON.

Formato mínimo

```
success
message
data
```

Erros

```
success
message
errors
```

Nunca retornar mensagens diferentes para erros equivalentes.

---

## 3.9 Testes

Criar

```
tests/Feature/AuthTest.php
```

Casos mínimos

### Login válido

- token válido
- usuário sincronizado
- HTTP 200

---

### Token inválido

- HTTP 401

---

### Token expirado

- HTTP 401

---

### Token revogado

- HTTP 401

---

### Endpoint me()

Usuário autenticado.

Retorna:

- usuário
- plano
- créditos

---

### Endpoint me()

Sem autenticação.

Retorna:

HTTP 401.

---

## Regras Obrigatórias da Fase 3

- Firebase é autoridade de identidade.
- Laravel é autoridade de autorização.
- Nunca armazenar tokens.
- Nunca utilizar sessões PHP.
- Nunca utilizar login tradicional.
- Nunca utilizar senha local.
- Nunca utilizar Sanctum.
- Nunca utilizar Breeze.
- Nunca utilizar Jetstream.
- Nunca acessar diretamente o SDK fora do FirebaseService.
- Toda autenticação deve passar pelo middleware.
- Todo usuário autenticado deve existir na tabela users.
- Toda sincronização deve ocorrer automaticamente.
- Toda rota protegida deve utilizar `firebase.auth`.

---

## Fluxo Oficial de Autenticação

```text
Frontend

      │

      ▼

Firebase Authentication

      │

ID Token

      │

      ▼

POST /api/auth/firebase

      │

      ▼

FirebaseService

      │

Validação

      │

      ▼

Sync User

      │

      ▼

Laravel Auth

      │

      ▼

Resposta JSON
```

---

# Critério de Conclusão da Fase 3

A Fase 3 somente estará concluída quando:

- [ ] FirebaseService implementado.
- [ ] Singleton registrado.
- [ ] Middleware criado.
- [ ] Middleware registrado.
- [ ] AuthController criado.
- [ ] Endpoint `/api/auth/firebase` funcional.
- [ ] Endpoint `/api/auth/me` funcional.
- [ ] Usuários sincronizados automaticamente.
- [ ] Tokens inválidos rejeitados.
- [ ] Tokens expirados rejeitados.
- [ ] Tokens revogados rejeitados.
- [ ] AuthTest.php criado.
- [ ] Todos os testes passando.

---

## Próxima Etapa

Após a autenticação estar completamente funcional, iniciar a **FASE 4 — CRUD de Projects (Brands)**, implementando a primeira funcionalidade de domínio da aplicação com Policies, Form Requests, Controller, rotas protegidas e testes de integração.


# FASE 4 — CRUD DE PROJECTS (BRANDS)

Objetivo desta fase:

Implementar o primeiro módulo funcional do domínio: gerenciamento de Brands (Projects).

A partir desta fase, todas as funcionalidades passam a existir dentro do contexto de um Project.

Nenhum outro recurso do domínio deve ser iniciado antes da conclusão desta fase.

---

# Ordem Oficial de Implementação

Seguir rigorosamente a sequência abaixo.

---

## 4.1 ProjectPolicy

### Arquivo

```
app/Policies/ProjectPolicy.php
```

### Responsabilidade

Centralizar todas as regras de autorização relacionadas aos Projects.

Nenhuma autorização deve ser implementada no Controller.

### Métodos mínimos

- viewAny()
- view()
- create()
- update()
- delete()

### Regras obrigatórias

- O usuário pode visualizar apenas seus próprios Projects.
- O usuário pode editar apenas Projects de sua propriedade.
- O usuário pode excluir apenas Projects de sua propriedade.
- Nunca utilizar verificações inline no Controller.

---

## 4.2 StoreProjectRequest

### Arquivo

```
app/Http/Requests/Project/StoreProjectRequest.php
```

### Responsabilidade

Centralizar toda validação da criação de Projects.

### Campos mínimos

- name
- description
- logo_url (opcional)

### Regras obrigatórias

- name obrigatório.
- name com tamanho máximo definido.
- description opcional.
- logo_url deve ser URL válida quando informada.

Nunca validar dentro do Controller.

---

## 4.3 UpdateProjectRequest

### Arquivo

```
app/Http/Requests/Project/UpdateProjectRequest.php
```

### Responsabilidade

Validar atualizações de Projects.

Reutilizar regras quando possível.

---

## 4.4 ProjectController

### Arquivo

```
app/Http/Controllers/Api/ProjectController.php
```

Responsabilidade exclusiva:

Orquestrar Requests, Services e Responses.

Nunca implementar regras de domínio.

---

### index()

Responsabilidade

Listar Projects pertencentes ao usuário autenticado.

Regras

- ordenar por nome;
- nunca retornar Projects de outros usuários;
- paginação obrigatória;
- selecionar apenas colunas necessárias.

---

### store()

Responsabilidade

Criar novo Project.

Fluxo

1. Validar Request.
2. Delegar criação ao Service (quando existente).
3. Associar ao usuário autenticado.
4. Retornar HTTP 201.

---

### show()

Responsabilidade

Exibir detalhes de um Project.

Obrigatório

- utilizar ProjectPolicy;
- retornar apenas o Project solicitado.

---

### update()

Responsabilidade

Atualizar Project.

Fluxo

1. Validar Request.
2. Verificar Policy.
3. Atualizar.
4. Retornar Resource atualizada.

---

### destroy()

Responsabilidade

Excluir Project.

Fluxo

1. Verificar Policy.
2. Remover Project.
3. Retornar HTTP 204.

Nunca excluir Projects de outros usuários.

---

## 4.5 Rotas

Criar em:

```
routes/api.php
```

Rotas obrigatórias

GET

```
/api/projects
```

POST

```
/api/projects
```

GET

```
/api/projects/{project}
```

PUT

```
/api/projects/{project}
```

PATCH

```
/api/projects/{project}
```

DELETE

```
/api/projects/{project}
```

Todas protegidas por:

```
firebase.auth
```

Aplicar ProjectPolicy em todas as ações que operem sobre uma instância de Project.

---

## 4.6 Escopo da Brand Ativa

Após a criação do CRUD, preparar a aplicação para operar sempre dentro de uma Brand ativa.

Regras obrigatórias

- Toda consulta futura deverá considerar `project_id`.
- Nunca acessar dados globais do domínio.
- Nunca misturar dados entre usuários.
- Toda Execution futura deverá pertencer a exatamente um Project.

---

## 4.7 Responses

Padronizar respostas JSON.

### Listagem

```json
{
  "success": true,
  "data": [...]
}
```

### Criação

```json
{
  "success": true,
  "message": "Project created successfully.",
  "data": { }
}
```

### Atualização

```json
{
  "success": true,
  "message": "Project updated successfully.",
  "data": { }
}
```

### Exclusão

HTTP 204.

Sem corpo de resposta.

---

## 4.8 Testes

Criar

```
tests/Feature/ProjectTest.php
```

Casos mínimos

### Listagem

- usuário visualiza apenas seus Projects.

---

### Criação

- Project criado com sucesso.
- associação correta ao usuário.

---

### Visualização

- proprietário possui acesso.
- terceiros recebem 403.

---

### Atualização

- proprietário atualiza.
- terceiros recebem 403.

---

### Exclusão

- proprietário exclui.
- terceiros recebem 403.

---

### Validação

- nome obrigatório.
- URL inválida rejeitada.
- campos inválidos retornam HTTP 422.

---

## Regras Obrigatórias da Fase 4

- Toda validação deve utilizar Form Requests.
- Toda autorização deve utilizar Policies.
- Nenhuma lógica de negócio deve existir no Controller.
- Nenhuma consulta deve ignorar o usuário autenticado.
- Nunca retornar Projects de terceiros.
- Utilizar paginação em listagens.
- Selecionar apenas colunas necessárias.
- Preparar toda a arquitetura para o escopo obrigatório por `project_id`.
- Manter compatibilidade com futuras Executions, Credits e Pipelines de IA.

---

## Fluxo Oficial do CRUD

```text
Request

    │

    ▼

Form Request

    │

    ▼

ProjectPolicy

    │

    ▼

ProjectController

    │

    ▼

Service (quando aplicável)

    │

    ▼

Project Model

    │

    ▼

Response JSON
```

---

# Critério de Conclusão da Fase 4

A Fase 4 somente estará concluída quando:

- [ ] ProjectPolicy implementada.
- [ ] StoreProjectRequest implementado.
- [ ] UpdateProjectRequest implementado.
- [ ] ProjectController implementado.
- [ ] Rotas REST criadas.
- [ ] CRUD completo funcional.
- [ ] Todas as ações protegidas por `firebase.auth`.
- [ ] Policies impedem acesso a Projects de terceiros.
- [ ] Paginação implementada.
- [ ] Escopo por usuário validado.
- [ ] ProjectTest.php criado.
- [ ] Todos os testes passando.

---

## Próxima Etapa

Após o CRUD de Projects estar totalmente funcional, iniciar a **FASE 5 — Infraestrutura de Filas**, configurando Redis, Horizon, filas especializadas e o processamento assíncrono que servirá de base para todas as Execuções de IA do Arkion.


# FASE 5 — INFRAESTRUTURA DE FILAS

Objetivo desta fase:

Preparar toda a infraestrutura de processamento assíncrono da plataforma.

Nenhuma Execution poderá ser processada de forma síncrona.

Toda operação de IA deverá obrigatoriamente passar pelas filas Redis.

---

# Ordem Oficial de Implementação

---

## 5.1 Configurar Redis

Atualizar o arquivo:

```
.env
```

Definir:

- CACHE_STORE=redis
- QUEUE_CONNECTION=redis
- REDIS_CACHE_DB=1
- QUEUE_REDIS_DB=0
- REDIS_PREFIX conforme ambiente

Validar conexão utilizando Artisan.

---

## 5.2 Configurar Horizon

Publicar a configuração oficial.

Criar:

```
config/horizon.php
```

Configurar supervisores para:

- default
- viral-refs
- notifications

Definir quantidade inicial de workers conforme capacidade do servidor.

---

## 5.3 Criar Filas Oficiais

As filas do projeto serão:

### default

Responsável por:

- processamento geral
- tarefas rápidas
- operações de domínio

---

### viral-refs

Responsável por:

- pesquisas de mercado
- análises de concorrentes
- pipelines longos
- geração de conteúdo baseada em IA

---

### notifications

Responsável por:

- notificações
- broadcasts
- e-mails
- eventos assíncronos

---

Nenhuma nova fila deve ser criada sem ADR.

---

## 5.4 Configurar Queue Workers

Workers dedicados:

```
default
```

```
viral-refs
```

```
notifications
```

Cada worker deverá consumir exclusivamente sua fila.

Nunca utilizar um único worker para todas as filas em produção.

---

## 5.5 Configurar Supervisor

Criar processos independentes para:

- Horizon
- Queue Workers
- Reverb

Configurar:

- restart automático
- logs
- autorecovery

---

## 5.6 Criar Job Dummy

Criar um Job simples apenas para validação da infraestrutura.

Objetivo

Confirmar que:

- Job é despachado.
- Redis recebe a mensagem.
- Worker processa.
- Horizon registra execução.

Após validação, o Job poderá ser removido.

---

## 5.7 Validar Horizon

Confirmar:

- filas aparecem no Dashboard;
- Jobs processam corretamente;
- retries funcionam;
- falhas são registradas.

---

## Regras Obrigatórias da Fase 5

- Toda Execution futura utilizará Queue.
- Nunca executar IA diretamente pelo Controller.
- Nunca chamar n8n durante uma requisição HTTP.
- Todo processamento pesado deve ocorrer em Jobs.
- Toda fila deve possuir timeout adequado.
- Toda fila deve possuir número de tentativas definido.
- Nunca utilizar driver database para filas.
- Redis é obrigatório.

---

# Critério de Conclusão da Fase 5

- [ ] Redis configurado.
- [ ] Horizon configurado.
- [ ] Supervisor configurado.
- [ ] Filas criadas.
- [ ] Workers funcionando.
- [ ] Job Dummy executado.
- [ ] Horizon exibindo processamento corretamente.

---

# FASE 6 — LAYOUT BASE BLADE

Objetivo desta fase:

Criar toda a infraestrutura visual da aplicação.

Nesta fase não serão implementadas funcionalidades de negócio.

Somente a fundação da interface.

---

# Ordem Oficial de Implementação

---

## 6.1 Layout Principal

Criar:

```
resources/views/layouts/app.blade.php
```

Este será o layout padrão da aplicação autenticada.

Deve conter:

- estrutura HTML5
- classe `dark` no elemento `<html>`
- fonte Inter
- Tailwind
- Alpine.js
- Livewire
- Reverb
- Vite
- área para navegação
- área para conteúdo
- área para notificações
- stack de scripts

---

## 6.2 Layout Guest

Criar:

```
resources/views/layouts/guest.blade.php
```

Responsável por:

- páginas públicas
- autenticação
- onboarding futuro

Estrutura mínima.

---

## 6.3 Componentes Compartilhados

Preparar estrutura para:

```
resources/views/components/
```

Componentes previstos:

- button
- card
- modal
- input
- textarea
- badge
- dropdown
- avatar
- toast
- spinner

Nesta fase apenas a estrutura deverá existir.

---

## 6.4 AppController

Criar:

```
app/Http/Controllers/Web/AppController.php
```

Responsabilidade

Retornar as páginas Blade principais.

Métodos mínimos

- index()
- briefing()
- history()
- plans()
- result()

Sem lógica de negócio.

---

## 6.5 Rotas Web

Criar em:

```
routes/web.php
```

Rotas mínimas

```
/
```

```
/briefing
```

```
/history
```

```
/plans
```

```
/result/{execution}
```

Todas protegidas por:

```
firebase.auth
```

---

## 6.6 Livewire

Preparar diretórios para componentes.

Não implementar funcionalidades.

Estrutura mínima:

```
Dashboard/
Projects/
Executions/
Notifications/
Shared/
```

---

## 6.7 Assets

Validar carregamento de:

- Tailwind
- Alpine
- Livewire
- Vite

Confirmar funcionamento em modo dark.

A fonte oficial deve ser Inter.

Nenhuma outra tipografia deve ser utilizada.

---

## Regras Obrigatórias da Fase 6

- Blade é a camada oficial de renderização.
- Livewire controla apenas interação.
- Alpine controla apenas comportamento local.
- Nenhuma regra de domínio na View.
- Nenhuma consulta ao banco nas Views.
- Todo layout deve iniciar em modo Dark.
- Utilizar apenas Inter.
- Todo CSS deve ser construído com Tailwind.
- Toda página autenticada utiliza `layouts.app`.
- Toda página pública utiliza `layouts.guest`.

---

# Critério de Conclusão da Fase 6

- [ ] Layout app criado.
- [ ] Layout guest criado.
- [ ] AppController criado.
- [ ] Rotas web funcionando.
- [ ] Estrutura Livewire preparada.
- [ ] Componentes compartilhados preparados.
- [ ] Tailwind carregando.
- [ ] Alpine carregando.
- [ ] Livewire carregando.
- [ ] Reverb preparado.
- [ ] Tema Dark funcionando.
- [ ] Fonte Inter aplicada em toda a aplicação.

---

## Próxima Etapa

Após a infraestrutura de filas e a fundação visual estarem concluídas, iniciar a **FASE 7 — Validação do Mês 1**, garantindo que todos os componentes implementados durante o primeiro ciclo do projeto estejam integrados, funcionais e cobertos por testes.


# FASE 7 — VALIDAÇÃO DO MÊS 1

Objetivo desta fase:

Validar que toda a fundação do Arkion esteja concluída, integrada e pronta para iniciar o desenvolvimento dos módulos de IA no Mês 2.

Nenhuma nova funcionalidade deve ser criada nesta fase.

O objetivo é garantir estabilidade, consistência arquitetural e cobertura mínima de testes antes da evolução do projeto.

---

# Ordem Oficial de Validação

Executar os testes exatamente na sequência abaixo.

---

## 7.1 Banco de Dados

Validar:

- Todas as migrations executam sem erro.
- Todas as migrations são reversíveis.
- Todas as Foreign Keys funcionam.
- Todos os índices foram criados.
- Todos os campos JSON utilizam JSONB.
- Todos os Seeders executam corretamente.

Executar:

```bash
php artisan migrate:fresh --seed
```

Resultado esperado

Banco recriado completamente sem erros.

---

## 7.2 Models

Validar:

- Todos os relacionamentos.
- Todos os Scopes.
- Todos os Casts.
- Todos os Fillable.

Confirmar:

- Nenhum Model utiliza `$guarded`.
- Nenhum Model contém regras de negócio complexas.

---

## 7.3 Firebase Authentication

Validar:

POST

```
/api/auth/firebase
```

Resultado esperado

- autenticação bem-sucedida;
- sincronização automática do usuário;
- retorno do plano;
- retorno do saldo de créditos;
- retorno da Brand ativa (quando existir).

---

## 7.4 Usuário Autenticado

Validar:

GET

```
/api/auth/me
```

Resultado esperado

Retornar:

- usuário autenticado;
- plano;
- assinatura;
- créditos;
- Brand ativa.

---

## 7.5 CRUD de Projects

Validar integralmente:

GET

```
/api/projects
```

POST

```
/api/projects
```

GET

```
/api/projects/{project}
```

PUT

```
/api/projects/{project}
```

PATCH

```
/api/projects/{project}
```

DELETE

```
/api/projects/{project}
```

Confirmar:

- Policies funcionando;
- Form Requests funcionando;
- Paginação funcionando;
- Escopo por usuário funcionando.

---

## 7.6 Redis

Validar:

- conexão;
- cache;
- filas.

Confirmar:

- databases separados;
- prefixo correto;
- nenhum conflito entre cache e filas.

---

## 7.7 Horizon

Validar:

- Dashboard disponível;
- workers ativos;
- filas processando;
- retries funcionando;
- Jobs concluídos corretamente.

---

## 7.8 Queue Workers

Executar Job Dummy.

Confirmar:

- Job entra na fila;
- Worker consome;
- Horizon registra;
- Job finaliza com sucesso.

---

## 7.9 Blade

Abrir aplicação.

Confirmar:

- layout carregado;
- Tailwind funcionando;
- Alpine funcionando;
- Livewire funcionando;
- Vite funcionando.

---

## 7.10 Tema

Confirmar:

- modo Dark ativo por padrão;
- classe `dark` presente no elemento `<html>`;
- tipografia Inter aplicada em toda a interface.

---

## 7.11 Rotas

Executar:

```bash
php artisan route:list
```

Confirmar existência das rotas previstas para:

API

- Auth
- Projects

WEB

- Dashboard
- Briefing
- History
- Plans
- Result

Webhook

- n8n

---

## 7.12 Testes Automatizados

Executar:

```bash
php artisan test
```

Resultado esperado

Todos os testes passando.

Executar também:

```bash
php artisan test --parallel
```

Resultado esperado

Execução paralela sem falhas.

---

## Checklist Oficial de Entregáveis do Mês 1

Infraestrutura

- [ ] Dois Droplets provisionados.
- [ ] VPC privada configurada.
- [ ] HTTPS funcionando.
- [ ] PostgreSQL operacional.
- [ ] Redis operacional.
- [ ] Horizon operacional.
- [ ] Reverb operacional.
- [ ] Firebase operacional.
- [ ] Stripe configurado.
- [ ] n8n operacional.

Banco

- [ ] Todas as migrations concluídas.
- [ ] Todas reversíveis.
- [ ] Todos os índices criados.
- [ ] Todas as Foreign Keys válidas.
- [ ] Todos os Seeders executando.

Domínio

- [ ] Todos os Models implementados.
- [ ] Todos os relacionamentos funcionando.
- [ ] Todos os Scopes implementados.

Autenticação

- [ ] POST /api/auth/firebase funcionando.
- [ ] GET /api/auth/me funcionando.
- [ ] Middleware Firebase funcionando.
- [ ] Sincronização automática de usuários funcionando.

Projects

- [ ] CRUD completo.
- [ ] Policies funcionando.
- [ ] Form Requests funcionando.
- [ ] Escopo por usuário funcionando.

Filas

- [ ] Redis recebendo Jobs.
- [ ] Horizon monitorando filas.
- [ ] Workers ativos.
- [ ] Job Dummy executado.

Frontend

- [ ] Layout Blade funcionando.
- [ ] Tailwind funcionando.
- [ ] Alpine funcionando.
- [ ] Livewire funcionando.
- [ ] Tema Dark ativo.
- [ ] Fonte Inter aplicada.

Qualidade

- [ ] AuthTest passando.
- [ ] ProjectTest passando.
- [ ] Testes paralelos passando.
- [ ] Nenhum erro de arquitetura identificado.

---

# Critério Oficial de Conclusão do Mês 1

O Mês 1 somente poderá ser considerado concluído quando todos os itens abaixo forem verdadeiros:

- Toda a infraestrutura estiver operacional.
- Toda a autenticação estiver funcional.
- O CRUD completo de Projects estiver validado.
- O sistema de filas estiver operacional.
- O layout base estiver implementado.
- Todos os testes automatizados estiverem passando.
- Não houver violações registradas no `PROJECT_RULES.md`.
- Todas as regras do `AGENTS.md` forem atendidas.
- Não existirem decisões arquiteturais pendentes para a fundação do sistema.

---

# Encerramento do Mês 1

Após a conclusão desta fase, o projeto estará apto para iniciar o **Mês 2 — Núcleo de Execuções de IA**, contemplando:

- criação do módulo de Executions;
- consumo de créditos;
- integração Laravel → n8n via Webhooks;
- processamento assíncrono dos pipelines de IA;
- atualização em tempo real via Reverb;
- persistência dos resultados em JSONB;
- início dos primeiros fluxos de geração de conteúdo do Arkion.

Nenhuma dessas funcionalidades deve ser iniciada antes da aprovação integral desta fase.

---

# MÊS 3 — INTERFACE E BRAND CORE

> As fases do Mês 3 ainda não foram detalhadas e serão especificadas em sessão própria.
> Esta entrada existe para fixar os **HTMLs de referência oficiais** do novo Design System (`SAD.md`, Seção 10).

## HTMLs de Referência — Sidebar, Header e Brand Core

O Design System foi integralmente redesenhado com o rebranding para Arkion. Os HTMLs de referência anteriores estão **obsoletos** e não devem mais ser consultados por nenhum agente.

| Fase do Mês 3 | HTML obsoleto | HTML oficial |
|---------------|---------------|--------------|
| Sidebar | ~~`sidebar.html`~~ | **`sidebar_2_0.html`** |
| Header | ~~`header.html`~~ | **`header_2_0.html`** |
| Brand Core | ~~`brand_core.html`~~ | **`brand_core_v2.html`** |

O mapeamento HTML → componente Blade/Livewire está em `SAD.md`, Seção 10.13.

Qualquer implementação dessas telas deve partir dos arquivos `_2_0` / `_v2` e respeitar:

- Light mode como padrão, Dark como variante `dark:` (Seção 10.2);
- a paleta da Seção 10.4 — nenhum token antigo;
- Newsreader em `H1`–`H5` e Inter no restante (Seção 10.6);
- ícones SVG com `stroke-width="0.3"` (Seção 10.7);
- todo texto de interface em inglês (Seção 10.1.1).

---

# MÊS 4 — MONETIZAÇÃO, ADMINISTRAÇÃO E DISTRIBUIÇÃO

> **Nota de numeração:** as fases do Mês 4 utilizam numeração escopada ao mês (`Fase 4.1`, `Fase 4.2`, `Fase 4.3`).
> Não confundir com a **FASE 4 — CRUD de Projects**, pertencente ao Mês 1.

## Ordem Oficial das Fases do Mês 4

| Ordem | Fase | Especificação |
|-------|------|---------------|
| 1 | Fase 4.1 — Stripe, Cashier e `CheckSubscription` | A detalhar em sessão própria. Inclui a decisão **[A DEFINIR]** sobre a infraestrutura de pagamento de créditos avulsos — Stripe Checkout one-time vs Lemon Squeezy (`SAD.md`, Seção 12.13) — e a migration dos dois saldos de crédito (`SAD.md`, Seção 12.8) |
| 2 | **Fase 4.2 — Admin Panel** | **Especificada abaixo** |
| 3 | Fase 4.3 — PWA | A detalhar em sessão própria (ver `SAD.md`, Seção 15) |

Os Meses 2 e 3 e as Fases 4.1 e 4.3 serão detalhados em sessões próprias. Nenhuma fase deve ser antecipada.

---

# Fase 4.2 — Admin Panel

Objetivo desta fase:

Implementar a interface interna de operação do Arkion, permitindo suporte, inspeção de contas e ajuste manual de créditos dentro do domínio Laravel — sujeitos a Policies, Services e auditoria — eliminando a necessidade de qualquer alteração manual em banco de produção.

Referência arquitetural: `SAD.md`, **Seção 14 — Painel Administrativo**.

---

## Dependências

Esta fase não pode ser iniciada antes de:

- **Fase 4.1** concluída — `CheckSubscription`, Cashier e `PlanService` operacionais;
- módulo de **Executions** concluído (Mês 2);
- **`CreditService`** implementado e validado.

O Admin Panel é exclusivamente um consumidor dessas camadas. Nenhuma regra de negócio nova deve ser criada nesta fase.

---

# Ordem Oficial de Implementação

Seguir rigorosamente a sequência abaixo.

---

## 4.2.1 Migration — `add_is_admin_to_users_table`

### Arquivo

```
database/migrations/xxxx_xx_xx_xxxxxx_add_is_admin_to_users_table.php
```

### Objetivo

Adicionar o marcador de administrador à tabela `users`.

### Colunas obrigatórias

| Coluna | Tipo | Regra |
|--------|------|-------|
| `is_admin` | `boolean` | `default(false)`, `NOT NULL` |

### Regras obrigatórias

- Migration **incremental**. Nunca alterar a migration original `create_users_table` — conforme `SAD.md`, Apêndice K.7, migrations já executadas jamais são editadas.
- Nomenclatura obrigatória `add_is_admin_to_users_table`, conforme Apêndice K.7.
- A migration deve ser reversível (`down()` removendo a coluna).
- A migration **não** popula dados. A atribuição de `is_admin` é manual e não pertence a Seeders de produção.

---

## 4.2.2 Migration — `create_admin_action_logs_table`

### Arquivo

```
database/migrations/xxxx_xx_xx_xxxxxx_create_admin_action_logs_table.php
```

### Objetivo

Registrar de forma permanente toda ação administrativa que altere dados de um usuário.

### Colunas obrigatórias

| Coluna | Tipo | Regra |
|--------|------|-------|
| `id` | `bigIncrements` | Padrão Laravel |
| `admin_id` | FK → `users.id` | `restrictOnDelete()` — o log sobrevive ao usuário |
| `action` | `string` | Identificador da ação executada |
| `target_type` | `string` | Entidade afetada |
| `target_id` | `unsignedBigInteger` | Identificador da entidade afetada |
| `payload` | `jsonb` | Contexto da ação (antes/depois, motivo) |
| `created_at` / `updated_at` | `timestamps` | Padrão Laravel |

### Índices obrigatórios

- `admin_id`
- `(target_type, target_id)` — índice composto
- `created_at`

### Regras obrigatórias

- **Sem SoftDeletes.** Logs de auditoria são permanentes e não possuem rotina de expurgo.
- Utilizar `jsonb()` no campo `payload`. Nunca `json()`.
- O par `target_type` / `target_id` é uma referência polimórfica manual e **não** possui Foreign Key: o alvo pode ser um `User`, uma `Subscription` ou uma `Execution`.
- A Foreign Key `admin_id` deve possuir política explícita `restrictOnDelete()`.
- O `payload` nunca deve conter segredos, tokens ou dados de cartão.

---

## 4.2.3 Model — `AdminActionLog`

### Arquivo

```
app/Models/AdminActionLog.php
```

### Fillable obrigatório

- `admin_id`
- `action`
- `target_type`
- `target_id`
- `payload`

### Casts obrigatórios

- `payload` → `array`

### Relacionamentos

- `admin()` → `belongsTo(User::class, 'admin_id')`

### Regras obrigatórias

- Declarar `$fillable` explicitamente. Nunca utilizar `$guarded = []`.
- Nenhuma regra de negócio no Model.
- Não utilizar `SoftDeletes`.

---

## 4.2.4 Gate — `access-admin`

### Arquivo

```
app/Providers/AppServiceProvider.php
```

### Responsabilidade

Centralizar em um único ponto a definição de quem é administrador.

### Implementação esperada

```php
Gate::define('access-admin', fn (User $user) => $user->is_admin);
```

### Regras obrigatórias

- O Gate é a **única** fonte de verdade da autorização administrativa.
- Nunca verificar `$user->is_admin` inline em Controllers, Livewire ou Blade. Sempre através do Gate ou do middleware.
- Nenhuma lista de e-mails ou IDs hardcoded.

---

## 4.2.5 Middleware — `EnsureIsAdmin`

### Arquivo

```
app/Http/Middleware/EnsureIsAdmin.php
```

### Responsabilidade

Aplicar o Gate `access-admin` a todas as rotas `/admin/*`.

### Fluxo esperado

1. O usuário já foi autenticado por `VerifyFirebaseToken`.
2. Avaliar o Gate `access-admin`.
3. Em caso de falha, retornar **403 JSON**.
4. Em caso de sucesso, seguir para o Controller.

### Regras obrigatórias

- Executar sempre **após** `VerifyFirebaseToken`. O Admin Panel não substitui a autenticação Firebase.
- A resposta de negação deve ser JSON com status `403`, sem revelar a existência do recurso solicitado.
- Registrar o middleware com o alias `admin` em `bootstrap/app.php`.
- Nenhuma rota administrativa pode existir sem este middleware.

---

## 4.2.6 Service — `LogsAdminActions`

### Arquivo

```
app/Services/Admin/LogsAdminActions.php
```

### Responsabilidade

Persistir o registro em `admin_action_logs`.

### Regras obrigatórias

- Toda ação administrativa que altera dados de um usuário deve invocar este Service **antes** de retornar sucesso ao administrador.
- A gravação do log e a alteração do dado devem ocorrer dentro da mesma transação: se o log falhar, a alteração é revertida.
- Nenhum componente Livewire grava diretamente em `AdminActionLog`.
- O Service não decide *se* deve registrar — ele sempre registra. A decisão pertence à ação que o chama.

---

## 4.2.7 Controllers Administrativos

### Diretório

```
app/Http/Controllers/Admin/
```

### Regras obrigatórias

- Namespace **isolado**: `App\Http\Controllers\Admin`.
- Nunca reutilizar, estender ou compartilhar Controllers de `Controllers/Web` ou `Controllers/Api`.
- Nunca reutilizar Form Requests de `Web` ou `Api`; Requests administrativos residem em `app/Http/Requests/Admin/`.
- Controller contém apenas orquestração: recebe o Request, delega ao Service e retorna a Response.
- Toda validação em Form Requests. Nunca validar no Controller.

---

## 4.2.8 Componentes Livewire

### Diretório

```
app/Livewire/Admin/
```

### Componentes obrigatórios

| Componente | Responsabilidade |
|------------|------------------|
| `Admin/UserList` | Listagem paginada e busca de usuários |
| `Admin/UserDetail` | Inspeção de conta: plano, assinatura, saldo e execuções |
| `Admin/CreditAdjustment` | Ajuste manual de créditos |

### Regras obrigatórias

- **Nenhuma lógica de negócio nova.** Os componentes reaproveitam `CreditService` e `PlanService` já existentes.
- Nenhum componente calcula, credita ou debita saldo por conta própria.
- Nenhum custo de crédito hardcoded; toda leitura vem de `plan_limits`.
- `Admin/UserList` deve paginar e utilizar `select()` explícito. Nunca `User::all()`.
- Nenhuma listagem pode carregar `executions.result`.
- `Admin/CreditAdjustment` deve exigir um motivo textual obrigatório, persistido no `payload` do log.
- Os componentes apenas apresentam dados e disparam ações.

---

## 4.2.9 Rotas

### Regras obrigatórias

- Todas as rotas sob o prefixo `/admin`.
- Grupo protegido por `VerifyFirebaseToken` + `EnsureIsAdmin`, nesta ordem.
- Nenhuma rota administrativa fora deste grupo.
- Nenhuma rota administrativa compartilha Controller com `Web` ou `Api`.

---

## 4.2.10 Testes

### Arquivo

```
tests/Feature/AdminTest.php
```

### Casos obrigatórios

- Usuário sem `is_admin` recebe **403** em qualquer rota `/admin/*`.
- Usuário não autenticado recebe **401**, nunca 403.
- Usuário com `is_admin` acessa o painel com sucesso.
- Ajuste manual de créditos altera o saldo através do `CreditService`.
- Ajuste manual de créditos grava o registro correspondente em `admin_action_logs`.
- Falha na gravação do log reverte a alteração de saldo.
- `Admin/UserList` não carrega `executions.result`.

---

## Regras Obrigatórias da Fase 4.2

- `is_admin` é um boolean em `users`; nunca criar tabela de papéis ou pacote de RBAC.
- Toda autorização administrativa passa pelo Gate `access-admin`.
- Rotas `/admin/*` nunca compartilham Controllers com `Web` ou `Api`.
- Toda ação que altera dados de um usuário é registrada em `admin_action_logs` antes do retorno de sucesso.
- Nenhuma regra de negócio nova: reaproveitar `CreditService` e `PlanService`.
- `admin_action_logs` não utiliza SoftDeletes.
- `payload` sempre `jsonb`.
- Nenhum Seeder de produção atribui `is_admin`.

---

## Fluxo Oficial de uma Ação Administrativa

```text
Request /admin/*

    │

    ▼

VerifyFirebaseToken

    │

    ▼

EnsureIsAdmin (Gate access-admin)

    │

    ▼

Form Request (App\Http\Requests\Admin)

    │

    ▼

Admin Controller

    │

    ▼

CreditService / PlanService

    │

    ▼

LogsAdminActions  →  admin_action_logs

    │

    ▼

Response (sucesso somente após o log)
```

---

# Critério de Conclusão da Fase 4.2

A Fase 4.2 somente estará concluída quando:

- [ ] Migration `add_is_admin_to_users_table` criada e executada.
- [ ] Migration `create_admin_action_logs_table` criada e executada.
- [ ] Model `AdminActionLog` implementado.
- [ ] Gate `access-admin` definido no `AppServiceProvider`.
- [ ] Middleware `EnsureIsAdmin` implementado e registrado.
- [ ] Service `LogsAdminActions` implementado.
- [ ] Controllers criados em `App\Http\Controllers\Admin`.
- [ ] `Admin/UserList`, `Admin/UserDetail` e `Admin/CreditAdjustment` implementados.
- [ ] Nenhuma regra de negócio nova introduzida.
- [ ] Rotas `/admin/*` protegidas pelo middleware.
- [ ] `AdminTest.php` criado.
- [ ] Todos os testes passando.

---

# Checklist Oficial de Conclusão do Mês 4

- [ ] Assinaturas Stripe operacionais via Cashier.
- [ ] Middleware `CheckSubscription` validando assinatura ativa.
- [ ] **Admin panel operacional com CRUD de usuários e ajuste manual de créditos, todas as ações auditadas em `admin_action_logs`.**
- [ ] PWA instalável em dispositivos compatíveis.
- [ ] Todos os testes automatizados passando.
- [ ] Nenhuma violação registrada no `PROJECT_RULES.md`.
- [ ] Nenhuma decisão arquitetural pendente marcada como `[A DEFINIR]` nos módulos entregues.
