# DiffOps-develop.md — Documento Vivo de Desenvolvimento

> Este documento é a **fonte da verdade do estado atual do desenvolvimento** do DiffOps.
> Mantido obrigatoriamente pela skill `diffops-develop` após todo trabalho de build.
> O blueprint master (visão, arquitetura, features, decisões) vive em `DiffOps.md`.

---

## 1. STATUS DO PROJETO

| Campo | Valor |
|---|---|
| Fase atual | **B — Fundação** (em andamento — U2 Inertia/React/Tailwind concluída) |
| Próxima fase | U3: config/DB · U4: docker-compose + migrations |
| Stack | Laravel 12 · Inertia/React/Tailwind · Expo/NativeWind · Supabase Cloud · Redis/Horizon · OpenRouter |
| Supabase | projeto `qkrsrfrlwclzloqjisdr.supabase.co` (DB + Auth + Realtime) |
| Testes | Pest · Vitest/RTL · jest-expo/RNTL |
| Pipeline | PLANNER → BUILDER → TESTER → ORCHESTRATOR (skill `pipeline`) |

**Checklist funcional (concluído ✅ / pendente ⬜):**
- ✅ Scaffold Laravel 12 + Inertia/React + Tailwind
- ⬜ docker-compose (php-fpm, nginx, redis, horizon)
- ⬜ Migrations + RLS + triggers
- ⬜ Autenticação JWT (VerifySupabaseJwt, guard, RBAC)
- ⬜ GitHub App + webhook HMAC + ProcessIncursionJob
- ⬜ Sanitização + Heurística + OpenRouterService
- ⬜ Web UI Command Center
- ⬜ Mobile Expo completo
- ⬜ F1 Recon Comment · F2 Combat History · F3 Briefing · F4 Risk Fingerprint
- ⬜ M1–M4 · Realtime · CI

---

## 2. SETUP DO AMBIENTE

### Credenciais (.env — NUNCA versionar)
- `DB_*` → Supabase `qkrsrfrlwclzloqjisdr` (host `db.qkrsrfrlwclzloqjisdr.supabase.co`)
- `SUPABASE_SERVICE_ROLE_KEY` → **somente no servidor** (nunca em cliente web/mobile)
- `SUPABASE_URL` → `https://qkrsrfrlwclzloqjisdr.supabase.co`
- `GITHUB_APP_ID` / `GITHUB_APP_CLIENT_SECRET` / `GITHUB_APP_PRIVATE_KEY` / `GITHUB_WEBHOOK_SECRET`
- `OPENROUTER_API_KEY`
- `APP_URL` (HTTPS obrigatório para webhook do GitHub)

### Comandos úteis
- Scaffold Laravel 12: `composer create-project laravel/laravel:^12.0 /tmp/scaffold --prefer-dist --no-interaction` (PHP 8.3.6 · Composer 2.10.2 locais)
- Dependências: `composer install` · `npm install && npm run build` (Vite 7 → `public/build/manifest.json`)
- Testes: `php artisan test` (Pest 3.8 via plugin-laravel 3.2)
- Observação local: **`pdo_pgsql` e `pdo_sqlite` NÃO instalados** no PHP local (U3/U4: configurar via docker ou instalar ext)

### Serviços locais
- docker-compose: php-fpm, nginx, redis, horizon worker (a definir na U4)
- Extensões PHP ausentes localmente: pdo_pgsql, pdo_sqlite (impacta `migrate` local até a containerização)

---

## 3. DECISÕES DE ARQUITETURA (rationale para a banca)

| # | Decisão | Rationale |
|---|---|---|
| D1 | **Auth JWT stateless** (Supabase Auth + VerifySupabaseJwt) em web e mobile | Mesma identidade nos dois clientes; sem sincronização frágil de sessão Laravel↔Supabase; Laravel stateless |
| D2 | **Supabase Cloud free como fonte única** (DB+Auth+Realtime) em todos os ambientes | Evita divergência de Realtime/RLS entre local e produção |
| D3 | **Laravel é o único escritor**; service role exclusiva no servidor; clientes só leem (API/Realtime) | Evita corridas de escrita, inconsistência e vazamento de chave |
| D4 | **Heurística local como ancora + fallback da IA** | Determinística e barata; sustenta análise quando a IA falha/limita |
| D5 | **Arquitetura 100% padrão Laravel 12** (diretórios do scaffold; Api/ e Web/ só dentro de Controllers; Services/ por convenção) | Facilita manutenção e defesa do TCC; nada de estrutura customizada |
| D6 | **Pipeline 4 agentes + skills** (planner/builder/tester/orchestrator) | Qualidade por etapas; testes sempre antes do código |
| D7 | **Git**: branches `@user/num/tipo/desc`; commits atômicos convencionais; push só com aprovação do usuário | Rastreabilidade da evolução; atomicidade = 1 unidade lógica por commit |
| D8 | **Design System TACTICAL OPS** com tokens compartilhados (Tailwind/NativeWind) | Paridade visual web/mobile com identidade militar-tática |

---

## 4. MÓDULOS IMPLEMENTADOS

> Preenchido pela skill `diffops-develop` a cada entrega. Formato:
> **Módulo** — arquivos-chave — como testar — observações

- **Scaffold Laravel 12 (U1 Fase B)** — árvore oficial `laravel/laravel 12.66.0` no root do repo (composer create-project via tar; `.gitignore` raiz e internos recriados do scaffold oficial); Pest 3.8 instalado (substitui PHPUnit como runner padrão); smoke test em `tests/Feature/ScaffoldSmokeTest.php` (boot 12.x, GET / 200, manifest.json do Vite). Como testar: `php artisan test` → 5 testes verdes.
- **Integração Inertia/React/Tailwind (U2 Fase B)** — `inertiajs/inertia-laravel` ^3.3 (composer) + `@inertiajs/react` 3.6.1 + `@vitejs/plugin-react` 5.2.0 + React 19.2.8 (npm); stack: `resources/views/app.blade.php` (root `@inertia` + `@vite`), `resources/js/app.jsx` (bootstrap Inertia), `resources/js/Pages/Welcome.jsx` (header mínimo TACTICAL OPS: título `DiffOps` + subtítulo pt-BR, paleta obsidian/asphalt/bone/comms-cyan), `app/Http/Middleware/HandleInertiaRequests.php` registrado no grupo `web` em `bootstrap/app.php`, `config/inertia.php` publicado (path ajustado para `resources/js/Pages`), `routes/web.php` → `Inertia::render('Welcome', ['appName' => 'DiffOps'])`, Tailwind 4 já vinha no skeleton (`@import "tailwindcss"` + plugin `@tailwindcss/vite`; adicionado `@source '../**/*.jsx'`). Como testar: `php artisan test` → 7 testes verdes (smoke + 2 Inertia).

---

## 5. DESVIOS DO BLUEPRINT

> Registrar aqui TODA divergência entre o código real e o `DiffOps.md`, com motivo.

- **`php artisan inertia:install react` não existe no inertia-laravel v3** (comando removido) → instalação manual equivalente: `php artisan inertia:middleware` + registro do `HandleInertiaRequests` no grupo `web` + npm `@inertiajs/react` + `@vitejs/plugin-react` + `vite.config.js` com plugin react e input `resources/js/app.jsx` + `app.blade.php` + `Welcome.jsx` (o plano já previa este caminho alternativo).

---

## 6. ARMADILHAS & APRENDIZADOS

> Registro contínuo de problemas encontrados e soluções (rate limits, config, bugs).

- **`rsync` ausente** no ambiente → substituído por `tar -C <src> -cf - . | tar -C <dest> -xf -` com os mesmos `--exclude` (Fase A intacta, verificado via `git diff`).
- **`create-project` não entrega os `.gitignore` internos** (bootstrap/cache, database, storage/*) → sem eles, `packages.php`/`services.php`/`database.sqlite`/views compiladas seriam versionados. Recriados manualmente a partir do repo oficial `laravel/laravel@12.x`.
- **Scaffold Laravel 12 usa PHPUnit**; para Pest é preciso `composer require pestphp/pest pestphp/pest-plugin-laravel --dev` e criar `tests/Pest.php` manualmente (`php artisan pest:install` não existe no plugin-laravel 3.2).
- **Driver sqlite ausente localmente** → `php artisan migrate` falha no host (aviso `could not find driver`) durante o create-project; irrelevante para testes (`:memory:` só é usado se houver DB, e os testes U1 não tocam banco).
- **`@vitejs/plugin-react@6` exige Vite 8** (peer dep) mas o skeleton usa Vite 7 → fixar `@vitejs/plugin-react@^5` (5.2.0), compatível com Vite 7.
- **Path das páginas Inertia no config publicado é `resource_path('js/pages')` (minúsculo)** e o dir real é `resources/js/Pages` (maiúsculo, convenção React) → `assertInertia` falhava com "page component does not exist" até ajustar o path no `config/inertia.php` publicado.

---

## 7. PRÓXIMOS PASSOS (backlog priorizado)

1. **Fase B** — Scaffold Laravel 12 + Inertia/React/Tailwind + docker-compose + migrations/RLS/triggers + models/enums (test-first via pipeline)
2. Fase C — Autenticação JWT profissional
3. Fase D — Ingestão GitHub
4. Fase E — Pipeline de análise (heurística + IA)
5. Fase F — Web UI
6. Fase G — Mobile
7. Fase H — F1–F4, M1–M4, Realtime, CI

---

## 8. HISTÓRICO DE MUDANÇAS (append-only)

| Data | O que mudou | Quem/Agente | Fase |
|---|---|---|---|
| 2026-08-15 | Fase B U2 concluída (branch `@carlosegoulart/02/feat/foundation`, commit `1ab382d`): Inertia Laravel ^3.3 + React 19 + Tailwind 4 integrados; app.blade.php root, app.jsx bootstrap, Welcome.jsx (header TACTICAL OPS `DiffOps`), HandleInertiaRequests no grupo web, config/inertia.php publicado (path `resources/js/Pages`), rota `/` → Inertia::render('Welcome', appName='DiffOps'); smoke suite ampliada para 7 testes verdes (2 Inertia novos); desvio: `inertia:install` removido no v3 → instalação manual | opencode (builder) | B |
| 2026-08-15 | Fase B U1 concluída: scaffold Laravel 12.66 no root (branch `@carlosegoulart/02/feat/foundation`, commits 8d134b7 + 7dcbaf3); Pest 3.8 + tests/Pest.php; ScaffoldSmokeTest verde (5 testes); pdo_pgsql/pdo_sqlite ausentes localmente; rsync→tar por indisponibilidade | opencode (builder) | B |
| 2026-08-15 | Fase A concluída: branch `@carlosegoulart/01/chore/setup-foundations` com 5 commits atômicos (DiffOps.md v2.0, DiffOps-develop.md, agentes, skills, AGENTS.md+opencode.json); identidade git local: CarlosEGoulart / goulart193@gmail.com | opencode (build) | A |
| 2026-08-15 | Criação do documento vivo; definição de Fase A (mecanismos: agentes, skills, AGENTS.md, opencode.json); blueprint final gravado em DiffOps.md | opencode (build) | A |
