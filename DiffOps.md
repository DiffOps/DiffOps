# DIFFOPS — MASTER BLUEPRINT v2.0
**Triagem tática, auditoria de segurança e análise preditiva de Pull Requests do GitHub**
*SaaS · TCC · Estética militar-tática (Special Forces / Tactical Ops) · Custo zero de IA*

---

## 1. VISÃO GERAL & MISSÃO

**Codnome:** DiffOps

**Missão:** automatizar a triagem, auditoria de segurança e análise preditiva de Pull Requests em projetos open source do GitHub, com foco em PRs de contribuidores externos, entregando inteligência em tempo real em uma interface de centro de comando (web + mobile).

**Fluxo operacional (resumo):**

```
GitHub Webhook → HMAC (X-Hub-Signature-256) → Redis/Horizon (ProcessIncursionJob)
→ Extração & Sanitização do Diff → Heurística local + IA via OpenRouter (DeepSeek free)
→ Persistência PostgreSQL/Supabase (RLS) → Realtime feed (Web Inertia/React · Mobile Expo)
```

---

## 2. OBJETIVOS & DIFERENCIAIS (banca do TCC)

1. **Auditoria de código com custo zero** — integração com modelos open-weights gratuitos via OpenRouter, com resiliência a limites de rate (fallback de modelos, retry, fila).
2. **Hibridismo Heurística + IA** — camada heurística determinística local (segredos, dependências, arquivos sensíveis) que ancora o *threat score* e atua como fallback quando a IA falha.
3. **Predição de risco de contribuidor** — *Contributor Risk Fingerprint*: score preditivo por autor baseado no histórico (substancia a promessa de "análise preditiva").
4. **Tempo real multiplataforma** — feed tático simultâneo em Web (Inertia/React/Tailwind) e Mobile completo (Expo/NativeWind) via Supabase Realtime.
5. **Rastreabilidade total** — *Combat History* (audit trail imutável) de todas as operações do sistema.
6. **Integração reversa com o GitHub** — Recon Report comentado diretamente na PR via GitHub App.

---

## 3. FEATURES

### 3.1 Core
| # | Feature | Descrição |
|---|---|---|
| C1 | Ingestão por Webhook | GitHub App envia eventos `pull_request`; validação HMAC; fila assíncrona Redis/Horizon |
| C2 | Extração de diff | API GitHub (`vnd.github.v3.diff`), fallback `compare`, tratamento de patches truncados |
| C3 | Sanitização & Chunking | Filtro por caminho/bytes, truncamento de lockfiles, estimativa de tokens, análise parcial com agregação |
| C4 | Heurística local | Secret scanning, downgrade de deps, binários/arquivos sensíveis, detecção de scripts suspeitos |
| C5 | Motor IA (OpenRouter) | DeepSeek free + fallback de modelos, retry/backoff, JSON estrito validado, fallback heurístico |
| C6 | Feed Tático Realtime | Web (Inertia) + Mobile (Expo) com assinatura Realtime e RLS |
| C7 | Autenticação JWT stateless | Supabase Auth unificado (web+mobile), middleware `VerifySupabaseJwt`, guard custom |
| C8 | Gestão de repositórios | Onboarding, ativação, config de segurança por repo |
| C9 | Diff Viewer tático | Visualização estilo terminal com findings ancorados |

### 3.2 Features prioritárias (planejadas — alta)
| # | Feature | Descrição técnica |
|---|---|---|
| F1 | **Recon Report no GitHub** | Após a análise, o backend comenta na PR (`POST /repos/{owner}/{repo}/issues/{number}/comments`) o relatório tático (verdict, threat score, DEFCON, findings e link). Dedupe via tabela `report_comments` (1 comentário por `analysis_id`). Habilitável por repo (`repositories.comment_on_pr`). Permissão do App: `pull_requests: write`. |
| F2 | **Combat History** | Tabela `audit_logs` (append-only via service): ações de triagem, comentários postados, re-scan, registro de repo, mudanças de config. Ponto de auditoria para a banca. |
| F3 | **Battle Briefing (Analytics)** | Endpoint/agregações SQL (verdict por repo, distribuição de threat score em faixas, tendência DEFCON, tempo médio de execução, findings por categoria). Gráficos Recharts no web; KPIs no mobile. |
| F4 | **Contributor Risk Fingerprint** | Serviço `RiskScoringService` recalcula score 0–100 por autor/org ao concluir cada análise (novo contribuidor, razão flagged/hostile, densidade média de findings, total de PRs, recência). Tabela `contributor_risks`. Exibido em cards de PR e detalhe do autor. |

### 3.3 Prioridade média
- **M1 Re-scan manual** — rota autenticada que re-despacha `ProcessIncursionJob` invalidando cache de `head_sha`.
- **M2 Escalação DEFCON** — webhook (Discord/Webhook.site) disparado em verdict `hostile` (`repositories.escalate_on_hostile` + `escalation_webhook_url`).
- **M3 Dossier export** — relatório MD/PDF de uma análise (view renderizada para download).
- **M4 Watchlist tática** — tabela `repo_watchlist`; notificação Realtime de novas análises em repos seguidos.

### 3.4 Prioridade baixa (opcional)
- **B1** Global search; **B2** Rating de PRs do próprio usuário; **B3** Tema "Day Ops" (claro sóbrio).

---

## 4. FLUXO OPERACIONAL (ponta a ponta, 9 passos)

```
┌─────────────┐  POST /api/webhooks/github  ┌─────────────────────────────────┐
│  GitHub App │ ──────────────────────────▶ │ Laravel 12 · ValidateGitHubSignature │
└─────────────┘  X-Hub-Signature-256 (HMAC) └────────────────┬────────────────┘
                                                             │ 200 imediato
                                                             ▼
                                    ┌────────── Redis / Horizon ──────────┐
                                    │  ProcessIncursionJob (retry, backoff)│
                                    └────────────────┬────────────────────┘
                                                     ▼
  1. Upsert pull_request (idempotência por repository_id+pr_number; early-return se head_sha já analisada ou PR fechada)
  2. Busca do diff: GET pulls/{n} (Accept: vnd.github.v3.diff) → se truncado/largo: GET compare/{base}...{head}
  3. Sanitização: filtro por caminho → bytes/linhas → estimativa de tokens → chunking (se >60% do contexto)
  4. Heurística local (determinística, rápida): gera findings + ancora threat_score base
  5. IA OpenRouter: retry 429/5xx com backoff exponencial+jitter, token bucket (Redis), fallback de modelos
  6. Validação do JSON (schema + reparo) → persist analysis + findings (service role)
  7. Pós-análise: RiskFingerprint update, Combat History log, [F1] comentário no GitHub, [M2] escalação se hostile
  8. Mudança no Postgres → Supabase Realtime broadcast → Web/Mobile feed
  9. [F2] Auditoria completa registrada
```

**Garantias:** idempotência (head_sha único), resposta 2xx imediata ao GitHub (evita retry externo), jobs falhos com retentativa Horizon + marcação `failed` + log.

---

## 5. ARQUITETURA & REPOSITÓRIO (padrão Laravel 12)

```text
diffops/
├── app/
│   ├── Console/                 # Kernel, comandos agendados (php artisan make:*)
│   ├── Enums/                   # VerdictEnum, DefconLevelEnum, ThreatCategoryEnum, RepoSecurityLevelEnum
│   ├── Events/                  # AnalysisCompleted, PullRequestReceived, EscalationTriggered
│   ├── Exceptions/              # Handler + exceções customizadas
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/             # WebhookController, AnalyticsController, RescanController, WatchlistController
│   │   │   └── Web/             # DashboardController, IncursionController, RepoController, OperationsLogController
│   │   ├── Middleware/          # ValidateGitHubSignature, VerifySupabaseJwt
│   │   └── Requests/            # Form Requests (validação)
│   ├── Jobs/                    # ProcessIncursionJob
│   ├── Listeners/               # Escutam Events (log Combat History, postar comentário, escalação)
│   ├── Mail/ · Notifications/   # Notificações (se aplicável)
│   ├── Models/                  # Organization, Repository, PullRequest, Analysis, Finding,
│   │                            #   ContributorRisk, AuditLog, RepoWatchlist, ReportComment
│   ├── Policies/                # RBAC commander/operator (policies por recurso)
│   ├── Providers/               # AppServiceProvider, registros de Guards
│   ├── Services/                # GitHub/ (GitHubAppClient, DiffFetcher), OpenRouter/, Risk/
│   └── Support/                 # Helpers/utilitários
├── bootstrap/ · config/ · public/ · storage/
├── database/
│   ├── factories/               # Factories para testes
│   ├── migrations/              # Schema completo + RLS + triggers (via exec/raw)
│   └── seeders/
├── resources/js/                # Inertia/React/Tailwind (design tokens, Tactical UI kit, Pages)
├── routes/                      # web.php · api.php · console.php · channels.php
├── tests/                       # Pest: Unit/ + Feature/ (mockups de tela no front via Vitest)
├── mobile/                      # Expo + NativeWind (theme/tokens.ts, screens, services, navigation)
├── supabase/migrations/         # SQL de extensão: RLS policies, triggers de perfil, Realtime publication
├── .opencode/
│   ├── agent/                   # planner.md · builder.md · tester.md · orchestrator.md
│   └── skills/                  # pipeline/ · tdd/ · diffops-develop/ · git-workflow/
├── AGENTS.md
├── DiffOps-develop.md
├── docker-compose.yml           # php-fpm, nginx, redis, horizon worker
└── opencode.json
```

**Notas:**
- Tudo que o Laravel 12 instala (`app/Http`, `app/Models`, `app/Jobs`, `database/migrations`, `routes`, `tests`, `config`, `bootstrap`, `public`) permanece **como o framework define**; apenas adicionamos conteúdo dentro desses diretórios.
- Subpastas de `Controllers` (`Api/` e `Web/`) e `Services/` são convenções amplamente aceitas no ecossistema Laravel (não desviam do padrão).
- Onde o Laravel não tem suporte nativo (RLS, triggers, Realtime publication), a camada fica isolada em `supabase/migrations/`, fora da árvore PHP.

---

## 6. MATRIZ TECNOLÓGICA

| Camada | Tecnologia | Observação |
|---|---|---|
| Backend | Laravel 12 (PHP 8.3+) | API + Web (Inertia) + Jobs |
| Frontend Web | Inertia.js + React + Tailwind CSS | design tokens táticos |
| Mobile | React Native + Expo + NativeWind | paridade visual com web |
| Fila | Redis + Laravel Horizon | concorrência controlada, retry |
| DB/Auth/Realtime | Supabase Cloud free (PostgreSQL + Auth + Realtime) | projeto: `qkrsrfrlwclzloqjisdr.supabase.co` |
| IA | OpenRouter — `deepseek/deepseek-chat:free` (fallback: qwen/llama free) | custo zero |
| Segurança borda | Cloudflare Free (WAF, rate limit) | opcional |
| Testes | Pest · Vitest + React Testing Library · jest-expo + RNTL | |
| CI | GitHub Actions (lint + testes) | sem deploy automatizado |

---

## 7. DESIGN SYSTEM — TACTICAL OPS

### 7.1 Conceito
Centro de comando militar: preto-asfalto, superfícies metálicas cinza-escuro, bordas técnicas, HUD com cantoneiras, tipografia mono para dados táticos, scanlines sutis, status em verde/âmbar/vermelho.

### 7.2 Paleta (tokens únicos — web e mobile)
| Token | Hex | Uso |
|---|---|---|
| obsidian | `#0a0c10` | Background global |
| asphalt | `#0f1318` | Camadas alternadas |
| plate | `#141a21` | Cards/containers |
| steel | `#1a222b` | Superfícies elevadas/hover |
| graphite | `#24303e` | Bordas/divisores |
| barrel | `#334155` | Ícones/desabilitado |
| bone | `#e2e8f0` | Texto principal |
| dusk | `#94a3b8` | Texto secundário |
| nv-green | `#22c55e` | CLEAR / radar |
| amber | `#f59e0b` | FLAGGED / alerta |
| defcon-red | `#ef4444` | HOSTILE / crítico |
| comms-cyan | `#38bdf8` | Links/telemetria/foco |

### 7.3 Tipografia & textura
- Dados numéricos/labels/badges: JetBrains Mono (mono)
- Corpo/UI: Inter (sans)
- Efeitos: scanlines, cantoneiras HUD (corner brackets), grid pontilhado em vazios, tons planos

### 7.4 Componentes-chave
Badges CLEAR/FLAGGED/HOSTILE · medidor DEFCON segmentado · Threat Meter 0–100 · Diff Viewer terminal (`+` verde / `-` vermelho / contexto cinza) · Stats HUD (mono grande) · Status pills (`scanning` piscante, `completed`, `failed`) · botões (solid metallic, primary nv-green, danger defcon-red) · cards de incursão com cantoneiras.

---

## 8. MODELAGEM DE DADOS (PostgreSQL/Supabase)

### 8.1 Tabelas core (schema base + RLS)
`profiles`, `organizations`, `organization_members` (roles `commander|operator`), `repositories`, `pull_requests`, `analyses`, `analysis_findings` — **todas com RLS ativo** e políticas por membership (apenas SELECT para clientes; escrita exclusiva do Laravel via service role).

### 8.2 Novas colunas e tabelas (features F1–F4 e M2)

```sql
ALTER TABLE repositories ADD COLUMN comment_on_pr BOOLEAN DEFAULT false;
ALTER TABLE repositories ADD COLUMN escalate_on_hostile BOOLEAN DEFAULT false;
ALTER TABLE repositories ADD COLUMN escalation_webhook_url TEXT;

CREATE TABLE report_comments (            -- F1: dedupe 1 comentário por análise
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    analysis_id UUID NOT NULL UNIQUE REFERENCES analyses(id) ON DELETE CASCADE,
    github_comment_id BIGINT,
    created_at TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE audit_logs (                 -- F2: Combat History (append-only)
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES profiles(id) ON DELETE SET NULL,
    action TEXT NOT NULL,
    entity_type TEXT NOT NULL,
    entity_id UUID,
    payload JSONB,
    created_at TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE contributor_risks (          -- F4: Risk Fingerprint por autor/org
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    author_username TEXT NOT NULL,
    score INT NOT NULL CHECK (score BETWEEN 0 AND 100),
    total_prs INT DEFAULT 0,
    flagged_prs INT DEFAULT 0,
    hostile_prs INT DEFAULT 0,
    avg_findings_per_pr NUMERIC(5,2) DEFAULT 0,
    is_new_contributor BOOLEAN DEFAULT true,
    updated_at TIMESTAMPTZ DEFAULT now(),
    UNIQUE(organization_id, author_username)
);

CREATE TABLE repo_watchlist (             -- M4
    user_id UUID NOT NULL REFERENCES profiles(id) ON DELETE CASCADE,
    repository_id UUID NOT NULL REFERENCES repositories(id) ON DELETE CASCADE,
    created_at TIMESTAMPTZ DEFAULT now(),
    PRIMARY KEY (user_id, repository_id)
);

ALTER TABLE audit_logs ENABLE ROW LEVEL SECURITY;          -- + policies por membership
ALTER TABLE contributor_risks ENABLE ROW LEVEL SECURITY;
ALTER TABLE report_comments ENABLE ROW LEVEL SECURITY;
ALTER TABLE repo_watchlist ENABLE ROW LEVEL SECURITY;
```

### 8.3 Triggers
- `sync_profile_on_auth_user_insert`: cria `profiles` quando um `auth.users` é criado.
- `set_updated_at`: atualiza `updated_at` em todas as tabelas.
- Realtime: publicação WAL habilitada para `pull_requests`, `analyses`, `analysis_findings`, `contributor_risks`.

---

## 9. MOTOR DE IA (OpenRouter)

### 9.1 Prompt (revisado — anti prompt-injection)
- Instruções de sistema no topo, diff tratado como **dado, nunca instrução**.
- Schema JSON estrito (verdict, threat_score, defcon_level, flags, findings[]).
- Regra: "responda EXCLUSIVAMENTE com JSON válido, sem markdown".
- `response_format: {"type": "json_object"}` quando suportado.

### 9.2 OpenRouterService (resiliente)
| Mecanismo | Detalhe |
|---|---|
| Retry | 429/5xx/`overloaded` com backoff exponencial + jitter (3 tentativas) |
| Rate limit | Token bucket por modelo no Redis (respeita RPM/TPM do free tier) |
| Circuit breaker | Corta modelo após N falhas em janela; reabre com half-open |
| Fallback de modelos | `deepseek/deepseek-chat:free` → `qwen/qwen-2.5-72b-instruct:free` → `meta-llama/llama-3.3-70b-instruct:free` |
| Timeout | Leitura/Total configurados no Guzzle |
| Cache | Por `head_sha` (evita re-análise e queima de cota) |

### 9.3 Validação e reparo de JSON
1. Parse direto → 2. falha: remover code fences/ruído e reparse → 3. falha: retry único → 4. falha: **fallback 100% heurístico** (análise degradada persistida com `execution_time_ms` e nota no summary).

---

## 10. PIPELINE DE ANÁLISE (detalhe técnico)

### 10.1 Sanitização (serviço de sanitização)
- Excluir por caminho: `vendor/`, `node_modules/`, `dist/`, `build/`, binários, imagens, fontes.
- Truncar lockfiles gigantes (manter cabeçalho + delta de versões afetadas).
- Limite por arquivo (ex.: 300KB) e total (ex.: 60% do contexto do modelo).
- Estimativa de tokens: ~chars/4 (heurística conservadora).
- **Chunking:** se exceder limite → grupos de arquivos por prioridade (core vs test/asset); cada chunk analisado em chamada separada; resultado agregado (max threat, merge de findings, dedupe).

### 10.2 Heurística local (determinística, ~ms)
| Regra | Detecção |
|---|---|
| H1 Secret scan | regex `.env`, chaves privadas (PRIVATE KEY), `sk-`, `AKIA`, `ghp_`, tokens JWT embutidos |
| H2 Downgrade de deps | parse de `composer.json`/`package.json`/lockfiles no diff → versão menor que a atual |
| H3 Arquivos sensíveis | `.env*`, `*.pem`, `credentials*`, `*.sql` com dados |
| H4 Sinais perigosos | `eval(`, `exec(`, `shell_exec`, `curl \| sh`, permissão de arquivo alterada |

Cada regra vira `Finding` e **ancora o threat_score base** (pontuação ponderada) que a IA pode ajustar.

### 10.3 Flush order
Heurística → IA (se disponível) → merge → persist → eventos (F1/F2/F4/M2) → Realtime.

---

## 11. AUTENTICAÇÃO (JWT STATELESS PROFISSIONAL)

1. **Identidade:** Supabase Auth (OAuth GitHub + email/senha). `profiles` referenciam `auth.users`.
2. **Web e Mobile:** supabase-js autentica; token enviado como `Authorization: Bearer <JWT>`.
3. **`VerifySupabaseJwt` (middleware):**
   - Busca JWKS do projeto (cacheado em Redis, com **rotação de chaves**: invalida cache se `kid` desconhecido).
   - Valida: assinatura, `alg` (somente `RS256`), `exp` com **clock skew tolerance** (±30s), `iss`, `aud` se exigido, `sub` presente.
4. **Integração com o framework:** `CustomGuard` + `UserProvider` — `auth()->user()` e funcionalidades do Laravel funcionam em rotas web e API; `Guest`/`Authenticated` corretos.
5. **RBAC:** roles `commander` (tudo) / `operator` (triagem e visualização); verificadas por `Gate`/policy.
6. **Sync lazy de `profiles`:** no primeiro login via JWT, upsert do profile (além do trigger).
7. **Mobile:** refresh token gerenciado pelo SDK do Supabase; 401 → revalidação silenciosa.
8. **Web:** rotas Inertia protegidas por guard; CSRF via cookies do Laravel para rotas web; API stateless.

---

## 12. WEBHOOKS GITHUB

- **GitHub App:** permissão `pull_requests: read/write` (F1) e `contents: read`; eventos: `pull_request` (opened, synchronize, closed, reopened, edited).
- **Rota:** `POST /api/webhooks/github` (excluída de CSRF).
- **Validação HMAC:** `X-Hub-Signature-256` = HMAC-SHA256(body, secret) comparado com `hash_equals` (tempo constante).
- **Idempotência:** chave única `(repository_id, pr_number)`; `analyses` dedup por `(pull_request_id, head_sha)`; early-return para PRs não `open` já analisadas.
- **Resposta:** 200 imediato; falha de validação → 401/422.
- **Registro do repo:** repositórios só são processados se registrados e `is_active` no banco.

---

## 13. FRONTEND WEB (Inertia/React/Tailwind)

| Página | Conteúdo |
|---|---|
| `/` Dashboard | HUD stats (PRs abertas, threat médio, DEFCON atual, tempo médio), feed de incursão em tempo real |
| `/incursions/{id}` | Detail: diff viewer terminal, threat meter, findings ancorados por arquivo, autor + risk fingerprint, ações (rescan, comentar no GitHub) |
| `/repos` | Gestão: registrar repo, ativar/desativar, `comment_on_pr`, `escalate_on_hostile`, webhook url |
| `/operations-log` | Combat History com filtros |
| `/briefing` | Analytics: gráficos (Recharts) |
| `/settings` | Perfil, preferências |
| `/watchlist` | Repos seguidos (M4) |
| Auth | Login Supabase (popup OAuth GitHub) |

Componentes: UI kit `Tactical/` (badges, meters, diff viewer, HUD stats, pills, botões).

---

## 14. MOBILE (Expo + NativeWind) — completo

| Tela | Conteúdo |
|---|---|
| AuthScreen | Supabase SDK + refresh token |
| TacticalFeedScreen | Feed Realtime (RLS), KPI cards, watchlist |
| ThreatDetailScreen | Diff viewer, findings, risk fingerprint, ações |
| RepoManagementScreen | CRUD de repos + config (paridade web) |
| OperationsLogScreen | Combat History filtrado |
| BriefingScreen | KPIs resumidos |
| SettingsScreen | Perfil/preferências |

Tokens idênticos (NativeWind) via `theme/tokens.ts`.

---

## 15. REALTIME & NOTIFICAÇÕES

- **Princípio:** Laravel é o único escritor; clientes só leem. Service role exclusiva no servidor.
- Web e Mobile assinam canais de tabelas com RLS (Supabase Realtime respeita as políticas).
- Feed atualiza ao inserir `analyses`/`findings`; watchlist filtra por repos seguidos.

---

## 16. ESTRATÉGIA DE TESTES (test-first)

| Suíte | Framework | Cobertura mínima |
|---|---|---|
| Backend | Pest | HMAC (tempo constante, body correto/incorreto), JWT (assinatura, expirado, alg errado, rotação), webhook (idempotência, eventos), `OpenRouterService` com `Http::fake` (429, JSON inválido, fallback), heurística (cada regra), `RiskScoringService`, analytics, auth guard/RBAC |
| Web | Vitest + RTL | Mockup de cada tela (contrato visual), componentes do UI kit, feed/detail com dados fake |
| Mobile | jest-expo + RNTL | Telas com dados mockados, auth flow |
| E2E (opcional) | Playwright | Fluxo completo com webhook simulado |

**Regra:** nenhuma feature é considerada pronta sem testes; pipeline bloqueia com vermelho.

---

## 17. PIPELINE DE DESENVOLVIMENTO — AGENTS & SKILLS (guia completo)

### 17.1 Agentes (`.opencode/agent/` — modelo `opencode/deepseek-v4-flash-free`)

| Agente | Modo | Permissões | Responsabilidades | Output formal |
|---|---|---|---|---|
| **PLANNER** | subagent | `edit: deny`, bash read-only | Lê blueprint + `DiffOps-develop.md`; define escopo, mockups, testes red, aceitação | Plano executável (checklists) |
| **BUILDER** | subagent | total | Executa test-first; commits atômicos por unidade; aplica skills `tdd` e `diffops-develop` | Código + testes + commits |
| **TESTER** | subagent | `edit: deny`, bash (suítes) | Roda Pest/Vitest/jest-expo; valida cobertura e regras `tdd` | Laudo APROVADO/REPROVADO com evidências |
| **ORCHESTRATOR** | subagent | bash `git:*` (sem push) | Cria branch, verifica atomicidade do `git log`, ajusta mensagens, prepara resumo | Branch + commits + sumário para aprovação do push |

### 17.2 Skills (`.opencode/skills/`)
| Skill | Gatilho (description) | Regras |
|---|---|---|
| **pipeline** | tarefas de desenvolvimento no DiffOps | Sequência PLANNER → BUILDER → TESTER (loop até green) → ORCHESTRATOR; bloqueios |
| **tdd** | trabalho de feature/tela/backend | Mockup primeiro → testes red → green → refactor; atualizar doc |
| **diffops-develop** | qualquer build concluído | Atualizar/corrigir `DiffOps-develop.md`: mudanças, arquivos, decisões, comandos, desvios; histórico append-only |
| **git-workflow** | commit/branch/push | Naming, conventional commits, atomicidade, push policy |

### 17.3 AGENTS.md (regras globais)
1. Toda tarefa de build segue a pipeline (skill `pipeline`).
2. Test-first obrigatório (skill `tdd`).
3. Commits atômicos e convencionais permitidos sem autorização (skill `git-workflow`).
4. **Push apenas com autorização explícita do usuário.**
5. Manter `DiffOps-develop.md` atualizado (skill `diffops-develop`).
6. **Integração em `main` exclusivamente via Pull Request** (revisada e mergeada pelo usuário).

### 17.4 Fluxo da pipeline
```
PLANNER ──plano──▶ BUILDER ──código+testes──▶ TESTER ──reprovado──▶ BUILDER (correção)
                                                   │ aprovado
                                                   ▼
                                          ORCHESTRATOR ── branch+commits+sumário ──▶ USUÁRIO
                                                                  │ testa / autoriza
                                                                  ▼
                                                              PUSH (somente autorizado)
                                                                  │
                                                                  ▼
                                               ORCHESTRATOR abre a PR (base main) ──▶ USUÁRIO revisa e mergeia
```

### 17.5 Regras de bloqueio
- TESTER não aprova com vermelho.
- BUILDER não pula mockups/testes.
- ORCHESTRATOR não faz push; não commita em `main`; commit "gordo" é dividido.
- **Nenhum agente mergeia em `main`** — merge somente via PR pelo usuário.
- Nenhum agente altera `DiffOps.md` sem aprovação do plano.

---

## 18. GIT WORKFLOW

- **Branch:** `@<user>/<num-sequencial>/<tipo>/<descricao>` — ex.: `@carloseduardo/42/feat/recon-comment`. `num` = contador sequencial de tarefa (sem issue).
- **Tipos:** `feat|fix|chore|docs|refactor|test|perf|style|build|ci`.
- **Commits atômicos (1 unidade lógica = 1 commit, suíte verde):**
  - Controller: `feat(UserController): create controller` → `feat(UserController): add register` → `feat(UserController): add login` → `feat(UserController): add logout`
  - Tela: `feat(TacticalFeed): build header` → `feat(TacticalFeed): build menu` → `feat(TacticalFeed): build body`
  - `fix(VerifySupabaseJwt): handle key rotation`
- Convencionais com escopo (`feat|fix(scope): subject`), `!` para breaking change, corpo quando necessário.
- **Push:** apenas com autorização; após o push, ORCHESTRATOR abre a **Pull Request** (base `main`); **merge em `main` exclusivamente via PR** revisada e mergeada pelo usuário (proibido merge local direto por agentes).

---

## 19. AMBIENTE, DEPLOY & INFRA

- **Local:** docker-compose (php-fpm, nginx, redis, horizon worker) + Supabase Cloud como fonte única (evita divergência de Realtime).
- **Variáveis `.env`:** `APP_URL`, `DB_*` (Supabase `qkrsrfrlwclzloqjisdr`), `SUPABASE_SERVICE_ROLE_KEY` (somente servidor), `SUPABASE_JWT_SECRET`/JWKS, `GITHUB_APP_*` (id, client secret, private key, webhook secret), `OPENROUTER_API_KEY`. **Nunca no repo/docs.**
- **Deploy TCC:** VPS barata (ou Railway/Render) + Upstash Redis + Cloudflare free; URL pública para webhook (GitHub App exige HTTPS).
- **Segurança:** service role só no backend; RLS protegendo leitura; Cloudflare WAF; rotação de segredos documentada.

---

## 20. ROADMAP (fases com entregáveis)

| Fase | Entregáveis | Aceitação |
|---|---|---|
| **A** Mecanismos | Agent files, 4 skills, AGENTS.md, opencode.json, `DiffOps-develop.md`, `DiffOps.md` (este) | opencode reiniciado; pipeline funcional |
| **B** Fundação | Laravel 12 + Inertia/Tailwind scaffold, docker-compose, migrations + RLS + triggers, models/enums + testes | `php artisan test` verde |
| **C** Auth | VerifySupabaseJwt, guard/provider, RBAC, sync profiles, fluxo web+mobile + testes | login web e mobile ok |
| **D** Ingestão | GitHub App, webhook HMAC, `ProcessIncursionJob` + testes (`Http::fake`) | PR real processada |
| **E** Análise | Sanitização, HeuristicAuditor, OpenRouterService (fallback/retry), F4 RiskFingerprint + testes | análise E2E com IA free |
| **F** Web UI | Command Center completo (mockups→testes→implementação) | telas testadas |
| **G** Mobile | App Expo completo com NativeWind | paridade web |
| **H** Integrações | F1 Recon Comment, F2 Combat History, F3 Briefing, M1–M4, Realtime, CI | tudo verde + demo |

---

## 21. RISCOS & MITIGAÇÕES

| Risco | Mitigação |
|---|---|
| Rate limit IA free (429/pico) | Retry+backoff, token bucket, fallback de modelos, cache por head_sha |
| Diff grande > contexto | Sanitização, truncamento, chunking + agregação |
| JSON inválido da LLM | Reparo, retry, fallback heurístico |
| GitHub API truncando patches | Fallback `compare` + aceitar diff por arquivo |
| Ambiguidade auth | Decisão fechada: JWT stateless + guard custom |
| Dupla via de escrita | Laravel único escritor; service role só servidor |
| Prompt injection em diffs | Diff como dado; JSON estrito |
| Realtime local divergente | Supabase Cloud único em todos ambientes |
| Escopo mobile grande | Paridade por telas; testes por mockup |

---

## 22. GLOSSÁRIO (militar ↔ técnico)

| Militar | Técnico |
|---|---|
| Incursão / Target Vector | Pull Request |
| Tactical Debrief / Recon Report | Análise gerada (Analysis) |
| CLEAR / FLAGGED / HOSTILE | Veredictos (`clear|flagged|hostile`) |
| DEFCON 1–5 | Severidade/risco |
| Threat Score | Score 0–100 |
| Combat History | Audit trail (`audit_logs`) |
| Battle Briefing | Analytics |
| Recon Report no GitHub | Comentário da App na PR |
| Contribuidor hostil | Autor com risco elevado (fingerprint) |

---

## 23. DOCUMENTOS RELACIONADOS
`DiffOps-develop.md` (vivo) · `.opencode/agent/*` · `.opencode/skills/*` · `AGENTS.md` · `opencode.json` · `supabase/migrations/*`
