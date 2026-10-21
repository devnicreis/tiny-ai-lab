# Software Architecture Document (SAD)

**Projeto:** Arkion  
**Versão:** 2.2 (Living Document)  
**Status:** Production Ready Architecture Specification  
**Stack:** Laravel 11 + Livewire 3 + PostgreSQL + n8n + Redis + Reverb  
**Última atualização:** Outubro/2026

> **Revisão 2.2 — Três papéis de organização (ADR-010).** Decisão de Nicolas e Erich: `organization_user.role`
> passa a ter **três** valores — `owner`, `admin` e `member`. Há **exatamente um Owner** por organização, único
> responsável por faturamento, compra de créditos para a org, atribuição de papéis, transferência de posse e
> exclusão da org; gerenciar Marcas passa a ser de Owner e Admin, não mais de Member. Foram reescritas a
> **Seção 20.4** (matriz de permissões e invariantes do Owner), a **Seção 12** nos pontos de compra de créditos,
> assinatura e CTAs por papel, e a **Seção 4.15** (`role` e índice único parcial). Nova **ADR-010** (Seção 17) e
> nova **Seção 12.20** (pendência do Mês 4: Organization como customer Stripe). As decisões "dois papéis apenas",
> "a organização nunca fica sem Admin" e "Members criam e editam marcas" estão marcadas como **SUPERSEDED** e
> preservadas como histórico. Nada desta revisão está implementado em código (ver Seção 20.8).
>
> **Revisão 2.1 — Organizações.** O Arkion adota um modelo de **Organizações** inspirado no Make.com:
> a Organização é a entidade de faturamento, as Marcas pertencem à Organização e nunca ao usuário, e o
> crédito passa a viver em **três carteiras** (plano da org → comprado pela org → carteira pessoal do usuário,
> esta última só debitada com confirmação explícita). Foram reescritos: a **Seção 12** (créditos, planos e
> os três estados de bloqueio), a nova **Seção 4.15** (`organizations` e `organization_user`) e a nova
> **Seção 20** (Organizações e Hierarquia de Propriedade). Trechos superados da revisão 2.0 estão marcados
> como **SUPERSEDED** e preservados como histórico. Nada desta revisão está implementado em código.
>
> **Revisão 2.0 — Rebranding e redesenho.** O produto passou de "Tiny AI Lab" para **Arkion**.
> Três blocos foram reescritos: **Seção 10** (novo Design System — Light first, paleta `#d2691e`,
> tipografia Newsreader + Inter, interface do cliente final em inglês), **Seção 12** (dois saldos
> de crédito, feature gating de pipelines por plano, `credit_packages`) e a nova **Seção 19**
> (Hierarquia de Marcas — umbrella brands). Nenhuma dessas mudanças está implementada em código.

---

# 1. Visão Geral do Sistema

## 1.1 Objetivo

O Arkion é uma plataforma SaaS B2B construída para atuar como um orquestrador inteligente de marketing orientado por IA. Seu propósito é eliminar a fragmentação do processo estratégico de criação de conteúdo, concentrando em uma única plataforma toda a inteligência da marca, ativos, pesquisas, diagnósticos e geração automatizada de materiais de marketing.

Em vez de funcionar como uma ferramenta isolada de geração de texto, o sistema mantém uma memória estruturada da marca ("Brand Memory"), permitindo que todos os artefatos produzidos sejam contextualizados pelas características específicas de cada empresa. A plataforma converte informações brutas fornecidas pelo usuário em ativos estratégicos estruturados, utilizando múltiplos modelos de IA coordenados por pipelines especializados executados pelo n8n.

Todos os resultados produzidos são armazenados em formato JSON estruturado, permitindo versionamento, auditoria, reutilização, exportação e composição por outras funcionalidades futuras da plataforma.

---

## 1.2 Problema Resolvido

Empresas e agências frequentemente mantêm suas informações estratégicas distribuídas entre documentos, planilhas, chats, apresentações e ferramentas distintas.

Essa fragmentação gera:

- perda de contexto entre campanhas;
- inconsistência de identidade da marca;
- retrabalho constante;
- dificuldade de reaproveitamento de informações;
- dependência de conhecimento tácito das equipes.

O Arkion centraliza essas informações em um único workspace por marca ("Brand Workspace"), permitindo que todos os processos de geração utilizem uma fonte consistente de conhecimento.

---

## 1.3 Público-alvo

O sistema foi concebido para atender principalmente:

- Agências de Marketing;
- Pequenas e Médias Empresas (SMBs);
- Consultorias;
- Empresas com equipes internas de marketing;
- Criadores de conteúdo que gerenciam múltiplas marcas.

Mercados prioritários:

- Brasil
- Estados Unidos

---

## 1.4 Modelo de Monetização

O Arkion utiliza um modelo de assinatura baseado em **créditos de IA**, e **não** em quantidade fixa de execuções mensais.

Cada ação disponível na plataforma possui um custo específico de créditos.

O consumo ocorre apenas após a validação da assinatura ativa e da existência de saldo suficiente.

### Custos conhecidos

| Ação | Consumo |
|---------|---------:|
| Brand Research | 50 créditos |
| Briefing Generation | 5 créditos |
| Image Generation | 7 créditos |
| Copywriting Edit | 15 créditos |
| SEO Optimization | 10 créditos |
| Create Article | **[A DEFINIR]** |
| Create Post | **[A DEFINIR]** |
| Create Ad | **[A DEFINIR]** |

Cada usuário possui **dois saldos separados**: `plan_credits_balance`, que renova mensalmente e não é cumulativo, e `purchased_credits_balance`, adquirido em pacotes avulsos e que nunca expira. O consumo debita sempre o saldo do plano primeiro. Ver Seção 12.

Além do saldo, cada plano concede acesso a um **subconjunto dos pipelines** disponíveis — o plano determina não apenas quanto o usuário pode executar, mas o que ele pode executar.

O sistema nunca limita funcionalidades através de número fixo de execuções.

Toda operação consome créditos conforme sua complexidade computacional.

Os planos comerciais vigentes são **Arkion Copy & Briefing**, **Arkion Studio Visual** e **Arkion Full Pass**, detalhados na Seção 12.6.

---

## 1.5 Conceitos Fundamentais

### Organização

A **entidade de faturamento** da plataforma e a dona de todas as Marcas.

Uma Organização possui:

- o plano assinado (ou nenhum — `plan = null`);
- os créditos do plano e os créditos comprados para a operação;
- os membros, cada um com um papel (`owner`, `admin` ou `member`), com **exatamente um Owner** (Seção 20.4);
- as Marcas.

Toda operação do sistema acontece dentro de exatamente uma Organização ativa. Ver **Seção 20**.

---

### Usuário

Representa uma conta autenticada via Firebase.

Cada usuário pode:

- pertencer a múltiplas organizações, com um papel em cada uma;
- disparar execuções nas Marcas das organizações de que participa;
- consultar o histórico completo de execuções da Brand ativa;
- manter uma **carteira pessoal de créditos**, portátil entre organizações.

O usuário **não é dono de Marcas** nem de assinatura. Marca e plano pertencem à Organização (Seção 20.2).

---

### Brand (Projeto)

O conceito central da plataforma.

Uma Brand representa um workspace independente contendo:

- identidade;
- ativos;
- pesquisas;
- estratégias;
- execuções;
- resultados.

Toda operação do sistema acontece dentro de exatamente uma Brand ativa.

---

### Brand Ativa

Embora uma Organização possa possuir múltiplas Brands, existe sempre apenas uma Brand ativa durante uma sessão — e ela **precisa pertencer à Organização ativa** (Seção 20.5).

Toda consulta ao banco deve ser automaticamente escopada pela Brand ativa.

---

### Execução

Uma execução representa qualquer pipeline enviado ao mecanismo de IA.

Exemplos:

- Brand Research
- Briefing Generation
- Image Generation
- Create Article
- Create Post
- Create Ad

Cada execução possui:

- configuração original;
- estado;
- consumo de créditos;
- resultado estruturado.

---

### Pipeline

Pipeline representa um fluxo independente executado pelo n8n.

Cada pipeline possui:

- entrada própria;
- sequência própria de modelos de IA;
- custo próprio em créditos;
- retorno específico.

---

### Resultado

Todo pipeline retorna um JSON estruturado.

O sistema nunca trata respostas da IA como texto bruto.

Todos os artefatos produzidos são armazenados em JSONB no PostgreSQL.

---

# 1.6 Premissas Arquiteturais Não-Negociáveis

As seguintes decisões fazem parte da arquitetura oficial do sistema e não podem ser alteradas por implementações futuras.

## Laravel é o único dono do banco

Somente Laravel pode:

- ler dados;
- gravar dados;
- atualizar dados;
- excluir dados.

Nenhum outro serviço possui acesso direto ao PostgreSQL.

---

## n8n nunca acessa o banco

O n8n funciona exclusivamente como motor de orquestração.

Ele:

- recebe payloads;
- executa integrações;
- retorna resultados.

Nunca realiza consultas SQL.

Nunca abre conexões com PostgreSQL.

Nunca modifica registros diretamente.

---

## Toda autenticação pertence ao Firebase

Laravel nunca autentica usuários utilizando sessões próprias.

Toda autenticação é baseada na validação do Firebase ID Token.

---

## Laravel controla toda autorização

Mesmo após autenticação pelo Firebase, Laravel continua responsável por:

- autorização;
- policies;
- assinatura;
- créditos;
- ownership;
- acesso às Brands.

---

## O banco pertence exclusivamente ao domínio

Nenhuma lógica de IA depende da estrutura física do banco.

Os pipelines trabalham exclusivamente com payloads.

---

## Toda comunicação entre Laravel e n8n ocorre por Webhooks HTTP

Não existem:

- filas compartilhadas;
- acesso direto ao Redis;
- acesso ao banco;
- chamadas RPC.

Toda comunicação é baseada em HTTP autenticado.

---

## O frontend nunca conversa com o n8n

Fluxo obrigatório:

Frontend

↓

Laravel

↓

Redis

↓

Job

↓

n8n

↓

Laravel

↓

WebSocket

↓

Frontend

---

## Toda execução é assíncrona

Nenhum pipeline é executado durante a requisição HTTP original.

Toda geração passa obrigatoriamente por:

- Redis
- Horizon
- Queue Worker

---

## Resultados são persistidos

Todo resultado produzido pela IA deve ser armazenado integralmente.

Não existem respostas descartáveis.

---

## O sistema é Multi-Brand

Todo dado pertence obrigatoriamente a uma Brand.

Não existem dados globais de marketing pertencentes diretamente ao usuário.

---

## Toda interface é Light First

O modo claro representa a aparência oficial da plataforma.

O tema escuro existe como variante, aplicada exclusivamente pelo prefixo `dark:` do Tailwind.

O elemento `<html>` inicia **sem** classe de tema:

```html
<html>
```

O tema escuro é ativado sob demanda pelo `theme-toggle-btn`, que adiciona a classe `dark` ao elemento `<html>` e persiste a escolha em `localStorage`.

Ver Seção 10 — Design System e Frontend.

---

## Blade + Livewire constituem a única arquitetura frontend

Não existe frontend separado.

Não existe API pública para consumo da interface.

Toda UI é construída utilizando:

- Blade
- Livewire 3
- Alpine.js
- Tailwind CSS

---

## Toda atualização de status utiliza Reverb

Atualizações de execução nunca utilizam polling como mecanismo principal.

Fluxo oficial:

Worker

↓

Evento

↓

Laravel Reverb

↓

Livewire

↓

UI

O polling existe apenas como fallback.

---

## O sistema utiliza PostgreSQL como banco oficial

Todos os recursos do banco podem ser utilizados.

Incluindo:

- JSONB
- GIN Index
- Partial Index
- Generated Columns
- Transactions
- Advisory Locks

A arquitetura não precisa manter compatibilidade com MySQL.

---

> ⚠️ **Regra crítica:** Nenhuma implementação futura pode substituir ou contornar estas premissas arquiteturais. Elas constituem a base do sistema e devem ser consideradas invariáveis por qualquer agente de IA responsável pela geração de código.

---

# 2. Stack Tecnológico

## 2.1 Visão Geral

A arquitetura do Arkion adota um monólito moderno baseado na TALL Stack (Tailwind, Alpine.js, Laravel, Livewire), complementado por serviços especializados para filas, autenticação, tempo real e orquestração de IA.

Todo componente foi escolhido para reduzir complexidade operacional, minimizar custos de infraestrutura e concentrar a lógica de negócio em uma única aplicação Laravel.

| Tecnologia | Versão | Papel no Sistema | Onde Roda |
|------------|---------|------------------|------------|
| PHP | 8.3 | Runtime principal | Droplet 1 |
| Laravel | 11 | Backend, domínio, API, autenticação, ORM | Droplet 1 |
| Livewire | 3 | Componentes reativos | Droplet 1 |
| Alpine.js | 3.x | Interações locais da interface | Navegador |
| Tailwind CSS | 3.x | Design System | Navegador (Vite) |
| Blade | Laravel 11 | Templates da interface | Droplet 1 |
| Inter | Latest | Tipografia oficial | Navegador |
| PostgreSQL | [A DEFINIR] | Banco de dados principal | Droplet 1 |
| Redis | [A DEFINIR] | Filas, cache e Horizon | Droplet 1 |
| Laravel Horizon | Laravel 11 | Gerenciamento de filas | Droplet 1 |
| Laravel Reverb | Laravel 11 | WebSockets | Droplet 1 |
| Laravel Cashier | Laravel 11 | Assinaturas Stripe | Droplet 1 |
| Firebase Auth | Spark | Autenticação | Firebase |
| n8n | Self-hosted | Orquestração de IA | Droplet 2 |
| Apify | Cloud | Web Scraping | Via n8n |
| Perplexity API | Cloud | Pesquisa estratégica | Via n8n |
| Gemini 1.5 Flash | Cloud | Diagnóstico e raciocínio | Via n8n |
| Claude 3.5 Sonnet | Cloud | Geração criativa | Via n8n |
| Nginx | [A DEFINIR] | Web Server | Droplet 1 |
| DigitalOcean | Ubuntu 24.04 LTS | Infraestrutura | Cloud |
| Cloudflare | Free | DNS, SSL e proteção | Cloud |
| Stripe | Latest | Cobrança | Cloud |
| Vite | Laravel 11 | Build de assets | Droplet 1 |

---

# 3. Arquitetura de Infraestrutura

## 3.1 Visão Geral

O Arkion é implantado em uma arquitetura composta por dois Droplets independentes na DigitalOcean, conectados através de uma VPC privada.

Essa separação possui dois objetivos principais:

- isolar completamente a aplicação Laravel do mecanismo de automação (n8n);
- impedir que workflows de IA concorram por CPU e memória com a aplicação principal.

Todo o acesso externo ocorre exclusivamente através do Cloudflare.

O n8n nunca fica exposto diretamente à Internet para consumo dos usuários da plataforma.

---

## 3.2 Diagrama Geral

```text
                                      Internet
                                          │
                                          │
                                   Cloudflare DNS
                              SSL • WAF • Proxy • CDN
                                          │
                      ┌───────────────────┴───────────────────┐
                      │                                       │
                app.tinyailab.com                     api.tinyailab.com
                      │
                      │
         ┌─────────────────────────────────────────────┐
         │           DigitalOcean VPC Privada          │
         │                                             │
         │                                             │
         │   ┌──────────────────────────────┐          │
         │   │        Droplet 1             │          │
         │   │------------------------------│          │
         │   │ Laravel 11                   │          │
         │   │ PHP 8.3                      │          │
         │   │ PostgreSQL                   │          │
         │   │ Redis                        │          │
         │   │ Horizon                      │          │
         │   │ Reverb                       │          │
         │   │ Nginx                        │          │
         │   └──────────────┬───────────────┘          │
         │                  │                          │
         │          HTTP (Private VPC)                │
         │                  │                          │
         │                  ▼                          │
         │   ┌──────────────────────────────┐          │
         │   │        Droplet 2             │          │
         │   │------------------------------│          │
         │   │ Docker                       │          │
         │   │ n8n                          │          │
         │   │ AI Integrations              │          │
         │   └──────────────────────────────┘          │
         │                                             │
         └─────────────────────────────────────────────┘
                      │
          ┌───────────┴────────────────────────────┐
          │                                        │
          ▼                                        ▼
     Firebase Auth                     Stripe / APIs IA
```

---

# 3.3 Componentes da Infraestrutura

## Cloudflare

O Cloudflare representa o único ponto de entrada público da aplicação.

Responsabilidades:

- gerenciamento de DNS;
- emissão e renovação de SSL;
- proxy reverso;
- proteção contra ataques;
- cache de ativos estáticos;
- ocultação do IP dos Droplets.

Nenhum usuário acessa diretamente o endereço IP da DigitalOcean.

---

## Firebase Authentication

Responsável exclusivamente por:

- login;
- emissão do ID Token;
- gerenciamento de identidade do usuário.

Laravel nunca realiza autenticação por senha própria.

---

## Droplet 1 — Aplicação Principal

### Responsabilidade

Executa todo o domínio de negócio.

Este servidor concentra:

- Laravel;
- banco PostgreSQL;
- Redis;
- Horizon;
- Reverb;
- Blade;
- Livewire;
- API interna.

---

### Especificação

| Item | Valor |
|------|-------|
| Provedor | DigitalOcean |
| Sistema Operacional | Ubuntu 24.04 LTS |
| vCPU | [A DEFINIR] |
| Memória | [A DEFINIR] |
| SSD | [A DEFINIR] |
| Custo mensal | US$ 24/mês |

---

### Serviços Instalados

- PHP 8.3
- Laravel 11
- Nginx
- PostgreSQL
- Redis
- Supervisor
- Horizon
- Laravel Reverb
- Vite Build
- Cron
- Certificados (via Cloudflare)

---

### Responsabilidades

Executa:

- autenticação;
- autorização;
- regras de negócio;
- acesso ao banco;
- geração de filas;
- recebimento de callbacks;
- broadcasting;
- renderização da interface.

---

### Serviços que NÃO executa

Nunca hospeda:

- n8n
- Docker de workflows
- automações de IA

Esses componentes permanecem isolados no Droplet 2.

---

# 3.4 Droplet 2 — Motor de Automação

## Responsabilidade

Executa exclusivamente os workflows do n8n.

Seu objetivo é desacoplar processamento de IA da aplicação principal.

---

## Especificação

| Item | Valor |
|------|-------|
| Provedor | DigitalOcean |
| Sistema Operacional | Ubuntu 24.04 LTS |
| vCPU | [A DEFINIR] |
| Memória | [A DEFINIR] |
| SSD | [A DEFINIR] |
| Custo mensal | US$ 12/mês |

---

## Serviços Instalados

- Docker
- Docker Compose
- n8n
- Reverse Proxy [A DEFINIR]

---

## Responsabilidades

Executa:

- pipelines;
- chamadas para APIs externas;
- processamento de respostas;
- transformação de payloads.

---

## Restrições

O Droplet 2:

NÃO possui:

- PostgreSQL;
- Redis;
- Laravel;
- Horizon;
- acesso SSH compartilhado com usuários da aplicação.

Também nunca realiza autenticação de usuários.

---

# 3.5 Comunicação entre Droplets

Toda comunicação ocorre utilizando exclusivamente a rede privada (VPC) da DigitalOcean.

Fluxo:

```text
Laravel

↓

HTTP POST

↓

IP Privado do Droplet 2

↓

n8n

↓

HTTP POST

↓

Laravel Callback
```

Não existe comunicação utilizando IP público entre os servidores.

---

## Regras Obrigatórias

Toda chamada Laravel → n8n deve utilizar:

- endereço privado;
- HTTPS quando aplicável;
- autenticação por header secreto.

Nunca utilizar:

- localhost;
- IP público;
- domínio Cloudflare.

---

## Portas

As portas utilizadas internamente permanecem **[A DEFINIR]**.

A documentação disponível não especifica portas de produção.

---

# 3.6 Comunicação Externa

## Usuário → Laravel

Fluxo:

```text
Browser

↓

Cloudflare

↓

Nginx

↓

Laravel
```

---

## Laravel → Firebase

Utilizado para:

- validação do ID Token.

---

## Laravel → Stripe

Utilizado para:

- checkout;
- assinaturas;
- webhooks.

---

## Laravel → n8n

Utilizado para:

- iniciar pipelines.

---

## n8n → APIs

Chamadas para:

- Apify;
- Perplexity;
- Gemini;
- Claude.

---

## n8n → Laravel

Utilizado exclusivamente para:

- callback final;
- callback de erro.

---

# 3.7 Estratégia de SSL

Toda criptografia TLS é gerenciada pelo Cloudflare.

Responsabilidades:

- emissão;
- renovação;
- revogação.

A aplicação Laravel não gerencia certificados manualmente.

---

## Política

Todo tráfego externo utiliza HTTPS.

HTTP simples deve ser redirecionado automaticamente.

---

# 3.8 DNS

A documentação identifica o Cloudflare como responsável pelo gerenciamento DNS.

Hosts conhecidos:

| Host | Finalidade |
|-------|------------|
| app | Aplicação principal |
| api | Backend/API |
| [A DEFINIR] | n8n |

---

# 3.9 Backup

A estratégia oficial utiliza o serviço nativo de backups da DigitalOcean.

Serviço:

DigitalOcean Backups

Custo aproximado:

US$ 2/mês

---

## Escopo

Inclui:

- snapshots do Droplet;
- recuperação de desastre.

---

## Banco de Dados

A documentação fornecida não especifica:

- política de dumps PostgreSQL;
- retenção;
- backup incremental.

Esses itens permanecem:

**[A DEFINIR]**

---

# 3.10 Escalabilidade

A arquitetura foi desenhada para permitir evolução incremental.

Separações existentes:

- aplicação;
- automações;
- banco;
- filas.

Possíveis evoluções futuras:

- Redis dedicado;
- PostgreSQL gerenciado;
- múltiplos Workers Horizon;
- múltiplos servidores Laravel;
- balanceador de carga.

Essas evoluções não alteram o domínio do sistema.

---

# 3.11 Custos Conhecidos

| Recurso | Custo |
|----------|-------:|
| Droplet 1 | US$ 24/mês |
| Droplet 2 | US$ 12/mês |
| DigitalOcean Backups | US$ 2/mês |
| Cloudflare | Free |
| Firebase | Conforme uso |
| Stripe | Conforme transações |
| APIs de IA | Conforme consumo |

---

> ⚠️ **Regra crítica:** Nenhum componente externo além do Laravel pode possuir acesso direto ao PostgreSQL. Toda comunicação entre os Droplets deve ocorrer exclusivamente pela VPC privada autenticada. O n8n nunca deve ser exposto como backend público da aplicação.

---

# 4. Modelo de Dados (PostgreSQL)

## 4.1 Visão Geral

O PostgreSQL constitui o banco de dados oficial do Arkion.

A modelagem privilegia:

- consistência transacional;
- suporte nativo a JSONB;
- índices GIN;
- consultas relacionais;
- versionamento implícito dos resultados gerados por IA.

Todos os dados persistentes pertencem ao domínio Laravel.

O n8n nunca possui acesso direto ao banco.

---

## 4.2 Princípios de Modelagem

A modelagem segue as seguintes regras obrigatórias:

- toda tabela possui chave primária `id`;
- todas as tabelas de domínio utilizam `created_at` e `updated_at`;
- relacionamentos utilizam chaves estrangeiras explícitas;
- resultados de IA são armazenados em JSONB;
- consultas devem sempre ser escopadas pela Brand ativa;
- dados pertencem ao usuário através da Brand, nunca diretamente quando fizerem parte do domínio de marketing.



## 4.3 Tabela `users`

### Propósito

Armazena os usuários autenticados pelo Firebase e seus metadados de assinatura, consumo de créditos e preferências da aplicação.

A autenticação **não utiliza senha local**. O registro representa apenas a identidade interna do domínio Laravel.

---

### Colunas

| Coluna | Tipo | Constraints | Descrição |
|---------|------|-------------|-----------|
| id | bigint | PK | Identificador interno |
| firebase_uid | string | UNIQUE, NOT NULL | UID recebido do Firebase |
| name | string | NOT NULL | Nome do usuário |
| email | string | UNIQUE, NOT NULL | Email principal |
| avatar_url | string | nullable | Foto do usuário |
| locale | string | default 'en' | Idioma preferido |
| theme | string | default 'dark' | Preferência de tema |
| credits_balance | integer | default 0 | **Legado.** Saldo único, a ser removido pela migração da Seção 12.8 |
| purchased_credits_balance | integer | default 0 | **Carteira pessoal** — comprada pelo próprio usuário, nunca expira, portátil entre organizações (Seção 12.2). Migration pendente |
| is_admin | boolean | default false | **Staff da plataforma Arkion**, nunca papel em organização (Seções 14.3 e 20.7) |
| credits_used_this_month | integer | default 0 | Consumo mensal acumulado |
| last_credit_reset_at | timestamp | nullable | Última renovação de créditos |
| email_verified_at | timestamp | nullable | Compatibilidade Laravel |
| remember_token | string | nullable | Compatibilidade Laravel |
| created_at | timestamp | NOT NULL | Auditoria |
| updated_at | timestamp | NOT NULL | Auditoria |

---

### Índices

```
PRIMARY KEY (id)

UNIQUE(firebase_uid)

UNIQUE(email)
```

---

### Relacionamentos

```
User
 ├── belongsToMany(Organization) through organization_user   ← com role por organização
 ├── hasMany(Execution)
 └── hasMany(Notification)
```

> ⚠️ **Regra crítica:** `User hasMany(Project)` e `User hasOne(Subscription)` **deixam de existir** como relações de propriedade. A Marca pertence à Organização e a assinatura é da Organização (Seção 20.2). A **carteira pessoal de créditos** é o único recurso que pertence diretamente a um usuário.

Nenhum saldo de plano existe em `users`: `plan_credits_balance` vive exclusivamente em `organizations` (Seção 12.2).

---

### Política de Retenção

Não definida na documentação.

**[A DEFINIR]**

---

# 4.4 Tabela `projects`

> Apesar do nome físico da tabela ser `projects`, ela representa conceitualmente uma **Brand** (workspace).

Todo dado estratégico pertence a uma Brand.

---

## Propósito

Representa um workspace independente.

Cada Brand possui:

- identidade;
- ativos;
- pesquisas;
- estratégias;
- execuções.

Uma Organização pode possuir inúmeras Brands. **Nenhuma Brand existe fora de uma Organização** (Seção 20.2).

A aplicação mantém exatamente **uma Brand ativa** durante cada sessão, e ela precisa pertencer à Organização ativa (Seção 20.5).

---

### Colunas

| Coluna | Tipo | Constraints |
|---------|------|-------------|
| id | bigint | PK |
| organization_id | bigint | FK organizations, **NOT NULL** |
| user_id | bigint | FK users, nullable — apenas auditoria de criação |
| parent_project_id | bigint | FK projects, **nullable** |
| name | string | NOT NULL |
| slug | string | UNIQUE |
| logo | string | nullable |
| color | string | nullable |
| created_at | timestamp | NOT NULL |
| updated_at | timestamp | NOT NULL |

`organization_id` é a coluna de propriedade da Brand e é **NOT NULL**: não existe marca órfã.

`user_id` deixa de ser critério de propriedade e passa a significar apenas **quem criou a Brand** — dado de auditoria, nunca critério de autorização ou de escopo de consulta (Seção 20.2).

`parent_project_id` é auto-referencial e opcional. Quando preenchido, a Brand é uma **sub-brand** da Brand apontada, que precisa pertencer à **mesma `organization_id`**. Quando nulo, a Brand é de topo. A hierarquia completa está documentada na Seção 19 — Hierarquia de Marcas (Umbrella Brands).

> 🔁 **SUPERSEDED:** a regra "o parent precisa pertencer ao mesmo `user_id`" da revisão 2.0 foi substituída por "o parent precisa pertencer à mesma `organization_id`".

**Estado:** migrations pendentes. As colunas são documentação de arquitetura; o schema atual ainda não as possui.

---

### Índices

```
INDEX(organization_id)

INDEX(parent_project_id)

UNIQUE(slug)
```

---

### Relacionamentos

```
Organization

↓

Projects

↓

Executions

↓

Assets

↓

Research
```

---

### Política de Retenção

Ao excluir uma Brand, o comportamento de cascata não está documentado.

**[A DEFINIR]**

---

# 4.5 Tabela `executions`

## Propósito

Representa qualquer execução enviada ao mecanismo de IA.

Cada registro representa exatamente um pipeline.

Nunca existem múltiplos pipelines no mesmo registro.

---

### Colunas

| Coluna | Tipo | Constraints |
|---------|------|-------------|
| id | bigint | PK |
| project_id | bigint | FK |
| user_id | bigint | FK |
| pipeline_type | string | NOT NULL |
| status | enum | NOT NULL |
| config | jsonb | NOT NULL |
| result | jsonb | nullable |
| tokens_used | integer | default 0 |
| credits_consumed | integer | NOT NULL |
| started_at | timestamp | nullable |
| finished_at | timestamp | nullable |
| error_message | text | nullable |
| created_at | timestamp | NOT NULL |
| updated_at | timestamp | NOT NULL |

---

## Status

Os documentos identificam uma máquina de estados baseada em execução assíncrona.

Estados conhecidos:

```
pending

processing

completed

failed
```

Outros estados permanecem:

**[A DEFINIR]**

---

## Campo `config`

Tipo:

```
JSONB
```

Contém a configuração enviada ao pipeline.

Exemplo conceitual:

```json
{
    "brand_id": 4,
    "pipeline_type": "brand_research",
    "language": "en",
    "inputs": {}
}
```

A estrutura exata depende do pipeline.

---

## Campo `result`

Tipo:

```
JSONB
```

Contém todo o retorno produzido pela IA.

Pode ultrapassar facilmente dezenas de kilobytes.

---

### Regra obrigatória

**Nunca carregar `result` em listagens.**

Sempre utilizar:

```php
Execution::select([
'id',
'status',
'pipeline_type',
'created_at'
])
```

Jamais:

```php
Execution::all();
```

---

### Índices

```
PRIMARY(id)

INDEX(project_id)

INDEX(user_id)

INDEX(status)

INDEX(pipeline_type)

GIN(result)

GIN(config)
```

---

### Observação importante

Os documentos indicam que os resultados podem crescer significativamente.

Por esse motivo:

- JSONB é obrigatório;
- índice GIN é obrigatório;
- consultas administrativas nunca devem selecionar `result` sem necessidade.

---

### Relacionamentos

```
Execution

belongsTo(User)

belongsTo(Project)
```

---

### Política de Retenção

Não documentada.

**[A DEFINIR]**

---

# 4.6 Tabela `video_references`

## Propósito

Armazena referências de vídeos utilizados pelos pipelines de pesquisa.

Esses registros constituem um banco reutilizável para análises futuras.

---

### Colunas

| Coluna | Tipo |
|---------|------|
| id | bigint |
| external_id | string |
| platform | string |
| title | string |
| author | string |
| url | string |
| metadata | jsonb |
| created_at | timestamp |
| updated_at | timestamp |

---

### Índices

```
PRIMARY(id)

UNIQUE(external_id)
```

---

### Estratégia de Persistência

Sempre utilizar UPSERT baseado em:

```
external_id
```

Nunca criar duplicatas.

---

### Campo metadata

Tipo:

```
JSONB
```

Conteúdo:

**[A DEFINIR]**

---

# 4.7 Tabela `plan_limits`

## Propósito

Centraliza todas as regras de consumo de créditos.

Os custos nunca podem ser codificados diretamente no Laravel.

Toda consulta deve utilizar esta tabela.

---

### Colunas

| Coluna | Tipo |
|---------|------|
| id | bigint |
| plan | string |
| action | string |
| credits | integer |
| created_at | timestamp |
| updated_at | timestamp |

---

### Exemplos

| Plano | Ação | Créditos |
|--------|------|----------|
| Starter | Brand Research | 50 |
| Starter | Briefing | 5 |
| Starter | Image Generation | 7 |

---

### Regras

Os documentos determinam que:

- diferentes ações possuem custos diferentes;
- o middleware consulta estes valores antes da execução;
- Redis pode cachear esses registros.

---

### Índices

```
INDEX(plan)

INDEX(action)
```

---

# 4.8 Subscriptions

A plataforma utiliza Laravel Cashier.

As tabelas padrão do Cashier permanecem válidas.

Incluem:

```
subscriptions

subscription_items
```

Os documentos não especificam alterações estruturais.

> **Pendência do Mês 4:** hoje `subscriptions.user_id` e as colunas `stripe_*` estão em `users`, porque o `Billable` está em `User`. Com a Organização como entidade de faturamento e o Owner como único responsável por ele (ADR-010), o customer Stripe deve ser a `Organization`. O levantamento e o escopo da migração estão na Seção 12.20.

---

# 4.9 Notifications

A aplicação utiliza notificações Laravel.

Tabela:

```
notifications
```

Sem customizações documentadas.

---

# 4.10 Tabelas do Laravel

Mantêm-se as tabelas padrão:

```
cache

cache_locks

jobs

job_batches

failed_jobs

sessions

password_reset_tokens

migrations
```

---

# 4.11 Relacionamento Geral

```text
Users

│

├──────────────┐

│              │

Projects   Subscriptions

│

│

Executions

│

│

Result (JSONB)
```

---

# 4.12 Estratégias de Indexação

Obrigatórias:

- PK em todas as tabelas
- FK indexadas
- UNIQUE onde aplicável
- GIN para JSONB
- índices para filtros frequentes

Os documentos não especificam índices parciais.

---

# 4.13 Convenções

Toda tabela utiliza:

```
snake_case
```

Todos os nomes seguem convenções Laravel.

---

# 4.14 Convenções JSONB

Os documentos definem que JSONB é utilizado para preservar flexibilidade.

Campos conhecidos:

```
config

result

metadata
```

Todos devem permanecer válidos mesmo com evolução dos pipelines.

Migrações futuras nunca devem depender da estrutura interna desses JSONs.

---

> ⚠️ **Regra crítica:** O campo `result` pode conter dezenas de kilobytes de dados estruturados. Nunca carregue esse campo em consultas de listagem, dashboards ou tabelas administrativas. Utilize `select()` explícito e carregue o conteúdo completo apenas em páginas de visualização detalhada.

---

# 4.15 Tabelas `organizations` e `organization_user`

A Organização é a entidade de faturamento da plataforma e a dona de todas as Marcas. As regras de propriedade, papéis e contexto de sessão estão na **Seção 20 — Organizações e Hierarquia de Propriedade**.

**Estado:** migrations pendentes. Estas tabelas são documentação de arquitetura; o schema atual ainda não as possui.

---

## Tabela `organizations`

### Propósito

Representa a unidade de faturamento e de propriedade. Concentra o plano assinado e as duas carteiras de crédito da operação (Seção 12.2).

### Colunas

| Coluna | Tipo | Constraints | Descrição |
|---------|------|-------------|-----------|
| id | bigint | PK | Identificador interno |
| name | string | NOT NULL | Nome da organização |
| slug | string | UNIQUE, NOT NULL | Identificador legível |
| plan | string | **nullable** | Plano assinado. `null` = sem assinatura ativa (Seção 12.18) |
| plan_credits_balance | integer | default 0 | Créditos do plano — renovam mensalmente, não cumulativos |
| purchased_credits_balance | integer | default 0 | Créditos comprados pela org — nunca expiram |
| created_at | timestamp | NOT NULL | Auditoria |
| updated_at | timestamp | NOT NULL | Auditoria |

`plan` é **NULLABLE por decisão confirmada**. Toda organização recém-criada — inclusive a "Personal" automática — nasce sem plano. Ver Seção 12.18.

### Índices

```
PRIMARY KEY (id)

UNIQUE(slug)
```

### Relacionamentos

```
Organization
 ├── belongsToMany(User) through organization_user
 ├── hasMany(Project)
 └── hasOne(Subscription)
```

---

## Tabela `organization_user`

### Propósito

Pivot que registra a participação de um usuário em uma organização **e o papel dele nessa organização**. Um usuário pertence a N organizações, com um papel por organização.

### Colunas

| Coluna | Tipo | Constraints | Descrição |
|---------|------|-------------|-----------|
| id | bigint | PK | Identificador interno |
| organization_id | bigint | FK organizations, NOT NULL, cascade | Organização |
| user_id | bigint | FK users, NOT NULL, cascade | Usuário membro |
| role | string | NOT NULL | `owner`, `admin` ou `member` — nada além disso (Seção 20.4, ADR-010) |
| created_at | timestamp | NOT NULL | Auditoria |
| updated_at | timestamp | NOT NULL | Auditoria |

### Índices

```
PRIMARY KEY (id)

UNIQUE(organization_id, user_id)

UNIQUE(organization_id) WHERE role = 'owner'     ← índice único parcial

INDEX(user_id)
```

`UNIQUE(organization_id, user_id)` garante um único papel por usuário em cada organização.

O índice único parcial `UNIQUE(organization_id) WHERE role = 'owner'` garante que **nenhuma organização tenha mais de um Owner**. O Schema Builder do Laravel não expressa índices parciais; a migration o cria por `DB::statement()`. O índice garante o teto ("no máximo um"); o piso ("pelo menos um") é invariante de domínio, verificado dentro da transação (Seção 20.4).

### Regras de Integridade

- `role` aceita exclusivamente `owner`, `admin` e `member`. A restrição vale no domínio (Enum `App\Enums\OrganizationRole` + validação); a decisão entre `CHECK` constraint e Enum PostgreSQL nativo é **[A DEFINIR]**.
- **Toda organização tem exatamente um Owner** (Seção 20.4). O teto é garantido pelo índice único parcial; o piso — a organização nunca fica sem Owner — é verificado dentro da transação, sob lock. O Owner só deixa de sê-lo por transferência de posse.

> 🔁 **SUPERSEDED (ADR-010) — Dois valores de `role` e "nunca sem Admin".** A revisão 2.1 desta seção limitava `role` a `admin` e `member` ("nada além disso") e registrava como invariante que **uma organização nunca pode ficar sem nenhum Admin** — o último Admin não podia ser removido nem rebaixado, e o banco não expressava a regra. Ambos estão superados pela ADR-010: `role` tem três valores, e o invariante passa a ser sobre o **Owner**, com parte dele (o teto de um Owner) garantida por índice. Uma organização pode legitimamente não ter nenhum Admin — o Owner sozinho basta.
- Remover a última participação de um usuário **não** o deixa sem organização em nenhum fluxo previsto; o destino da carteira pessoal nesse cenário é **[A DEFINIR]** (Seção 20.8).

### Política de Retenção

Excluir uma organização com Marcas e Executions é **[A DEFINIR]** (Seção 20.8).

---

# 5. Arquitetura de Autenticação e Segurança

## 5.1 Visão Geral

A autenticação do Arkion é baseada no **Firebase Authentication**, enquanto toda autorização e aplicação das regras de negócio permanecem sob responsabilidade exclusiva do Laravel.

Essa separação estabelece um limite claro de responsabilidades:

- **Firebase** comprova a identidade do usuário.
- **Laravel** determina o que esse usuário pode fazer.

O sistema não utiliza autenticação nativa do Laravel (Breeze, Jetstream, Fortify ou Sanctum) para login por senha.

Toda sessão autenticada deriva da validação de um **Firebase ID Token**.


## 5.2 Fluxo de Autenticação (Firebase → Laravel)

Todo processo de autenticação segue rigorosamente o fluxo abaixo.

```text
Usuário

↓

Tela de Login

↓

Firebase Authentication

↓

Firebase ID Token

↓

Request HTTP

Authorization: Bearer <token>

↓

Laravel

↓

VerifyFirebaseToken Middleware

↓

Firebase Admin SDK

↓

Token válido?

├── NÃO → HTTP 401
│
└── SIM
      │
      ▼

Busca (ou cria) usuário local

↓

Carrega assinatura

↓

Carrega saldo de créditos

↓

Carrega contexto da Brand ativa

↓

Controller / Livewire Component
```

---

## Etapa 1 — Autenticação

O usuário autentica-se utilizando o Firebase Authentication.

O mecanismo específico de login (Google, Email/Senha, OAuth etc.) não está documentado.

**[A DEFINIR]**

---

## Etapa 2 — Emissão do ID Token

Após autenticação, o Firebase gera um **ID Token**.

Esse token representa a identidade do usuário.

Laravel nunca gera JWT próprio.

Laravel nunca emite Access Tokens.

---

## Etapa 3 — Requisição

Toda chamada autenticada envia:

```http
Authorization: Bearer eyJhbGciOi...
```

---

## Etapa 4 — Middleware

Toda rota protegida passa obrigatoriamente por:

```
VerifyFirebaseToken
```

---

## Etapa 5 — Validação

O middleware valida:

- assinatura;
- expiração;
- emissor;
- audience;
- integridade.

Utilizando o Firebase Admin SDK.

---

## Etapa 6 — Usuário Local

Após validação:

```
firebase_uid

↓

users.firebase_uid

↓

User Model
```

Caso o registro ainda não exista:

O comportamento não está explicitamente documentado.

**[A DEFINIR]**

---

## Etapa 7 — Contexto

Após localizar o usuário:

Laravel passa a controlar:

- assinatura;
- créditos;
- Brand ativa;
- autorização.

---

# 5.3 Middleware `VerifyFirebaseToken`

## Responsabilidade

Autenticar toda requisição protegida.

Ele representa a fronteira entre:

Firebase

↓

Laravel

---

## Fluxo Interno

```text
Recebe Request

↓

Extrai Authorization Header

↓

Existe Token?

├── NÃO → 401
│
└── SIM
      │
      ▼

Valida Token Firebase

↓

Token Inválido?

├── SIM → 401
│
└── NÃO
      │
      ▼

Busca User

↓

$request->setUserResolver()

↓

Próximo Middleware
```

---

## Responsabilidades

- validar ID Token;
- localizar usuário;
- anexar usuário autenticado ao Request.

---

## Não é responsabilidade

Este middleware nunca valida:

- assinatura;
- saldo de créditos;
- ownership;
- permissões.

Essas verificações ocorrem posteriormente.

---

# 5.4 Middleware `CheckSubscription`

## Responsabilidade

Verificar se o usuário possui assinatura válida.

Fluxo:

```text
User

↓

Subscription

↓

Ativa?

├── NÃO → 402 / Redirect
│
└── SIM
      │
      ▼

Continue
```

---

## Verificações

Confirma:

- assinatura existente;
- assinatura ativa.

Regras específicas de grace period ou trial não estão documentadas.

**[A DEFINIR]**

---

## Quando Executa

Executa antes de qualquer ação que consuma créditos.

---

# 5.5 Middleware `EnsureCreditBalance`

Este middleware substitui completamente qualquer lógica baseada em limite de execuções.

Toda autorização operacional ocorre através do saldo de créditos.

---

## Fluxo

```text
Pipeline solicitado

↓

Identifica ação

↓

Consulta plan_limits

↓

Obtém custo

↓

User.credits_balance

↓

Saldo suficiente?

├── NÃO
│
│ HTTP 402
│ CTA Buy Credits
│
└── SIM
      │
      ▼

Permite execução
```

---

## Responsabilidades

- identificar ação;
- consultar custo;
- validar saldo.

---

## Não decrementa créditos

O middleware apenas valida.

O débito ocorre durante o processamento da execução.

---

## Fonte da Verdade

O custo nunca pode ser definido em:

- constantes;
- enums;
- controllers;
- componentes Livewire.

Sempre:

```
plan_limits
```

---

## Cache

Os documentos permitem cache via Redis.

Fluxo esperado:

```text
Redis

↓

plan_limits

↓

Middleware
```

---

# 5.6 Autenticação Laravel ↔ n8n

A comunicação entre os dois servidores utiliza um segredo compartilhado.

Header:

```http
X-N8N-Secret:
```

---

## Geração

O segredo é armazenado como variável de ambiente.

Nome:

**[A DEFINIR]**

---

## Armazenamento

Nunca armazenar em:

- banco;
- código;
- arquivos versionados.

Somente:

```
.env
```

---

## Validação

A comparação deve utilizar:

```php
hash_equals(
    config('services.n8n.secret'),
    $request->header('X-N8N-Secret')
);
```

Nunca utilizar:

```php
==
```

ou

```php
===
```

para comparação de segredos.

---

## Objetivo

Impedir callbacks falsificados.

---

# 5.7 Fluxo Laravel → n8n

```text
Job

↓

HTTP POST

↓

Header:

X-N8N-Secret

↓

n8n

↓

Validação

↓

Pipeline
```

---

# 5.8 Fluxo n8n → Laravel

```text
Workflow Finalizado

↓

HTTP POST

↓

Laravel Callback

↓

Validação Header

↓

Atualização Execution

↓

Broadcast
```

---

# 5.9 Policies

A documentação identifica duas Policies obrigatórias.

---

## ExecutionPolicy

Responsável por controlar acesso às execuções.

Permissões esperadas:

```
view

update

delete
```

Toda verificação deve considerar:

- usuário;
- Brand;
- ownership.

---

## ProjectPolicy

Responsável por controlar acesso às Brands.

Permissões esperadas:

```
view

update

delete

restore
```

A autorização verifica se o usuário é **membro da organização dona da Brand**, e não se `projects.user_id` é dele.

O papel também é consultado (Seção 20.4, ADR-010):

| Permissão | Quem |
|-----------|------|
| `view` | Qualquer membro — Owner, Admin ou Member |
| `create`, `update`, `delete`, `restore` | Somente Owner e Admin |

Editar o Brand Core e os demais dados da Marca é `update`. Um Member enxerga as Marcas e roda as ferramentas de IA nelas, mas não as cria, edita nem exclui. A verificação usa os métodos `can*()` do Enum `OrganizationRole`, nunca comparação de strings de papel.

> 🔁 **SUPERSEDED (ADR-010):** a revisão 2.1 autorizava `update` e `delete` de Brand para qualquer membro ("Gerenciar Marcas é capacidade de Admin e Member") e restringia por papel apenas as ações de faturamento e participação. Member deixou de gerenciar Marcas.

---

## Escopo

Nunca basta verificar:

```
organization_id
```

Também deve ser validado:

```
project_id
```

da Brand ativa, que precisa pertencer à organização ativa (Seção 20.5).

> 🔁 **SUPERSEDED:** a revisão 2.0 exigia aqui a verificação de `user_id`. Ela é substituída pela verificação de `organization_id` — `user_id` deixou de ser critério de isolamento (Seção 20.6).

---

# 5.10 Matriz de Segurança

| Camada | Responsabilidade |
|---------|------------------|
| Firebase | Identidade |
| VerifyFirebaseToken | Validação do Token |
| CheckSubscription | Assinatura |
| EnsureCreditBalance | Créditos |
| Policy | Ownership |
| Controller | Regras específicas |
| Model | Persistência |

---

# 5.11 Endpoints × Segurança

| Endpoint | Firebase | Subscription | Créditos | Policy |
|----------|----------|--------------|-----------|--------|
| Dashboard | ✔ | ✔ | — | ✔ |
| Brand Core | ✔ | ✔ | — | ✔ |
| Brand Assets | ✔ | ✔ | — | ✔ |
| Research | ✔ | ✔ | ✔ | ✔ |
| Brand Research | ✔ | ✔ | ✔ | ✔ |
| Briefing | ✔ | ✔ | ✔ | ✔ |
| Image Generation | ✔ | ✔ | ✔ | ✔ |
| Create Article | ✔ | ✔ | ✔ | ✔ |
| Create Post | ✔ | ✔ | ✔ | ✔ |
| Create Ad | ✔ | ✔ | ✔ | ✔ |

---

# 5.12 Segurança da Brand Ativa

O sistema é Multi-Organização e Multi-Brand.

Isso significa que dois registros acessíveis ao mesmo usuário podem não pertencer ao mesmo contexto ativo.

O contexto de sessão tem **duas** variáveis, `active_organization_id` e `active_project_id`, e a Brand ativa **precisa pertencer à organização ativa** (Seção 20.5).

Toda consulta deve respeitar simultaneamente:

```
organization_id
```

e

```
project_id
```

Exemplo correto:

```php
Execution::query()
    ->where('organization_id', $activeOrganizationId)
    ->where('project_id', $activeProjectId);
```

Nunca:

```php
Execution::whereUserId(auth()->id());
```

> 🔁 **SUPERSEDED:** a revisão 2.0 desta seção exigia o par `user_id` + `project_id`. Com o modelo de Organizações, `user_id` **deixa de ser critério de isolamento** e é substituído por `organization_id` (Seção 20.6). O usuário precisa ser membro da organização ativa — verificação que antecede qualquer consulta de domínio.

A coluna `executions.organization_id` (ou a derivação a partir de `projects.organization_id`) é **[A DEFINIR]** e será decidida na migração descrita na Seção 12.8.

---

# 5.13 Tratamento de Erros

| Situação | Resposta |
|----------|----------|
| Token ausente | HTTP 401 |
| Token inválido | HTTP 401 |
| Assinatura inexistente | HTTP 402 |
| Créditos insuficientes | HTTP 402 |
| Brand não pertence ao usuário | HTTP 403 |
| Execução de outra Brand | HTTP 403 |

---

> ⚠️ **Regra crítica:** A autenticação comprova apenas a identidade do usuário. Toda autorização continua sendo responsabilidade do Laravel. Nunca permita acesso a uma Execution, Project ou qualquer outro recurso apenas com base no `user_id`; toda consulta deve ser obrigatoriamente escopada pela Brand ativa (`project_id`) e validada por Policy.

---

# 6. O Pipeline de Execução

## 6.1 Visão Geral

Todo processamento de IA no Arkion segue uma arquitetura assíncrona baseada em filas.

Nenhuma requisição HTTP do usuário permanece aberta aguardando processamento de modelos de IA.

O Laravel é responsável por:

- validar a requisição;
- verificar assinatura;
- verificar saldo de créditos;
- registrar a execução;
- despachar o Job.

O n8n é responsável apenas por:

- executar o pipeline;
- integrar APIs externas;
- devolver o resultado estruturado.

---

## Fluxo Geral

```text
Usuário

↓

Livewire

↓

Laravel

↓

Policies

↓

CheckSubscription

↓

EnsureCreditBalance

↓

Execution (pending)

↓

Queue

↓

Job

↓

HTTP

↓

n8n

↓

IA

↓

Callback

↓

Execution (completed)

↓

Broadcast

↓

Livewire
```

---

## Estados da Execução

```text
pending

↓

processing

↓

completed
```

ou

```text
pending

↓

processing

↓

failed
```

---

## Estrutura Comum

Independentemente do tipo de pipeline, todas as execuções seguem as mesmas etapas:

1. validação da requisição;
2. validação da Brand ativa;
3. validação da assinatura;
4. validação de créditos;
5. criação da Execution;
6. envio para fila;
7. chamada HTTP ao n8n;
8. processamento externo;
9. callback;
10. persistência do resultado;
11. broadcast em tempo real.

Nas próximas subseções será detalhado cada pipeline individualmente (`Brand Research`, `Briefing Generation`, `Image Generation`, `Create Article`, `Create Post` e `Create Ad`), incluindo atores, estados, consumo de créditos e cenários completos de falha.


## 6.2 Pipeline — Brand Research

### Objetivo

Executar uma pesquisa aprofundada sobre a marca utilizando fontes externas e modelos de IA para produzir uma base estratégica estruturada.

Este pipeline representa o processamento mais custoso identificado na documentação.

---

### Consumo

| Ação | Créditos |
|------|----------:|
| Brand Research | **50** |

---

### Ator

Usuário autenticado.

---

### Entrada

Origina-se da interface:

```
Research and Strategy

↓

Brand Research
```

Os campos específicos enviados pelo formulário não estão integralmente documentados.

**[A DEFINIR]**

---

### Fluxo

```text
Usuário

↓

Livewire

↓

Validate Request

↓

Execution::create()

status = pending

↓

Dispatch Job

↓

Queue

↓

n8n

↓

Apify

↓

Perplexity

↓

Gemini

↓

Claude

↓

Resultado JSON

↓

Laravel Callback

↓

Execution Completed

↓

Broadcast
```

---

### Persistência

Ao iniciar:

```
status = pending
```

Durante processamento:

```
status = processing
```

Ao finalizar:

```
status = completed

result = JSONB

credits_consumed = 50
```

---

### Resultado

O resultado é persistido integralmente em:

```
executions.result
```

Nunca apenas como texto.

---

### Cenários de Falha

#### Falha de validação

Nenhum registro é criado.

---

#### Créditos insuficientes

```
HTTP 402

Execution NÃO criada.
```

---

#### Falha ao enviar Job

Execution:

```
status = failed
```

Erro registrado.

---

#### Timeout do n8n

Execution:

```
failed
```

Sem débito adicional além da política definida pelo domínio.

A política de estorno não está documentada.

**[A DEFINIR]**

---

#### Callback inválido

Descartado.

Execution permanece:

```
processing
```

até timeout interno.

---

# 6.3 Pipeline — Briefing Generation

## Objetivo

Produzir um briefing estruturado para orientar futuras gerações.

---

## Consumo

| Ação | Créditos |
|------|----------:|
| Briefing Generation | **5** |

---

## Fluxo

```text
Briefing

↓

Execution

↓

Queue

↓

n8n

↓

IA

↓

JSON

↓

Callback

↓

Completed
```

---

## Resultado

Persistido em:

```
result JSONB
```

---

## Estado Final

```
completed
```

ou

```
failed
```

---

# 6.4 Pipeline — Image Generation

## Objetivo

Produzir imagens para ativos da Brand.

---

## Consumo

| Ação | Créditos |
|------|----------:|
| Image Generation | **7** |

---

## Fluxo

```text
Execution

↓

Queue

↓

n8n

↓

Modelo IA

↓

Imagem

↓

Laravel

↓

Execution
```

---

## Persistência

Os documentos não especificam:

- formato do retorno;
- armazenamento físico da imagem;
- object storage.

Esses itens permanecem:

**[A DEFINIR]**

---

# 6.5 Pipeline — Create Article

## Objetivo

Gerar um artigo completo utilizando a memória da Brand.

---

## Consumo

```
[A DEFINIR]
```

---

## Fluxo

```text
Execution

↓

Queue

↓

n8n

↓

Claude

↓

Resultado

↓

Execution
```

---

## Resultado

JSON estruturado.

Estrutura:

**[A DEFINIR]**

---

# 6.6 Pipeline — Create Post

## Objetivo

Gerar conteúdo para redes sociais.

---

## Consumo

```
[A DEFINIR]
```

---

## Fluxo

Mesmo fluxo arquitetural dos demais pipelines.

---

## Resultado

Persistido em:

```
result JSONB
```

---

# 6.7 Pipeline — Create Ad

## Objetivo

Gerar peças publicitárias.

---

## Consumo

```
[A DEFINIR]
```

---

## Fluxo

```text
Execution

↓

Queue

↓

n8n

↓

Modelo IA

↓

Resultado

↓

Callback

↓

Execution
```

---

# 6.8 Máquina de Estados

Todos os pipelines compartilham exatamente a mesma máquina de estados.

```text
pending

↓

processing

↓

completed
```

ou

```text
pending

↓

processing

↓

failed
```

Nenhum pipeline possui estados exclusivos documentados.

---

# 6.9 Consumo de Créditos

Fluxo esperado:

```text
EnsureCreditBalance

↓

Execution criada

↓

Pipeline executado

↓

Conclusão

↓

Débito dos créditos
```

O momento exato do débito (antes do envio ao n8n ou após confirmação de processamento) não está explicitamente definido na documentação.

**[A DEFINIR]**

---

# 6.10 Reexecuções

A documentação disponível não descreve mecanismo oficial de retry por parte do usuário.

Permanece:

**[A DEFINIR]**

---

# 6.11 Idempotência

Cada execução representa um processamento único.

Nunca reutilizar um registro existente para uma nova solicitação.

Nova solicitação ⇒ nova Execution.

---

# 6.12 Cenários Gerais de Falha

## Erro de Validação

Consequências:

- nenhuma Execution criada;
- nenhuma fila;
- nenhum débito.

---

## Sem Assinatura

Resposta:

```
HTTP 402
```

Sem criação da Execution.

CTA "Subscribe to a plan" somente para o Owner; Admin e Member veem "Ask your organization owner to subscribe to a plan." (Seção 12.12, CTA por papel).

---

## Sem Créditos

Resposta:

```
HTTP 402
```

Interface deve apresentar CTA:

```
Buy AI Credits
```

A carteira de destino oferecida depende do papel: Owner escolhe entre organização e pessoal; Admin e Member, apenas pessoal (Seção 12.12, CTA por papel).

---

## Job Falhou

Execution:

```
failed
```

Erro registrado.

Broadcast enviado.

---

## Worker Offline

Execution permanece:

```
pending
```

Até que um Worker Horizon disponível processe a fila.

---

## n8n Offline

Execution:

```
failed
```

ou permanece em processamento conforme estratégia de retry.

A política oficial de retry não está documentada.

**[A DEFINIR]**

---

## APIs Externas Indisponíveis

O n8n deve retornar erro estruturado.

Laravel nunca tenta interpretar erros específicos das APIs externas.

---

## Callback Inválido

Descartado.

Nenhum dado persistido.

---

## Timeout HTTP

A chamada Laravel → n8n possui timeout próprio (documentado na seção seguinte).

Ao atingir o limite:

Execution:

```
failed
```

---

> ⚠️ **Regra crítica:** Toda execução deve possuir um registro persistido na tabela `executions` antes de qualquer comunicação com o n8n. Nenhum pipeline pode ser executado diretamente durante a requisição HTTP do usuário.

---

# 7. Contrato de Comunicação Laravel ↔ n8n

## 7.1 Visão Geral

Laravel e n8n comunicam-se exclusivamente através de requisições HTTP autenticadas.

Não existe:

- acesso compartilhado ao banco;
- acesso compartilhado ao Redis;
- RPC;
- WebSockets entre os serviços.

Todo intercâmbio de dados ocorre por payloads JSON.

---

## 7.2 Fluxo

```text
Laravel

↓

HTTP POST

↓

n8n

↓

Processamento

↓

HTTP POST

↓

Laravel Callback
```

---

## 7.3 Payload — Laravel → n8n

Todo pipeline deve enviar um payload JSON contendo, no mínimo:

```json
{
  "execution_id": 154,
  "pipeline_type": "brand_research",
  "user_id": 12,
  "project_id": 8,
  "credits_cost": 50,
  "locale": "en",
  "config": {},
  "requested_at": "2026-07-01T15:30:00Z"
}
```

### Campos

| Campo | Tipo | Obrigatório | Descrição |
|--------|------|-------------|-----------|
| execution_id | integer | Sim | Identificador da execução |
| pipeline_type | string | Sim | Tipo do pipeline |
| user_id | integer | Sim | Usuário proprietário |
| project_id | integer | Sim | Brand da execução |
| credits_cost | integer | Sim | Custo previsto em créditos |
| locale | string | Sim | Idioma da execução |
| config | object | Sim | Configuração específica do pipeline |
| requested_at | datetime ISO8601 | Sim | Timestamp de envio |

A estrutura interna de `config` varia conforme o pipeline e permanece **[A DEFINIR]** quando não especificada nos documentos.


## 7.4 Payload — n8n → Laravel (Callback)

Ao concluir um pipeline, o n8n deve realizar uma requisição HTTP para um endpoint de callback do Laravel.

O callback representa o único mecanismo permitido para atualização do estado de uma execução.

---

### Payload Base

```json
{
  "execution_id": 154,
  "status": "completed",
  "pipeline_type": "brand_research",
  "tokens_used": 18432,
  "credits_consumed": 50,
  "result": {},
  "finished_at": "2026-07-01T15:37:42Z",
  "error": null
}
```

---

### Campos

| Campo | Tipo | Obrigatório | Descrição |
|--------|------|-------------|-----------|
| execution_id | integer | Sim | Identificador da execução |
| status | string | Sim | Estado final |
| pipeline_type | string | Sim | Tipo do pipeline executado |
| tokens_used | integer | Sim | Tokens consumidos |
| credits_consumed | integer | Sim | Créditos efetivamente consumidos |
| result | object | Condicional | Resultado estruturado |
| finished_at | datetime ISO8601 | Sim | Data/hora de conclusão |
| error | object/null | Condicional | Estrutura de erro |

---

## Status Permitidos

```text
completed

failed
```

Outros estados não são documentados para callbacks provenientes do n8n.

---

## Callback de Sucesso

```json
{
  "execution_id": 154,
  "status": "completed",
  "result": {
    "...": "..."
  }
}
```

Laravel deve:

1. localizar a Execution;
2. validar o segredo compartilhado;
3. atualizar o status;
4. persistir `result`;
5. registrar `tokens_used`;
6. registrar `credits_consumed`;
7. registrar `finished_at`;
8. disparar evento de broadcast.

---

## Callback de Erro

```json
{
  "execution_id": 154,
  "status": "failed",
  "error": {
    "message": "Pipeline timeout"
  }
}
```

Laravel nunca interpreta erros internos das APIs externas.

O conteúdo de `error` é armazenado apenas para auditoria.

A estrutura completa desse objeto permanece:

**[A DEFINIR]**

---

# 7.5 Headers Obrigatórios

## Laravel → n8n

```http
Content-Type: application/json

Accept: application/json

X-N8N-Secret: ********

User-Agent: TinyAILab/Laravel
```

---

## n8n → Laravel

```http
Content-Type: application/json

Accept: application/json

X-N8N-Secret: ********
```

Headers adicionais não estão documentados.

---

# 7.6 Timeout

Toda chamada HTTP iniciada pelo Laravel deve possuir timeout máximo de:

```
10 segundos
```

Exemplo conceitual:

```php
Http::timeout(10)
```

Timeouts superiores não fazem parte da arquitetura documentada.

---

# 7.7 Estratégia de Atualização

Fluxo esperado:

```text
Execution

↓

pending

↓

Job

↓

HTTP

↓

n8n

↓

completed

↓

Callback

↓

Execution::update()

↓

Broadcast
```

A atualização do banco ocorre exclusivamente após o callback.

---

# 7.8 Idempotência

Caso o callback seja recebido mais de uma vez para a mesma execução, o comportamento esperado deve evitar atualizações inconsistentes.

A estratégia de idempotência (por exemplo, rejeição de callbacks para execuções já finalizadas) não está explicitamente documentada.

**[A DEFINIR]**

---

# 7.9 Responsabilidades

## Laravel

Responsável por:

- autenticação;
- autorização;
- validação;
- persistência;
- filas;
- broadcasting;
- créditos;
- assinatura.

---

## n8n

Responsável apenas por:

- executar pipelines;
- integrar APIs;
- montar o JSON de retorno.

---

# 7.10 Regras Invioláveis

## Regra 1

O n8n nunca acessa o banco de dados.

---

## Regra 2

O n8n nunca autentica usuários.

---

## Regra 3

O n8n nunca responde diretamente ao navegador.

Fluxo proibido:

```text
Browser

↓

n8n
```

---

## Regra 4

Laravel nunca delega regras de negócio ao n8n.

---

## Regra 5

Todo resultado produzido deve retornar para o Laravel antes de chegar ao usuário.

---

## Regra 6

Toda comunicação deve utilizar JSON.

---

## Regra 7

Todo callback deve validar:

```
X-N8N-Secret
```

antes de qualquer processamento.

---

> ⚠️ **Regra crítica:** O n8n é um mecanismo de orquestração, não um componente de domínio. Ele nunca deve acessar PostgreSQL, Redis, autenticar usuários, enviar respostas diretamente ao frontend ou implementar regras de negócio da aplicação.

---

# 7.11 Registro de Implementação — Callback do n8n

Implementado em `App\Http\Controllers\Api\WebhookController`, protegido por `App\Http\Middleware\VerifyN8nSecret` (alias `verify.n8n`) e coberto por `tests/Feature/WebhookTest.php`.

---

## Idempotência — lacuna 7.8 resolvida

A Seção 7.8 registra a estratégia de idempotência como **[A DEFINIR]**. A implementação adota a **rejeição silenciosa de callbacks para execuções já finalizadas**:

- se `executions.status` já for `completed` ou `failed`, o payload é descartado, a tentativa é registrada em log e o n8n recebe `200` com `ignored: true`;
- o `200` é deliberado: qualquer outro código faria o n8n reprocessar indefinidamente um callback que o Laravel já honrou.

Motivo da escolha: um retry do n8n após um timeout de rede pode chegar com `result` vazio. Reaplicá-lo destruiria o resultado já persistido e reemitiria o broadcast, fazendo a interface piscar entre dois estados finais.

A mesma proteção já existia em `ProcessBriefingJob` (`Execution::TERMINAL_STATUSES`), agora compartilhada como constante do Model.

---

## `credits_consumed` do payload é ignorado

A Seção 7.4 lista `credits_consumed` entre os campos que o Laravel deve registrar. **A implementação diverge deliberadamente**: o valor chega no payload, é aceito pela validação e é descartado.

Motivo: o débito ocorre na criação da Execution, a partir de `plan_limits` (Seção 12.5). Gravar o número devolvido pelo n8n colocaria o motor externo no controle da contabilidade do domínio, contra a regra 9 da `CLAUDE.md` e a Regra 4 da Seção 7.10 — e criaria divergência entre `executions.credits_consumed` e o saldo efetivamente debitado de `users.credits_balance`.

`tokens_used`, ao contrário, é gravado do payload: o n8n é a única fonte possível dessa informação.

---

## `finished_at` e o objeto `error`

| Campo do payload (7.4) | Destino na implementação |
|------------------------|--------------------------|
| `finished_at` | `executions.completed_at` ou `executions.failed_at`, conforme o status. Ausente ⇒ instante do callback |
| `error.message` | `executions.error_message`, truncado em 500 caracteres |
| `error` (objeto completo) | Log de auditoria — nunca o banco |

O objeto `error` completo não é persistido porque não existe coluna de auditoria para ele e `result` é reservado à conclusão bem-sucedida (`CLAUDE.md` §6). A mensagem trafega no `ExecutionFailed` até a interface; por isso apenas o campo textual documentado no exemplo da Seção 7.4 é gravado, nunca a estrutura bruta devolvida pelas APIs externas.

Falha sem `error.message` recebe mensagem genérica em português.

---

## Divergências de nomenclatura

| `CLAUDE.md` §4 | Implementado | Motivo |
|----------------|--------------|--------|
| `VerifyN8nWebhook.php` | `VerifyN8nSecret.php` | Nome definido no prompt da sessão; descreve o que o middleware de fato compara |
| `Requests/StoreBriefingRequest.php` | `Requests/Execution/StoreBriefingRequest.php` | A própria §4 prevê o subdiretório `Requests/Execution/`, e `Requests/Auth/` e `Requests/Project/` já seguem esse agrupamento |

Ambas são divergências de nome/local, não de arquitetura. Uma ADR pode fixar o vocabulário definitivo.

---

## Form Request sem Policy

`App\Http\Requests\Webhook\N8nResultRequest::authorize()` retorna `true`, contrariando a orientação da Seção L.4.

É uma exceção consciente e única: o fluxo não possui usuário — o n8n nunca autentica ninguém (Regra 2 da Seção 7.10) — e não há sujeito sobre o qual uma Policy possa decidir. A autorização inteira do endpoint é o `hash_equals()` do middleware, executado antes da instanciação do Form Request. A validação permanece fora do Controller, que é o que a regra 5 da `CLAUDE.md` protege.

---

## Ausência de rate limiting

`bootstrap/app.php` não chama `throttleApi()`, de modo que o grupo `api` inteiro roda sem `throttle`. A ausência é intencional e está coberta por teste: um limite por IP descartaria callbacks legítimos de pipelines já processados e já cobrados do usuário.

---

# 8. Arquitetura de Filas e Jobs

## 8.1 Visão Geral

Toda execução de IA é obrigatoriamente processada de forma assíncrona.

O usuário nunca aguarda a conclusão de um pipeline durante a requisição HTTP.

Laravel Horizon é responsável por:

- gerenciamento das filas;
- monitoramento dos Workers;
- retry de Jobs;
- métricas operacionais.

Redis atua como backend das filas.

---

## Fluxo Geral

```text
Usuário

↓

Controller / Livewire

↓

Execution

↓

Dispatch(Job)

↓

Redis

↓

Worker Horizon

↓

n8n

↓

Callback

↓

Broadcast
```

---

## 8.2 Filas

A documentação identifica três filas lógicas.

| Fila | Finalidade |
|------|------------|
| default | Processamento geral de pipelines |
| viral-refs | Atualização da base de referências virais |
| notifications | Notificações assíncronas |

---

## default

Responsável por:

- Brand Research;
- Briefing;
- Image Generation;
- Create Article;
- Create Post;
- Create Ad.

---

## viral-refs

Responsável exclusivamente pela atualização da tabela:

```
video_references
```

---

## notifications

Responsável por:

- notificações;
- broadcasts assíncronos quando aplicável;
- processos auxiliares de comunicação.

---

# 8.3 Job — ExecutePipelineJob

## Responsabilidade

Representa o Job principal da plataforma.

É responsável por iniciar qualquer pipeline no n8n.

---

### Fila

```
default
```

---

### tries

```
[A DEFINIR]
```

---

### backoff

```
[A DEFINIR]
```

---

### timeout

```
10 segundos (requisição HTTP ao n8n)
```

O tempo total permitido para o Job não está documentado.

**[A DEFINIR]**

---

### handle()

Fluxo esperado:

```text
Recebe Execution

↓

Atualiza status

↓

processing

↓

HTTP POST

↓

n8n

↓

Finaliza
```

O Job não conclui a execução; a conclusão depende do callback do n8n.

---

### failed()

Responsabilidades:

- registrar erro;
- atualizar status para `failed`;
- disparar evento de falha.

---

# 8.4 Job — UpdateViralReferencesJob

## Responsabilidade

Atualizar o banco de referências virais.

---

### Fila

```
viral-refs
```

---

### tries

```
[A DEFINIR]
```

---

### backoff

```
[A DEFINIR]
```

---

### handle()

Fluxo esperado:

```text
Consulta

↓

Pipeline

↓

Upsert

↓

video_references
```

---

### failed()

Registrar erro para auditoria.

---

# 8.5 Job — SendNotificationJob

## Responsabilidade

Processar notificações assíncronas.

---

### Fila

```
notifications
```

---

### tries

```
[A DEFINIR]
```

---

### backoff

```
[A DEFINIR]
```

---

### handle()

Enviar a notificação correspondente.

O mecanismo específico (database, mail, push etc.) não está completamente definido na documentação.

**[A DEFINIR]**

---

### failed()

Registrar erro.

---

# 8.6 Horizon

Laravel Horizon constitui a interface oficial de gerenciamento das filas.

---

## Supervisors

A configuração detalhada de Supervisors não está presente na documentação.

Permanece:

**[A DEFINIR]**

---

## Workers

Os documentos não especificam a quantidade de Workers por fila.

Permanece:

**[A DEFINIR]**

---

## Dashboard

O acesso ao Horizon deve permanecer restrito ao ambiente administrativo.

A estratégia de autorização (Gate, Policy ou outro mecanismo) não está documentada.

**[A DEFINIR]**

---

## Monitoramento

O Horizon deve monitorar:

- Jobs pendentes;
- Jobs falhos;
- tempo médio de processamento;
- throughput.

A configuração exata de métricas permanece:

**[A DEFINIR]**

---

> ⚠️ **Regra crítica:** Nenhum pipeline de IA pode ser executado diretamente em Controllers, componentes Livewire ou durante a requisição HTTP do usuário. Toda execução deve ser despachada para uma fila Redis e processada por um Worker do Laravel Horizon.


# 9. WebSockets e Tempo Real

## 9.1 Visão Geral

O Arkion utiliza **Laravel Reverb** como mecanismo oficial de comunicação em tempo real.

Toda atualização de estado das execuções deve ser propagada imediatamente para a interface sem necessidade de atualização manual da página.

O uso de polling é considerado apenas um mecanismo de contingência.

---

## 9.2 Arquitetura

```text
Worker Horizon

↓

Execution atualizada

↓

Evento Laravel

↓

Laravel Reverb

↓

Canal WebSocket

↓

Livewire

↓

Interface
```

---

## 9.3 Papel do Reverb

Responsabilidades:

- publicar eventos;
- distribuir mensagens em tempo real;
- reduzir polling constante;
- atualizar componentes Livewire.

O Reverb não substitui o banco de dados.

Toda informação continua sendo persistida no PostgreSQL antes do broadcast.

---

## 9.4 Canais

A documentação identifica necessidade de isolamento por usuário.

O nome exato dos canais não está documentado.

Permanece:

**[A DEFINIR]**

Exemplo conceitual:

```
private-user.{id}
```

ou

```
private-execution.{id}
```

A definição oficial deverá ser realizada durante a implementação.

---

## 9.5 Autorização

Todos os canais devem ser privados.

A autorização ocorre através do Laravel.

Fluxo:

```text
Cliente

↓

Solicita canal

↓

Laravel

↓

Policy

↓

Autorizado?

↓

Reverb
```

Nunca permitir canais públicos contendo informações de execução.

---

## 9.6 Evento `ExecutionUpdated`

### Objetivo

Notificar a interface sobre mudanças de estado de uma execução.

---

### Quando disparar

Sempre que:

- status mudar;
- processamento terminar;
- resultado ficar disponível.

---

### Payload

Nunca transmitir o resultado completo.

Payload mínimo:

```json
{
  "execution_id": 154,
  "status": "completed",
  "pipeline_type": "brand_research",
  "updated_at": "2026-07-01T15:37:42Z"
}
```

---

### Campos

| Campo | Tipo |
|--------|------|
| execution_id | integer |
| status | string |
| pipeline_type | string |
| updated_at | datetime |

---

### Não transmitir

Nunca incluir:

```
result

config

tokens completos

prompts

JSONB
```

Esses dados devem ser buscados posteriormente pelo componente Livewire.

---

# 9.7 Evento `ExecutionFailed`

## Objetivo

Informar imediatamente falhas de processamento.

---

### Payload

```json
{
  "execution_id": 154,
  "status": "failed",
  "pipeline_type": "brand_research"
}
```

---

### Responsabilidade da Interface

Ao receber o evento:

- atualizar badge de status;
- remover loading;
- exibir mensagem de erro apropriada.

---

# 9.8 Fluxo Completo

```text
Worker

↓

Execution::update()

↓

Event()

↓

Broadcast

↓

Reverb

↓

Browser

↓

Livewire Refresh
```

---

# 9.9 Livewire

Os componentes Livewire permanecem responsáveis por:

- atualização visual;
- renderização;
- carregamento do resultado completo.

O Reverb apenas informa que houve alteração.

---

# 9.10 Fallback

Caso o WebSocket esteja indisponível:

Livewire deve recorrer a polling periódico.

Fluxo:

```text
Livewire

↓

wire:poll

↓

Consulta status

↓

Atualiza interface
```

O intervalo oficial de polling não está documentado.

**[A DEFINIR]**

---

# 9.11 Ordem dos Eventos

A sequência correta é:

```text
Persistência

↓

Commit

↓

Broadcast
```

Nunca transmitir um evento antes que a atualização tenha sido persistida.

---

> ⚠️ **Regra crítica:** Eventos de broadcast nunca devem transportar o conteúdo completo de `executions.result`. O WebSocket serve apenas para sinalizar mudanças de estado; o carregamento do JSON completo deve ocorrer por consulta controlada ao Laravel.

---

# 9.12 Registro de Implementação — Canal e Payloads

## Canal Oficial

A lacuna **[A DEFINIR]** da Seção 9.4 foi resolvida na implementação: o canal oficial é

```
private-execution.{executionId}
```

autorizado em `routes/channels.php` pela posse da Execution (`$user->executions()`). O canal `private-App.Models.User.{id}` permanece reservado às notificações broadcast do usuário.

---

## Divergência de Payload — Pendente de ADR

Os eventos implementados em `app/Events/` divergem dos payloads descritos nas Seções 9.6 e 9.7:

| Evento | Payload documentado (9.6 / 9.7) | Payload implementado |
|--------|----------------------------------|----------------------|
| `ExecutionUpdated` | `execution_id`, `status`, `pipeline_type`, `updated_at` | `id`, `status`, `completed_at` |
| `ExecutionFailed` | `execution_id`, `status`, `pipeline_type` | `id`, `error_message`, `failed_at` |

A divergência é de **nomenclatura e granularidade**, não de princípio: nenhum dos dois payloads transporta `result`, `config`, prompts ou qualquer conteúdo JSONB, de modo que a Regra Crítica da Seção 9 e a regra 12 da `CLAUDE.md` permanecem íntegras.

Pontos que uma futura ADR precisa fixar:

- se a chave é `execution_id` ou `id`;
- se `pipeline_type` deve trafegar no payload ou ser lido pelo componente Livewire, que já conhece a Execution que está observando;
- se `ExecutionFailed` pode transportar `error_message`, considerando que a Seção 6 exige preservar diagnóstico **sem expor dados sensíveis ao usuário** — hoje o campo é a mensagem já tratada em `executions.error_message`, nunca o erro bruto devolvido pelo n8n.

Até a aprovação da ADR, o formato implementado é o vigente e está coberto por `tests/Feature/ExecutionTest.php`.

---

# 9.13 Registro de Implementação — Segredo Compartilhado no Despacho

O `App\Services\N8nService` envia o segredo compartilhado **exclusivamente** no header `X-N8N-Secret`, conforme a Seção 7.5, e nunca no corpo do payload da Seção 7.3.

Motivo: o corpo da requisição é persistido pelo n8n no histórico de execução do workflow. Replicar o segredo no payload o transformaria em dado armazenado no Droplet 2, enfraquecendo o único mecanismo de autenticação entre os dois serviços sem nenhum ganho — o n8n já valida o header antes de iniciar o workflow.

Pelo mesmo motivo, nem o segredo nem a URL do webhook podem chegar ao log: `N8nConnectionException` não encadeia a exception original do cliente HTTP (cuja mensagem contém a URL completa do Droplet 2) e registra apenas `execution_id`, status HTTP e a classe do erro de transporte.

---

# 10. Design System e Frontend

## 10.1 Visão Geral

A interface do Arkion é construída integralmente utilizando:

- Blade;
- Livewire 3;
- Alpine.js;
- Tailwind CSS.

Não existe frontend desacoplado.

O Design System constitui parte da arquitetura e deve ser seguido rigorosamente por qualquer agente responsável pela geração de componentes.

O **Light mode é o tema padrão** da plataforma. O Dark mode existe como **variante**, aplicada exclusivamente através do prefixo `dark:` do Tailwind.

Esta seção substitui integralmente o Design System anterior ("Dark First", paleta `#111111` / `#FF5500`, tipografia única Inter). Nenhum token do sistema antigo permanece válido.

A fonte de verdade visual é `.claude/docs/UI-KIT.md`. Esta seção é a transposição normativa daquele documento para a arquitetura do Arkion; onde houver divergência, o `UI-KIT.md` prevalece e esta seção deve ser corrigida.

---

## 10.1.1 Regra de Idioma da Interface

| Superfície | Idioma obrigatório |
|-----------|--------------------|
| Rotas de produto (interface do cliente final) | **Inglês** |
| Painel administrativo (`/admin/*`, uso interno) | Português permitido |
| Código (classes, métodos, variáveis, arquivos) | Inglês |
| Documentação de arquitetura (SAD, CLAUDE, PROJECT_RULES) | Português |

Todo texto voltado ao cliente final — labels, botões, placeholders, títulos, mensagens de validação, mensagens de erro, estados vazios, tooltips, e-mails transacionais e notificações — deve ser escrito **em inglês**.

Esta regra **substitui qualquer menção anterior a mensagens em português nas rotas de produto** presente em outras seções deste documento. Onde houver conflito, esta seção prevalece.

O painel administrativo é uma ferramenta interna e pode permanecer em português.

> ⚠️ **Regra crítica:** Nenhuma string em português pode ser introduzida em uma view, componente Livewire, Form Request ou Exception que alcance a interface do cliente final. Mensagens de validação em português já existentes nas rotas de produto constituem dívida técnica e devem ser traduzidas quando o arquivo for tocado.

---

# 10.2 Configuração do Tailwind

Arquivo:

```
tailwind.config.js
```

Configuração obrigatória:

```javascript
export default {

    darkMode: 'class',

    theme: {

        extend: {

            colors: {

                primary: '#d2691e',

                secondary: '#9e6136',

                accent: '#6b4e3a',

                app: {

                    'bg-dark': '#121214',

                    'card-dark': '#1c1c1c',

                    'text-dark': '#f4f4f6'

                },

                state: {

                    success: '#16a34a',

                    'success-dark': '#22c55e',

                    warning: '#f59e0b',

                    'warning-dark': '#fbbf24',

                    error: '#dc2626',

                    'error-dark': '#ef4444'

                }

            },

            fontFamily: {

                serif: ['Newsreader', 'serif'],

                sans: ['Inter', 'sans-serif']

            }

        }

    }

}
```

`darkMode: 'class'` permanece obrigatório, porém com a semântica invertida em relação ao sistema anterior: o documento **inicia sem a classe `dark`** e o tema escuro passa a ser um estado opcional.

O tema claro se apoia deliberadamente na escala neutra padrão do Tailwind (`gray-50`, `white`, `gray-200`, `gray-500`, `gray-900`). Apenas os valores que o `UI-KIT.md` define como exclusivos da marca ou do tema escuro recebem token próprio.

---

# 10.3 Princípios do Design

O sistema segue os seguintes princípios:

- Light first, dark como variante;
- contraste legível em ambos os temas;
- interface minimalista;
- foco em produtividade;
- uso consistente da cor de marca;
- componentes reutilizáveis;
- tipografia dupla — serifada para títulos, sans para corpo e UI;
- ícones traçados com peso mínimo.

---

# 10.4 Paleta Oficial

Fonte: `.claude/docs/UI-KIT.md`, Seção 2 — *Visual Identity, Exclusive Palette & Dual Theme*.

## Cores de Marca

| Token | Valor | Papel |
|-------|-------|-------|
| `primary` | `#d2691e` | Cor de marca. Botões primários, ícones ativos, estados de foco, etapas ativas, bordas selecionadas. |
| `secondary` | `#9e6136` | Cor de apoio. Texto secundário no tema escuro, ícones em repouso, realces discretos. |
| `accent` | `#6b4e3a` | Acento marrom. Backgrounds de hover e bordas do tema escuro, sempre com opacidade. |
| `app-bg-dark` | `#121214` | Background global do tema escuro. |
| `app-card-dark` | `#1c1c1c` | Superfície de cards, painéis, modais e dropdowns no tema escuro. |
| `app-text-dark` | `#f4f4f6` | Texto primário do tema escuro. |

`#f4f4f6` é uma cor de **texto do tema escuro**, não o background do tema claro. O background claro é `gray-50`.

## Regras de Aplicação

Toda superfície do sistema é definida por um par light/dark. Estes pares são normativos e devem ser copiados literalmente:

| Papel | Light | Dark |
|-------|-------|------|
| Background de tela e menus | `bg-gray-50` | `dark:bg-[#121214]` |
| Containers, cards e dropdowns | `bg-white` | `dark:bg-[#1c1c1c]` |
| Background de hover (interação dinâmica) | `hover:bg-[#6b4e3a]/20` | `dark:hover:bg-[#6b4e3a]/20` |
| Bordas e divisórias | `border-gray-200` | `dark:border-[#6b4e3a]/30` |
| Texto principal e títulos | `text-gray-900` | `dark:text-[#f4f4f6]` |
| Texto secundário / muted | `text-gray-500` | `dark:text-[#9e6136]` ou `dark:text-[#f4f4f6]/60` |
| Destaques e ações fortes | `text-[#d2691e]` / `bg-[#d2691e]` | idem — a cor de marca não varia por tema |
| Foco | `focus:ring-[#d2691e]` | idem |
| Borda em hover | `hover:border-[#d2691e]` | idem |

O background de hover usa o acento marrom com opacidade em **ambos** os temas — é o único par em que light e dark compartilham o mesmo valor.

Para texto secundário no tema escuro, `dark:text-[#9e6136]` é a escolha padrão. `dark:text-[#f4f4f6]/60` é a alternativa autorizada quando o marrom não alcança contraste suficiente sobre a superfície em questão.

## Hover do CTA Primário

O botão primário **não possui um hex de hover próprio**. O estado de hover é obtido escurecendo a própria cor de marca:

```html
<button class="bg-[#d2691e] text-white hover:brightness-90 transition">
    Generate
</button>
```

Motivo da escolha:

- um segundo hexadecimal seria mais um token para manter sincronizado com `#d2691e` a cada ajuste de marca;
- `brightness-90` é um filtro relativo e acompanha automaticamente qualquer mudança futura da cor primária;
- o mesmo utilitário funciona nos dois temas sem variante `dark:`, porque a cor de marca não muda entre eles;
- o comportamento se estende sem duplicação a qualquer elemento que use `#d2691e` como fundo — badges, chips, indicadores.

O hover do CTA primário nunca deve ser expresso como `hover:bg-[#...]`.

## Cores Semânticas

> **Recomendação, não UI-KIT.** As cores abaixo **não constam do `UI-KIT.md`** e foram definidas como recomendação do time durante a revisão 2.0 do SAD. Substituir sem ADR caso o UI-KIT passe a especificá-las.

| Estado | Light | Dark |
|--------|-------|------|
| Success | `#16a34a` | `#22c55e` |
| Warning | `#f59e0b` | `#fbbf24` |
| Error | `#dc2626` | `#ef4444` |

Aplicação:

```html
<p class="text-[#16a34a] dark:text-[#22c55e]">Execution completed</p>
<p class="text-[#f59e0b] dark:text-[#fbbf24]">Low credit balance</p>
<p class="text-[#dc2626] dark:text-[#ef4444]">Execution failed</p>
```

Regras:

- as cores semânticas comunicam **estado**, nunca hierarquia visual. Um botão de ação destrutiva usa `error`; um botão importante usa `primary`.
- os estados de `Execution` da Seção 6 mapeiam assim: `completed` → success, `failed` → error, `queued` e `processing` → `secondary` (neutro, sem cor semântica).
- saldo insuficiente e créditos próximos do fim usam warning.

---

# 10.5 Regras Gerais da Paleta

Nunca criar novas cores para componentes.

Todo elemento deve utilizar exclusivamente os tokens e os pares light/dark documentados nesta seção.

Novos componentes devem reutilizar a paleta existente.

Todo elemento gerado deve nascer no tema claro e receber sua variante escura via prefixo `dark:` — nunca o contrário.

Os tokens do Design System anterior estão **proibidos**:

```
#111111   ← background antigo
#FF5500   ← cor de marca antiga
#E64D00   ← hover antigo
#1C1917   #292524   #44403C   #554f49   #A8A29E   #FAFAFA
```

Toda a escala `orange.50` … `orange.900` do sistema anterior foi removida.

`#1c1c1c` permanece válido, porém exclusivamente como superfície de card do tema escuro (`app-card-dark`).

> ⚠️ **Regra crítica:** O Design System faz parte da arquitetura do sistema. Não criar novas cores, variações ou tokens fora dos definidos nesta seção. Nunca reintroduzir `#111111` ou `#FF5500`. Toda interface deve reutilizar exclusivamente a paleta oficial documentada acima.

---

# 10.6 Tipografia

## Famílias Oficiais

O sistema utiliza **duas** famílias tipográficas, e apenas duas.

| Família | Token Tailwind | Aplicação |
|---------|----------------|-----------|
| **Newsreader** | `font-serif` | Títulos `H1` a `H5` |
| **Inter** | `font-sans` | Corpo de texto, labels, botões, inputs, tabelas, badges e toda a UI |

Origem:

- Google Fonts; ou
- Bundle via Vite.

Nenhuma terceira família pode ser introduzida sem ADR aprovada.

---

## Escala de Títulos — Newsreader

| Nível | Uso | Classes |
|-------|-----|---------|
| `H1` | Título principal da página | `text-4xl tracking-tight` |
| `H2` | Título de bloco | `text-3xl tracking-tight` |
| `H3` | Título de card ou modal | `text-2xl` |
| `H4` | Subseção | `text-xl` ou `text-lg` |
| `H5` | Título de apoio menor | `text-base` ou `text-sm` |

`H6` não faz parte da escala documentada.

## Escala de Corpo e UI — Inter

| Uso | Classes |
|-----|---------|
| Texto padrão (inputs, botões, dropdowns) | `text-sm` |
| Textos de apoio longos (parágrafos, descrições) | `text-[13px] leading-relaxed` |
| Micro-copy (tags, badges, tooltips, botões pequenos) | `text-xs` |
| Micro absoluto (badges sobrepostos) | `text-[11px]` |

Elementos de UI que aparentam ser títulos — labels de seção, cabeçalhos de tabela, títulos de card curtos — permanecem em `font-sans`.

---

## Configuração

```css
/* Títulos */
font-family: 'Newsreader', serif;

/* Corpo e UI */
font-family: 'Inter', sans-serif;
```

No Tailwind:

```javascript
fontFamily: {
    serif: ['Newsreader', 'serif'],
    sans:  ['Inter', 'sans-serif']
}
```

---

## Regra de Aplicação

```html
<h1 class="font-serif text-4xl tracking-tight text-gray-900 dark:text-[#f4f4f6]">…</h1>
<h3 class="font-serif text-2xl text-gray-900 dark:text-[#f4f4f6]">…</h3>

<p class="font-sans text-[13px] leading-relaxed text-gray-500 dark:text-[#9e6136]">…</p>
<button class="font-sans text-sm">…</button>
```

---

## Estilo Editorial dos Títulos

Os títulos replicam um padrão editorial de alto contraste: dentro de uma mesma frase de cabeçalho, pesos finos e pesos fortes se alternam.

```html
<h1 class="font-serif text-4xl tracking-tight">
    The <span class="font-bold">official guidelines</span> and essential pillars of your brand.
</h1>
```

Este padrão é parte da identidade e deve ser aplicado nos títulos principais de página.

---

## Pesos Utilizados

| Peso | Uso |
|-------|-----|
| 400 | Texto corrido |
| 500 | Labels, botões, badges |
| 600 | Headings |
| 700 | Títulos principais e trechos em destaque dentro de títulos |

Outros pesos não fazem parte do Design System.

---

# 10.7 Ícones

Todos os ícones são **SVG inline**.

## Regras Obrigatórias

| Atributo | Valor |
|----------|-------|
| `stroke` | `currentColor` |
| `stroke-width` | `0.3` |
| `fill` | `currentColor` |
| Tamanho principal | `w-5 h-5` (20×20px) |
| Tamanho secundário | `w-4 h-4` (16×16px) |

Exemplo canônico:

```html
<svg class="w-5 h-5 text-[#9e6136] hover:text-[#d2691e]"
     viewBox="0 0 24 24"
     fill="currentColor" stroke="currentColor" stroke-width="0.3">
    <!-- path -->
</svg>
```

## Escolha do Tamanho

| Contexto | Tamanho |
|----------|---------|
| Ícones principais — Header, busca, notificações, Ask AI, ações globais | `w-5 h-5` |
| Ícones secundários — dropdowns, filtros, inputs, badges | `w-4 h-4` |

Nenhum outro tamanho de ícone pode ser introduzido.

O `stroke-width` de `0.3` é uma **compensação de traço** deliberada e define a identidade visual do produto — nunca substituí-lo por `1`, `1.5` ou `2`, que são os padrões de bibliotecas como Heroicons ou Lucide.

## Cor

Ícones nunca recebem cor literal no atributo. A cor é controlada por classes de texto na própria tag ou no elemento pai, e herdada via `currentColor`:

```html
<span class="text-[#9e6136] hover:text-[#d2691e]">
    <svg class="w-4 h-4" fill="currentColor" stroke="currentColor" stroke-width="0.3">…</svg>
</span>
```

## Interações Premium — Glow e Scale

Ícones de destaque e ícones dentro de botões `group` recebem a transição oficial:

```html
class="transition-all duration-300 group-hover:drop-shadow-[0_0_6px_#d2691e] group-hover:scale-110"
```

O glow usa sempre a cor de marca. Nenhuma outra cor de sombra é permitida.

---

# 10.8 Estrutura Geral do Layout

Toda a aplicação autenticada utiliza um único Application Shell.

Esse shell permanece constante durante toda a navegação.

Estrutura:

```text
┌──────────────────────────────────────────────────────────┐
│ Header                                                   │
├───────────────┬──────────────────────────────────────────┤
│               │                                          │
│               │                                          │
│               │                                          │
│ Sidebar       │     Main Content                         │
│               │                                          │
│               │                                          │
│               │                                          │
├───────────────┴──────────────────────────────────────────┤
```

O conteúdo principal muda conforme a página.

Sidebar e Header permanecem persistentes.

---

# 10.9 Sidebar

A Sidebar permanece fixa à esquerda.

Características:

- largura fixa;
- colapsável;
- versão mobile;
- navegação principal;
- seletor de Brand;
- informações de créditos.

---

## Funcionalidades

- navegação;
- troca de Brand;
- acesso ao perfil;
- ajuda;
- configurações;
- logout;
- alternância de tema.

---

## IDs Funcionais

Os seguintes IDs são considerados parte da arquitetura da interface.

| ID | Responsabilidade |
|----|------------------|
| sidebar | Container principal |
| toggle-btn | Colapsar Sidebar |
| mobile-overlay | Overlay mobile |
| client-dropdown-container | Dropdown de Brand |
| client-dropdown-btn | Botão da Brand |
| theme-toggle-btn | Alternância de tema |
| help-btn | Menu Help |
| help-submenu | Submenu de ajuda |
| logout-btn | Logout |

Esses identificadores devem permanecer estáveis para manter compatibilidade entre componentes.

---

# 10.10 Header

O Header permanece fixo na parte superior da aplicação.

---

## Componentes

Da esquerda para direita:

- busca global;
- botão "Ask AI";
- notificações;
- seletor de idioma;
- perfil do usuário.

---

## IDs Funcionais

| ID | Função |
|----|--------|
| btn-ask-ai | Botão principal de IA |
| notif-dropdown-container | Dropdown notificações |
| lang-dropdown-container | Idiomas |
| search-container | Busca global |
| profile-dropdown-container | Perfil |

Esses IDs não devem ser alterados.

---

# 10.11 Área Principal

Responsável por renderizar as páginas Livewire.

Estrutura:

```text
Header

↓

Content Wrapper

↓

Livewire Component

↓

Blade View
```

Não existe SPA externa.

Toda navegação permanece dentro do layout Blade.

---

# 10.12 Navegação da Sidebar

A ordem dos menus é parte do Design System.

Não deve ser alterada.

Todos os rótulos abaixo são apresentados ao cliente final e, portanto, permanecem em inglês (Seção 10.1.1).

---

## HOME

```
Dashboard
```

---

## BRAND

```
Brand Core

Brand Assets

Research and Strategy
```

---

## GENERATE

```
Create Article

Create Post

Create Ad

Library
```

---

## Footer

A parte inferior da Sidebar contém:

```
Brand Switcher

↓

Credits Used

↓

Buy AI Credits

↓

Theme Toggle

↓

Profile

↓

Help and Support

↓

Settings

↓

Log out
```

---

## Help and Support

O submenu contém:

```
Quick Start Guide

Support

Documentation

Academy

Report a Bug / Suggest
```

---

## Créditos

O indicador de créditos da Sidebar apresenta os **dois saldos** definidos na Seção 12:

```
Plan credits:      850 / 1000
Purchased credits: 240
```

Este componente representa apenas a visualização do consumo.

O cálculo é realizado no backend.

Nunca somar os dois saldos em um único número exibido — a distinção entre crédito que expira e crédito que não expira é informação relevante ao usuário.

---

## Buy AI Credits

CTA permanente na Sidebar.

Responsável por iniciar o fluxo de compra de créditos avulsos, que credita `purchased_credits_balance` (Seção 12.9).

O CTA é visível a todos os papéis, porque todo membro pode comprar créditos pessoais. A carteira de destino "organização" só é oferecida ao Owner; Admin e Member compram apenas a carteira pessoal (Seções 12.13 e 20.4, ADR-010).

---

# 10.13 Componentes Blade + Livewire

A arquitetura estabelece um mapeamento direto entre os arquivos HTML de referência e os componentes Laravel.

Os HTMLs de referência foram substituídos pelas versões do novo Design System. As versões antigas (`sidebar.html`, `header.html`, `brand_core.html`) estão obsoletas e não devem mais ser consultadas.

---

## Sidebar

HTML

```
sidebar_2_0.html
```

Implementação

```
resources/views/components/sidebar.blade.php

app/Livewire/Sidebar.php
```

Responsável por:

- navegação;
- Brand ativa (incluindo sub-brands, Seção 19);
- créditos;
- menus.

---

## Header

HTML

```
header_2_0.html
```

Implementação

```
resources/views/components/header.blade.php

app/Livewire/Header.php
```

Responsabilidades:

- notificações;
- perfil;
- idioma;
- busca.

---

## Dashboard

```
dashboard.html
```

↓

```
resources/views/pages/dashboard.blade.php

app/Livewire/Dashboard.php
```

---

## Logout Modal

```
logout-modal.html
```

↓

```
resources/views/livewire/logout-modal.blade.php

app/Livewire/LogoutModal.php
```

---

## Brand Core

```
brand_core_v2.html
```

↓

```
resources/views/pages/brand-core.blade.php

app/Livewire/BrandCore.php
```

---

## Brand Assets

Visual Identity

```
app/Livewire/BrandAssets/VisualIdentity.php
```

Characters

```
app/Livewire/BrandAssets/Characters.php
```

Products

```
app/Livewire/BrandAssets/Products.php
```

---

## Marketing

Market Research

```
app/Livewire/Marketing/MarketResearch.php
```

Buyer Personas

```
app/Livewire/Marketing/BuyerPersonas.php
```

Social Strategy

```
app/Livewire/Marketing/SocialStrategy.php
```

---

# 10.14 Padrões Alpine.js

Toda interação leve da interface utiliza Alpine.js.

---

## Dropdowns

Sempre utilizar:

```html
x-show
```

Fechamento:

```html
@click.outside
```

---

## Modais

Controle:

```html
x-data
```

Transições:

```
transition-opacity

scale-95

↓

scale-100
```

---

## Sidebar Mobile

Estrutura identificada:

```
mobile-overlay

↓

mobile-menu-btn
```

---

## Tema

O documento inicia **sem** classe de tema — o Light mode é o padrão:

```html
<html>
```

O Dark mode é ativado adicionando a classe `dark` ao elemento `<html>`:

```html
<html class="dark">
```

Persistência:

```
localStorage
```

O `theme-toggle-btn` da Sidebar é o único controle autorizado a alternar a classe. Na ausência de preferência salva em `localStorage`, a aplicação renderiza em Light mode.

Toda estilização de tema escuro ocorre exclusivamente através do prefixo `dark:`:

```html
<body class="bg-gray-50 text-gray-900 dark:bg-[#121214] dark:text-[#f4f4f6]">
```

Nunca criar folhas de estilo, componentes ou variantes separadas para o tema escuro.

---

## Multi Brand

Dropdown:

```
client-dropdown-container
```

Seleciona a Brand ativa.

---

# 10.15 Multi-Brand (Brand Switcher)

O conceito de Multi-Brand constitui uma das bases arquiteturais do Arkion.

Todo usuário trabalha dentro da **organização ativa**, composta por múltiplas Brands independentes. O `BrandSwitcher` lista apenas as Brands da organização ativa; a troca de organização é feita pelo seletor de organização, exibido apenas quando o usuário pertence a mais de uma (Seção 20.3).

Cada Brand possui seu próprio contexto de:

- execuções;
- ativos;
- pesquisas;
- resultados;
- estratégia.

A hierarquia entre Brands (umbrella brands e sub-brands) está documentada na Seção 19.

---

## Estado da Brand Ativa

A documentação indica que existe exatamente uma Brand ativa por sessão.

O mecanismo específico de persistência (Sessão Laravel, componente Livewire global ou outro) não é explicitamente definido.

Portanto:

**Estado da Brand ativa:** **[A DEFINIR]**

O contexto de sessão tem duas variáveis, `active_organization_id` e `active_project_id`, e a segunda precisa pertencer à primeira em todo acesso (Seção 20.5). O mecanismo de persistência de ambas permanece **[A DEFINIR]**.

---

## Escopo das Queries

Toda consulta ao banco deve ser automaticamente filtrada pela Brand ativa.

Exemplo correto:

```php
Execution::query()
    ->where('project_id', $activeProject->id);
```

Nunca retornar registros de outra Brand — nem de outra Organização —, mesmo quando uma Brand for sub-brand da outra.

---

## Componente BrandSwitcher

Responsável por:

- listar Brands;
- apresentar sub-brands aninhadas sob a respectiva umbrella brand;
- alterar Brand ativa;
- atualizar contexto da aplicação.

Implementação prevista:

```
app/Livewire/BrandSwitcher.php
```

View:

```
resources/views/livewire/brand-switcher.blade.php
```

---

## Atualização do Contexto

Ao selecionar uma nova Brand:

1. atualizar o estado da Brand ativa;
2. invalidar dados dependentes;
3. recarregar componentes Livewire;
4. todas as consultas subsequentes passam a utilizar o novo `project_id`.

A estratégia de propagação entre componentes permanece **[A DEFINIR]**.

---

> ⚠️ **Regra crítica:** A Brand ativa define todo o contexto funcional da aplicação. Nenhum componente, Controller, Query, Job ou Policy pode acessar dados sem aplicar o escopo da Brand ativa (`project_id`). Nunca assuma que `user_id` é suficiente para isolar informações.


# 11. Estrutura de Diretórios e Responsabilidades

## 11.1 Visão Geral

O Arkion adota a estrutura padrão do Laravel 11, organizada por responsabilidade de domínio.

Os componentes Livewire representam a camada de apresentação reativa.

A lógica de negócio permanece concentrada em:

- Models;
- Services;
- Jobs;
- Policies;
- Middleware.

Controllers devem permanecer enxutos.

---

# 11.2 Estrutura Geral

```text
app/
├── Events/
├── Http/
│   ├── Controllers/
│   ├── Middleware/
│   └── Requests/
├── Jobs/
├── Livewire/
├── Models/
├── Policies/
├── Providers/
├── Services/
└── Broadcasting/

bootstrap/

config/

database/
├── factories/
├── migrations/
└── seeders/

public/

resources/
├── css/
├── js/
└── views/

routes/

storage/

tests/
```

---

# 11.3 Models

```text
app/Models/
```

| Arquivo | Responsabilidade |
|----------|------------------|
| User.php | Usuário autenticado |
| Project.php | Brand (workspace) |
| Execution.php | Execuções de IA |
| VideoReference.php | Banco de referências virais |
| PlanLimit.php | Custos e limites por plano |

Laravel Cashier adiciona seus próprios Models conforme configuração padrão.

---

# 11.4 Livewire

```text
app/Livewire/
```

Responsável por toda interação da interface.

---

## Dashboard

```
Dashboard.php
```

Página inicial.

---

## Header

```
Header.php
```

Responsável por:

- notificações;
- idioma;
- perfil;
- busca.

---

## Sidebar

```
Sidebar.php
```

Responsável por:

- navegação;
- créditos;
- menus;
- Brand ativa.

---

## BrandSwitcher

```
BrandSwitcher.php
```

Gerencia:

- Brands disponíveis;
- troca da Brand ativa.

---

## LogoutModal

```
LogoutModal.php
```

Responsável pela confirmação de logout.

---

## Brand Core

```
BrandCore.php
```

Gerencia os dados centrais da Brand.

---

## BrandAssets

```text
BrandAssets/
```

### VisualIdentity.php

Gerencia identidade visual.

---

### Characters.php

Gerencia personagens.

---

### Products.php

Gerencia produtos.

---

## Marketing

```text
Marketing/
```

### MarketResearch.php

Pesquisa de mercado.

---

### BuyerPersonas.php

Personas.

---

### SocialStrategy.php

Estratégia social.

---

Outros componentes identificados nos HTMLs permanecem:

**[A DEFINIR]**

---

# 11.5 Jobs

```text
app/Jobs/
```

| Arquivo | Responsabilidade |
|----------|------------------|
| ExecutePipelineJob.php | Executa qualquer pipeline |
| UpdateViralReferencesJob.php | Atualiza referências |
| SendNotificationJob.php | Processa notificações |

---

# 11.6 Middleware

```text
app/Http/Middleware/
```

| Arquivo | Responsabilidade |
|----------|------------------|
| VerifyFirebaseToken.php | Autenticação |
| CheckSubscription.php | Assinatura |
| EnsureCreditBalance.php | Créditos |

---

# 11.7 Policies

```text
app/Policies/
```

| Arquivo | Responsabilidade |
|----------|------------------|
| ExecutionPolicy.php | Controle das execuções |
| ProjectPolicy.php | Controle das Brands |

---

# 11.8 Events

```text
app/Events/
```

Eventos identificados:

```
ExecutionUpdated

ExecutionFailed
```

---

# 11.9 Services

```text
app/Services/
```

Os documentos não especificam a organização detalhada desta camada.

Permanece:

**[A DEFINIR]**

---

# 11.10 Views

```text
resources/views/
```

Estrutura esperada:

```text
views/

components/

layouts/

pages/

livewire/
```

---

## Components

```text
components/

sidebar.blade.php

header.blade.php
```

---

## Layouts

```text
layouts/

app.blade.php
```

Layout principal autenticado.

---

## Pages

```text
pages/

dashboard.blade.php

brand-core.blade.php
```

Outras páginas permanecem:

**[A DEFINIR]**

---

## Livewire

```text
livewire/

logout-modal.blade.php

brand-switcher.blade.php
```

Demais componentes acompanham seus respectivos arquivos PHP.

---

# 11.11 Routes

```text
routes/

web.php

channels.php
```

A documentação não identifica necessidade de API pública.

---

# 11.12 Config

Arquivos relevantes:

```text
config/

app.php

queue.php

broadcasting.php

cashier.php

database.php

services.php

reverb.php
```

---

# 11.13 Banco

```text
database/

migrations/

seeders/

factories/
```

Todas as alterações estruturais devem ocorrer exclusivamente através de Migrations.

---

# 11.14 Assets

```text
resources/

css/

js/
```

Compilação realizada pelo:

```
Vite
```

---

# 11.15 Testes

```text
tests/

Feature/

Unit/
```

A organização específica de testes não está documentada.

**[A DEFINIR]**

---

> ⚠️ **Regra crítica:** Componentes Livewire devem conter apenas lógica de apresentação e orquestração da interface. Regras de negócio não devem ser implementadas em componentes Blade ou Livewire; elas pertencem à camada de domínio (Models, Services, Jobs, Policies e Middleware).

---

# 12. Regras de Negócio por Plano

## 12.1 Visão Geral

O Arkion utiliza um modelo baseado em créditos.

O sistema **não limita o número de execuções mensais**.

Cada operação consome uma quantidade específica de créditos, proporcional ao processamento computacional envolvido.

**A entidade de faturamento é a Organização, não o usuário.** O plano, os créditos do plano e os créditos comprados para a operação pertencem à Organização (Seção 20). O usuário carrega apenas uma carteira pessoal de créditos comprados, que o acompanha entre as organizações das quais participa.

O modelo comercial repousa sobre três eixos independentes, verificados **sempre nesta ordem**:

| Ordem | Eixo | Pergunta que responde | Bloqueio |
|:-----:|------|-----------------------|----------|
| 1 | **Plano ativo** | A organização ativa tem assinatura? | `NoActivePlanException` → **402** |
| 2 | **Feature gating** | O plano da organização permite este pipeline? | `FeatureNotAvailableException` → **403** |
| 3 | **Saldo de créditos** | As carteiras cobrem o custo da ação? | `InsufficientCreditsException` → **402** |

Os três eixos são independentes e **todos** precisam autorizar a operação. Saldo suficiente não concede acesso a um pipeline fora do plano; acesso ao pipeline não dispensa saldo; e nenhum dos dois existe sem uma assinatura ativa.

A ordem entre os eixos 1 e 2 é **regra de segurança**, não detalhe de implementação. Ver Seção 12.18.

---

# 12.2 As Três Carteiras de Crédito

O saldo do Arkion é distribuído em **três carteiras separadas**, nunca consolidadas em uma única coluna.

| # | Carteira | Dono | Origem | Renovação | Expira? |
|:-:|----------|------|--------|-----------|---------|
| 1 | `organizations.plan_credits_balance` | Organização | Assinatura mensal do plano da org | Reposto no início de cada ciclo de cobrança | **Sim** — não é cumulativo |
| 2 | `organizations.purchased_credits_balance` | Organização | Pacote comprado pelo **Owner** para a org | Somado ao saldo existente na compra | **Não** — nunca expira |
| 3 | `users.purchased_credits_balance` | Usuário | Pacote comprado pelo próprio usuário | Somado ao saldo existente na compra | **Não** — nunca expira |

As duas primeiras carteiras são **da organização** e são compartilhadas por todos os membros e por todas as Marcas dela — de topo ou sub-brands (Seção 19).

A terceira carteira é **pessoal e portátil**: acompanha o usuário para qualquer organização de que ele participe, e não é visível nem consumível por outros membros.

**Visibilidade dos saldos por papel (Seção 20.4):** todo membro — Owner, Admin ou Member — vê os saldos das duas carteiras da organização e o da **sua própria** carteira pessoal. Ver o **consumo total** da organização (relatórios de uso) é exclusivo de Owner e Admin. Nenhum papel, nem o Owner, enxerga a carteira pessoal de outro membro.

**Quem credita cada carteira comprada:** somente o **Owner** compra créditos para a organização (carteira 2). Qualquer membro compra créditos para a própria carteira pessoal (carteira 3). Ver Seção 12.9.

> 🔁 **SUPERSEDED (ADR-010) — Admin compra para a organização.** A revisão 2.1 desta tabela atribuía a carteira 2 a "pacote comprado por um **Admin** para a org". Com três papéis, a compra para a organização é exclusiva do Owner; Admin e Member compram apenas a carteira pessoal.

## Regra de não-acumulação

`organizations.plan_credits_balance` é **substituído**, não incrementado, na renovação do ciclo:

```text
Renovação do ciclo:

organizations.plan_credits_balance  =  créditos mensais do plano      ← atribuição
```

Nunca:

```text
organizations.plan_credits_balance += créditos mensais do plano       ← PROIBIDO
```

Crédito de plano não consumido dentro do mês é perdido. Este comportamento é deliberado e faz parte do modelo comercial.

As duas carteiras de crédito comprado são sempre **incrementadas** e jamais zeradas por rotina de ciclo:

```text
Compra confirmada pelo Owner para a org:

organizations.purchased_credits_balance += créditos do pacote          ← incremento

Compra confirmada pelo próprio usuário:

users.purchased_credits_balance         += créditos do pacote          ← incremento
```

> ⚠️ **Regra crítica:** Nunca somar as três carteiras em uma única coluna, campo de banco, atributo de Model, payload de API ou coluna de relatório. A distinção entre crédito que expira e crédito que não expira, e entre crédito da organização e crédito pessoal, é informação de domínio e precisa sobreviver a toda a stack. Um acessor de conveniência para exibição agregada é permitido, desde que seja explicitamente derivado, nunca persistido, e desde que exiba a carteira pessoal separada da carteira da organização na interface.

> 🔁 **SUPERSEDED — Dois saldos no usuário.** A versão 2.0 desta seção documentava dois saldos, ambos em `users`: `users.plan_credits_balance` e `users.purchased_credits_balance`. O modelo de Organizações (Seção 20) move o saldo do plano e o saldo comprado pela operação para `organizations`, e mantém em `users` apenas a carteira pessoal. O texto anterior está superado e não deve ser implementado; nenhuma coluna de saldo de plano existe em `users`.

---

# 12.3 Ordem de Consumo

O débito percorre as três carteiras **sempre nesta ordem**, e nunca em outra:

```text
custo = C

1. organizations.plan_credits_balance        ← crédito que expira, da org
2. organizations.purchased_credits_balance   ← crédito que não expira, da org
3. users.purchased_credits_balance           ← carteira pessoal — exige confirmação explícita
```

```text
custo = C

├── org.plan >= C
│   └── org.plan -= C
│
└── org.plan < C
    ├── restante = C - org.plan
    ├── org.plan = 0
    │
    ├── org.purchased >= restante
    │   └── org.purchased -= restante
    │
    └── org.purchased < restante
        ├── restante2 = restante - org.purchased
        ├── org.purchased = 0
        └── users.purchased -= restante2      ← SOMENTE com confirm_personal_credits = true
```

Um único débito pode, portanto, atravessar as três carteiras.

A ordem existe para proteger o usuário em dois eixos: o crédito que expira é gasto antes do crédito que não expira, e o crédito da organização é gasto antes do crédito pessoal.

## Exemplo

| Estado | org.plan | org.purchased | user.purchased |
|--------|---------:|--------------:|---------------:|
| Antes | 30 | 10 | 200 |
| Débito de 50 | | | |
| Depois | 0 | 0 | 190 |

Neste exemplo o débito toca a carteira pessoal, e portanto **exige confirmação explícita** antes de ocorrer (Seção 12.19).

## Regras

- A ordem de consumo é inviolável e não configurável por plano, por organização ou por usuário.
- **Nunca inverter a ordem.** Consumir a carteira pessoal antes das carteiras da organização é uma violação de domínio, não uma otimização.
- O débito ocorre dentro da mesma transação e sob o mesmo lock pessimista descrito na Seção 12.16, que passa a travar a linha da organização **e** a linha do usuário quando a carteira pessoal for tocada.
- A verificação de saldo suficiente considera a **soma disponível** das carteiras autorizadas para a requisição; o débito respeita a ordem acima.
- O terceiro nível nunca é debitado sem `confirm_personal_credits = true` na requisição. Ver Seção 12.19.

> 🔁 **SUPERSEDED — Ordem de débito de dois níveis.** A versão 2.0 documentava um débito de dois níveis (`plan_credits_balance` → `purchased_credits_balance`, ambos em `users`) e sem nenhuma etapa de confirmação. Esse fluxo está superado: a ordem agora tem **três níveis** e o terceiro exige confirmação explícita do usuário. Nenhuma implementação deve seguir o modelo de dois níveis.

---

# 12.4 Renovação Mensal

| Campo | Comportamento na virada do ciclo |
|-------|----------------------------------|
| `organizations.plan_credits_balance` | Atribuído ao total mensal do plano vigente da organização |
| `organizations.purchased_credits_balance` | Intocado |
| `users.purchased_credits_balance` | Intocado |
| `credits_used_this_month` | Zerado |

O gatilho da renovação é o ciclo de cobrança da assinatura **da organização**, não o dia 1º do calendário. Organizações diferentes renovam em datas diferentes; a carteira pessoal do usuário não participa de nenhum ciclo.

Uma organização com `plan = null` não tem ciclo de cobrança e não recebe nenhuma atribuição de `plan_credits_balance` (Seção 12.18).

Implementação do reset: **[A DEFINIR]** — Job agendado a ser especificado na sessão de Cashier do Mês 4. Ver Seção 12.15.

---

# 12.5 Feature Gating por Plano

Cada plano concede acesso a um **subconjunto dos `pipeline_types`**, não apenas a um custo diferenciado.

O gating é do **plano da organização ativa**, nunca do usuário: o mesmo usuário pode ter acesso a `image_generation` em uma organização e não ter em outra.

Esta é uma mudança em relação ao modelo anterior, no qual todos os planos acessavam todos os pipelines e diferiam apenas em volume de créditos.

## Pipelines existentes

Os `pipeline_types` válidos são os declarados em `App\Models\Execution::PIPELINE_TYPES`:

```text
brand_research
briefing_generation
image_generation
copywriting_edit
seo_optimization
create_article
create_post
create_ad
```

## Fluxo de verificação

```text
Usuário solicita pipeline P na organização ativa O

↓

O.plan é null?

├── Sim → NoActivePlanException → HTTP 402 + CTA "Subscribe to a plan"
│
└── Não

    ↓

    O plano de O permite P?

    ├── Não → FeatureNotAvailableException → HTTP 403 + CTA "Upgrade your plan"
    │
    └── Sim → verificação de saldo (Seção 12.10)
```

## Regras

- **A verificação de plano nulo precede o gating.** Ver Seção 12.18 — é regra de segurança, porque a convenção "linha ausente em `plan_limits` significa habilitado" concederia acesso irrestrito a uma organização sem assinatura se a ordem fosse invertida.
- O gating precede a verificação de saldo. Um pipeline fora do plano nunca chega a consultar créditos e nunca cria uma Execution.
- Sem plano retorna **402**; pipeline fora do plano retorna **403**; saldo insuficiente retorna **402** com exceção distinta. Os três casos são distintos e não devem compartilhar código de erro, exceção nem mensagem (Seção 12.12).
- O mapeamento plano → pipelines permitidos nunca pode ser hardcoded em Controllers, Livewire, Jobs ou Enums. A fonte é a mesma tabela de configuração comercial descrita na Seção 12.14.
- A modelagem exata desse mapeamento (coluna em `plan_limits`, tabela associativa `plan_pipeline_types` ou campo JSONB) permanece **[A DEFINIR]** e exige ADR antes da implementação.

---

# 12.6 Planos Comerciais

Fonte: `.claude/docs/PLANOS.md`.

O plano é assinado **por organização**. Os planos vigentes do Arkion são três, mais o estado "sem plano":

| Plano | Foco | Público |
|-------|------|---------|
| **(sem plano — `plan = null`)** | Estado inicial de toda organização recém-criada. Sem execuções. | Ver Seção 12.18 |
| **Arkion Copy & Briefing** | Trilha de texto — pesquisa, estratégia, briefing e conteúdo escrito | Influenciadores, copywriters, criadores de conteúdo, gestores de tráfego solo |
| **Arkion Studio Visual** | Trilha visual — identidade, personagens, imagem e vídeo | Designers, editores de vídeo, criadores de identidade visual |
| **Arkion Full Pass** | Acesso integral às duas trilhas + cursos da Tiny Academy | Criadores avançados, agências |

---

## Preços

| Plano | BRL / mês | USD / mês | Marca adicional | Pacote de créditos recomendado |
|-------|----------:|----------:|----------------:|-------------------------------:|
| Arkion Copy & Briefing | R$ 39,90 | US$ 12,90 | R$ 14,99 | R$ 19,00 |
| Arkion Studio Visual | R$ 59,90 | US$ 24,90 | R$ 14,99 | R$ 32,90 |
| Arkion Full Pass | R$ 119,90 | **[A DEFINIR]** | R$ 14,99 | R$ 43,90 |

### Marca adicional

O valor de **R$ 14,99/mês por marca adicional é idêntico nos três planos** e não escala com o preço da assinatura.

Uma marca adicional é um `Project` extra **dentro da organização** — de topo ou sub-brand da hierarquia da Seção 19. A cobrança é pela Brand, não pela posição dela na árvore: uma sub-brand custa o mesmo que uma marca de topo, e é cobrada da organização dona da Brand.

O saldo de créditos permanece o da organização (Seção 12.2). Marcas adicionais ampliam o número de contextos de trabalho, nunca o saldo.

### Pacote de créditos recomendado

Cada plano tem um pacote de créditos avulsos sugerido como *upsell* no fluxo de contratação. Os pacotes são registros de `credit_packages` (Seção 12.9).

A **quantidade de créditos** de cada pacote está marcada como `"X"` no `PLANOS.md` e permanece **[A DEFINIR]** — apenas o preço está fixado.

---

## Pipelines Habilitados por Plano

O feature gating da Seção 12.5 opera sobre `App\Models\Execution::PIPELINE_TYPES`.

| `pipeline_type` | Sem plano | Copy & Briefing | Studio Visual | Full Pass |
|-----------------|:---------:|:---------------:|:-------------:|:---------:|
| `brand_research` | ❌ | ✅ | ❌ | ✅ |
| `briefing_generation` | ❌ | ✅ | ❌ | ✅ |
| `create_article` | ❌ | ✅ | ❌ | ✅ |
| `copywriting_edit` | ❌ | **[A DEFINIR]** | ❌ | ✅ |
| `seo_optimization` | ❌ | **[A DEFINIR]** | ❌ | ✅ |
| `create_post` | ❌ | **[A DEFINIR]** | ❌ | ✅ |
| `create_ad` | ❌ | **[A DEFINIR]** | ❌ | ✅ |
| `image_generation` | ❌ | ❌ | ✅ | ✅ |

A coluna **sem plano** é integralmente `❌` e **não depende de `plan_limits`** — é decidida antes, pela verificação da Seção 12.18.

O **Full Pass libera todos os pipelines** — é a definição literal do plano no `PLANOS.md` ("todos os recursos liberados"), e as células **[A DEFINIR]** das outras colunas não o afetam.

### Origem de cada ✅

| Recurso no `PLANOS.md` | `pipeline_type` |
|------------------------|-----------------|
| Pesquisa de marketing, Buyer Persona | `brand_research` |
| Geração de Briefings | `briefing_generation` |
| Geração de Artigos | `create_article` |
| Geração de imagens | `image_generation` |

### Por que quatro células estão [A DEFINIR]

O `PLANOS.md` lista **recursos comerciais**, não `pipeline_types`. Quatro recursos do plano Copy & Briefing não têm correspondência inequívoca:

| Recurso | Ambiguidade |
|---------|-------------|
| Estratégia de social media | Pode significar acesso a `create_post` ou apenas a tela de estratégia (`app/Livewire/Marketing/SocialStrategy.php`), sem geração de peça. |
| Estratégia de tráfego pago | Mesma ambiguidade em relação a `create_ad`. |
| — | `copywriting_edit` e `seo_optimization` não aparecem em nenhuma lista de recursos, apesar de existirem como pipelines e terem custo definido na Seção 12.7. |

Resolver antes de implementar o gating. Marcar ✅ por analogia seria conceder acesso pago sem decisão comercial.

### Recursos sem pipeline correspondente

Estes recursos do `PLANOS.md` **não possuem `pipeline_type`** em `Execution::PIPELINE_TYPES`:

| Recurso | Plano | Situação |
|---------|-------|----------|
| Edição de imagens | Studio Visual | Sem pipeline. **[A DEFINIR]** |
| Storyboard | Studio Visual | Sem pipeline. **[A DEFINIR]** |
| Geração de vídeos | Studio Visual | Sem pipeline. **[A DEFINIR]** |
| Edição de vídeos | Studio Visual | Sem pipeline. **[A DEFINIR]** |

Cada um exige um novo `pipeline_type`, uma linha em `plan_limits` e um workflow no n8n. Enquanto não existirem, o Studio Visual entrega apenas `image_generation` como pipeline de IA — o que torna esses quatro itens **bloqueadores do lançamento comercial do plano**, não trabalho opcional.

### Recursos que não são pipelines

Brand Core, Visual Identity, Produtos, Personagens e mascotes são **áreas da interface** — telas de Brand, mapeadas na Seção 10.13 — e não consomem créditos. Aparecem nas listas do `PLANOS.md` como parte da proposta de valor, mas não entram no feature gating por `pipeline_type`. Permanecem acessíveis a uma organização sem plano (Seção 12.18).

Cursos da **Tiny Academy** (Full Pass) são conteúdo externo à plataforma. A forma de entrega e de controle de acesso é **[A DEFINIR]**.

---

## Créditos Mensais por Plano

| Plano | Créditos / mês |
|-------|---------------:|
| Sem plano (`plan = null`) | 0 — nenhuma atribuição ocorre |
| Arkion Copy & Briefing | **[A DEFINIR]** |
| Arkion Studio Visual | **[A DEFINIR]** |
| Arkion Full Pass | **[A DEFINIR]** |

**Esta pendência é deliberada e não deve ser preenchida por inferência.**

O `PLANOS.md` usa `"X"` como placeholder porque o número ainda não foi decidido. A decisão depende de **medição de uso real**: registrar o consumo de cada ferramenta em planilha para apurar a média por ação — ação pendente da call de **7 de agosto de 2026**.

Sem essa medição, qualquer número escolhido é arbitrário e define errado tanto a margem quanto a percepção de valor do plano. Nenhuma implementação de billing, de `plan_limits` por plano ou de renovação mensal (Seção 12.4) pode assumir um valor provisório.

O mesmo vale para a quantidade de créditos dos pacotes recomendados acima.

---

## Plano Enterprise

O tratamento de créditos ilimitados (`plan_limits.credits = null`) permanece implementado conforme a Seção 12.15.

`plan_limits.credits = null` (custo ilimitado/gratuito da ação) e `organizations.plan = null` (organização sem assinatura) são conceitos **distintos** e nunca devem ser confundidos: o primeiro é configuração comercial de custo, o segundo é ausência de contrato.

O `PLANOS.md` não menciona um plano Enterprise — os três planos comerciais acima são a oferta completa. A relação entre o comportamento *unlimited* já implementado e essa oferta é **[A DEFINIR]**: pode ser um plano negociado fora do catálogo público ou um resíduo a ser removido.

---

# 12.7 Consumo por Ação

| Ação | Créditos |
|-------|----------:|
| Brand Research | 50 |
| Briefing Generation | 5 |
| Image Generation | 7 |
| Copywriting Edit | 15 |
| SEO Optimization | 10 |
| Create Article | **[A DEFINIR]** |
| Create Post | **[A DEFINIR]** |
| Create Ad | **[A DEFINIR]** |

O custo deve ser obtido dinamicamente da tabela `plan_limits`.

---

# 12.8 Armazenamento do Saldo

Os saldos estão distribuídos entre duas tabelas.

| Tabela | Campo | Finalidade | Estado |
|--------|-------|------------|--------|
| `organizations` | `plan_credits_balance` | Saldo do plano da org, renovado mensalmente, não cumulativo | Migration pendente |
| `organizations` | `purchased_credits_balance` | Saldo comprado pela org, nunca expira | Migration pendente |
| `users` | `purchased_credits_balance` | Carteira pessoal do usuário, nunca expira, portátil entre orgs | Migration pendente |
| `users` | `credits_used_this_month` | Consumo mensal agregado (métrica de exibição) | Implementado |

Nenhuma coluna de saldo de plano existe em `users`. Nenhuma coluna de crédito existe em `projects` (Seção 19.3).

## Divergência com a implementação atual

O código em produção ainda utiliza uma **única** coluna `users.credits_balance` (migration `0001_01_01_000000_create_users_table`), consumida por `App\Services\CreditService`, e não existe tabela `organizations`.

A migração para o modelo de três carteiras é trabalho de código, não desta sessão de documentação, e envolve:

1. migrations criando `organizations` e `organization_user` (Seção 4.15);
2. migration adicionando `organizations.plan_credits_balance`, `organizations.purchased_credits_balance` e `users.purchased_credits_balance`;
3. backfill: uma organização "Personal" por usuário existente, com ele como Admin, recebendo o `credits_balance` atual em `organizations.plan_credits_balance`; *(🔁 SUPERSEDED pela ADR-010: o usuário da organização Personal é **Owner**, não Admin. O backfill já executado gravou `admin`; a promoção a `owner` é trabalho de código registrado na Seção 20.8.)*
4. backfill de `projects.organization_id` apontando para a organização Personal do antigo `projects.user_id`;
5. reescrita de `CreditService::deduct()` para aplicar a ordem da Seção 12.3 e o fluxo de confirmação da Seção 12.19 dentro do lock pessimista;
6. remoção de `users.credits_balance` em migration posterior, após o backfill validado.

Não há tabela de ledger de créditos. Toda a rastreabilidade de consumo depende de `executions` e de `admin_action_logs`.

> **[A DEFINIR]** — Rastreabilidade da carteira de origem. Sem ledger, um débito que atravessa três carteiras não deixa registro de quanto saiu de cada uma, o que inviabiliza o estorno proporcional citado na Seção 12.16. Decidir antes do lançamento comercial: colunas de breakdown em `executions` ou tabela de ledger própria. Exige ADR.

---

# 12.9 Tabela `credit_packages`

Pacotes de créditos avulsos são administráveis pelo painel administrativo e vivem em tabela própria.

## Propósito

Permitir que a operação crie, renomeie, reprecifique e desative pacotes de crédito **sem deploy de código**, exatamente como `plan_limits` faz para os custos de ação.

## Estrutura Conceitual

| Campo | Tipo | Papel |
|-------|------|-------|
| `id` | bigint | PK |
| `name` | string | Nome comercial apresentado ao usuário (em inglês, Seção 10.1.1) |
| `credits` | unsignedInteger | Quantidade creditada na carteira de destino |
| `price_cents` | unsignedInteger | Preço em centavos |
| `currency` | string(3) | Moeda ISO 4217 |
| `is_active` | boolean | Controla a exibição no catálogo |
| `sort_order` | unsignedInteger | Ordem de apresentação |
| `external_price_id` | string, nullable | Identificador do preço no gateway de pagamento |
| `created_at` / `updated_at` | timestamps | Laravel padrão |

## Regras

- Créditos adquiridos por um pacote **nunca** creditam nenhum `plan_credits_balance`.
- A carteira de destino depende de **quem comprou e em que capacidade**:
  - compra feita pelo **Owner** para a organização → `organizations.purchased_credits_balance`;
  - compra feita por qualquer usuário para si → `users.purchased_credits_balance`.
- **Admin e Member só podem comprar créditos pessoais.** A compra de créditos para a organização é exclusiva do Owner (Seção 20.4, ADR-010).
- A capacidade da compra é decidida no backend a partir do papel do usuário na organização ativa (`OrganizationRole::canPurchaseForOrganization()`), nunca por um campo enviado pelo cliente.

> 🔁 **SUPERSEDED (ADR-010):** a revisão 2.1 desta lista atribuía a compra para a organização a "um **Admin**" e restringia apenas o Member a créditos pessoais. O Admin perdeu essa capacidade.
- Um pacote desativado (`is_active = false`) desaparece do catálogo mas nunca é excluído — compras históricas precisam manter a referência.
- Alterar o preço de um pacote nunca reprecifica compras passadas.
- Toda alteração administrativa em `credit_packages` é registrada em `admin_action_logs` (Seção 14).
- A modelagem definitiva — incluindo a decisão sobre `external_price_id`, que depende do gateway escolhido na Seção 12.13 — permanece **[A DEFINIR]** até a sessão de Cashier do Mês 4.

## Pacotes Recomendados por Plano

O `PLANOS.md` define um pacote de *upsell* por plano. Os preços estão fixados; as quantidades de crédito, não:

| Plano | Preço do pacote | Créditos |
|-------|----------------:|---------:|
| Arkion Copy & Briefing | R$ 19,00 | **[A DEFINIR]** |
| Arkion Studio Visual | R$ 32,90 | **[A DEFINIR]** |
| Arkion Full Pass | R$ 43,90 | **[A DEFINIR]** |

A quantidade depende da mesma medição de uso descrita na Seção 12.6. Não inferir.

Nada impede que o catálogo tenha outros pacotes além destes três — `credit_packages` é uma tabela administrável, e a associação "pacote recomendado para o plano X" é apresentação, não uma restrição de compra.

---

# 12.10 Validação Antes de Cada Ação

Fluxo obrigatório:

```text
Usuário na organização ativa O, com a Brand ativa B (B pertence a O)

↓

Pipeline solicitado

↓

1. O.plan é null?  (Seção 12.18)

├── Sim → NoActivePlanException → HTTP 402 + CTA "Subscribe to a plan"
│
└── Não

    ↓

    2. Feature gating do plano de O (Seção 12.5)

    ├── Pipeline fora do plano → FeatureNotAvailableException → HTTP 403 + CTA "Upgrade your plan"
    │
    └── Pipeline permitido

        ↓

        3. EnsureCreditBalance

        ↓

        Consulta plan_limits

        ↓

        Consulta org.plan + org.purchased  (+ user.purchased, se confirmado)

        ↓

        3a. org.plan + org.purchased cobre o custo?

        ├── Sim → débito nas carteiras da org → execução
        │
        └── Não

            ↓

            3b. confirm_personal_credits = true na requisição?

            ├── Não → HTTP 409 + breakdown do custo por carteira (Seção 12.19)
            │
            └── Sim

                ↓

                3c. org.plan + org.purchased + user.purchased cobre o custo?

                ├── Não → InsufficientCreditsException → HTTP 402 + CTA "Buy AI Credits"
                │
                └── Sim → débito nas três carteiras → execução
```

Nenhuma ação que consuma créditos pode ignorar esta sequência, e a ordem das etapas 1 → 2 → 3 é obrigatória.

Os CTAs indicados acima são os do Owner. O que Admin e Member veem em cada bloqueio está na Seção 12.12 (CTA por papel).

---

# 12.11 Débito de Créditos

O débito ocorre na criação da Execution, antes de qualquer contato com o n8n, conforme registrado na Seção 12.16.

Independentemente da implementação:

- o valor debitado é sempre o registrado em `plan_limits`;
- a ordem de consumo é sempre a da Seção 12.3;
- as carteiras tocadas são atualizadas na mesma transação e sob o mesmo lock;
- a carteira pessoal só entra na transação quando a requisição trouxe `confirm_personal_credits = true`;
- o valor efetivamente retirado de cada carteira é **recalculado dentro da transação**, nunca lido de um cálculo anterior à confirmação (Seção 12.19).

---

# 12.12 Os Três Estados de Bloqueio

Uma execução pode ser recusada por três motivos **distintos**, com exceção, código HTTP, mensagem e CTA próprios. **Nunca colapsar os três em uma mensagem genérica** — cada um leva o usuário a uma ação diferente.

| Situação | Exceção | HTTP | Mensagem (inglês, Seção 10.1.1) | CTA |
|----------|---------|:----:|--------------------------------|-----|
| Organização sem plano ativo | `NoActivePlanException` | **402** | "This organization has no active plan." | **Subscribe to a plan** |
| Pipeline fora do plano | `FeatureNotAvailableException` | **403** | "This feature is not included in your plan." | **Upgrade your plan** |
| Saldo insuficiente nas carteiras autorizadas | `InsufficientCreditsException` | **402** | "Not enough credits to run this action." | **Buy AI Credits** |

Os dois primeiros estados não são resolvidos comprando créditos, e o terceiro não é resolvido assinando ou fazendo upgrade. Uma mensagem genérica manda o usuário para a tela errada.

## CTA por papel

A coluna CTA acima é o que vê **quem tem permissão** para resolver o bloqueio. Com três papéis (ADR-010), assinar, fazer upgrade e comprar créditos para a organização são exclusivos do Owner (Seção 20.4) — mostrar esses CTAs a Admin ou Member os levaria a um checkout que o backend recusaria com 403.

| Situação | Owner | Admin e Member |
|----------|-------|----------------|
| Sem plano (`NoActivePlanException`, 402) | **Subscribe to a plan** | Sem CTA de checkout. Mensagem: "Ask your organization owner to subscribe to a plan." |
| Pipeline fora do plano (`FeatureNotAvailableException`, 403) | **Upgrade your plan** | Sem CTA de checkout. Mensagem: "Ask your organization owner to upgrade the plan." |
| Saldo insuficiente (`InsufficientCreditsException`, 402) | **Buy AI Credits** — destino organização ou pessoal | **Buy AI Credits** — somente destino pessoal — e a mensagem "Or ask your organization owner to add organization credits." |

Regras:

- **O bloqueio continua distinto por situação.** A troca do CTA por papel não colapsa os três estados: a mensagem principal de cada linha da tabela anterior permanece; muda apenas a ação oferecida.
- **Sem plano, nenhum crédito resolve** — nem o pessoal. Por isso Admin e Member não recebem "Buy AI Credits" no estado sem plano: comprar créditos pessoais não destravaria a execução (Seção 12.18).
- **Saldo insuficiente pode ser resolvido por qualquer membro** com a carteira pessoal, já que comprar créditos pessoais é capacidade de todos os papéis. O CTA aparece para todos; o que muda é a carteira de destino disponível no checkout (Seção 12.13).
- **O papel exibido vem do backend.** A interface recebe a capacidade (`canManageBilling`, `canPurchaseForOrganization`) já resolvida pelo Laravel, nunca deduz o papel no cliente. Esconder o CTA é conveniência de UX; a recusa real é a Policy (Seção 20.4).
- Todo texto é em inglês (Seção 10.1.1). O texto exato das mensagens para Admin e Member acima é a redação de referência; ajustes de copy não alteram a regra.

Existe ainda um quarto retorno, que **não é um bloqueio** e sim uma pergunta: **HTTP 409** quando o custo tocaria a carteira pessoal sem confirmação (Seção 12.19).

Quando a soma das carteiras autorizadas for insuficiente:

- a execução não deve ser criada;
- nenhuma chamada ao n8n deve ocorrer;
- nenhuma das carteiras deve ser parcialmente debitada;
- retornar HTTP 402 com `InsufficientCreditsException`;
- exibir CTA **"Buy AI Credits"**.

---

# 12.13 Compra de Créditos Adicionais

A interface disponibiliza um botão permanente na Sidebar:

```
Buy AI Credits
```

Fluxo esperado:

```text
Sidebar

↓

Buy AI Credits

↓

Catálogo de credit_packages ativos

↓

Escolha da carteira de destino
  ├── Organização  (somente Owner)
  └── Pessoal      (qualquer membro — Owner, Admin ou Member)

↓

Checkout do gateway

↓

Pagamento

↓

Webhook do gateway

↓

Laravel

↓

organizations.purchased_credits_balance += credit_packages.credits   (compra da org)
        ou
users.purchased_credits_balance         += credit_packages.credits   (compra pessoal)
```

O papel exibido na etapa de escolha vem do backend. Para Admin e Member a opção "Organização" não é exibida — a etapa de escolha mostra apenas a carteira pessoal. Um Admin ou Member que forjar a opção "Organização" recebe 403: a autorização é reavaliada no processamento do webhook e na criação do checkout, nunca apenas na UI.

> 🔁 **SUPERSEDED (ADR-010):** a revisão 2.1 deste fluxo marcava a carteira da organização como "(somente Admin)" e descrevia apenas o Member forjando a opção. A compra para a organização é exclusiva do Owner.

## Infraestrutura de Pagamento

**[A DEFINIR]** — a plataforma de cobrança para créditos avulsos ainda não foi escolhida. As alternativas em avaliação são:

| Alternativa | Consideração |
|-------------|--------------|
| **Stripe Checkout one-time** | Reaproveita o Stripe já adotado para assinaturas (Seção 13.3) e o Laravel Cashier já previsto na stack. Exige tratar pagamentos avulsos fora do fluxo de subscription do Cashier. |
| **Lemon Squeezy** | Atua como Merchant of Record, absorvendo a apuração de impostos sobre vendas internacionais. Introduz um segundo gateway e um segundo conjunto de webhooks na arquitetura. |

**Decisão adiada para a sessão de Cashier do Mês 4.** Nenhuma implementação de compra de créditos avulsos pode começar antes desta decisão, pois ela determina o formato de `credit_packages.external_price_id`, o contrato do webhook de confirmação e a modelagem do registro da compra.

A assinatura mensal dos planos permanece no Stripe + Laravel Cashier, decisão já fixada e não reaberta por esta pendência. **O titular da assinatura passa a ser a Organização, não o usuário** — o `Billable` do Cashier muda de Model, o que é mudança estrutural e deve ser tratada na mesma sessão. O levantamento do estado atual e do que precisa migrar está na Seção 12.20.

---

# 12.14 Fonte da Verdade

Os custos das ações e o mapeamento de pipelines por plano nunca devem ser codificados em:

- constantes;
- enums;
- Controllers;
- Livewire;
- Jobs.

A única fonte oficial é:

```
plan_limits
```

com possibilidade de cache em Redis.

O catálogo de pacotes avulsos tem como fonte oficial:

```
credit_packages
```

O plano vigente e os saldos da operação têm como fonte oficial a linha da organização em:

```
organizations
```

---

> ⚠️ **Regra crítica:** Nunca hardcode valores de créditos por ação, volumes mensais de plano, preços de pacote ou listas de pipelines permitidos. Todo custo e todo gating devem ser consultados na configuração persistida (preferencialmente via cache Redis), garantindo que alterações comerciais possam ser realizadas sem mudanças no código da aplicação.

---

# 12.15 Registro de Implementação — Pendências do CreditService

- **Organizações ainda não existem**: não há tabela `organizations`, `organization_user` nem `projects.organization_id`. Todo o modelo das Seções 12.2, 12.3, 12.18, 12.19 e 20 depende dessa modelagem.
- **Três carteiras ainda não implementadas**: `CreditService` opera sobre a coluna única `users.credits_balance`. A migração descrita na Seção 12.8 é pré-requisito das Seções 12.2, 12.3 e 12.4.
- **Fluxo de confirmação da carteira pessoal não implementado**: não existe `confirm_personal_credits`, nem retorno 409, nem breakdown por carteira. Ver Seção 12.19.
- **Estado sem plano não implementado**: não existe `organizations.plan` nem `NoActivePlanException`. Hoje nenhuma verificação de assinatura antecede a execução — `CheckSubscription` permanece pendente até o Mês 4.
- **Feature gating ainda não implementado**: hoje qualquer plano pode solicitar qualquer `pipeline_type` válido. Não existe verificação de 403 por plano em `ExecutionController@store` nem em `BriefingForm`.
- **Reset mensal de `credits_used_this_month`**: [A DEFINIR]. Implementar como Job agendado no Mês 5 (`PruneOldExecutionsCommand` ou similar). O mesmo Job passa a ser responsável pela atribuição de `organizations.plan_credits_balance` descrita na Seção 12.4.
- **`PlanLimit::forAction()` ignora o plano do usuário** — busca o primeiro registro da action, sem escopar por `plan`. Corrigir quando houver precificação diferenciada por plano: mudará a chave de cache de `plan_limits:{action}` para `plan_limits:{plan}:{action}` e exigirá ADR antes da implementação. Os três planos comerciais da Seção 12.6 tornam essa correção obrigatória.
- **`plan_limits.credits` permanece NOT NULL no SQLite** — a migration que remove a restrição é exclusiva do PostgreSQL, de modo que `PlanLimitSeeder` (que grava `credits = null` para o plano enterprise) não pode ser executado na suíte de testes. Os testes que precisam de custo registram apenas a linha da action que exercitam.

---

# 12.16 Registro de Implementação — Momento do Débito

As Seções 6.9 e 12.11 registram o momento do débito. A implementação em `App\Http\Controllers\Api\ExecutionController@store` adota o **débito na criação da Execution**, antes de qualquer contato com o n8n:

```text
canExecute()  →  transação { Execution::create(queued) + CreditService::deduct() }  →  commit  →  Job
```

Duas consequências desenham a escolha:

- **A verificação otimista e o débito definitivo são etapas distintas.** `canExecute()` decide o fluxo (402/403/409 versus continuação); a garantia real vem do lock pessimista dentro de `deduct()`. Duas requisições simultâneas nunca gastam o mesmo crédito.
- **Criação e débito compartilham uma transação.** Se o saldo real, lido sob lock, não cobrir o custo, a Execution criada instantes antes desaparece com o rollback — a Seção 12.12 exige que saldo insuficiente não deixe execução alguma para trás.

O Job só é despachado após o commit: um Worker que lesse a Execution antes dela existir no banco falharia em `findOrFail`.

Quando as três carteiras forem implementadas, a ordem de consumo da Seção 12.3 deve ser aplicada **dentro** deste mesmo lock, que passa a travar a linha da organização e — apenas quando a carteira pessoal for tocada — também a linha do usuário. Ler um saldo fora do lock e debitar o outro dentro dele reabre exatamente a corrida que o lock existe para fechar.

Com organizações, essa corrida deixa de ser hipotética: **dois membros diferentes da mesma organização competem pelo mesmo saldo**, e não apenas duas abas do mesmo usuário. É essa concorrência que torna obrigatório o recálculo descrito na Seção 12.19.

**Não implementado:** política de estorno em caso de falha do pipeline. A Seção 6.2 registra o tema como **[A DEFINIR]**; hoje uma Execution que falha no n8n mantém o crédito debitado. Decidir antes do lançamento comercial. O estorno precisará devolver o crédito à carteira de origem — um débito que atravessou as três carteiras exige estorno proporcional a cada uma, o que depende da rastreabilidade pendente na Seção 12.8.

---

# 12.17 Registro de Implementação — Escopo da Brand em Listagens

O middleware `EnsureActiveProject` previsto na `CLAUDE.md` §4 ainda não existe, e não há mecanismo de sessão que fixe a Brand ativa do lado do servidor. Tampouco existe o contexto de organização ativa da Seção 20.5.

Enquanto ele não centraliza esse contexto, `App\Http\Requests\Execution\IndexExecutionRequest` resolve a Brand de `GET /api/executions` assim:

1. `project_id` da query string, quando informado — autorizado por `ProjectPolicy@view`, de modo que a Brand de outro usuário devolve 403;
2. na ausência dele, a Brand marcada como ativa em `projects.is_active`;
3. sem nenhuma das duas, 422 com mensagem em português.

> A mensagem em português citada no item 3 é anterior à regra de idioma da Seção 10.1.1 e constitui dívida técnica: deve ser traduzida para inglês quando o Form Request for tocado.

A consulta escopa hoje simultaneamente por `user_id` e `project_id`. Quando as organizações existirem, o escopo por `user_id` é **substituído** pelo escopo por `organization_id` (Seção 20.6) — a Brand deixa de pertencer ao usuário e passa a pertencer à organização, e a verificação passa a ser "a Brand pertence à organização ativa, e o usuário é membro dela".

`ExecutionPolicy@view`, por sua vez, verifica somente `user_id`: uma Execution de outra Brand do mesmo usuário continua sendo dele e não pode virar 403 ao ser aberta pelo link direto de `/result/{execution}`. Essa verificação também migra para `organization_id` quando as organizações existirem — um membro da organização precisa poder abrir a Execution disparada por outro membro na mesma Brand. O recorte por Brand permanece responsabilidade das consultas de listagem.

---

# 12.18 Organização sem Plano Ativo (`plan = null`)

**Decisão confirmada.** `organizations.plan` é **NULLABLE**. Uma organização recém-criada — incluindo a organização "Personal" criada automaticamente no primeiro login (Seção 20.3) — nasce **sem plano**.

`plan = null` significa **"sem assinatura ativa"**. Não é um plano gratuito, nem um trial, nem um estado de erro: é o estado inicial normal de toda organização.

## O que uma organização sem plano PODE fazer

- existir;
- ter membros, convidar e remover membros;
- criar, editar e excluir Marcas (Brands e sub-brands);
- preencher Brand Core, Visual Identity, produtos, personagens e demais assets;
- navegar por todo o histórico já produzido.

Esta lista descreve o que a **organização** pode fazer sem plano, não o que **cada papel** pode fazer. Quem executa cada ação continua decidido pela matriz da Seção 20.4: convidar e remover membros é de Owner e Admin (com os limites da matriz), e criar, editar e excluir Marcas é de Owner e Admin — um Member de uma organização sem plano navega pelas Marcas e pelo histórico, mas não as altera.

## O que uma organização sem plano NÃO PODE fazer

- **executar qualquer pipeline.** Nenhum. Sem exceção por tipo, por saldo ou por carteira pessoal.

Ter créditos na carteira pessoal **não** contorna a ausência de plano: o eixo do plano é verificado antes do eixo de saldo, e nenhuma carteira é consultada quando ele reprova.

## Ordem de verificação — regra de segurança

A verificação de plano nulo acontece **ANTES** da verificação de feature gating. Esta ordem é **obrigatória e é uma regra de segurança**, não um detalhe de implementação:

```text
1. O.plan é null?         → NoActivePlanException (402)     ← PRIMEIRO, sempre
2. plan permite pipeline? → FeatureNotAvailableException (403)
3. saldo cobre o custo?   → InsufficientCreditsException (402)
```

**Por quê:** a convenção de feature gating (Seção 12.5) trata **linha ausente em `plan_limits` como habilitado**. Uma organização sem plano não possui nenhuma linha em `plan_limits` — logo, se o gating fosse avaliado primeiro, ele concluiria que *todos* os pipelines estão liberados e concederia **acesso irrestrito a uma organização sem assinatura**.

Inverter esta ordem transforma a ausência de contrato em acesso total. Qualquer refatoração que reordene essas verificações é um defeito de segurança, não uma mudança de estilo.

## Renovação e créditos

Uma organização sem plano não tem ciclo de cobrança: `plan_credits_balance` permanece em `0` e nenhuma rotina de renovação a toca (Seção 12.4). `organizations.purchased_credits_balance` pode existir e permanecer intocado — créditos comprados não expiram e ficam disponíveis assim que um plano for assinado.

## Assinatura

Assinar um plano é ação exclusiva do **Owner** da organização (Seção 20.4, ADR-010). Admin e Member que encontram o bloqueio 402 **não** veem o CTA "Subscribe to a plan" nem qualquer caminho até o checkout; veem a mensagem "Ask your organization owner to subscribe to a plan." (Seção 12.12, CTA por papel). A abertura do checkout é reautorizada no backend — um Admin ou Member que chame a rota diretamente recebe 403.

Como o estado sem plano não é resolvido por créditos, nenhum papel recebe "Buy AI Credits" como saída deste bloqueio — nem o Owner, cujo único CTA aqui é assinar.

> 🔁 **SUPERSEDED (ADR-010):** a revisão 2.1 atribuía a assinatura a "**Admin** da organização" e mostrava o CTA "Subscribe to a plan" também ao Member, indicando que "um Admin da organização precisa assinar". Com três papéis, assinar é exclusivo do Owner e o CTA só aparece para quem tem a permissão.

---

# 12.19 Fluxo de Confirmação da Carteira Pessoal

A carteira pessoal (`users.purchased_credits_balance`) **nunca é debitada sem confirmação explícita do usuário**.

## Contrato

1. O usuário dispara uma ação que consome créditos.
2. O backend calcula o custo e o distribui pelas carteiras na ordem da Seção 12.3.
3. Se o débito calculado **tocaria a carteira pessoal** e a requisição **não** traz `confirm_personal_credits = true`, a API responde **HTTP 409** com o *breakdown* do custo por carteira — e **nada é debitado, nenhuma Execution é criada**.
4. O frontend exibe um modal de confirmação apresentando esse breakdown.
5. O usuário confirma e o frontend **reenvia a mesma requisição** com `confirm_personal_credits = true`.
6. O backend recalcula o débito dentro da transação e executa.

## Formato do 409

```json
{
  "error": "personal_credits_confirmation_required",
  "message": "This action will use your personal credits.",
  "breakdown": {
    "total_cost": 50,
    "organization_plan_credits": 30,
    "organization_purchased_credits": 10,
    "personal_credits": 10
  }
}
```

O breakdown é **informativo**, exibido para que o usuário decida com contexto.

## A confirmação autoriza a carteira, não um valor

> ⚠️ **Regra crítica:** `confirm_personal_credits = true` significa **"autorizo usar minha carteira pessoal nesta ação"**. Não significa "autorizo exatamente N créditos pessoais".

O valor real retirado da carteira pessoal é **recalculado dentro da transação de débito**, sob o lock da Seção 12.16, e pode ser **maior ou menor** do que o breakdown do 409.

O motivo é a concorrência intrínseca ao modelo de organizações: entre o aviso e a confirmação, **outro membro da mesma organização pode ter gasto créditos da org** (aumentando a parcela pessoal) ou **o Owner pode ter comprado créditos para a org** (reduzindo ou zerando a parcela pessoal; até a ADR-010 esta frase dizia "um Admin" — compra para a org é hoje exclusiva do Owner). Congelar o valor do aviso e debitá-lo cegamente gastaria crédito pessoal que já não era necessário, ou debitaria menos do que o custo real.

Se, no recálculo, a soma das três carteiras não cobrir mais o custo, a resposta é `InsufficientCreditsException` (402) — não um débito parcial.

Se, no recálculo, a carteira pessoal **não for mais necessária**, ela simplesmente não é tocada. A flag autoriza; não obriga.

## O bloqueio é no backend

> ⚠️ **Regra crítica:** O bloqueio da carteira pessoal é **do backend**, nunca apenas da interface. Uma chamada direta à API, sem passar pelo modal, que resultaria em débito pessoal e não traz a flag, é **rejeitada com 409**. O modal é conveniência de UX; a regra vive no serviço de crédito.

Nenhum componente Livewire, Controller ou cliente pode injetar a flag por conta própria: ela precisa vir da requisição originada pela confirmação do usuário.

## Escopo da confirmação

- A confirmação vale para **uma ação**. Não é preferência persistida, não é "não perguntar novamente", não é sessão.
- Cada nova ação que tocaria a carteira pessoal exige nova confirmação.
- Um eventual opt-in persistente ("sempre usar meus créditos pessoais nesta organização") é **[A DEFINIR]** e exige ADR — não implementar por inferência.

---

# 12.20 Pendência do Mês 4 — Organization como Customer Stripe

**Status:** pendência registrada, **não implementar** antes da sessão de Cashier do Mês 4. Esta seção registra o estado verificado do código e o escopo da migração; não é especificação de implementação.

## Por que

A Organização é a entidade de faturamento (Seção 20.1) e, com a ADR-010, o **Owner é o único responsável pelo faturamento** — assinar, trocar, cancelar plano, cartão e compra de créditos para a org (Seção 20.4). O customer Stripe, portanto, é a **Organization**: a assinatura e o meio de pagamento pertencem à org e sobrevivem à transferência de posse. Se o customer fosse o usuário Owner, transferir a posse deixaria a assinatura e o cartão presos à pessoa anterior.

## Estado atual verificado (Outubro/2026)

| Item | Onde | Estado |
|------|------|--------|
| Trait `Laravel\Cashier\Billable` | `app/Models/User.php` | **Aplicado em `User`**, não em `Organization` |
| Colunas de customer | migration `2026_07_23_030750_create_customer_columns` | Em `users`: `stripe_id` (nullable, indexada), `pm_type`, `pm_last_four`, `trial_ends_at` |
| Tabela `subscriptions` | migration `2026_07_23_030751_create_subscriptions_table` | FK `user_id` e índice `(user_id, stripe_status)` |
| Tabela `subscription_items` | migration `2026_07_23_030752_create_subscription_items_table` (+ `meter_id`, `meter_event_name`) | Referencia `subscription_id`; não depende do Billable e não muda |
| `Organization` | `app/Models/Organization.php` / `organizations` | Sem `Billable`, sem colunas `stripe_*` |
| Configuração do Cashier | `Cashier::useCustomerModel()` | Não chamado em nenhum provider — o Cashier assume `App\Models\User` |
| Uso do Cashier no código | `app/`, `routes/`, `resources/` | Nenhuma chamada a `subscribed()`, `newSubscription()`, `checkout()` ou similares — o trait está presente, mas sem uso |

A migration `2026_09_14_030500_remove_billing_columns_from_users` removeu de `users` as colunas de plano e saldo de plano, mas **preservou** as colunas `stripe_*` do Cashier: o faturamento migrou para `organizations` no domínio, não no Stripe.

## O que precisaria migrar

1. **Colunas de customer em `organizations`:** `stripe_id` (nullable, indexada), `pm_type`, `pm_last_four`, `trial_ends_at`.
2. **`subscriptions`:** trocar `user_id` por `organization_id` (FK `organizations`), refazer o índice como `(organization_id, stripe_status)`. Linhas existentes, se houver, precisam de backfill para a organização da qual o usuário é Owner.
3. **Trait `Billable`:** remover de `User` e aplicar em `Organization`; registrar `Cashier::useCustomerModel(Organization::class)` no `AppServiceProvider`.
4. **Colunas `stripe_*` em `users`:** remover em migration posterior ao backfill validado. Um `stripe_id` já existente em `users` aponta para um customer Stripe real; migrá-lo para a organização exige decisão sobre reaproveitar o customer ou criar um novo — **[A DEFINIR]** na sessão do Mês 4.
5. **E-mail e nome do customer:** o Cashier usa por padrão `email` e `name` do Billable; `organizations` não tem `email`. O e-mail de cobrança (do Owner atual, atualizado na transferência de posse, ou coluna própria `billing_email`) é **[A DEFINIR]**.
6. **Compra de créditos pessoais:** a carteira pessoal (`users.purchased_credits_balance`) é do usuário. Se o gateway da Seção 12.13 for o Stripe, a compra pessoal precisa de um pagador que é o **usuário**, não a org — o que pode exigir um segundo customer (usuário) ao lado do customer da organização, ou Checkout one-time sem customer persistido. **[A DEFINIR]**, junto da escolha do gateway.
7. **`subscription_items`:** sem alteração.

Nenhum destes passos é executado antes do Mês 4. Até lá, nenhum código novo deve passar a usar os métodos do `Billable` em `User`, porque eles seriam reescritos pela migração.

---

# 13. Integrações Externas

## 13.1 Visão Geral

O Arkion integra-se com serviços especializados para autenticação, cobrança, automação, inteligência artificial, monitoramento e disponibilidade.

Toda integração externa deve obedecer ao seguinte princípio arquitetural:

- Laravel é o orquestrador do domínio.
- n8n é o orquestrador das integrações de IA.
- Nenhum serviço externo possui acesso direto ao banco de dados.

---

# 13.2 Firebase Authentication

## Propósito

Responsável exclusivamente pela autenticação dos usuários.

---

## Chamado por

Laravel.

---

## Responsabilidades

- login;
- emissão do ID Token;
- verificação de identidade.

---

## Variáveis de Ambiente

```text
FIREBASE_PROJECT_ID

FIREBASE_CREDENTIALS

[A DEFINIR]
```

---

## Fluxo

```text
Usuário

↓

Firebase

↓

ID Token

↓

Laravel

↓

VerifyFirebaseToken
```

---

## Comportamento em Falha

| Situação | Comportamento |
|----------|---------------|
| Token inválido | HTTP 401 |
| Firebase indisponível | Requisição rejeitada |
| UID inexistente | **[A DEFINIR]** |

---

# 13.3 Stripe + Laravel Cashier

## Propósito

Gerenciar:

- assinaturas;
- cobrança recorrente;
- compra de créditos.

---

## Chamado por

Laravel.

---

## Componentes

- Checkout
- Webhooks
- Cashier

O Billable do Cashier deve ser a `Organization`, não o `User` — pendência do Mês 4 registrada na Seção 12.20. Toda operação de assinatura, troca de plano, cartão e compra de créditos para a org é autorizada apenas para o Owner (Seção 20.4).

---

## Variáveis

```text
STRIPE_KEY

STRIPE_SECRET

STRIPE_WEBHOOK_SECRET
```

---

## Comportamento em Falha

Pagamento recusado:

- assinatura permanece inalterada;
- créditos não são adicionados.

Webhook inválido:

- ignorado.

---

# 13.4 n8n

## Propósito

Executar pipelines de IA.

---

## Chamado por

Laravel.

---

## Responsabilidades

- orquestração;
- chamadas externas;
- composição de resultados.

---

## Nunca faz

- SQL;
- autenticação;
- autorização;
- broadcasting.

---

## Variáveis

```text
N8N_URL

N8N_SECRET
```

O nome oficial das variáveis permanece **[A DEFINIR]** caso difira da convenção acima.

---

## Comportamento em Falha

- timeout;
- indisponibilidade;
- callback inválido.

Todos os casos devem resultar em atualização apropriada da `Execution`.

---

# 13.5 Apify

## Propósito

Realizar coleta automatizada de informações públicas utilizadas pelos pipelines de pesquisa.

---

## Chamado por

n8n.

Nunca diretamente pelo Laravel.

---

## Variáveis

```text
APIFY_TOKEN
```

---

## Falha

O n8n deve capturar a falha e retornar erro estruturado ao Laravel.

---

# 13.6 Perplexity

## Propósito

Pesquisa estratégica e enriquecimento de contexto.

---

## Chamado por

n8n.

---

## Variáveis

```text
PERPLEXITY_API_KEY
```

---

## Falha

Erro propagado ao pipeline.

Laravel apenas registra o erro recebido.

---

# 13.7 Gemini 1.5 Flash

## Propósito

Raciocínio, classificação e análises durante determinados pipelines.

---

## Chamado por

n8n.

---

## Variáveis

```text
GEMINI_API_KEY
```

---

## Falha

Tratada exclusivamente pelo workflow.

---

# 13.8 Claude 3.5 Sonnet

## Propósito

Geração de conteúdo.

Aplicações identificadas:

- artigos;
- copies;
- materiais estratégicos;
- respostas textuais.

---

## Chamado por

n8n.

---

## Variáveis

```text
ANTHROPIC_API_KEY
```

---

## Falha

Erro estruturado devolvido ao Laravel.

---

# 13.9 Cloudflare

## Propósito

- DNS;
- SSL;
- Proxy;
- WAF;
- CDN.

---

## Chamado por

Usuário.

Não existe integração direta de domínio.

---

## Variáveis

Não aplicável.

---

## Falha

Caso o Cloudflare esteja indisponível, a aplicação torna-se inacessível externamente.

---

# 13.10 Sentry

## Propósito

Monitoramento de exceções.

---

## Chamado por

Laravel.

---

## Variáveis

```text
SENTRY_LARAVEL_DSN
```

Outras configurações permanecem:

**[A DEFINIR]**

---

## Falha

A indisponibilidade do Sentry nunca deve impedir o funcionamento da aplicação.

---

# 13.11 UptimeRobot

## Propósito

Monitoramento de disponibilidade.

---

## Chamado por

Serviço externo.

---

## Responsabilidades

Monitorar:

- disponibilidade;
- tempo de resposta;
- indisponibilidade.

---

## Variáveis

Não aplicável.

---

## Falha

Não interfere no funcionamento do sistema.

---

# 13.12 Matriz Geral

| Serviço | Chamado por | Acesso ao Banco |
|----------|-------------|-----------------|
| Firebase | Laravel | Não |
| Stripe | Laravel | Não |
| n8n | Laravel | Não |
| Apify | n8n | Não |
| Perplexity | n8n | Não |
| Gemini | n8n | Não |
| Claude | n8n | Não |
| Cloudflare | Usuário | Não |
| Sentry | Laravel | Não |
| UptimeRobot | Externo | Não |

---

> ⚠️ **Regra crítica:** Nenhuma integração externa pode acessar diretamente o PostgreSQL. Todo dado persistido deve passar obrigatoriamente pelo domínio Laravel.

---

# 14. Painel Administrativo

## 14.1 Propósito

O Painel Administrativo é a interface interna de operação do Arkion.

Ele existe para que a operação do produto — suporte, correção de saldo, inspeção de conta e diagnóstico de execuções — seja realizada dentro do domínio Laravel, sujeita às mesmas Policies, Services e regras de negócio da aplicação, e **nunca** através de acesso manual ao banco de dados.

Toda tarefa administrativa que hoje seria resolvida com um `UPDATE` manual em produção deve, obrigatoriamente, possuir uma ação equivalente no Painel Administrativo.

---

## 14.2 Quem Acessa

O acesso é restrito aos operadores internos do produto — atualmente apenas os dois fundadores (Nicolas e Erich).

A identificação de um administrador ocorre exclusivamente pela coluna booleana `is_admin` na tabela `users`.

Não existe autoatendimento, convite ou promoção de administradores pela interface: a atribuição de `is_admin` é realizada manualmente em banco pelos fundadores.

O Painel Administrativo **não** substitui a autenticação Firebase. Um administrador continua sendo um usuário comum que autentica via Firebase ID Token; `is_admin` concede apenas autorização adicional dentro do Laravel.

---

## 14.3 Decisão Fixa — `is_admin` na Tabela `users`

**Decisão:** o papel de administrador é representado por uma coluna booleana `is_admin` na tabela `users`.

**Status:** decisão fixa. Este ponto **não** é `[A DEFINIR]`.

**Alternativa rejeitada:** tabela separada (`admins`, `roles`, `permissions`) ou pacote de RBAC.

**Motivo:** o universo de administradores é fechado e composto por dois operadores internos, sem hierarquia, sem delegação e sem granularidade de permissão. Uma tabela de papéis introduziria joins, cache de permissões e superfície de erro sem resolver nenhum problema existente.

**Condição de reavaliação:** apenas por meio de uma ADR aprovada, e apenas se o sistema passar a contar com múltiplos funcionários exigindo hierarquia ou permissões granulares.

> ⚠️ **Regra crítica:** `users.is_admin` é **staff da plataforma Arkion** (Nicolas e Erich). Não tem nenhuma relação com `organization_user.role`, que é o papel do usuário **dentro de uma organização** (`owner`, `admin` ou `member` — ADR-010). Um não implica o outro em nenhuma direção: um staff da plataforma não é Owner nem Admin de nenhuma organização de cliente, e um Owner ou Admin de organização não tem acesso a `/admin/*`. Ver Seção 20.7. A decisão de manter `is_admin` como booleano **não** é revista por este modelo — os dois eixos coexistem por serem coisas diferentes.

---

## 14.4 Dependências

O Painel Administrativo é um consumidor de domínio já existente. Ele não introduz um modelo de dados paralelo.

| Domínio | Origem | Uso no painel |
|---------|--------|---------------|
| Users | Tabela `users` | Listagem, busca, inspeção de conta, ajuste de créditos |
| Subscriptions | Laravel Cashier / Stripe | Leitura do plano e do status da assinatura do usuário |
| Executions | Tabela `executions` | Diagnóstico de execuções e histórico por usuário e Brand |

Consequência de ordenação: o Painel Administrativo depende da conclusão da fase de Stripe/Cashier e do módulo de Executions. Ele não pode ser iniciado antes.

---

## 14.5 Divisão de Responsabilidade — Metabase vs Painel Livewire

A operação interna do Arkion é atendida por duas ferramentas distintas, com fronteira explícita.

| | Metabase | Painel Administrativo (Livewire) |
|--|----------|----------------------------------|
| Natureza | Leitura e análise | Escrita e operação |
| Escopo | Relatórios agregados e BI | Ações sobre um usuário específico |
| Exemplos | MRR, coortes, retenção, churn, volume de execuções por pipeline | Ajuste manual de créditos, inspeção de conta, alteração de status de conta |
| Regras de negócio | Nenhuma | Todas — Policies, Services e validação de domínio |
| Auditoria | Não aplicável (somente leitura) | Obrigatória em `admin_action_logs` |

**Regra de fronteira:** se a pergunta é *"quanto"* ou *"qual a tendência"*, a resposta pertence ao Metabase. Se a operação é *"alterar o dado de um usuário"*, ela pertence ao Painel Livewire.

Nenhum relatório agregado de BI deve ser reimplementado em Livewire, e nenhuma escrita deve ser realizada pelo Metabase.

---

## 14.6 Auditoria

Toda ação administrativa que altera dados de um usuário é registrada na tabela `admin_action_logs` **antes** de o sucesso ser retornado ao administrador.

O registro é permanente: `admin_action_logs` não utiliza Soft Deletes e não possui rotina de expurgo.

O detalhamento de colunas e da camada responsável pelo registro encontra-se em `IMPLEMENTATION_ORDER.md`, Mês 4, Fase 4.2.

---

## 14.7 Lacuna Arquitetural — Acesso do Metabase aos Dados

O Metabase é uma ferramenta de leitura externa e, portanto, entra em tensão direta com a propriedade exclusiva do banco pelo Laravel.

### Regras em Jogo

Uma futura ADR que resolva esta lacuna deverá confrontar — e, se for o caso, emendar — exatamente os quatro registros abaixo. Nenhum outro ponto da documentação restringe o acesso direto ao PostgreSQL.

| Documento | Localização exata | Texto da regra |
|-----------|-------------------|----------------|
| `SAD.md` | **Seção 1.6 — Premissas Arquiteturais Não-Negociáveis**, premissa *"Laravel é o único dono do banco"* | "Nenhum outro serviço possui acesso direto ao PostgreSQL." |
| `SAD.md` | **Seção 18 — Restrições e Regras Invioláveis (para Agentes de IA)**, subseção *"Arquitetura"* | "**SEMPRE** faça toda persistência exclusivamente pelo Laravel." |
| `CLAUDE.md` | **Seção 3 — Regras Invioláveis**, **regra 1** | "Laravel é o único serviço autorizado a acessar diretamente o PostgreSQL." |
| `PROJECT_RULES.md` | **Seção 4 — Decisões Arquiteturais Fixadas**, linha *"Apenas Laravel acessa PostgreSQL"* | Marcada como "Nunca Reverter Sem ADR: Sim". |

Observar que a Seção 1.6 e a `CLAUDE.md` regra 1 são formuladas de modo **absoluto** (qualquer serviço), enquanto a Seção 18 e o `PROJECT_RULES.md` foram redigidos tendo o n8n como alvo concreto. A ADR precisa deixar explícito se a exceção de leitura vale apenas para BI ou se reformula a premissa como um todo.

### Alternativas Conhecidas

- réplica de leitura dedicada ao BI;
- usuário PostgreSQL somente-leitura, restrito a views analíticas;
- exportação periódica para um destino analítico separado.

A escolha entre essas alternativas permanece:

**[A DEFINIR]**

Esta lacuna deve ser resolvida por uma ADR aprovada **antes** da introdução do Metabase na infraestrutura. Ela não bloqueia a implementação do Painel Administrativo em Livewire, que é independente do Metabase.

---

> ⚠️ **Regra crítica:** As rotas `/admin/*` nunca compartilham Controllers com as rotas de Web ou de Api. Todo Controller administrativo reside em `App\Http\Controllers\Admin`, e toda ação que altera dados de um usuário deve estar registrada em `admin_action_logs` antes do retorno de sucesso.

---

# 15. Progressive Web App (PWA)

## 15.1 Objetivo

O Arkion deve ser disponibilizado como Progressive Web App (PWA), permitindo instalação em dispositivos compatíveis e melhorando a experiência de uso em desktop e mobile.

A arquitetura continua sendo uma aplicação web; o PWA não substitui uma aplicação nativa.

---

# 15.2 manifest.json

Arquivo:

```
public/manifest.json
```

Campos obrigatórios identificados:

```json
{
  "name": "Arkion",
  "short_name": "Arkion",
  "theme_color": "#d2691e",
  "background_color": "#f4f4f6"
}
```

---

## Campos Não Documentados

Os seguintes campos não aparecem na documentação fornecida:

- display;
- orientation;
- start_url;
- icons;
- scope.

Todos permanecem:

**[A DEFINIR]**

---

# 15.3 Service Worker

Arquivo:

```
public/sw.js
```

---

## Responsabilidades

- cache de assets estáticos;
- funcionamento offline parcial;
- melhoria de performance.

---

## Estratégias de Cache

A documentação identifica a existência de um Service Worker, porém não define estratégias específicas.

Itens permanecem:

**[A DEFINIR]**

Exemplos de estratégias possíveis (não oficializadas):

- Cache First;
- Network First;
- Stale While Revalidate.

---

## Recursos Elegíveis

O Service Worker pode armazenar em cache:

- CSS;
- JavaScript;
- fontes;
- imagens;
- assets compilados pelo Vite.

Não há documentação autorizando cache de respostas dinâmicas da aplicação.

---

# 15.4 Atualizações

A política de atualização do Service Worker não está documentada.

Permanece:

**[A DEFINIR]**

---

# 15.5 Limitações iOS

A documentação reconhece limitações inerentes ao Safari/iOS.

Entre elas:

- suporte parcial a notificações;
- diferenças de comportamento do Service Worker;
- instalação via "Adicionar à Tela de Início".

Não há adaptações específicas documentadas para iOS.

---

# 15.6 Escopo

O PWA melhora:

- carregamento;
- experiência de instalação;
- cache local.

Ele não altera:

- autenticação;
- autorização;
- arquitetura do backend;
- processamento dos pipelines.

---

> ⚠️ **Regra crítica:** O PWA é uma camada de experiência do usuário. Nenhuma regra de negócio, autenticação, autorização ou processamento de IA deve ser implementada no Service Worker.


# 16. Variáveis de Ambiente

## 16.1 Visão Geral

Todas as credenciais e parâmetros sensíveis devem ser configurados exclusivamente através do arquivo `.env`.

É proibido armazenar:

- chaves de API;
- segredos;
- URLs privadas;
- credenciais de banco;
- tokens

diretamente no código-fonte.

---

# 16.2 App

| Variável | Finalidade |
|----------|------------|
| APP_NAME | Nome da aplicação |
| APP_ENV | Ambiente |
| APP_KEY | Chave de criptografia |
| APP_DEBUG | Debug |
| APP_URL | URL pública |
| APP_TIMEZONE | Fuso horário |
| APP_LOCALE | Idioma padrão |

---

# 16.3 Banco de Dados

| Variável | Finalidade |
|----------|------------|
| DB_CONNECTION | Driver |
| DB_HOST | Host |
| DB_PORT | Porta |
| DB_DATABASE | Banco |
| DB_USERNAME | Usuário |
| DB_PASSWORD | Senha |

Banco oficial:

```
PostgreSQL
```

---

# 16.4 Redis

| Variável | Finalidade |
|----------|------------|
| REDIS_HOST | Host |
| REDIS_PORT | Porta |
| REDIS_PASSWORD | Senha |
| REDIS_CLIENT | Cliente Redis |

Redis é utilizado para:

- filas;
- Horizon;
- cache.

---

# 16.5 Firebase

| Variável | Finalidade |
|----------|------------|
| FIREBASE_PROJECT_ID | Projeto |
| FIREBASE_CREDENTIALS | Credenciais |

Demais parâmetros permanecem:

**[A DEFINIR]**

---

# 16.6 n8n

| Variável | Finalidade |
|----------|------------|
| N8N_URL | Endpoint |
| N8N_SECRET | Segredo compartilhado |

Caso outros parâmetros sejam necessários, permanecem:

**[A DEFINIR]**

---

# 16.7 Stripe

| Variável | Finalidade |
|----------|------------|
| STRIPE_KEY | Public Key |
| STRIPE_SECRET | Secret Key |
| STRIPE_WEBHOOK_SECRET | Webhook |

---

# 16.8 Laravel Reverb

| Variável | Finalidade |
|----------|------------|
| REVERB_APP_ID | Aplicação |
| REVERB_APP_KEY | Chave |
| REVERB_APP_SECRET | Segredo |
| REVERB_HOST | Host |
| REVERB_PORT | Porta |

Demais parâmetros seguem a configuração oficial do Laravel Reverb.

---

# 16.9 Sentry

| Variável | Finalidade |
|----------|------------|
| SENTRY_LARAVEL_DSN | Monitoramento |

Configurações adicionais:

**[A DEFINIR]**

---

# 16.10 Mail

A documentação identifica utilização do sistema padrão do Laravel.

Variáveis esperadas:

| Variável | Finalidade |
|----------|------------|
| MAIL_MAILER | Driver |
| MAIL_HOST | SMTP |
| MAIL_PORT | Porta |
| MAIL_USERNAME | Usuário |
| MAIL_PASSWORD | Senha |
| MAIL_FROM_ADDRESS | Remetente |
| MAIL_FROM_NAME | Nome |

O provedor SMTP permanece:

**[A DEFINIR]**

---

# 16.11 IA

As integrações de IA utilizam variáveis específicas.

| Serviço | Variável |
|----------|----------|
| Claude | ANTHROPIC_API_KEY |
| Gemini | GEMINI_API_KEY |
| Perplexity | PERPLEXITY_API_KEY |
| Apify | APIFY_TOKEN |

---

# 16.12 Produção

Em ambiente de produção:

- APP_DEBUG=false
- HTTPS obrigatório
- Credenciais nunca versionadas
- `.env` exclusivo por ambiente

---

> ⚠️ **Regra crítica:** Nenhuma credencial deve ser armazenada em código, arquivos Blade, componentes Livewire, JavaScript, migrations ou seeders. Toda informação sensível deve ser carregada exclusivamente por variáveis de ambiente.

---

# 17. Decisões Arquiteturais (ADRs)

## ADR-001 — Blade + Livewire vs Next.js

### Decisão

Utilizar Blade + Livewire.

---

### Alternativa

Next.js + API Laravel.

---

### Motivo

Os documentos do projeto demonstram uma arquitetura monolítica baseada em Laravel 11 com Livewire 3.

Essa abordagem reduz complexidade operacional, elimina duplicação de autenticação, simplifica deploy e mantém uma única base de código.

---

# ADR-002 — Dois Droplets vs Um

## Decisão

Separar aplicação Laravel e n8n em dois Droplets distintos.

---

## Alternativa

Hospedar todos os serviços em um único servidor.

---

## Motivo

A separação:

- reduz acoplamento;
- melhora isolamento;
- simplifica manutenção;
- permite escalabilidade independente.

---

# ADR-003 — PostgreSQL vs MySQL

## Decisão

Utilizar PostgreSQL.

---

## Alternativa

MySQL.

---

## Motivo

Necessidade de:

- JSONB;
- índices GIN;
- consultas avançadas.

Especialmente para o armazenamento de resultados estruturados das execuções.

---

# ADR-004 — Firebase Auth vs Laravel Breeze

## Decisão

Firebase Authentication.

---

## Alternativa

Laravel Breeze.

---

## Motivo

Os documentos estabelecem autenticação baseada em Firebase, centralizando identidade e validação de tokens.

---

# ADR-005 — PWA vs Aplicação Nativa

## Decisão

Progressive Web App.

---

## Alternativa

Aplicativos iOS/Android.

---

## Motivo

Redução de custo de desenvolvimento, manutenção simplificada e instalação direta em dispositivos compatíveis.

---

# ADR-006 — Redis Local vs Upstash

## Decisão

Redis hospedado no Droplet da aplicação.

---

## Alternativa

Redis gerenciado (Upstash).

---

## Motivo

A infraestrutura definida utiliza Redis local integrado ao Laravel Horizon.

Não há documentação prevendo serviço gerenciado.

---

# ADR-007 — n8n Self-hosted vs Make

## Decisão

n8n self-hosted.

---

## Alternativa

Make.

---

## Motivo

Maior controle operacional, flexibilidade de workflows, integração direta com a infraestrutura do projeto e ausência de dependência de SaaS externo.

---

# ADR-008 — Créditos vs Execuções Mensais

## Decisão

Modelo baseado em créditos.

---

## Alternativa

Limite fixo de execuções por mês.

---

## Motivo

Os pipelines possuem custos computacionais significativamente diferentes.

Exemplos documentados:

- Brand Research → 50 créditos;
- Briefing → 5;
- Image Generation → 7.

O modelo por créditos permite precificação proporcional ao consumo real de recursos de IA.

---

# ADR-009 — JSONB vs Estrutura Relacional para Resultados

## Decisão

Persistir resultados em `JSONB`.

---

## Alternativa

Criar dezenas de tabelas específicas para cada tipo de pipeline.

---

## Motivo

Os pipelines produzem estruturas heterogêneas e potencialmente extensas.

O uso de JSONB oferece:

- flexibilidade;
- versionamento simplificado;
- consultas indexadas via GIN;
- menor custo de evolução.

---

# ADR-010 — Três Papéis de Organização

**Status:** Aceita — Outubro/2026. Decisão de Nicolas e Erich.

**Supersede:** "dois papéis apenas — a introdução de um terceiro exige ADR" (Seção 20.4, revisão 2.1); "a organização nunca fica sem Admin" (Seção 20.4, revisão 2.1; `CLAUDE.md` regra 32 anterior); "Members criam e editam marcas" (Seção 20.4, revisão 2.1; Seção 5.9).

## Contexto

A revisão 2.1 definiu dois papéis por organização, `admin` e `member`, com todas as capacidades sensíveis — faturamento, compra de créditos para a org, gerenciamento de membros e exclusão da org — concentradas em **qualquer** Admin, e com gerenciamento de Marcas aberto a todos os membros.

Esse modelo tem três problemas para uma organização com mais de uma pessoa:

- **Ninguém responde pelo faturamento.** Qualquer Admin podia trocar o cartão, mudar o plano, comprar créditos debitados no meio de pagamento da org ou excluir a organização. Não existia a figura de quem paga a conta.
- **Admins podiam remover uns aos outros.** Inclusive quem criou a organização e paga por ela, desde que restasse ao menos um Admin. O invariante "nunca sem Admin" protegia contra a organização órfã, mas não contra a perda de controle por quem é dono dela.
- **Member alterava a identidade da Marca.** Com Member gerenciando Marcas, um colaborador convidado apenas para usar as ferramentas de IA podia editar ou excluir o Brand Core — a Brand Memory, que é o ativo central do produto.

O código da revisão 2.1 já precisava de um "dono" para apresentação e o resolvia com `Organization::owner()`: o Admin mais antigo por `joined_at`, documentado explicitamente como "apenas apresentação, nunca critério de autorização". A necessidade existia; o modelo é que não a reconhecia.

## Decisão

`organization_user.role` passa a ter **três** valores: `owner`, `admin` e `member`.

- **Owner** — exatamente um por organização. É o único responsável pelo faturamento (cartão, plano, assinatura), pela compra de créditos para a organização, pela atribuição de papéis, pela transferência de posse e pela exclusão da organização. Pode convidar e remover Admins e Members.
- **Admin** — gerencia o trabalho: cria, edita e exclui Marcas e sub-brands (incluindo Brand Core), vê relatórios de consumo e convida e remove **Members**. Não toca faturamento, papéis nem a existência da organização.
- **Member** — usa as ferramentas de IA nas Marcas e vê os saldos. Não gerencia Marcas nem membros.

A matriz completa e as invariantes estão na Seção 20.4. Resumo das invariantes:

- exatamente um Owner por organização, garantido por **índice único parcial** no banco (`UNIQUE(organization_id) WHERE role = 'owner'`) **e** por verificação dentro de transação;
- o Owner não pode ser removido, rebaixado nem sair da organização; só deixa de ser Owner por **transferência de posse** a um membro existente, e o Owner anterior vira Admin;
- a organização nunca fica sem Owner;
- a organização Personal criada no primeiro login tem o usuário como Owner.

O papel é sempre o Enum `App\Enums\OrganizationRole`, e toda decisão de autorização passa por seus métodos `can*()` — nunca por comparação de strings de papel.

O eixo `users.is_admin` (staff da plataforma, Seção 20.7) **não muda** e continua sem nenhuma relação com o papel `admin` de `organization_user`.

## Alternativa rejeitada

**Manter dois papéis, com `Organization::owner()` como helper de exibição.** O "dono" seria derivado — o Admin mais antigo por `joined_at` — e serviria apenas para apresentação (e-mail de cobrança, exibição na UI), nunca para autorização.

Rejeitada porque:

- **Um dono derivado não é dono.** Basta outro Admin remover o Admin mais antigo para que o "owner" mude sem que ninguém tenha transferido nada. A posse precisa ser um fato persistido, não uma consequência da ordem de entrada.
- **Não resolve a autorização.** O problema não é exibir quem é o dono, é impedir que outros Admins façam o que só ele deve fazer. Um helper de exibição deixa faturamento e exclusão abertos a todos os Admins.
- **Cria dois conceitos de "owner" que divergem.** O helper diria "este é o dono" enquanto as Policies tratariam todos os Admins como iguais — exatamente a ambiguidade que a regra "nunca usar um eixo pelo outro" (Seção 20.7) existe para evitar.
- **O banco não protegeria nada.** Com papel persistido, "no máximo um Owner" vira índice único parcial; com papel derivado, não há o que indexar.

Também considerado e mantido como rejeitado: RBAC granular por organização (permissões configuráveis por papel). Três papéis fixos cobrem os casos atuais sem tabela de permissões, cache de permissões nem tela de configuração.

## Consequências

**Banco**

- `organization_user.role` ganha o valor `owner`; o `enum('role', ['admin', 'member'])` atual precisa ser alterado (no PostgreSQL, o `CHECK` gerado pelo Laravel).
- Novo índice único parcial `UNIQUE(organization_id) WHERE role = 'owner'`, criado por `DB::statement()`.
- Backfill: em cada organização existente, um Admin passa a Owner. O backfill de organizações já executado criou exatamente um Admin por organização (a Personal), então o critério de desempate — o Admin mais antigo por `joined_at`, o mesmo de `Organization::owner()` — só importa se existirem organizações com mais de um Admin. Uma organização sem Admin tem o membro mais antigo promovido pelo mesmo critério, e a migration registra o id da organização no log (`Log::warning`) em vez de falhar; uma organização sem nenhum membro também é registrada. A migration é idempotente: organização que já tem Owner não é tocada.

**Código** (implementado; estado na Seção 20.8)

- `OrganizationRole` ganha `Owner` e um conjunto de métodos `can*()` que espelha a matriz da Seção 20.4.
- `Organization::owner()` deixa de ser helper de exibição e passa a ler o papel persistido; `isLastAdmin()` e `admins()->count()` deixam de ser invariantes e são substituídos pela verificação de Owner.
- `OrganizationPolicy` deixa de tratar todo Admin como capaz de faturamento, papéis, exclusão e compra para a org; `ProjectPolicy` passa a exigir Owner ou Admin para criar, editar e excluir Marcas.
- `SyncFirebaseUserAction` cria a organização Personal com o usuário como Owner.
- Novo fluxo de transferência de posse, transacional, que troca os dois papéis em uma única operação sob lock.

**Produto**

- CTAs de assinatura, upgrade e compra para a org aparecem só para o Owner; os demais papéis veem "ask your organization owner" (Seção 12.12).
- Member perde a capacidade de criar e editar Marcas. Organizações existentes onde Members editavam Marcas perdem esse comportamento na migração — é intencional.
- O customer Stripe passa a ser a `Organization` (Seção 12.20): o faturamento sobrevive à transferência de posse.

**Em aberto** (Seção 20.4)

- Aceite do destinatário na transferência de posse — **[A DEFINIR]**.
- Semântica de exclusão da organização (soft delete, prazo de carência, efeito sobre Marcas e assinatura) — **[A DEFINIR]**.

---

> ⚠️ **Regra crítica:** As ADRs documentadas representam decisões arquiteturais consolidadas do projeto. Alterá-las exige revisão formal da arquitetura e atualização deste SAD; agentes de IA não devem substituí-las por preferência técnica própria.

---

# 18. Restrições e Regras Invioláveis (para Agentes de IA)

As regras abaixo possuem caráter imperativo.

Qualquer implementação que as viole deve ser considerada incorreta.

---

## Arquitetura

**NUNCA** implemente regras de negócio em componentes Blade.

**SEMPRE** mantenha a lógica de domínio em Models, Services, Jobs, Policies ou Middleware.

---

**NUNCA** execute pipelines durante a requisição HTTP.

**SEMPRE** utilize filas Redis processadas pelo Laravel Horizon.

---

**NUNCA** permita que o n8n acesse PostgreSQL, Redis ou usuários.

**SEMPRE** faça toda persistência exclusivamente pelo Laravel.

---

## Banco de Dados

**NUNCA** carregue `executions.result` em listagens.

**SEMPRE** utilize `select()` para excluir o campo `result` quando ele não for necessário.

---

**NUNCA** faça consultas sem escopo da Brand ativa.

**SEMPRE** filtre por `project_id`.

---

**NUNCA** utilize `user_id` como critério de isolamento.

**SEMPRE** escope por `organization_id` (organização ativa) e por `project_id` (Brand ativa). O único recurso que pertence diretamente a um usuário é a carteira pessoal de créditos (Seção 20.6).

---

**NUNCA** trate uma Brand como pertencente a um usuário.

**SEMPRE** trate `projects.organization_id` como a coluna de propriedade. Não existe marca órfã nem vínculo direto entre User e Project (Seção 20.2).

---

**NUNCA** aceite uma Brand ativa que não pertença à organização ativa.

**SEMPRE** valide o par `active_organization_id` / `active_project_id` em todo acesso (Seção 20.5).

---

## Créditos

**NUNCA** hardcode valores de créditos.

**SEMPRE** consulte a tabela `plan_limits`, preferencialmente utilizando cache Redis.

---

**NUNCA** permita iniciar um pipeline sem saldo suficiente.

**SEMPRE** valide `EnsureCreditBalance` antes da criação da `Execution`.

---

**NUNCA** some as três carteiras de crédito em uma única coluna persistida.

**SEMPRE** mantenha `organizations.plan_credits_balance`, `organizations.purchased_credits_balance` e `users.purchased_credits_balance` separadas, e debite exatamente nessa ordem (Seção 12.3).

---

**NUNCA** inverta a ordem de débito das carteiras.

**SEMPRE** consuma o plano da org, depois o comprado pela org, e só então a carteira pessoal.

---

**NUNCA** debite a carteira pessoal sem `confirm_personal_credits = true` na requisição.

**SEMPRE** responda **409** com o breakdown por carteira quando a confirmação faltar, e **recalcule** o valor pessoal dentro da transação — a confirmação autoriza a carteira, não um valor (Seção 12.19). O bloqueio é do backend, nunca só da UI.

---

**NUNCA** permita execução em uma organização sem plano ativo.

**SEMPRE** verifique `organizations.plan is null` **antes** do feature gating, retornando `NoActivePlanException` (402). Inverter essa ordem concede acesso irrestrito, porque linha ausente em `plan_limits` significa habilitado (Seção 12.18).

---

**NUNCA** permita que uma organização execute um pipeline fora do seu plano.

**SEMPRE** verifique o feature gating antes do saldo, retornando 403 (fora do plano) e não 402 (sem saldo).

---

**NUNCA** colapse os três estados de bloqueio em uma mensagem genérica.

**SEMPRE** distinga `NoActivePlanException` (402, "Subscribe to a plan"), `FeatureNotAvailableException` (403, "Upgrade your plan") e `InsufficientCreditsException` (402, "Buy credits") — Seção 12.12.

---

## Segurança

**NUNCA** aceite callbacks sem validar `X-N8N-Secret`.

**SEMPRE** utilize `hash_equals()` para comparação do segredo.

---

**NUNCA** exponha informações de outra Brand.

**SEMPRE** utilize Policies para autorização.

---

**NUNCA** armazene segredos no código.

**SEMPRE** utilize variáveis de ambiente.

---

## Broadcast

**NUNCA** envie `executions.result` via WebSocket.

**SEMPRE** transmita apenas metadados da execução.

---

**NUNCA** faça broadcast antes da persistência.

**SEMPRE** realize a sequência:

```text
Persistência

↓

Commit

↓

Broadcast
```

---

## Frontend

**NUNCA** invente novas cores.

**SEMPRE** utilize exclusivamente os tokens definidos na Seção 10.

---

**NUNCA** altere a ordem da navegação da Sidebar.

**SEMPRE** preserve a estrutura documentada.

---

**NUNCA** substitua as fontes oficiais.

**SEMPRE** utilize **Newsreader** (`font-serif`) em títulos `H1`–`H5` e **Inter** (`font-sans`) em corpo de texto e UI.

---

**NUNCA** inicialize a aplicação com a classe `dark` fixa no `<html>`.

**SEMPRE** trate o Light mode como estado inicial e o Dark mode como variante `dark:`:

```html
<html>
```

**NUNCA** utilize os tokens do Design System anterior (`#111111`, `#FF5500`).

**SEMPRE** utilize a paleta da Seção 10.4.

---

**NUNCA** exiba texto em português em rotas de produto.

**SEMPRE** escreva a interface do cliente final em inglês (Seção 10.1.1). O painel administrativo é a única exceção.

---

**NUNCA** remova os IDs funcionais documentados.

**SEMPRE** mantenha compatibilidade com os componentes Livewire e Alpine.js.

---

## Organizações

**NUNCA** confunda `users.is_admin` com `organization_user.role`.

**SEMPRE** trate `users.is_admin` como staff da plataforma Arkion e `organization_user.role` como papel dentro de uma organização. Um não implica o outro em nenhuma direção (Seção 20.7).

---

**NUNCA** deixe uma organização sem Owner, nem com mais de um Owner.

**SEMPRE** impeça a remoção, o rebaixamento e a saída do Owner; ele só deixa de ser Owner por transferência de posse, verificada dentro da transação, sob lock, com o índice único parcial como segunda barreira (Seção 20.4).

---

**NUNCA** permita que Admin ou Member gerenciem faturamento, comprem créditos para a organização, atribuam papéis, transfiram a posse ou excluam a organização. **NUNCA** permita que um Member crie, edite ou exclua Marcas, ou convide e remova membros.

**SEMPRE** aplique a matriz de permissões da Seção 20.4 por Policy, nunca escondendo botões na interface.

---

**NUNCA** compare strings de papel (`$role === 'admin'`, `wherePivot('role', 'owner')` em regra de autorização).

**SEMPRE** use o Enum `OrganizationRole` e seus métodos `can*()` para decidir o que um papel pode fazer.

> 🔁 **SUPERSEDED (ADR-010):** as regras anteriores deste bloco eram "NUNCA deixe uma organização sem nenhum Admin / SEMPRE impeça a remoção e o rebaixamento do último Admin" e "NUNCA permita que um Member assine plano, compre créditos para a organização, gerencie membros ou exclua a organização". Foram substituídas pelas regras acima.

---

## Multi-Brand

**NUNCA** retorne dados pertencentes a outra Brand ou a outra Organização.

**SEMPRE** utilize o `project_id` da Brand ativa em consultas, Jobs, Policies e componentes, dentro do escopo da organização ativa.

---

## Infraestrutura

**NUNCA** comunique os Droplets pela rede pública.

**SEMPRE** utilize a VPC privada.

---

**NUNCA** desative SSL.

**SEMPRE** utilize Cloudflare como camada de proteção e gerenciamento DNS.

---

## Evolução do Sistema

**NUNCA** substitua decisões arquiteturais documentadas sem ADR.

**SEMPRE** registre novas decisões arquiteturais antes de alterar componentes estruturais.

---

> ⚠️ **Regra crítica:** Este Software Architecture Document é a fonte única de verdade para agentes de IA responsáveis pela implementação do Arkion. Quando uma informação não estiver explicitamente definida neste documento, a implementação deve utilizar o marcador **[A DEFINIR]** como indicação de lacuna arquitetural, em vez de inferir ou inventar comportamentos.


# 19. Hierarquia de Marcas (Umbrella Brands)

## 19.1 Visão Geral

O Arkion permite que uma Brand seja **filha de outra Brand** da mesma Organização.

> 🔁 **SUPERSEDED:** a revisão 2.0 desta seção dizia "do mesmo usuário". Com o modelo de Organizações (Seção 20), a Marca pertence à Organização e a hierarquia ocorre **sempre dentro da mesma `organization_id`**.

O caso de uso é a *umbrella brand*: uma marca guarda-chuva que abriga marcas menores — linhas de produto, submarcas regionais, spin-offs — cada uma com identidade e conteúdo próprios, mas pertencentes à mesma operação comercial.

```text
Organization: Acme Inc.

Acme Group                    (umbrella brand — parent_project_id = null)
├── Acme Coffee               (sub-brand    — parent_project_id = Acme Group)
├── Acme Tea                   (sub-brand    — parent_project_id = Acme Group)
└── Acme Roasters              (sub-brand    — parent_project_id = Acme Group)

Northwind                     (brand de topo, sem filhas)
```

Uma sub-brand é, para todos os efeitos de domínio, uma Brand completa. A hierarquia adiciona **organização**, não um novo tipo de entidade.

---

# 19.2 Modelagem

A relação é auto-referencial na tabela `projects`:

| Coluna | Tipo | Constraints |
|--------|------|-------------|
| `organization_id` | bigint | FK `organizations.id`, **NOT NULL** |
| `parent_project_id` | bigint | FK `projects.id`, **nullable** |

```php
// App\Models\Project

public function parent(): BelongsTo
{
    return $this->belongsTo(Project::class, 'parent_project_id');
}

public function children(): HasMany
{
    return $this->hasMany(Project::class, 'parent_project_id');
}
```

## Regras de Integridade

- `parent_project_id` nulo significa Brand de topo. É o estado padrão e o estado de toda Brand criada antes desta seção.
- Quando preenchido, o Project referenciado **deve pertencer à mesma `organization_id`**. Apontar para a Brand de outra organização é uma violação de isolamento e deve ser rejeitado na validação, não apenas pela FK. *(Substitui a regra anterior, que exigia o mesmo `user_id`.)*
- Um Project nunca pode ser seu próprio pai.
- Ciclos são proibidos.
- A política de cascata ao excluir uma umbrella brand com filhas é **[A DEFINIR]**, assim como a política de retenção geral da Seção 4.4.
- A profundidade máxima da hierarquia é **[A DEFINIR]**. Até que seja decidida, nenhum código deve assumir recursão arbitrária nem assumir um único nível.

> ⚠️ **Regra crítica:** A verificação de que o parent pertence à mesma organização é responsabilidade da `ProjectPolicy` e do Form Request, não da constraint de banco. Uma FK para `projects.id` aceita qualquer Project do sistema, inclusive de outra organização.

---

# 19.3 Créditos

**Sub-brands não possuem saldo próprio.**

> 🔁 **SUPERSEDED — "Sub-brands compartilham o saldo do usuário pai".** A revisão 2.0 desta seção afirmava que os dois saldos viviam em `users` e eram compartilhados por todas as Brands **do usuário**. Isso está superado: o saldo é **da Organização**. Sub-brands compartilham o saldo da organização dona da árvore, não o saldo de um usuário. O texto anterior é preservado apenas como histórico e não deve ser implementado.

As carteiras da Seção 12.2 que financiam uma Brand são as **da organização** — `organizations.plan_credits_balance` e `organizations.purchased_credits_balance` — compartilhadas por todas as Brands dela, de topo ou não, e por todos os seus membros.

```text
Organization
├── plan_credits_balance         ← único, da org
└── purchased_credits_balance    ← único, da org
    │
    ├── Acme Group        consome deste saldo
    ├── Acme Coffee       consome deste saldo
    └── Acme Tea          consome deste saldo

User
└── purchased_credits_balance    ← carteira pessoal, só com confirmação (Seção 12.19)
```

## Consequências

- Uma execução disparada dentro de uma sub-brand debita exatamente o mesmo saldo que uma execução disparada na umbrella brand.
- Não existe alocação, cota ou reserva de créditos por Brand.
- O feature gating por plano (Seção 12.5) é **da organização**, não da Brand nem do usuário: uma sub-brand nunca tem acesso a pipelines que o plano da organização não permite, e nunca perde acesso a pipelines que ele permite.
- Uma organização sem plano bloqueia todas as suas Brands, de topo e sub-brands, sem exceção (Seção 12.18).
- O indicador de créditos da Sidebar (Seção 10.12) exibe os mesmos saldos da organização ativa independentemente da Brand ativa, e exibe a carteira pessoal separadamente.

Nenhuma coluna de crédito pode ser adicionada a `projects`.

---

## Cobrança de Marcas Adicionais

A assinatura de qualquer um dos três planos (Seção 12.6) inclui um número de marcas **[A DEFINIR]**. Marcas além desse limite custam **R$ 14,99/mês cada**, valor idêntico nos três planos, e são cobradas **da organização**.

A cobrança é por Brand, independentemente da posição na árvore: uma sub-brand custa exatamente o mesmo que uma marca de topo. `parent_project_id` não altera preço.

Marcas adicionais ampliam o número de contextos de trabalho e **nunca** o saldo de créditos — o saldo continua sendo um só, da organização.

Quantas marcas cada plano inclui antes de começar a cobrar o adicional é **[A DEFINIR]**: o `PLANOS.md` fixa o preço do adicional mas não a franquia inicial.

---

# 19.4 Dados de Domínio

Sub-brands possuem **dados próprios e integralmente isolados**.

| Dado | Escopo |
|------|--------|
| Briefing | `project_id` |
| Brand assets (identidade visual, personagens, produtos) | `project_id` |
| Pesquisas e estratégia | `project_id` |
| Executions | `project_id` |
| Video references | `project_id` |

A regra 10 da `CLAUDE.md` e a Seção 10.15 continuam valendo sem exceção, agora sob a organização ativa (Seção 20.6):

```php
Execution::query()
    ->where('project_id', $activeProject->id);
```

> ⚠️ **Regra crítica:** A hierarquia **nunca** amplia o escopo de uma consulta. Consultar a umbrella brand não retorna dados das sub-brands, e consultar uma sub-brand não retorna dados da umbrella brand. `parent_project_id` organiza a apresentação; jamais entra na cláusula `where` de uma listagem de domínio como forma de agregar conteúdo entre Brands.

Qualquer necessidade futura de visão consolidada entre uma umbrella brand e suas filhas é uma funcionalidade nova, exige ADR, e não pode ser introduzida relaxando o escopo por `project_id`.

---

# 19.5 Brand Ativa

A hierarquia não altera o modelo de Brand ativa.

- Existe exatamente **uma** Brand ativa por sessão, sempre pertencente à **organização ativa** (Seção 20.5).
- Uma umbrella brand pode ser a Brand ativa, e nesse caso o usuário vê apenas os dados dela.
- Selecionar uma sub-brand troca o `project_id` do contexto como qualquer outra troca de Brand.
- Não existe estado de "umbrella brand ativa mais sub-brand ativa".

O `BrandSwitcher` (Seção 10.15) apresenta as sub-brands aninhadas sob a respectiva umbrella brand, mas cada entrada da lista continua sendo uma seleção única e exclusiva.

---

# 19.6 Estado da Implementação

**Nada desta seção está implementado.**

| Item | Estado |
|------|--------|
| Coluna `organization_id` em `projects` | Migration pendente |
| Coluna `parent_project_id` | Migration pendente |
| Relações `parent()` / `children()` no Model `Project` | Pendente |
| Validação "parent pertence à mesma organização" | Pendente |
| Prevenção de ciclos | Pendente |
| Aninhamento no `BrandSwitcher` | Pendente |
| Política de cascata na exclusão | **[A DEFINIR]** |
| Profundidade máxima | **[A DEFINIR]** |

Toda Brand existente hoje é, por definição, uma Brand de topo com `parent_project_id` nulo. A introdução dessa coluna é retrocompatível e não exige backfill.

`organization_id`, ao contrário, **exige backfill**: toda Brand existente precisa ser adotada pela organização "Personal" do seu antigo `user_id` (Seção 12.8).


# 20. Organizações e Hierarquia de Propriedade

## 20.1 Visão Geral

O Arkion adota um modelo de **Organizações**, inspirado no Make.com.

A **Organização é a entidade de faturamento** da plataforma. É dela o plano, os créditos do plano e os créditos comprados para a operação. As Marcas pertencem à Organização — **nunca ao usuário**.

```text
Organization  (entidade de faturamento — plano, créditos, membros)
│
├── Brand            (parent_project_id = null)
│   ├── Sub-brand    (parent_project_id = Brand)
│   └── Sub-brand
│
└── Brand
```

Um usuário **pertence a N organizações**, com **um papel por organização**, através da tabela pivot `organization_user`.

O usuário não é dono de nada além da própria identidade e da sua **carteira pessoal de créditos** (Seção 12.2).

---

## 20.2 Regra Absoluta de Propriedade

> ⚠️ **REGRA ABSOLUTA:** Nenhuma Marca existe fora de uma Organização.

Consequências, todas invioláveis:

- **Não existe marca órfã.** `projects.organization_id` é `NOT NULL`.
- **Não existe vínculo direto entre User e Project.** Não há `Project` "do usuário": o vínculo do usuário com a Marca é sempre indireto, via participação na organização dona da Marca.
- Um usuário só enxerga uma Marca porque é membro da organização dela — e deixa de enxergá-la no instante em que sai dessa organização, sem que a Marca seja afetada.
- A relação `User hasMany Project` **deixa de existir** como relação de propriedade. `projects.user_id`, se preservado, passa a significar apenas "quem criou" — dado de auditoria, jamais critério de autorização ou de escopo de consulta.

### Hierarquia dentro da Organização

- `parent_project_id` é a hierarquia de Marcas da Seção 19 e permanece válida.
- **`parent_project_id` sempre aponta para uma Marca da mesma Organização.** Uma sub-brand nunca cruza a fronteira de organizações. Apontar para uma Marca de outra org é violação de isolamento e deve ser rejeitado na validação, não apenas pela FK.
- A regra anterior — "o parent precisa pertencer ao mesmo `user_id`" — é **substituída** por "o parent precisa pertencer à mesma `organization_id`".
- A hierarquia continua **não ampliando** o escopo de nenhuma consulta (Seção 19.4).

---

## 20.3 Organização "Personal" Automática

Todo usuário recebe automaticamente uma organização **"Personal"** no **primeiro login**, com ele como **Owner** (ADR-010).

> 🔁 **SUPERSEDED (ADR-010):** a revisão 2.1 criava a organização Personal com o usuário como **Admin**. Com três papéis, ele é o Owner — o único membro da Personal no nascimento, e o responsável pelo faturamento dela.

- A criação é automática e faz parte do fluxo de sincronização do usuário Firebase (`App\Actions\Auth\SyncFirebaseUserAction`).
- A organização Personal nasce **sem plano** (`plan = null`, Seção 12.18), como qualquer outra organização.
- A organização Personal não é um tipo especial de organização: é uma organização comum, com o mesmo schema, as mesmas regras de papéis e o mesmo faturamento. O nome é convenção de apresentação, não um flag de comportamento.
- Nenhum usuário existe sem ao menos uma organização. Um usuário sem organização é estado inválido.

### Seletor de organização na UI

Se o usuário pertence a **apenas uma** organização, o **seletor de organização não é exibido** na interface. O contexto existe no backend de qualquer forma; a ausência do seletor é decisão de apresentação, nunca de modelagem.

A partir da segunda organização, o seletor aparece e passa a ser o controle de troca de `active_organization_id` (Seção 20.5).

---

## 20.4 Matriz de Permissões — Três Papéis

O papel do usuário dentro de uma organização vive em `organization_user.role` e tem exatamente **três valores**: `owner`, `admin` e `member` (ADR-010). Em código, o papel é sempre o Enum `App\Enums\OrganizationRole`; a introdução de um quarto papel exige nova ADR.

| Ação | Owner | Admin | Member |
|------|:-----:|:-----:|:------:|
| Ver a organização e usar as ferramentas de IA nas marcas | ✅ | ✅ | ✅ |
| Ver saldos de créditos (org e pessoal) | ✅ | ✅ | ✅ |
| Ver consumo total de créditos (relatórios de uso) | ✅ | ✅ | ❌ |
| Criar, editar e excluir Marcas e Sub-marcas; editar Brand Core e demais dados da marca | ✅ | ✅ | ❌ |
| Convidar membros | Admin ou Member | somente Member | ❌ |
| Remover membros | Admin ou Member | somente Member | ❌ |
| Atribuir ou alterar papéis | ✅ | ❌ | ❌ |
| Faturamento: cartão, plano, assinatura | ✅ | ❌ | ❌ |
| Comprar créditos para a organização | ✅ | ❌ | ❌ |
| Comprar créditos pessoais | ✅ | ✅ | ✅ |
| Transferir a posse | ✅ | ❌ | ❌ |
| Excluir a organização | ✅ | ❌ | ❌ |

Leitura das linhas de membros: o Owner convida e remove membros **com papel** Admin ou Member; o Admin convida e remove apenas membros **com papel** Member. Ninguém convida um segundo Owner, e ninguém remove o Owner (ver invariantes abaixo).

Notas:

- **Ver saldos** significa ver as duas carteiras da organização e a **própria** carteira pessoal. Nenhum papel vê a carteira pessoal de outro membro (Seção 12.2).
- **Usar as ferramentas de IA** continua sujeito aos três eixos da Seção 12.1: plano ativo, feature gating e saldo. O papel autoriza a ação; não dispensa nenhum dos três.
- **Comprar créditos pessoais** é capacidade do **usuário**, não da organização: qualquer membro compra para a própria carteira (`users.purchased_credits_balance`), que o acompanha entre organizações.
- **Atribuir ou alterar papéis** é exclusivo do Owner e se limita a `admin` ↔ `member`. Tornar alguém Owner não é "alterar papel": é transferência de posse, um fluxo próprio (abaixo).
- **Admin convidando** só pode atribuir o papel `member` ao convite. Um convite com papel `admin` vindo de um Admin é recusado no backend.
- A verificação de papel é responsabilidade de Policies do Laravel, que consultam os métodos `can*()` de `OrganizationRole` — nunca da interface e nunca por comparação de strings. Esconder um botão não é autorização.
- O que a interface mostra a cada papel diante de um bloqueio de plano ou de saldo está na Seção 12.12 (CTA por papel).
- **Editar os dados da própria organização** (nome, slug) não consta da matriz. Quem pode fazê-lo é **[A DEFINIR]**; até a decisão, não ampliar essa capacidade além do Owner.

> 🔁 **SUPERSEDED (ADR-010) — Matriz de dois papéis.** A revisão 2.1 desta seção, intitulada "Matriz de Permissões — Dois Papéis", definia `organization_user.role` com exatamente dois valores, `admin` e `member`, declarava que "não há terceiro papel, e a introdução de qualquer outro exige ADR", e registrava esta matriz:
>
> | Capacidade | Admin | Member |
> |------------|:-----:|:------:|
> | Gerenciar faturamento da organização (assinar, trocar e cancelar plano) | ✅ | ❌ |
> | Comprar créditos **para a organização** | ✅ | ❌ |
> | Gerenciar membros (convidar, remover, alterar papéis) | ✅ | ❌ |
> | Excluir a organização | ✅ | ❌ |
> | Gerenciar Marcas (criar, editar, excluir Brands e sub-brands) | ✅ | ✅ |
> | Rodar execuções | ✅ | ✅ |
> | Comprar **créditos pessoais** | ✅ | ✅ |
>
> A ADR exigida foi registrada (ADR-010) e substitui integralmente esta matriz. Em particular, **Member deixou de gerenciar Marcas** e **Admin deixou de gerenciar faturamento, comprar créditos para a org, alterar papéis e excluir a organização**.

### Invariantes obrigatórias — o Owner

> ⚠️ **REGRAS DE NEGÓCIO:** as regras abaixo são invariantes de domínio, não detalhes de implementação. Valem em toda superfície que altere participação ou papel — API, Livewire, painel administrativo, comandos Artisan, seeders e factories.

1. **Exatamente um Owner por organização.** Garantido em duas camadas, ambas obrigatórias:
   - no banco, pelo **índice único parcial** `UNIQUE(organization_id) WHERE role = 'owner'` em `organization_user` (Seção 4.15), que torna impossível persistir um segundo Owner;
   - no domínio, por **verificação dentro da transação**, sob lock da linha da organização, em toda operação que crie, altere ou remova participação. O índice garante o teto ("no máximo um"); só a transação garante o piso ("pelo menos um").
2. **O Owner não pode ser removido nem rebaixado.** Nenhum papel — nem o próprio Owner, nem staff da plataforma pelo painel administrativo — remove o Owner da organização ou altera o papel dele para `admin` ou `member` por "alterar papel". O Owner só deixa de ser Owner por **transferência de posse**.
3. **Transferência de posse:**
   - o destino é um **membro existente** da organização (Admin ou Member);
   - na mesma transação, o destino passa a `owner` e o **Owner anterior passa a `admin`**;
   - a ordem das escritas dentro da transação respeita o índice único parcial (rebaixar o Owner anterior antes de promover o destino), de modo que em nenhum instante existam dois Owners;
   - destino **fora da organização** não é aceito: é preciso **convidar primeiro** e, depois que o convite for aceito, transferir;
   - a transferência é exclusiva do Owner (matriz acima).
4. **Uma organização nunca fica sem Owner.** Esta regra **substitui** "a organização nunca fica sem Admin". Uma organização pode legitimamente não ter nenhum Admin — o Owner sozinho cobre todas as capacidades.
5. **O Owner não pode sair da organização sem transferir antes.** Sair é uma remoção como outra qualquer e cai na regra 2. A única outra forma de o Owner deixar de pertencer à organização é excluí-la.
6. **A organização Personal criada no primeiro login tem o usuário como Owner** (Seção 20.3).
7. **O papel `admin` de `organization_user` não tem relação com `users.is_admin`** (staff da plataforma). Os dois eixos continuam independentes, em todas as direções, inclusive para o Owner: ser Owner não concede `users.is_admin`, e ser staff da plataforma não torna ninguém Owner ou Admin de organização de cliente (Seção 20.7).

Uma organização sem Owner é um estado irrecuperável pelo próprio cliente: ninguém poderia mais assinar um plano, comprar créditos para a org, atribuir papéis, transferir a posse ou excluí-la. Por isso o piso é verificado dentro da transação, e não apenas na Policy — duas operações simultâneas avaliadas fora da transação passariam ambas.

> 🔁 **SUPERSEDED (ADR-010) — Invariante "a organização nunca fica sem Admin".** A revisão 2.1 registrava: "Uma organização **nunca** pode ficar sem nenhum Admin. O **último Admin não pode ser removido** da organização. O **último Admin não pode ser rebaixado a Member**. O último Admin também não pode sair da organização por conta própria: sair é uma remoção como outra qualquer. Para deixar a organização, o último Admin precisa antes **promover outro membro a Admin** ou **excluir a organização**." A verificação era feita por `Organization::isLastAdmin()` / contagem de Admins (`admins()->count()`), sem constraint de banco. Esse invariante, o helper `isLastAdmin()` e a contagem de Admins como critério estão superados: o invariante passa a ser sobre o Owner (regras 1 a 5 acima), com o teto garantido por índice.

### [A DEFINIR] — Aceite na transferência de posse

Se a transferência exige **aceite do destinatário** (convite de transferência pendente, aceito ou recusado por ele) ou é efetivada imediatamente pelo Owner é **[A DEFINIR]**. A decisão afeta: a existência de um estado "transferência pendente", a expiração desse estado, o que acontece se o destinatário sair da organização ou for removido enquanto pendente, e a notificação de ambas as partes. Não implementar por inferência. Enquanto não houver decisão, as invariantes 1 a 5 valem para qualquer das duas formas.

### [A DEFINIR] — Exclusão da organização

Excluir a organização é exclusivo do Owner (matriz acima). A **semântica** da exclusão é **[A DEFINIR]**:

- **soft delete ou exclusão definitiva** — a tabela `organizations` já possui `deleted_at` (migration `2026_09_14_030000_create_organizations_table`), mas isso é capacidade de schema, não decisão de produto;
- **prazo de carência** — se existe janela de recuperação e quem pode restaurar;
- **efeito sobre as Marcas** e sub-brands, suas Executions, assets e o histórico produzido;
- **efeito sobre a assinatura** — cancelamento imediato, ao fim do ciclo, reembolso, e o destino de `organizations.purchased_credits_balance` não consumido;
- **efeito sobre os membros** — inclusive usuários para quem esta era a única organização (o destino da carteira pessoal nesse cenário já é [A DEFINIR], Seção 20.8), e a interação com a regra "nenhum usuário existe sem ao menos uma organização" (Seção 20.3), em particular na exclusão da organização Personal.

Não implementar exclusão de organização antes desta decisão.

---

## 20.5 Contexto de Sessão — Duas Variáveis

O contexto de sessão do Arkion passa a ter **duas** variáveis, não uma:

| Variável | Significado |
|----------|-------------|
| `active_organization_id` | Organização ativa da sessão. Define plano, feature gating, carteiras da org e conjunto de Marcas visíveis. |
| `active_project_id` | Brand ativa da sessão. Define o escopo de todo dado de domínio (Seção 19.4). |

> ⚠️ **Regra crítica:** A `active_project` **DEVE** pertencer à `active_organization`. Esta relação precisa ser **validada em todo acesso**, não apenas no momento da troca.

- Trocar de organização **invalida** a Brand ativa: ou o sistema seleciona uma Brand da nova organização, ou o contexto de Brand fica vazio até que o usuário escolha uma.
- Um `active_project_id` que não pertence à `active_organization_id` é estado inválido e nunca deve ser tratado como "quase certo": a requisição é rejeitada, não corrigida silenciosamente.
- A validação é do backend. Um cliente que envie `project_id` de outra organização recebe 403, mesmo que o usuário seja membro de ambas.
- A verificação de que o usuário é membro da `active_organization` acontece antes de qualquer verificação de Brand.

O middleware `EnsureActiveProject` previsto na `CLAUDE.md` §4 passa a ser responsável também por essa validação, ou é acompanhado de um `EnsureActiveOrganization` que a antecede. A divisão exata entre os dois middlewares é **[A DEFINIR]**.

---

## 20.6 Escopo de Consultas

> ⚠️ **Regra crítica:** Toda consulta de domínio é escopada pela **organização ativa**, nunca por `user_id`.

```php
// PROIBIDO — escopo por usuário
Project::query()->where('user_id', $user->id);

// Correto — escopo por organização ativa
Project::query()->where('organization_id', $activeOrganization->id);

// Correto — dado de domínio permanece escopado pela Brand ativa
Execution::query()->where('project_id', $activeProject->id);
```

- O escopo por `project_id` da Seção 19.4 e da regra 10 da `CLAUDE.md` **permanece integralmente válido** para todo dado de domínio. A organização é a camada acima, não a substituta.
- `user_id` deixa de ser critério de isolamento. O único recurso que pertence diretamente a um usuário é a **carteira pessoal de créditos**.
- Consultas que hoje filtram por `user_id` (listagens de Executions, Policies, resolução de Brand ativa) migram para `organization_id`, conforme registrado na Seção 12.17.
- Uma Execution disparada por um membro é visível aos demais membros da mesma organização, na mesma Brand. Isso é intencional: a Brand é da organização, e o trabalho produzido nela também.

---

## 20.7 Dois Eixos de Permissão que Nunca Se Confundem

O sistema tem **dois eixos de permissão completamente independentes**. Confundi-los é falha de segurança.

| Eixo | Onde vive | O que significa | Escopo |
|------|-----------|-----------------|--------|
| `users.is_admin` | Coluna booleana em `users` | **Staff da plataforma Arkion** — hoje, Nicolas e Erich. Dá acesso ao painel administrativo `/admin/*` (Seção 14). | Global, da plataforma |
| `organization_user.role` | Coluna na pivot `organization_user` | **Papel dentro de uma organização** — `owner`, `admin` ou `member` (Seção 20.4, ADR-010). | Por organização |

> ⚠️ **Regra crítica:** Um não implica o outro **em nenhuma direção**.

- Ser `users.is_admin = true` **não** torna o usuário Owner nem Admin de nenhuma organização, nem lhe dá qualquer papel implícito nas organizações de clientes.
- Ser Owner ou Admin de uma organização **não** concede `users.is_admin` nem qualquer acesso a `/admin/*`.
- Um staff da plataforma que também seja cliente tem os dois atributos, de forma independente, e cada verificação consulta apenas o eixo que lhe corresponde.
- Nenhuma verificação pode usar um eixo como atalho para o outro. `if ($user->is_admin)` jamais autoriza uma ação de organização; `$user->roleIn($org)?->canManageBilling()` jamais autoriza uma ação de plataforma.

O termo "admin" aparece nos dois eixos por acidente de vocabulário — e continua aparecendo depois da ADR-010, que manteve o valor `admin` em `organization_user.role` ao lado do novo `owner`. Em código, nomear com precisão (`isPlatformStaff()` versus `OrganizationRole::Admin`) é preferível a repetir a ambiguidade. O Owner também não é "o admin da plataforma daquela organização": é um papel de cliente, no eixo da organização.

---

## 20.8 Estado da Implementação

Implementado (verificado em Outubro/2026):

| Item | Estado |
|------|--------|
| Tabelas `organizations` e `organization_user`; coluna `projects.organization_id` NOT NULL | ✅ Implementado |
| Organização "Personal" criada no primeiro login, com o usuário como Owner (`SyncFirebaseUserAction`) | ✅ Implementado |
| Contexto de sessão com duas variáveis (`OrganizationContext`, middleware `EnsureActiveOrganization`); a participação é reconferida a cada acesso e o contexto some quando o usuário perde a organização | ✅ Implementado |
| Escopo das consultas por `organization_id` e `project_id` | ✅ Implementado |
| Três papéis no Enum `OrganizationRole` (`owner`, `admin`, `member`), com os métodos `can*()` espelhando a matriz da Seção 20.4, inclusive `canLeave()` | ✅ Implementado (ADR-010) |
| `OrganizationPolicy` e `ProjectPolicy` delegando ao Enum; `leave` é de qualquer membro que não seja o Owner | ✅ Implementado |
| Índice único parcial `UNIQUE(organization_id) WHERE role = 'owner'` | ✅ Implementado (ver Apêndice K, nota sobre SQLite) |
| Backfill do Owner: promove o Admin mais antigo (`joined_at`, desempate por `id`); sem Admin, o membro mais antigo, registrando a organização no log | ✅ Implementado |
| `OrganizationMembershipService`: `addMember`, `removeMember`, `changeRole`, `transferOwnership`, `leave`, `invite`, `revokeInvitation` e `acceptInvitation`, todas em transação, com `lockForUpdate` na organização, papéis relidos dentro do lock e verificação de exatamente um Owner antes do commit | ✅ Implementado |
| Transferência de posse: efetivada imediatamente pelo Owner, o anterior vira Admin | ✅ Implementado; o aceite do destinatário segue **[A DEFINIR]** (Seção 20.4) |
| Sair da organização (regra abaixo) | ✅ Implementado no Service e na Policy |
| Convites de membros (`organization_invitations`): endpoint `POST /api/invitations/{token}/accept` | ✅ Implementado; a rota web `GET /invitations/{token}` entra na Sessão 3.2 |
| ~~Invariante do último Admin~~ | 🔁 **SUPERSEDED (ADR-010)** — substituído pelas invariantes do Owner |

Pendente ou **[A DEFINIR]**:

| Item | Estado |
|------|--------|
| Fluxo de convite: provedor SMTP | **[A DEFINIR]**; o link também é devolvido a quem convida |
| Aceite do destinatário na transferência de posse | **[A DEFINIR]** (Seção 20.4) |
| Política de exclusão de organização (soft delete, carência, Marcas, Executions, assinatura) | **[A DEFINIR]** (Seção 20.4) |
| Destino da carteira pessoal quando o usuário deixa todas as orgs | **[A DEFINIR]** |
| Titular da assinatura Stripe = Organization (`Billable` em `Organization`) | Pendente — **Mês 4** (Seção 12.20) |
| Edição de nome e slug da organização | **[A DEFINIR]**; não ampliado além do Owner |
| Endpoints HTTP e telas de membros (convidar, remover, alterar papel, sair, transferir) e seletor de organização no header | Pendente; hoje só o Service e a Policy existem |
| CTA por papel nos três estados de bloqueio | Pendente (Seção 12.12) |

Esta tabela cobre o que as sessões de código de setembro e outubro de 2026 tocaram; os demais itens de outras seções (por exemplo, as três carteiras de crédito) têm estado próprio nas respectivas seções.

### Fluxo de convite

Definido (antes **[A DEFINIR]**):

- **Quem convida:** Owner (Admin ou Member) e Admin (somente Member), conforme a matriz da Seção 20.4. Ninguém é convidado como Owner.
- **Validade:** 7 dias (`OrganizationMembershipService::INVITATION_TTL_DAYS`). Um convite pendente já expirado é revogado ao se convidar o mesmo e-mail de novo.
- **Um convite pendente por e-mail por organização**, garantido por índice único parcial no banco.
- **E-mail de quem já é membro é recusado** com `409 already_organization_member`.
- **Token:** gerado com `random_bytes(32)` e devolvido em claro uma única vez; o banco guarda somente o hash sha256 (`token_hash`).
- **Aceite:** exige usuário autenticado com e-mail **verificado pelo Firebase** (`email_verified_at`) e igual ao do convite, sem diferenciar maiúsculas. Sem isso, quem se cadastrasse com o e-mail alheio sequestraria o convite. Aceitar sendo já membro é idempotente e não altera o papel existente.
- **Revogação:** quem pode convidar com aquele papel pode revogar; convite aceito não é revogável (a participação se desfaz por remoção).

### Sair da organização

- Admin e Member saem por conta própria (`OrganizationMembershipService::leave()`; ability `leave` na `OrganizationPolicy`; `OrganizationRole::canLeave()`).
- O Owner **não** sai: recebe `OwnerIsImmutableException` (`409 owner_is_immutable`) orientando a transferir a posse antes. Depois da transferência ele é Admin e pode sair. Corolário da invariante 5 (Seção 20.4).
- Mesmas garantias das demais operações: transação, `lockForUpdate` na organização e papel relido dentro do lock.
- Se a organização que ele deixou era a ativa na sessão, `OrganizationContext` revalida a participação no acesso seguinte e limpa o contexto.
- As Marcas ficam com a organização (Seção 20.2). O destino da carteira pessoal de quem deixa a última organização segue **[A DEFINIR]**.

A migração foi estrutural e retrocompatível por backfill: toda Brand existente foi adotada pela organização Personal do seu antigo `user_id` (Seção 12.8).

---

# APÊNDICES

---

# Apêndice A — Convenções Gerais de Desenvolvimento

## Objetivo

Este apêndice estabelece regras de implementação destinadas aos agentes de IA responsáveis pela geração de código. Seu objetivo é garantir uniformidade arquitetural, previsibilidade e manutenção do projeto.

Estas convenções complementam as regras definidas nas seções anteriores e possuem caráter normativo.

---

## A.1 Convenções de Nomenclatura

### Models

Sempre utilizar nomes no singular.

Exemplos:

```
User

Project

Execution

VideoReference

PlanLimit
```

---

### Controllers

Sufixo obrigatório:

```
Controller
```

Exemplo:

```
ExecutionController
```

---

### Livewire

Nome igual ao domínio da tela.

Exemplo:

```
Dashboard

BrandCore

Sidebar

Header

LogoutModal

BrandSwitcher
```

---

### Jobs

Sempre utilizar verbo no infinitivo ou ação explícita.

Exemplos:

```
ExecutePipelineJob

UpdateViralReferencesJob

SendNotificationJob
```

---

### Policies

```
ExecutionPolicy

ProjectPolicy
```

---

### Events

Sempre no passado.

```
ExecutionUpdated

ExecutionFailed
```

---

### Middleware

Sempre iniciar com verbo.

```
VerifyFirebaseToken

CheckSubscription

EnsureCreditBalance
```

---

## A.2 Organização da Lógica

Cada camada possui responsabilidade única.

| Camada | Responsabilidade |
|---------|------------------|
| Blade | HTML |
| Alpine | Microinterações |
| Livewire | Estado da interface |
| Controller | Orquestração HTTP |
| Request | Validação |
| Policy | Autorização |
| Job | Processamento assíncrono |
| Service | Regras reutilizáveis |
| Model | Persistência |

---

## A.3 Form Requests

Toda entrada HTTP deve utilizar Form Requests quando aplicável.

Validação nunca deve permanecer dentro de Controllers.

---

## A.4 Services

A documentação não define uma camada formal de Services.

Caso esta camada seja introduzida durante a implementação, sua estrutura permanece:

**[A DEFINIR]**

---

## A.5 Exceptions

Exceções customizadas permanecem **[A DEFINIR]**.

Nunca utilizar mensagens técnicas diretamente para o usuário.

---

# Apêndice B — Fluxo Operacional Completo

Toda operação da plataforma deve obedecer ao seguinte fluxo arquitetural.

```text
Usuário

↓

Blade

↓

Livewire

↓

Controller

↓

Middleware

↓

Policy

↓

Form Request

↓

Execution criada

↓

Dispatch Job

↓

Redis

↓

Laravel Horizon

↓

HTTP

↓

n8n

↓

APIs Externas

↓

Callback

↓

Laravel

↓

Persistência

↓

Broadcast

↓

Livewire

↓

Atualização da Interface
```

Qualquer implementação que ignore uma dessas camadas deve ser considerada incompatível com esta arquitetura.

---

# Apêndice C — Estratégia de Cache

## Objetivo

Reduzir consultas repetitivas ao banco de dados e minimizar latência.

---

## Itens Elegíveis para Cache

### Plan Limits

Tabela:

```
plan_limits
```

É o principal candidato a cache.

---

### Configurações

Caso existam configurações globais do sistema.

Estado:

**[A DEFINIR]**

---

### Brand Ativa

O mecanismo de cache da Brand ativa não está documentado.

Permanece:

**[A DEFINIR]**

---

## Itens que NÃO Devem Ser Cacheados

Nunca armazenar em cache:

- Execution.result;
- dados de autenticação;
- saldo de créditos quando houver risco de inconsistência;
- objetos mutáveis durante execução.

---

## Invalidação

A estratégia oficial de invalidação permanece:

**[A DEFINIR]**

---

# Apêndice D — Observabilidade

## Logs

Toda exceção deve ser registrada.

Categorias esperadas:

- autenticação;
- filas;
- callbacks;
- integração;
- billing;
- exceções inesperadas.

---

## Horizon

Monitorar continuamente:

- filas;
- Jobs falhos;
- Jobs lentos;
- throughput;
- Workers.

---

## Sentry

Registrar:

- Exceptions;
- Stack Trace;
- Ambiente;
- URL;
- Usuário autenticado (quando disponível).

Nunca registrar:

- segredos;
- tokens;
- credenciais.

---

## UptimeRobot

Monitorar:

- disponibilidade HTTP;
- SSL;
- tempo de resposta.

---

## Correlation ID

Sempre que possível, utilizar o `execution_id` como identificador de correlação entre:

- logs;
- callbacks;
- eventos;
- broadcasts;
- filas.

Isso facilita auditoria ponta a ponta.

---

# Apêndice E — Padrões Livewire

## Componentes

Cada componente representa uma única responsabilidade.

Não criar componentes monolíticos.

---

## Loading

Sempre utilizar estados de carregamento para operações assíncronas.

---

## Validação

Toda validação deve reutilizar Rules do Laravel.

Nunca duplicar regras entre frontend e backend.

---

## Polling

Polling deve existir apenas como mecanismo de fallback quando Reverb estiver indisponível.

---

## Estado

Livewire deve manter apenas estado de apresentação.

Nunca persistir regras de negócio no componente.

---

# Apêndice F — Fluxo de Deploy

A documentação não descreve pipeline CI/CD.

Permanece:

**[A DEFINIR]**

Fluxo esperado de produção:

```text
Deploy

↓

Migrations

↓

Cache Config

↓

Cache Routes

↓

Restart Horizon

↓

Restart Reverb

↓

Health Check
```

---

# Apêndice G — Ordem Recomendada de Implementação

Para reduzir retrabalho entre agentes de IA, recomenda-se a seguinte sequência de desenvolvimento.

1. Configuração Laravel.
2. Infraestrutura.
3. Banco de Dados.
4. Models.
5. Policies.
6. Middleware.
7. Firebase Auth.
8. Cashier.
9. Redis.
10. Horizon.
11. Reverb.
12. Layout Base.
13. Sidebar.
14. Header.
15. Brand Switcher.
16. Dashboard.
17. Pipelines.
18. n8n.
19. Broadcast.
20. PWA.

Cada etapa depende da conclusão da anterior.

---

# Apêndice H — Checklist para Agentes de IA

Antes de implementar qualquer funcionalidade, verificar:

- [ ] Existe Migration?
- [ ] Existe Model?
- [ ] Existe Policy?
- [ ] Existe Middleware adequado?
- [ ] A query está escopada pela Brand ativa?
- [ ] O consumo de créditos consulta `plan_limits`?
- [ ] A execução utiliza fila?
- [ ] Existe tratamento de falha?
- [ ] Existe broadcast quando necessário?
- [ ] O componente respeita o Design System?
- [ ] As cores utilizam apenas os tokens oficiais?
- [ ] A fonte Inter está sendo utilizada?
- [ ] Não existem credenciais hardcoded?
- [ ] O callback valida `X-N8N-Secret`?
- [ ] Nenhum `result` JSONB é carregado desnecessariamente?
- [ ] O código respeita as ADRs documentadas?

Nenhum Pull Request ou geração automática de código deve ser considerado concluído sem atender integralmente a este checklist.

---

# Apêndice I — Critérios de Qualidade Arquitetural

Uma implementação será considerada aderente ao Arkion somente quando cumprir simultaneamente os seguintes critérios:

- Conformidade com todas as ADRs documentadas;
- Isolamento completo entre Brands;
- Processamento assíncrono via Horizon para todas as operações de IA;
- Comunicação exclusiva Laravel ↔ n8n por HTTP autenticado;
- Persistência centralizada no Laravel;
- Uso exclusivo do Design System oficial;
- Ausência de valores hardcoded para créditos, planos e limites;
- Observabilidade adequada (logs, Horizon e Sentry);
- Compatibilidade com o fluxo de autenticação Firebase;
- Compatibilidade com o modelo de créditos e assinaturas via Stripe/Cashier.

---

# Apêndice J — Declaração de Fonte de Verdade

Este Software Architecture Document constitui a especificação arquitetural oficial do projeto Arkion.

Toda implementação gerada por agentes de IA deve ser considerada derivada deste documento.

Na ausência de uma especificação explícita, o agente deve utilizar o marcador **[A DEFINIR]** como indicação de lacuna arquitetural, abstendo-se de inferir comportamentos, estruturas de dados, integrações ou regras de negócio não documentadas.

Qualquer alteração estrutural relevante na arquitetura deve ser refletida neste documento antes da implementação correspondente, preservando-o como a fonte única de verdade para futuras sessões de desenvolvimento.

# Apêndice K — Convenções de Banco de Dados

## Objetivo

Este apêndice estabelece as convenções oficiais de modelagem de dados, migrations e persistência utilizadas pelo Arkion.

Todas as migrations futuras devem seguir estas regras. Agentes de IA não devem criar novas convenções.

---

# K.1 Convenções de Nomenclatura de Tabelas

## Regra Geral

Toda tabela deve ser nomeada:

- no plural;
- em `snake_case`;
- utilizando o nome físico do domínio persistido.

### Correto

| Entidade | Tabela |
|----------|---------|
| User | `users` |
| Project | `projects` |
| Execution | `executions` |
| VideoReference | `video_references` |
| PlanLimit | `plan_limits` |

### Incorreto

| Incorreto | Motivo |
|-----------|--------|
| `user` | singular |
| `Project` | PascalCase |
| `VideoReferences` | Camel/Pascal |
| `Brand` | o nome físico adotado é `projects` |

---

## Tabelas Pivot

Sempre utilizar:

```
{modelo_singular}_{modelo_singular}
```

em ordem alfabética.

Exemplo:

```text
project_user
```

Nunca:

```text
user_projects

projects_users

brand_user
```

Embora o conceito de negócio seja **Brand**, a entidade persistida é `projects`.

Portanto:

```text
project_user
```

é a única nomenclatura permitida.

---

# K.2 Convenções de Colunas

## snake_case obrigatório

Todas as colunas seguem `snake_case`.

### Correto

```
credits_balance

created_at

completed_at

project_id
```

### Incorreto

```
creditsBalance

ProjectID

CompletedAt
```

---

## Chaves Estrangeiras

Sempre utilizar:

```
{tabela_singular}_id
```

Exemplos:

```
project_id

user_id

execution_id
```

Nunca utilizar nomes derivados apenas do conceito de domínio.

Exemplo incorreto:

```
brand_id
```

A tabela física chama-se:

```
projects
```

Logo:

```
project_id
```

é obrigatório.

---

## Booleanos

Sempre utilizar prefixo:

```
is_

has_
```

Exemplos:

```
is_active

is_default

has_completed_setup
```

Nunca:

```
active

completed
```

---

## Datas de Domínio

Sempre utilizar sufixo:

```
_at
```

Exemplos:

```
completed_at

failed_at

last_credit_reset_at
```

Nunca:

```
completedDate

failure_time
```

---

## JSONB

Campos JSON nunca devem indicar o tipo no nome.

### Correto

```
config

result

metadata
```

### Incorreto

```
config_json

result_json

metadata_object
```

---

# K.3 Chaves Primárias

## Padrão Oficial

O projeto utiliza:

```php
$table->id();
```

Tipo efetivo:

```
bigint unsigned auto increment
```

Esta decisão é consistente com a modelagem existente, incluindo a tabela `users`.

---

## UUID

UUID **não** é utilizado como chave primária das tabelas de domínio.

O uso de UUID permanece **[A DEFINIR]** para identificadores públicos, integrações externas ou compartilhamento de URLs.

Até que uma ADR específica seja criada, utilize exclusivamente `bigint` como chave primária.

---

# K.4 Chaves Estrangeiras

Toda Foreign Key deve ser declarada explicitamente.

Sempre utilizar:

```php
$table
    ->foreignId('project_id')
    ->constrained()
    ->cascadeOnDelete();
```

Nunca criar FK manualmente com `unsignedBigInteger()` sem constraint.

---

## Política de onDelete

| Relacionamento | Regra |
|----------------|-------|
| Dados pertencentes exclusivamente ao pai | `cascadeOnDelete()` |
| Dados de configuração compartilhados | `restrictOnDelete()` |
| Relacionamentos opcionais | `nullOnDelete()` |

---

### Exemplo — executions → projects

```php
$table
    ->foreignId('project_id')
    ->constrained()
    ->cascadeOnDelete();
```

---

### Exemplo — projects → users

```php
$table
    ->foreignId('user_id')
    ->constrained()
    ->cascadeOnDelete();
```

---

### Exemplo Opcional

```php
$table
    ->foreignId('approved_by')
    ->nullable()
    ->constrained('users')
    ->nullOnDelete();
```

---

# K.5 Soft Deletes

## Utilizar SoftDeletes

Tabelas de domínio principal:

- users
- projects
- executions

---

## Não utilizar SoftDeletes

Tabelas de configuração:

- plan_limits

Tabelas técnicas:

- jobs
- failed_jobs
- cache
- cache_locks
- notifications (seguir implementação padrão do Laravel, salvo decisão futura)

---

## Policies

Policies devem considerar apenas registros ativos.

Nunca autorizar acesso a registros soft deleted sem fluxo administrativo específico.

Fluxos de restauração permanecem **[A DEFINIR]**.

---

# K.6 Índices

## Regra Geral

Toda Foreign Key possui índice.

Mesmo quando criado implicitamente, a migration deve tornar essa intenção explícita quando necessário.

---

## Índices Simples

Utilizar para colunas frequentemente usadas em:

- where
- orderBy
- join

Exemplos:

```
status

project_id

user_id

created_at
```

---

## Índices Compostos

Utilizar quando as consultas combinarem colunas recorrentes.

Exemplo:

```sql
(project_id, status)

(project_id, created_at)
```

---

## Índices Parciais

Quando houver benefício comprovado em PostgreSQL.

Exemplo:

```sql
WHERE status = 'pending'
```

Sua utilização depende de necessidade observada em produção.

Estado atual:

Em uso: `organization_user_single_owner_unique`, `UNIQUE(organization_id) WHERE role = 'owner'` (SAD 20.4, invariante 1), criado por `DB::statement()` porque o Schema Builder não expressa índice parcial.

> ⚠️ **SQLite (suíte de testes):** um `->change()` futuro em `organization_user` faz o Laravel reconstruir a tabela e recriar os índices **sem a cláusula `WHERE`**; o índice vira um `UNIQUE(organization_id)` simples e passa a recusar qualquer segunda participação na organização. A migration que alterar colunas dessa tabela precisa descartar e recriar o índice parcial depois do `->change()`. O mesmo vale para os demais índices parciais do projeto.

---

## Índices GIN

Obrigatórios para:

```
executions.config

executions.result
```

Utilizar:

```sql
USING GIN
```

Sempre que consultas internas explorarem atributos desses documentos.

---

# K.7 Convenções de Migrations

## Arquivos

Criar tabela:

```
create_projects_table
```

Adicionar coluna:

```
add_status_to_executions_table
```

Modificar coluna:

```
modify_result_in_executions_table
```

---

## Produção

Nunca editar migrations já executadas.

Sempre criar nova migration incremental.

---

## Ordem

1. tabelas sem FK;
2. tabelas dependentes;
3. pivots.

---

## Regras

Nunca:

- inserir lógica de domínio;
- consumir APIs;
- executar cálculos;
- popular dados de negócio.

Seeds pertencem exclusivamente aos Seeders.

---

# K.8 Estratégia JSONB

Sempre utilizar PostgreSQL nativo.

```php
$table->jsonb('config');

$table->jsonb('result');
```

Nunca:

```php
$table->json('config');
```

---

## Casts Obrigatórios

```php
protected function casts(): array
{
    return [

        'config' => 'array',

        'result' => 'array',

    ];
}
```

---

## Queries

Nunca depender rigidamente da estrutura interna do JSON para regras críticas do domínio.

O JSONB é considerado um contrato evolutivo.

Mudanças estruturais internas não exigem migrations, mas devem ser documentadas neste SAD.

---

# K.9 Convenções de Seeding

A ordem do `DatabaseSeeder` deve respeitar dependências entre entidades.

Exemplo:

```php
public function run(): void
{
    $this->call([

        PlanLimitSeeder::class,

        [A DEFINIR],

    ]);
}
```

---

## PlanLimitSeeder

Obrigatório em qualquer ambiente.

Sem ele, o sistema não consegue calcular consumo de créditos.

---

## DevelopmentSeeder

Executar apenas em ambiente local.

```php
if (app()->environment('local')) {

    $this->call(DevelopmentSeeder::class);

}
```

---

## Produção

Nunca inserir:

- usuários reais;
- marcas reais;
- execuções reais;
- dados sensíveis.

---

# K.10 Exemplo Completo — Migration da Tabela `executions`

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('executions', function (Blueprint $table) {

            $table->id();

            $table->foreignId('project_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('pipeline_type');

            $table->string('status')->index();

            $table->jsonb('config');

            $table->jsonb('result')->nullable();

            $table->unsignedInteger('tokens_used')->default(0);

            $table->unsignedInteger('credits_consumed');

            $table->timestamp('completed_at')->nullable();

            $table->timestamp('failed_at')->nullable();

            $table->timestamps();

            $table->softDeletes();

            $table->index(['project_id', 'status']);

            $table->index(['project_id', 'created_at']);
        });

        DB::statement(
            'CREATE INDEX executions_config_gin
             ON executions
             USING GIN (config);'
        );

        DB::statement(
            'CREATE INDEX executions_result_gin
             ON executions
             USING GIN (result);'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('executions');
    }
};
```

---

> ⚠️ **Regra crítica:** A modelagem do banco de dados é parte da arquitetura do Arkion. Nunca introduza novas convenções de nomenclatura, tipos, índices ou relacionamentos sem atualizar este SAD e registrar uma ADR correspondente. Toda migration futura deve ser consistente com as convenções definidas neste apêndice.


# Apêndice L — Convenções de Código PHP/Laravel

## Objetivo

Este apêndice define o padrão oficial de organização do código PHP do Arkion.

Todos os agentes de IA devem seguir estas convenções rigorosamente. O objetivo é garantir que código gerado em sessões diferentes possua a mesma estrutura arquitetural, estilo e organização.

---

# L.1 Namespaces

## Estrutura Oficial

A estrutura de namespaces do projeto deve seguir a organização abaixo.

```text
App
├── Actions/
├── Broadcasting/
├── Console/
├── Events/
├── Exceptions/
├── Http/
│   ├── Controllers/
│   │   ├── Web/
│   │   └── Api/
│   ├── Middleware/
│   └── Requests/
├── Jobs/
├── Livewire/
│   ├── BrandAssets/
│   ├── Marketing/
│   └── Components/
├── Models/
├── Policies/
├── Providers/
├── Services/
├── Notifications/
└── Support/
```

---

## Controllers

O Arkion é um monólito Blade + Livewire.

Portanto:

```text
App\Http\Controllers\Web\
```

é o namespace padrão.

Utilize:

```text
App\Http\Controllers\Api\
```

apenas para endpoints externos, callbacks, webhooks ou integrações que não pertençam ao fluxo Livewire.

Exemplos:

```text
Web

DashboardController

ExecutionController

ProjectController
```

```text
Api

N8nCallbackController

StripeWebhookController
```

---

## Livewire

Organize componentes por domínio.

```text
App\Livewire

Dashboard

Sidebar

Header

LogoutModal

BrandSwitcher
```

Subpastas:

```text
App\Livewire\BrandAssets

VisualIdentity

Characters

Products
```

```text
App\Livewire\Marketing

MarketResearch

BuyerPersonas

SocialStrategy
```

Nunca criar dezenas de componentes na raiz quando existir um domínio claro.

---

## Services

Os Services devem ser organizados em estrutura plana até que o crescimento do projeto justifique modularização.

```text
App\Services

PipelineService

CreditService

BrandService

ExecutionService

NotificationService
```

Caso futuramente existam mais de aproximadamente 15 Services de um mesmo domínio, a organização por subpastas poderá ser revisada mediante ADR.

---

## Actions

O SAD atual não adota uma camada dedicada de Actions.

Toda lógica reutilizável deve permanecer em:

```text
App\Services
```

Portanto:

```text
App\Actions
```

permanece **não utilizado**.

Sua adoção exige nova ADR.

---

# L.2 Controllers

## Responsabilidade

Controllers apenas orquestram a requisição HTTP.

Nunca concentram regras de negócio.

Fluxo obrigatório:

```text
Controller

↓

Form Request

↓

Policy

↓

Service

↓

Job

↓

Response
```

---

## Limite de Complexidade

Cada método público deve possuir preferencialmente até **15 linhas úteis**.

Quando ultrapassar esse limite devido à lógica de domínio, extraia para um Service.

---

## Acesso ao Eloquent

Nunca realizar consultas complexas diretamente no Controller.

### Correto

```php
$project = $this->brandService->findForUser(
    auth()->user(),
    $id
);
```

ou

```php
$execution = Execution::query()
    ->forBrand($project->id)
    ->findOrFail($id);
```

---

### Incorreto

```php
Execution::where(...)
    ->join(...)
    ->orderBy(...)
    ->get();
```

---

## Padrões de Retorno

| Cenário | Retorno |
|----------|----------|
| Página Blade | `view()` |
| Pós-formulário | `redirect()` |
| Callback/API | `response()->json()` |
| Livewire | retorno implícito do componente |

---

## Exemplo Correto

```php
public function store(
    StoreBriefingRequest $request,
    PipelineService $pipelineService
): RedirectResponse {

    $execution = $pipelineService->dispatchBriefing(
        $request->validated(),
        auth()->user()
    );

    return redirect()
        ->route('executions.show', $execution);
}
```

---

## Exemplo Incorreto

```php
public function store(Request $request)
{
    $request->validate([...]);

    $credits = auth()->user()->credits_balance;

    if ($credits < 5) {

        abort(403);

    }

    $execution = Execution::create(...);

    Http::post(...);

    return view(...);
}
```

Este exemplo viola múltiplas regras do projeto:

- validação no Controller;
- regra de créditos no Controller;
- chamada ao n8n síncrona;
- persistência sem Service.

---

# L.3 Services

## Quando Criar

Crie um Service quando:

- a lógica for reutilizada;
- envolver múltiplos Models;
- representar um caso de uso do domínio.

Não utilize Service apenas para encapsular uma linha de código.

---

## Quando NÃO Criar

Lógica simples pertencente exclusivamente ao Model deve permanecer no próprio Model.

Processamento assíncrono deve permanecer em Jobs.

---

## Nomenclatura

Sempre utilizar o sufixo:

```
Service
```

Exemplos:

```
PipelineService

CreditService

BrandService

ExecutionService
```

---

## Injeção

Sempre utilizar Dependency Injection.

```php
public function __construct(

    private readonly CreditService $credits

) {}
```

Nunca:

```php
$service = new CreditService();
```

---

## Singleton

Services stateless e amplamente reutilizados podem ser registrados como singleton no `AppServiceProvider`.

Exemplo:

```php
$this->app->singleton(
    CreditService::class
);
```

A decisão deve considerar ausência de estado interno.

---

## Exemplo — CreditService

```php
class CreditService
{
    public function canExecute(
        User $user,
        string $action
    ): bool
    {
        $cost = $this->cost($action);

        return $user->credits_balance >= $cost;
    }

    public function deduct(
        User $user,
        string $action
    ): void
    {
        $cost = $this->cost($action);

        $user->decrement(
            'credits_balance',
            $cost
        );
    }

    public function getRemainingBalance(
        User $user
    ): int
    {
        return $user->credits_balance;
    }

    protected function cost(
        string $action
    ): int
    {
        return Cache::remember(
            "plan_limits:{$action}",
            now()->addMinutes(60),
            fn () => PlanLimit::where('action', $action)
                ->value('credits')
        );
    }
}
```

---

# L.4 Form Requests

Toda entrada HTTP deve utilizar Form Request.

Nunca valide diretamente no Controller.

---

## Nomenclatura

```
StoreBriefingRequest

UpdateProjectRequest

StoreExecutionRequest
```

Formato:

```
{Verbo}{Recurso}Request
```

---

## authorize()

Nunca retornar simplesmente:

```php
return true;
```

Utilize Policy.

---

## Exemplo

```php
public function authorize(): bool
{
    return $this->user()
        ->can('create', Execution::class);
}
```

---

## messages()

As mensagens internas permanecem em português.

```php
public function messages(): array
{
    return [

        'title.required' => 'Informe um título.',

        'description.required' => 'Informe a descrição.',

    ];
}
```

---

## rules()

```php
public function rules(): array
{
    return [

        'title' => ['required', 'string', 'max:255'],

        'description' => ['required', 'string'],

    ];
}
```

---

# L.5 API Resources

Utilize Resources sempre que houver serialização explícita.

Nunca exponha Models diretamente em endpoints públicos.

---

## Collections

Nunca incluir:

```
result
```

na coleção de execuções.

Exemplo:

```php
ExecutionCollection
```

Campos:

```text
id

status

pipeline_type

credits_consumed

created_at
```

---

## Resource Individual

```php
ExecutionResource
```

Pode incluir:

```
result
```

quando necessário.

---

# L.6 Exceptions Customizadas

Localização:

```text
app/Exceptions
```

---

## Nomenclatura

Sempre terminar com:

```
Exception
```

Exemplos:

```
InsufficientCreditsException

InvalidN8nSecretException

BrandNotFoundException
```

---

## Handler

Toda Exception customizada deve possuir renderização consistente.

| Exception | HTTP |
|-----------|------|
| InsufficientCreditsException | 402 |
| InvalidN8nSecretException | 401 |
| BrandNotFoundException | 404 |

Nunca expor stack trace em produção.

---

# L.7 Models

Todo Model deve declarar explicitamente:

- `$fillable`
- `casts()`
- relacionamentos
- scopes

Nunca utilizar:

```php
protected $guarded = [];
```

---

## Scopes

Prefixo:

```
scopeForBrand()

scopeCompleted()

scopeThisMonth()
```

---

## Accessors

Utilizar sintaxe moderna.

```php
public function status(): Attribute
{
    return Attribute::make(
        get: fn ($value) => strtoupper($value)
    );
}
```

---

## Exemplo — Execution

```php
class Execution extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'pipeline_type',
        'status',
        'config',
        'result',
        'credits_consumed',
        'tokens_used',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'result' => 'array',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scopeForBrand(
        Builder $query,
        int $projectId
    ): Builder {
        return $query->where(
            'project_id',
            $projectId
        );
    }
}
```

---

# L.8 Jobs

Todos os Jobs devem implementar:

```php
ShouldQueue
```

---

## Valores Padrão

```php
public int $tries = 3;

public array $backoff = [60, 300, 900];
```

Timeouts recomendados:

| Tipo | Timeout |
|-------|---------|
| Dispatch para n8n | 30 s |
| Notificações | 60 s |

---

## handle()

O método deve permanecer enxuto.

Toda lógica complexa pertence a Services.

---

## failed()

Sempre:

1. atualizar Execution;
2. registrar no Sentry;
3. disparar evento;
4. registrar log.

---

## IDs

Jobs recebem IDs.

Nunca Models completos quando puderem ser reconsultados.

---

# L.9 Eventos e Listeners

## Eventos

Sempre nomeados no passado.

```
ExecutionUpdated

ExecutionFailed
```

---

## Listeners

Sempre nomeados por ação.

```
NotifyUserOfCompletion

DeductCreditsOnCompletion
```

---

## Registro

Todos os eventos devem ser registrados em:

```text
EventServiceProvider
```

---

## Broadcast

Eventos de broadcast implementam:

```php
ShouldBroadcast
```

---

## Listeners Assíncronos

Sempre implementar:

```php
ShouldQueue
```

quando executarem qualquer operação potencialmente lenta.

---

> ⚠️ **Regra crítica:** Controllers apenas coordenam o fluxo HTTP. Toda regra de negócio pertence ao domínio (Services, Models, Policies e Jobs). Se um Controller começa a decidir créditos, consultar APIs externas, montar queries complexas ou alterar múltiplas entidades, a implementação está em desacordo com a arquitetura oficial do Arkion.


# Apêndice M — Padrões Livewire 3

## Objetivo

Este apêndice define os padrões oficiais para desenvolvimento de componentes Livewire 3 no Arkion.

Como o projeto utiliza **Laravel 11 + Blade + Livewire 3** como arquitetura principal de frontend, todos os componentes devem seguir exatamente estas convenções.

O objetivo é garantir previsibilidade, evitar duplicação de lógica e manter a separação entre apresentação e domínio.

---

# M.1 Estrutura de um Componente

Todo componente Livewire deve possuir exatamente dois arquivos:

```text
Classe PHP

app/Livewire/{Componente}.php
```

```text
View Blade

resources/views/livewire/{componente-kebab-case}.blade.php
```

Exemplo:

```text
app/Livewire/BrandSwitcher.php

resources/views/livewire/brand-switcher.blade.php
```

Nunca colocar Blade dentro da classe.

Nunca criar componentes inline.

---

## Organização por Domínio

Quando houver diversos componentes relacionados, utilizar subpastas.

Exemplo:

```text
app/Livewire

BrandAssets/
    VisualIdentity.php
    Characters.php
    Products.php

Marketing/
    MarketResearch.php
    BuyerPersonas.php
    SocialStrategy.php
```

A View acompanha a mesma estrutura.

```text
resources/views/livewire

brand-assets/

marketing/
```

---

## Estado

Toda propriedade pública representa estado do componente.

Exemplo:

```php
public string $title = '';

public string $description = '';

public ?Execution $execution = null;
```

Nunca armazenar estado reativo em:

- Session;
- variáveis locais;
- propriedades estáticas.

---

## Computed Properties

Dados derivados devem utilizar:

```php
use Livewire\Attributes\Computed;
```

Exemplo:

```php
#[Computed]
public function remainingCredits(): int
{
    return auth()->user()->credits_balance;
}
```

Nunca recalcular manualmente no Blade.

Nunca duplicar cálculos.

---

# M.2 wire:model — Quando Utilizar Cada Variante

A escolha da variante de `wire:model` impacta diretamente desempenho e quantidade de requisições.

Utilize sempre o modificador adequado ao contexto.

---

## wire:model

Atualização imediata.

Utilize apenas quando o componente precisar reagir durante a digitação.

Exemplos:

- busca instantânea;
- contador de caracteres;
- validação imediata.

```blade
<input
    type="text"
    wire:model="search"
/>
```

---

## wire:model.lazy

Atualiza apenas no evento `blur`.

Utilize para campos longos que não exigem sincronização contínua.

Exemplo:

```blade
<textarea
    wire:model.lazy="description">
</textarea>
```

Preferido para:

- descrições;
- observações;
- textos do Briefing.

---

## wire:model.defer

Padrão recomendado para formulários completos.

Todos os valores são enviados apenas no submit.

Exemplo:

```blade
<form wire:submit="save">

    <input wire:model.defer="title">

    <textarea wire:model.defer="description"></textarea>

</form>
```

Este deve ser o padrão para:

- Briefing Generation;
- Brand Core;
- Brand Assets;
- formulários administrativos.

---

## wire:model.debounce.500ms

Utilizar exclusivamente para buscas em tempo real.

```blade
<input
    wire:model.debounce.500ms="query"
/>
```

Exemplo:

- busca de Library;
- busca de referências;
- autocomplete.

Nunca utilizar debounce em campos comuns de formulário.

---

# M.3 Actions e Submit

Toda submissão deve ocorrer através de `<form>`.

Nunca utilizar botão isolado para enviar formulários.

---

## Correto

```blade
<form wire:submit="save">

    ...

</form>
```

---

## Incorreto

```blade
<button wire:click="save">

Salvar

</button>
```

---

## Validação

Sempre validar antes de qualquer lógica.

```php
public function save(): void
{
    $this->validate();

    ...
}
```

---

## Navegação

Após sucesso:

Utilizar:

```php
return redirect()
    ->route(...);
```

Quando permanecer na mesma página:

```php
$this->dispatch(
    'briefing-created'
);
```

---

## Loading

Todo botão de submit deve possuir estado de carregamento.

Nunca permitir múltiplos envios.

---

# M.4 Loading States

Padrão obrigatório.

```blade
<button
    wire:click="executePipeline"
    wire:loading.attr="disabled"
>
    <span wire:loading.remove>
        Run Pipeline
    </span>

    <span wire:loading>
        Processing...
    </span>
</button>
```

---

## Pipelines

Após o dispatch do Job:

O botão deixa de representar o progresso.

O componente responsável passa a ser:

```
ExecutionStatus
```

que acompanha a execução via Reverb.

---

## Regra

Nunca deixar ações que consomem créditos sem loading state.

Isso evita:

- clique duplo;
- consumo duplicado;
- múltiplas execuções.

---

# M.5 Escutando Eventos Reverb

O Arkion utiliza Laravel Reverb.

Componentes devem reagir apenas aos metadados necessários.

---

## Exemplo

```php
use Livewire\Attributes\On;

#[On(
    'echo-private:execution.{executionId},ExecutionUpdated'
)]
public function onExecutionUpdated(
    array $event
): void {

    $this->status = $event['status'];

}
```

---

## Nunca

Nunca carregar:

```
result
```

via evento.

O broadcast transmite apenas:

- status;
- timestamps;
- identificador;
- progresso (quando existir).

---

## Canal

O nome do canal deve coincidir exatamente com:

```text
routes/channels.php
```

Nunca criar nomes arbitrários.

---

## Polling

Caso Reverb esteja indisponível, o polling é declarado **na view**, no elemento raiz do componente:

```blade
<div wire:poll.3s="refreshStatus">
```

A documentação oficial do projeto prevê polling apenas como mecanismo de fallback.

> **Nota — não existe `#[Polling]` no Livewire 3.** Versões anteriores deste apêndice indicavam `#[Polling(3000)]`; esse atributo não faz parte da API do Livewire 3 e nunca teve efeito. O mecanismo oficial é `wire:poll` na view, opcionalmente com intervalo (`wire:poll.3s`) e com o método a ser chamado a cada ciclo (`wire:poll.3s="refreshStatus"`). Sem método, o Livewire apenas re-renderiza o componente.

O polling deve ser desligado assim que o estado se tornar final — nada mais vai mudar depois de `completed` ou `failed`:

```blade
<div @if (! $this->isTerminal()) wire:poll.5s="refreshStatus" @endif>
```

---

# M.6 Validação

Para validação em tempo real:

Utilizar:

```php
#[Validate(
    'required|min:3'
)]
public string $title = '';
```

---

Para submissão:

```php
$this->validate();
```

---

## Form Requests

Quando a lógica estiver concentrada em endpoint HTTP:

Não duplicar regras.

O componente deve delegar ao endpoint responsável.

---

## Blade

Sempre utilizar:

```blade
@error('title')

<div>

{{ $message }}

</div>

@enderror
```

Nunca exibir mensagens manualmente.

---

# M.7 Lazy Components

Componentes pesados devem ser carregados sob demanda.

Utilizar:

```blade
<livewire:history-list lazy />
```

ou

```blade
@livewire(
    'history-list',
    lazy: true
)
```

---

## Candidatos

- HistoryList
- ExecutionHistory
- AiHealthDashboard
- ExecutionResult
- LibraryList

---

## Placeholder

Sempre implementar:

```php
public function placeholder()
{
    return view(
        'livewire.placeholders.history-list'
    );
}
```

Nunca deixar área vazia durante carregamento.

---

# M.8 Brand Switcher — Propagação de Contexto

A Brand ativa define o contexto completo da aplicação.

Toda troca deve propagar imediatamente o novo contexto.

---

## Fluxo Oficial

```text
Usuário

↓

BrandSwitcher

↓

Atualiza sessão

↓

Dispatch brand-switched

↓

Componentes atualizam estado

↓

Queries passam a usar novo project_id
```

---

## Implementação

```php
$this->dispatch(

    'brand-switched',

    projectId: $project->id

);
```

---

## Componentes Dependentes

```php
#[On('brand-switched')]
public function reload(
    int $projectId
): void {

    ...

}
```

---

## Sessão

A Brand ativa continua armazenada em sessão Laravel, conforme definido na arquitetura.

O evento Livewire existe para sincronizar imediatamente a interface.

Portanto, a estratégia oficial combina:

- sessão como fonte de verdade;
- evento para reatividade.

---

# M.9 O Que Nunca Fazer em Livewire

Nunca realizar chamadas HTTP externas.

Sempre utilizar Jobs.

---

Nunca chamar:

```php
Http::post(...);
```

dentro de componentes.

---

Nunca acessar:

```php
request()
```

diretamente.

Receba dados via:

```php
mount()
```

ou propriedades.

---

Nunca implementar:

- regras de créditos;
- autorização;
- cobrança;
- comunicação com Stripe;
- comunicação com Firebase.

Tudo pertence ao backend.

---

Nunca utilizar Session para estado reativo.

A sessão armazena apenas contexto persistente (como Brand ativa).

O estado visual pertence ao componente.

---

Nunca renderizar:

```
Execution.result
```

integralmente durante a renderização inicial.

Utilizar:

- lazy loading;
- paginação;
- componentes dedicados.

---

# M.10 Comunicação entre Componentes

A comunicação deve utilizar exclusivamente eventos Livewire.

Nunca acessar diretamente propriedades internas de outro componente.

Exemplo correto:

```php
$this->dispatch(
    'execution-created',
    executionId: $execution->id
);
```

Componente receptor:

```php
#[On('execution-created')]
public function refreshExecution(
    int $executionId
): void
{
    ...
}
```

---

# M.11 Ciclo de Vida

Utilize cada hook apenas para sua finalidade.

| Hook | Utilização |
|-------|------------|
| `mount()` | Inicialização e injeção de dependências simples |
| `boot()` | Configuração interna do componente |
| `hydrate()` | Ajustes após hidratação |
| `render()` | Apenas retornar a view |

Nunca executar consultas pesadas em `render()`.

Toda consulta recorrente deve utilizar propriedades computadas, paginação ou cache quando apropriado.

---

# M.12 Componentes Compartilhados

Os seguintes componentes devem ser tratados como componentes globais reutilizáveis:

- Sidebar
- Header
- BrandSwitcher
- NotificationDropdown
- LanguageSwitcher
- ThemeToggle
- LogoutModal
- ExecutionStatus

Não duplicar implementações equivalentes em páginas diferentes.

---

> ⚠️ **Regra crítica:** Componentes Livewire pertencem exclusivamente à camada de apresentação. Eles controlam estado da interface, validação visual e interação do usuário. Nunca implemente lógica de domínio, consumo de créditos, autorização, comunicação com n8n ou integração com APIs externas dentro de componentes Livewire.

## Nota Arquitetural — Tailwind v4

O projeto usa Tailwind CSS v4 via @tailwindcss/vite.
NÃO existe tailwind.config.js — a configuração é feita via
@theme no arquivo resources/css/app.css.
Nunca criar tailwind.config.js em sessões futuras.


# Apêndice N — Estratégia de Cache Redis

## Objetivo

Este apêndice define a estratégia oficial de utilização do Redis no Arkion.

Todos os agentes de IA devem utilizar exatamente as mesmas chaves, TTLs e políticas de invalidação.

É proibido criar novas convenções de cache sem atualização deste documento.

---

# N.1 Responsabilidades do Redis no Projeto

O Redis possui três responsabilidades distintas na arquitetura.

Cada responsabilidade deve permanecer isolada.

---

## 1. Backend de Filas (Laravel Horizon)

Responsável por:

- filas;
- Jobs;
- retry;
- delayed jobs;
- métricas do Horizon.

Toda interação ocorre exclusivamente através do sistema de filas do Laravel.

Nunca acessar diretamente estruturas internas do Horizon utilizando comandos Redis.

Sempre utilizar:

```php
ExecutePipelineJob::dispatch($execution->id);
```

Nunca:

```php
Redis::lpush(...);
```

---

## 2. Cache da Aplicação

Responsável por reduzir consultas repetidas ao PostgreSQL.

Toda leitura deve utilizar:

```php
Cache::
```

Nunca utilizar diretamente:

```php
Redis::
```

para dados de aplicação.

A fachada `Cache` representa a única API oficial.

---

## 3. Armazenamento Temporário

Os documentos atuais identificam apenas o armazenamento da Brand ativa como estado temporário.

Outros usos permanecem:

**[A DEFINIR]**

---

# N.2 Convenção Oficial de Chaves

Todas as chaves seguem obrigatoriamente o padrão:

```text
{prefixo}:{identificador}
```

Nunca utilizar:

```text
credits42

my_cache

foo

config1
```

---

## Plan Limits

Formato:

```text
plan_limits:{action}
```

Exemplo:

```text
plan_limits:brand_research

plan_limits:image_generation
```

TTL:

```
60 minutos
```

Invalidado quando:

- administrador altera `plan_limits`.

---

## Créditos Utilizados

Formato:

```text
credits_used:{user_id}:{ano_mes}
```

Exemplo:

```text
credits_used:42:2026-07
```

TTL:

Até o final do mês corrente.

Invalidado quando:

- mudança de mês;
- recálculo administrativo.

---

## Brand Ativa

Formato:

```text
active_brand:{user_id}
```

Exemplo:

```text
active_brand:42
```

TTL:

Durante toda a sessão autenticada.

Invalidado quando:

- usuário troca de Brand.

---

## Configurações

Formato:

```text
app_config:{chave}
```

Exemplo:

```text
app_config:maintenance_mode

app_config:default_language
```

TTL:

```
5 minutos
```

Invalidado quando:

- administrador altera configurações.

---

## Resumo

| Chave | Exemplo | TTL | Invalidada Quando |
|--------|----------|-----|------------------|
| `plan_limits:{action}` | `plan_limits:brand_research` | 60 min | Alteração de `plan_limits` |
| `credits_used:{user_id}:{ano_mes}` | `credits_used:42:2026-07` | Fim do mês | Reset mensal |
| `active_brand:{user_id}` | `active_brand:42` | Sessão | Troca de Brand |
| `app_config:{key}` | `app_config:maintenance_mode` | 5 min | Alteração manual |

---

# N.3 Implementação Padrão

Toda leitura deve utilizar `Cache::remember()`.

Este é o padrão obrigatório.

---

## Correto

```php
$cost = Cache::remember(
    "plan_limits:{$action}",
    now()->addMinutes(60),
    fn () => PlanLimit::query()
        ->where('action', $action)
        ->value('credits')
);
```

---

Outro exemplo:

```php
$config = Cache::remember(
    'app_config:maintenance_mode',
    now()->addMinutes(5),
    fn () => Setting::query()
        ->where('key', 'maintenance_mode')
        ->value('value')
);
```

---

## Incorreto

```php
$cost = PlanLimit::query()
    ->where('action', $action)
    ->value('credits');
```

Esta consulta cria um hot path desnecessário no PostgreSQL.

---

## Nunca

Nunca utilizar:

```php
Redis::get(...)
```

para dados de domínio quando houver equivalente em `Cache`.

---

# N.4 Estratégia de Invalidação

A invalidação deve ocorrer imediatamente após alteração da fonte de verdade.

Nunca depender exclusivamente do TTL quando o sistema conhece o momento da mudança.

---

## Plan Limits

Evento:

Administrador altera custo de créditos.

Implementação:

```php
Cache::forget(
    "plan_limits:{$action}"
);
```

Invalidação:

Imediata.

---

## Brand Ativa

Evento:

Usuário seleciona outra Brand.

Implementação:

```php
Cache::forget(
    "active_brand:{$user->id}"
);
```

Novo valor deve ser gravado imediatamente após a troca.

---

## Configuração

Evento:

Alteração administrativa.

Implementação:

```php
Cache::forget(
    "app_config:{$key}"
);
```

---

## Créditos Mensais

Evento:

Mudança de competência.

Implementação:

Recalcular e recriar a chave.

---

## Cache Tags

Como o driver oficial é Redis, o Laravel oferece suporte a Cache Tags.

Entretanto, o SAD atual não define sua utilização.

Estado:

**[A DEFINIR]**

Até definição formal, utilizar `Cache::forget()` para invalidação individual.

---

# N.5 O Que Nunca Cachear

Alguns dados jamais devem ser armazenados no Redis.

---

## Execution.result

Nunca cachear.

Motivos:

- tamanho imprevisível;
- JSONB potencialmente superior a dezenas de KB;
- alto consumo de memória;
- atualização durante processamento.

---

## Tokens Firebase

Nunca cachear.

Motivos:

- possuem expiração própria;
- podem ser revogados;
- risco de autenticação inconsistente.

---

## Saldo de Créditos Durante Execução

Nunca manter cache do saldo em operações de débito.

Motivo:

possibilidade de race condition entre múltiplas execuções concorrentes.

Sempre consultar a fonte de verdade no momento da operação crítica.

---

## Dados Completos do Usuário

Nunca cachear o objeto User completo.

Caso necessário, armazenar apenas identificadores temporários.

---

## Resultados de Pipelines

Nunca armazenar:

```
Execution.result
```

no Redis.

Persistir exclusivamente em PostgreSQL.

---

# N.6 Prefixo de Ambiente

O projeto deve utilizar prefixos distintos por ambiente.

Exemplos:

```text
tinyai_local_

tinyai_staging_

tinyai_prod_
```

O prefixo é configurado através do Laravel.

Exemplo:

```env
REDIS_PREFIX=tinyai_prod_
```

Não incluir manualmente o prefixo nas chaves.

Nunca criar:

```text
tinyai_prod_plan_limits:...
```

O framework realiza essa concatenação automaticamente.

---

# N.7 Organização em Databases Redis

Para evitar conflito entre filas e cache, utilizar databases distintos.

| Database | Finalidade |
|-----------|------------|
| DB 0 | Horizon, Queues |
| DB 1 | Cache da aplicação |

Nunca compartilhar o mesmo database entre filas e cache quando houver risco de política de eviction afetar Jobs.

---

## Política de Eviction

As necessidades do Horizon e do cache são diferentes.

### Filas

Objetivo:

Jamais perder Jobs.

Política recomendada:

```text
noeviction
```

---

### Cache

Objetivo:

Descartar entradas menos utilizadas quando necessário.

Política recomendada:

```text
allkeys-lru
```

---

Como uma única instância Redis não pode operar simultaneamente com duas políticas de eviction por database, esta configuração depende da estratégia operacional adotada para o servidor Redis.

O mecanismo exato permanece:

**[A DEFINIR]**

Caso seja utilizada apenas uma instância Redis, a política global deverá ser escolhida considerando a preservação das filas como prioridade.

---

# N.8 Monitoramento

Monitorar continuamente:

- uso de memória;
- número de chaves;
- hit ratio;
- filas;
- latência.

---

## Indicadores Mínimos

- Memória utilizada
- Memória livre
- Evictions
- Connected Clients
- Keyspace Hits
- Keyspace Misses

---

## Horizon

Monitorar:

- Jobs pendentes
- Jobs falhos
- Tempo médio
- Throughput
- Workers ativos

---

## Alertas

Sempre investigar:

- aumento contínuo de memória;
- crescimento inesperado do número de chaves;
- aumento de cache miss;
- crescimento da fila `default`;
- falhas recorrentes em Jobs.

---

# N.9 Checklist de Uso do Cache

Antes de adicionar uma nova entrada em cache, responder obrigatoriamente às seguintes perguntas:

- [ ] O dado é derivado de uma fonte de verdade persistente?
- [ ] O dado é lido com frequência suficiente para justificar cache?
- [ ] Existe uma estratégia clara de invalidação?
- [ ] O TTL está definido?
- [ ] A chave segue o padrão `{prefixo}:{identificador}`?
- [ ] O valor não contém dados sensíveis?
- [ ] O valor não representa estado mutável crítico?
- [ ] A aplicação continua correta caso o cache expire imediatamente?

Se qualquer resposta for negativa, reavalie a necessidade de utilizar Redis.

---

> ⚠️ **Regra crítica:** O Redis é uma camada de aceleração, nunca uma fonte de verdade. Toda informação crítica do Arkion deve permanecer persistida no PostgreSQL. O sistema deve continuar funcional mesmo que todo o conteúdo do Redis seja perdido, restando apenas degradação de desempenho e reprocessamento de cache.
