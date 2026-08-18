# DiffOps

SaaS de **triagem tática, auditoria de segurança e análise preditiva de Pull Requests** do GitHub (Trabalho de Conclusão de Curso).

O DiffOps audita cada PR antes do merge: analisa o diff com uma combinação de heurísticas locais e IA, classifica o risco em **CLEAR / FLAGGED / HOSTILE**, aplica o protocolo **DEFCON 1–5** e gera relatórios de triagem (Tactical Debrief) — com escalonamento automático e histórico auditável (Combat History).

## Stack

| Camada | Tecnologia |
|---|---|
| Backend | Laravel 12 · PHP 8.3 · Pest |
| Frontend | Inertia.js · React 19 · Tailwind CSS 4 · Vite 7 |
| Banco de dados | PostgreSQL (Supabase Cloud) — SQLite em testes |
| Auth | JWT stateless via Supabase |
| Fila/Cache | Redis · Laravel Horizon |
| Infra | Docker Compose · GitHub Actions (CI) |

## Pré-requisitos

**Caminho recomendado (Docker):**
- Docker Engine 24+ · Docker Compose v2+

**Caminho alternativo (host):**
- PHP 8.3+ com extensões `pdo_sqlite`, `pdo_pgsql`, `pcntl`
- Composer 2.x · Node.js 24+

## Setup rápido (Docker)

```bash
# 1. Ambiente
cp .env.example .env
#    → preencha DB_PASSWORD (credencial do Supabase; nenhum segredo é versionado)

# 2. Suba a stack (app + nginx :8080 + node assets + redis + horizon)
docker compose up -d --build

# 3. Acesse
#    http://localhost:8080

# 4. Testes (dentro do container)
docker compose exec app php artisan test
```

## Setup local (host)

```bash
composer install
cp .env.example .env && php artisan key:generate
npm ci && npm run build
php artisan serve          # http://localhost:8000
```

## Testes

```bash
php artisan test               # suíte completa (offline, SQLite :memory:)
php artisan test --group=rls   # contrato de políticas RLS (3 behavior exigem pgsql → skipped)
vendor/bin/pint --test         # gate de estilo
```

A suíte roda **100% offline** — sem credenciais, sem rede. O CI valida todo push e toda PR.

## Arquitetura de dados

16 migrations versionadas (`database/migrations/`), executáveis em SQLite (testes) e PostgreSQL (Supabase):

- **7 tabelas core**: `organizations`, `users` (perfil tático + `supabase_uid`), `organization_members`, `pull_requests`, `pull_request_files`, `risk_assessments`, `ai_decisions`
- **5 tabelas de features**: `repositories`, `report_comments`, `audit_logs`, `contributor_risks`, `repo_watchlist`
- **4 migrations RLS/triggers/Realtime** (pgsql-only, guardadas por driver — no-op no SQLite)

Princípios:

- **UUID gerado no app** (sem defaults pgsql-only) — portabilidade total entre drivers
- **RLS com SELECT-only para clientes** (web/mobile via JWT) — escrita exclusiva do Laravel (service role); políticas por membership da organização ou commander global, sempre via `users.supabase_uid`
- **Append-only** em registros imutáveis: `ai_decisions`, `audit_logs`, `report_comments`, `repo_watchlist` (sem `updated_at`)
- **Enums PHP** (domínio no código, não no banco) + constraints CHECK (ex.: `security_score` 0–100, DEFCON 1–5)

## Fluxo de desenvolvimento

Toda tarefa segue a **pipeline**: `PLANNER → BUILDER → TESTER → ORCHESTRATOR`.

- Branches no padrão `@<user>/<num>/<tipo>/<descricao>` (ex.: `@carlosegoulart/06/feat/feature-tables-migrations`)
- **Commits atômicos** em Conventional Commits (1 unidade lógica = 1 commit, suíte verde)
- **Toda PR exige descrição** apresentando as alterações (resumo, escopo, validação, como testar)
- **Merge em `main` exclusivamente via Pull Request** — revisado e mergeado pelo usuário; CI deve passar
- Test-first obrigatório: mockup → testes red → implementação green → refactor

## CI (GitHub Actions)

Um job auto-contido roda em todo push e toda PR: PHP 8.3 + Composer → Node 24 + build de assets → `pint --test` → suíte Pest (offline). Sem credenciais no CI.

## Documentação do projeto

| Arquivo | Papel |
|---|---|
| `DiffOps.md` | Blueprint master — visão, arquitetura, features, decisões |
| `DiffOps-develop.md` | Documento vivo — estado real do desenvolvimento (histórico append-only) |
| `AGENTS.md` | Regras globais de desenvolvimento do projeto |
| `.opencode/` | Agentes e skills da pipeline (PLANNER, BUILDER, TESTER, ORCHESTRATOR) |

## Status do desenvolvimento

- **Fase A** (fundações) — concluída: blueprint, agentes/skills, regras
- **Fase B** (fundação técnica) — U1–U8 entregues:
  - U1 scaffold Laravel 12 · U2 Inertia/React/Tailwind · U3 ambiente Supabase · U4 stack Docker + Horizon
  - U4b CI · U5 migrations core (7 tabelas) · U6 RLS/triggers/Realtime + Enums + Models core
  - U7 migrations das tabelas de features + RLS · U8 models das tabelas de features
- **Próxima**: U9 — autenticação JWT Supabase (`VerifySupabaseJwt`) + RBAC