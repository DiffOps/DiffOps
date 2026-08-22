# Phase F Plan — Web UI (Command Center) Inertia/React/Tailwind

## 1. Escopo e Unidades de Trabalho

| Unidade | Entregável | Páginas/Rotas | Componentes Principais |
|---|---|---|---|
| F-1 | **Design Tokens & UI Kit Base** | — | `resources/js/theme/tokens.ts`, `resources/js/components/Tactical/` (Badge, Meter, DiffViewer, HUDStat, Pill, Button, Card, StatusPill) |
| F-2 | **Layout & Auth Guards** | `/login`, `/register`, `/` (redirect) | `Layout.jsx` (HUD shell, sidebar, header), `VerifySupabaseJwt` middleware nas rotas web, `auth.php` guard `web` + Inertia SSR |
| F-3 | **Dashboard — Tactical HUD** | `GET /` | Stats cards (PRs abertas, threat médio, DEFCON atual, tempo médio), `IncursionFeed` realtime (Supabase Realtime via `channel`), loading states, empty states |
| F-4 | **Incursion Detail — Recon Report** | `GET /incursions/{analysis}` | `DiffViewer` terminal-style (+ verde / - vermelho / contexto cinza), `ThreatMeter` 0-100, `DefconMeter` 1-5, `FindingsList` ancorado por arquivo, `AuthorRiskCard` (fingerprint F4), actions: Rescan, Comment on GitHub (F1) |
| F-5 | **Repositories Management** | `GET /repos`, `POST /repos`, `PATCH /repos/{id}`, `DELETE /repos/{id}` | Table/Grid de repos, toggle `is_active`, `comment_on_pr`, `escalate_on_hostile`, webhook URL, install GitHub App link |
| F-6 | **Operations Log — Combat History** | `GET /operations-log` | Tabela filtrável (ação, entidade, usuário, período), paginação, export CSV |
| F-7 | **Briefing — Analytics** | `GET /briefing` | Recharts: verdict distribution, threat score histogram, DEFCON trend, avg execution time, findings by category |
| F-8 | **Watchlist & Settings** | `GET /watchlist`, `GET /settings` | Watchlist: cards de repos seguidos com status realtime; Settings: perfil, preferências (tema, notificações) |
| F-9 | **Supabase Realtime Integration** | — | `useRealtime` hook, channels: `analyses`, `pull_requests`, `analysis_findings`, `contributor_risks`; RLS-aware subscriptions |

## 2. Mockups (Descrição Textual)

### F-1: Design Tokens (theme/tokens.ts)
- Exporta objeto `tokens` com: `colors` (paleta 7.2), `fontMono`, `fontSans`, `spacing`, `radius`, `shadows`, `breakpoints`, `zIndex`.
- `Tactical` components importam de `@/theme/tokens`.

### F-2: Layout (`resources/js/Layouts/TacticalLayout.jsx`)
- **Shell**: `min-h-screen bg-obsidian text-bone font-sans`
- **Sidebar** (fixa `w-64 bg-asphalt border-r-graphite`): logo DiffOps, navegação (Dashboard, Incursões, Repositórios, Combat History, Briefing, Watchlist, Settings), user avatar dropdown (logout).
- **Header** (sticky `h-16 bg-plate border-b-graphite`): breadcrumbs, status de conexão realtime (pill pulsing `nv-green` / `defcon-red`), relógio UTC mono.
- **Main**: `flex-1 p-6 overflow-auto` — slot para page props.
- **Mobile**: sidebar vira drawer (hamburger), header compacto.

### F-3: Dashboard (`resources/js/Pages/Dashboard.jsx`)
- **Grid 4 cols** (xl) / 2 (md) / 1 (sm): `HUDStat` cards
  - `totalOpenPRs` (ícone git-pull-request, valor mono grande)
  - `avgThreatScore` (ícone gauge, cor dinâmica: clear/amber/defcon-red)
  - `currentDefcon` (badge DEFCON 1-5 segmentado)
  - `avgExecutionTimeMs` (ícone clock, mono)
- **IncursionFeed** (full width below): lista de `IncursionRow` (real-time)
  - Colunas: timestamp (mono), repo/pr# (link), author (avatar + username), verdict badge, threat score (meter mini), defcon badge, tempo exec.
  - Estados: `scanning` (pulsing spinner), `completed`, `failed`.
  - WebSocket: `supabase.channel('analyses').on('INSERT', ...)` — insere no topo, anima slide-down.

### F-4: Incursion Detail (`resources/js/Pages/IncursionDetail.jsx`)
- **Header**: repo/pr#, author (avatar + risk fingerprint badge), verdict badge grande, threat meter circular 0-100, defcon meter segmentado 1-5, timestamp UTC.
- **Tabs**: [Diff Viewer] [Findings] [Risk Fingerprint] [Raw JSON]
- **DiffViewer** (tab ativa): container `font-mono bg-obsidian border-graphite`
  - Linhas: `+` → `text-nv-green`, `-` → `text-defcon-red`, contexto → `text-dusk`
  - Findings inline: marcador `▸` na gutter com tooltip (category + severity), click → scroll para finding na aba Findings.
  - Virtualized list para diffs grandes (`react-window` ou implementação simples).
- **FindingsList**: grouped by file_path; cada finding: badge severity, category, description, link para linha no diff.
- **AuthorRiskCard**: score 0-100 (circular progress), total PRs, flagged/hostile counts, avg findings/PR, is_new_contributor badge.
- **Actions bar** (sticky bottom): [Rescan] (POST /api/incursions/{id}/rescan), [Comment on GitHub] (se `repo.comment_on_pr` e permissão) — POST /api/incursions/{id}/comment.

### F-5: Repositories (`resources/js/Pages/Repositories.jsx`)
- **Toolbar**: [Add Repository] button (modal), filter by org, search.
- **Table**: cols: Name (link to GitHub), Owner, Active (toggle), Comment on PR (toggle), Escalate Hostile (toggle + webhook URL input), Webhook Status (pill: connected/pending/error), Last Incursion (timestamp), Actions (Edit, Delete).
- **Add/Edit Modal**: form com fields: `github_repo_id` (hidden, preenchido via GitHub API search), `name`, `full_name`, `owner_login`, `is_active`, `comment_on_pr`, `escalate_on_hostile`, `escalation_webhook_url`, `security_level` (select: standard/elevated/critical).
- **Webhook helper**: mostra URL pública esperada (`{APP_URL}/api/webhooks/github`) e secret configurado.

### F-6: Operations Log (`resources/js/Pages/OperationsLog.jsx`)
- **Filters toolbar**: date range, action type select, entity type select, user select, text search.
- **Table**: Timestamp (mono), Action (badge), Entity (type + id link), User (avatar + name), Payload (expandable JSON viewer mono).
- **Pagination**: server-side (Inertia `Link::paginate`).
- **Export CSV** button (GET /operations-log/export).

### F-7: Briefing (`resources/js/Pages/Briefing.jsx`)
- **Tabs**: [Overview] [Trends] [Heatmap]
- **Overview** (4 cards + 2 charts):
  - Verdict distribution: donut chart (Recharts `PieChart`) — clear/flagged/hostile
  - Threat score histogram: `BarChart` buckets 0-10, 10-20...
- **Trends**: `LineChart` — DEFCON médio por dia (últimos 30d), tempo médio execução por dia.
- **Heatmap**: `Heatmap` (custom ou `Recharts` `Treemap`) — findings por categoria x repo.
- **Date range picker** global (últimos 7/30/90 dias).

### F-8: Watchlist & Settings
- **Watchlist**: grid de `RepoWatchCard` — repo name, last incursion status, toggle follow/unfollow, realtime pill.
- **Settings**: 2 sections: Profile (name, avatar, email — read from Supabase profile), Preferences (theme: tactical-only, notifications: email/push toggles — stubbed).

### F-9: Realtime Hook
- `useRealtime(channel, filters)` → retorna `data[]`, `status` (connecting/connected/disconnected/error).
- Subscriptions por organização (RLS): `supabase.channel(`org:${orgId}:analyses`)`.
- Auto-reconnect com backoff.

## 3. Testes Red Esperados

### Backend (Pest) — `tests/Feature/Web/`
| Teste | Descrição |
|---|---|
| `DashboardControllerTest` | GET / retorna 200 com props esperadas (stats, feed vazio); middleware `verify.supabase.jwt` bloqueia não-autenticado |
| `IncursionDetailControllerTest` | GET /incursions/{id} retorna 200 com analysis + findings + pull_request + risk_fingerprint; 404 se não existe; 403 se org diferente |
| `RepositoriesControllerTest` | CRUD completo: create (valida GitHub repo existe), read (lista paginada), update (toggles), delete; autorização commander/operator |
| `OperationsLogControllerTest` | GET com filtros (date, action, entity, user), paginação, export CSV |
| `BriefingControllerTest` | GET retorna agregações SQL corretas (verdict dist, threat histogram, defcon trend) |
| `WatchlistControllerTest` | toggle follow/unfollow, lista com realtime status |
| `AuthControllerTest` (web) | login via Inertia (redirect to Supabase OAuth), logout, guest middleware |

### Frontend (Vitest + RTL) — `resources/js/__tests__/`
| Teste | Descrição |
|---|---|
| `TacticalLayout.test.jsx` | renderiza sidebar, header, slot; navegação funciona; responsive drawer mobile |
| `HUDStat.test.jsx` | renderiza valor mono, ícone, cor dinâmica por props |
| `IncursionFeed.test.jsx` | insere item no topo ao receber evento realtime (mock); animação; estados scanning/completed/failed |
| `DiffViewer.test.jsx` | renderiza linhas +/ -/ contexto com cores corretas; findings inline markers; virtualização |
| `ThreatMeter.test.jsx` | arco circular 0-100 com cor por faixa; label mono central |
| `DefconMeter.test.jsx` | 5 segmentos, preenchidos até nível, cor por nível |
| `VerdictBadge.test.jsx` | CLEAR/FLAGGED/HOSTILE com cores nv-green/amber/defcon-red |
| `RepositoriesTable.test.jsx` | toggles disparam PATCH; modal create/edit valida; delete confirma |
| `OperationsLogFilters.test.jsx` | filtros atualizam URL query params; paginação funciona |
| `BriefingCharts.test.jsx` | Recharts renderiza com dados mock; responsive container |
| `useRealtime.test.ts` | hook conecta, recebe INSERT/UPDATE/DELETE, limpa ao unmount |

### Testes de Contrato Visual (Storybook opcional)
- Cada componente `Tactical/*` com stories para estados: default, loading, error, empty, variant.

## 4. Critérios de Aceitação

1. **Todas as rotas web protegidas** — `verify.supabase.jwt` middleware ativo; guest redireciona para `/login`.
2. **Design System aplicado** — zero cores hardcoded; todos componentes usam `tokens`; scanlines/cantoneiras visuais nos cards.
3. **Realtime funcional** — feed do dashboard atualiza sem refresh ao inserir analysis via job (teste E2E com `Http::fake` + broadcast).
4. **Diff Viewer performático** — diffs de 5000+ linhas renderizam < 200ms (virtualização).
5. **Acessibilidade** — semantic HTML, focus-visible, ARIA labels nos meters/badges, contraste AA (tokens já garantem).
6. **Testes verdes** — Pest backend 100% pass; Vitest frontend 100% pass; cobertura mínima 80% nas novas features.
7. **Build produção** — `npm run build` exit 0, assets manifest gerado, Vite manifest no `public/build`.
8. **Inertia SSR opcional** — se habilitado, primeira carga renderiza HTML (não bloqueante para MVP).

## 5. Decisões Técnicas (Definidas pelo Usuário)

| Decisão | Escolha | Justificativa |
|---|---|---|
| **Inertia SSR** | **Sim** — ativar `ssr: true` + `php artisan inertia:start-ssr` | First paint/SEO; complexidade aceita |
| **Charts** | **Recharts** | React nativo, declarativo, citado no blueprint |
| **Global State** | **Zustand** | Leve (~1kb), API simples, não requer provider |
| **Ícones** | **lucide-react** | Tree-shakable, SVG, ~500 ícones, estilo técnico |
| **Virtualização DiffViewer** | **react-window** | Robusto, 2.5kb gz, padrão da indústria |

**Dependências a adicionar no `package.json`:**
```json
{
  "dependencies": {
    "lucide-react": "^0.400.0",
    "recharts": "^2.12.0",
    "zustand": "^4.5.0",
    "react-window": "^1.8.10"
  },
  "devDependencies": {
    "@types/react-window": "^1.8.8"
  }
}
```

---

## 6. Ordem de Execução Sugerida (BUILDER)

```
F-1  → Design Tokens & UI Kit (componentes atômicos)
F-2  → Layout + Auth Guards + Rotas base
F-3  → Dashboard (HUD + Feed realtime)
F-4  → Incursion Detail (DiffViewer + Findings + Risk)
F-5  → Repositories CRUD
F-6  → Operations Log
F-7  → Briefing (Charts)
F-8  → Watchlist + Settings
F-9  → Integração Realtime completa + Polish
```

Cada unidade = 1 commit atômico (conventional) com testes daquela unidade. TDD: mockup → testes red → implementação green → refactor.

## 7. Comandos de Validação

```bash
# Backend
vendor/bin/pest --filter=Web
vendor/bin/pest --filter=Auth

# Frontend
npm run test           # vitest
npm run build          # vite build production
npm run lint           # eslint (se configurado)

# Typecheck (se TypeScript — não é o caso, JSX)
```

---

**Pronto para aprovação do usuário.** Após aprovação, o BUILDER inicia pela F-1.