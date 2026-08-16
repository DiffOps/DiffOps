# DiffOps-develop.md — Documento Vivo de Desenvolvimento

> Este documento é a **fonte da verdade do estado atual do desenvolvimento** do DiffOps.
> Mantido obrigatoriamente pela skill `diffops-develop` após todo trabalho de build.
> O blueprint master (visão, arquitetura, features, decisões) vive em `DiffOps.md`.

---

## 1. STATUS DO PROJETO

| Campo | Valor |
|---|---|
| Fase atual | **B — Fundação** (em andamento — U4 docker-compose concluída) |
| Próxima fase | U5: migrations + RLS + triggers |
| Stack | Laravel 12 · Inertia/React/Tailwind · Expo/NativeWind · Supabase Cloud · Redis/Horizon · OpenRouter |
| Supabase | projeto `qkrsrfrlwclzloqjisdr.supabase.co` (DB + Auth + Realtime) |
| Testes | Pest · Vitest/RTL · jest-expo/RNTL |
| Pipeline | PLANNER → BUILDER → TESTER → ORCHESTRATOR (skill `pipeline`) |

**Checklist funcional (concluído ✅ / pendente ⬜):**
- ✅ Scaffold Laravel 12 + Inertia/React + Tailwind
- ✅ Config ambiente: Supabase Postgres (pgsql + SSL) + suíte de teste offline (sqlite :memory:/array/sync)
- ✅ docker-compose totalmente conteinerizado (app php-fpm, nginx, node build, redis, horizon)
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
- `DB_*` → Supabase `qkrsrfrlwclzloqjisdr` (host `db.qkrsrfrlwclzloqjisdr.supabase.co`, porta 5432, `DB_CONNECTION=pgsql`, `DB_SSLMODE=require`; `DB_PASSWORD` vazio no `.env.example` versionado)
- `SUPABASE_SERVICE_ROLE_KEY` → **somente no servidor** (nunca em cliente web/mobile)
- `SUPABASE_URL` → `https://qkrsrfrlwclzloqjisdr.supabase.co`
- `GITHUB_APP_ID` / `GITHUB_APP_CLIENT_SECRET` / `GITHUB_APP_PRIVATE_KEY` / `GITHUB_WEBHOOK_SECRET`
- `OPENROUTER_API_KEY`
- `APP_URL` (HTTPS obrigatório para webhook do GitHub)

### Variáveis `.env` definidas (nomes apenas; `.env.example` versionado contém placeholders vazios)
- `DB_CONNECTION=pgsql` · `DB_HOST=db.qkrsrfrlwclzloqjisdr.supabase.co` · `DB_PORT=5432` · `DB_DATABASE=postgres` · `DB_USERNAME=postgres` · `DB_PASSWORD=` · `DB_SSLMODE=require`
- `CACHE_STORE=redis` · `SESSION_DRIVER=redis` · `QUEUE_CONNECTION=redis` · `REDIS_HOST=redis` · `REDIS_PORT=6379` · `REDIS_CLIENT=predis`
- Suíte de teste (forçado no `phpunit.xml`, independente de `.env`): `DB_CONNECTION=sqlite` · `DB_DATABASE=:memory:` · `CACHE_STORE=array` · `SESSION_DRIVER=array` · `QUEUE_CONNECTION=sync`
- `APP_URL=http://localhost:8080` (porta pública do nginx; `REDIS_HOST=redis` = nome do serviço no compose, tanto `.env` local quanto `.env.example`)

### Comandos úteis
- Scaffold Laravel 12: `composer create-project laravel/laravel:^12.0 /tmp/scaffold --prefer-dist --no-interaction` (PHP 8.3.6 · Composer 2.10.2 locais)
- Dependências: `composer install` · `npm install && npm run build` (Vite 7 → `public/build/manifest.json`)
- Testes: `php artisan test` (Pest 3.8 via plugin-laravel 3.2)
- Config env: `cp .env.example .env` → `php artisan key:generate` (`.env` nunca é versionado)
- Observação local: **`pdo_pgsql` e `pdo_sqlite` NÃO instalados** no PHP local; os testes de config da U3 leem apenas `config()` e não conectam no banco (rodam sem driver)
- **Containerização (U4)**: `docker compose up -d --build` → app em `http://localhost:8080` (nginx→php-fpm), redis na 6379, horizon supervisionando filas; `docker compose exec app php artisan test` roda a suíte DENTRO do container (com pdo_sqlite/pdo_pgsql); `docker compose ps` mostra app/nginx/redis/horizon Up + node Exited(0) (one-shot de build de assets)

### Serviços locais
- docker-compose (U4): **app** (php:8.3-fpm-alpine, vendor na imagem + bind `.:/var/www`, entrypoint ajusta permissões do storage), **nginx** (nginx:alpine, `8080:80`, depende de node completed), **node** (node:24-alpine, one-shot `npm install && npm run build`), **redis** (redis:7-alpine, healthcheck, `6379:6379`), **horizon** (php:8.3-cli-alpine, `composer install && php artisan horizon`, depende de redis healthy)
- Extensões PHP ausentes localmente: pdo_pgsql, pdo_sqlite (impacta `migrate` local até a containerização — resolvido na U4: rodar `migrate`/testes via `docker compose exec app`)

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
| D9 | **RLS/triggers/Realtime via migrations Laravel** (guardadas por driver; SQL versionado; `supabase/migrations/` reservado com README) | Fonte única versionada e reproduzível; SQL solto no editor do Supabase gera drift |
| D10 | **Stack 100% conteinerizada** (app/nginx/node/redis/horizon) + horizon/predis no composer | Testes e migrate rodam no container com pdo_sqlite/pdo_pgsql/pcntl (PHP local não tem as extensões) |
| D11 | **Merge em `main` exclusivamente via Pull Request** (ORCHESTRATOR abre a PR; usuário revisa e mergeia; agente nunca mergeia) | Rastreabilidade e controle total das integrações; CI valida toda PR |
| D12 | **CI no GitHub Actions** (backend: pint + pest em SQLite offline; frontend: build assets) — sem credenciais no CI | Qualidade automatizada nas PRs com custo zero |
| D9 | **Stack 100% conteinerizada para dev/testes** (php-fpm + nginx + node + redis + horizon via docker-compose; vendor na imagem com bind `.:/var/www`; entrypoint ajusta permissões do storage em runtime) | Contorna a falta de pdo_pgsql/pdo_sqlite no PHP local; `migrate` e testes rodam no container com os drivers reais; bind mount mantém o ciclo de edição rápido |
| D10 | **Horizon + Predis como dependências do composer** (`laravel/horizon ^5.48`, `predis/predis ^3.6`) | O serviço horizon do compose executa `php artisan horizon` (precisa do pacote) e o `.env` usa `REDIS_CLIENT=predis` (precisa do client predis — ausentes no scaffold da U1) |

---

## 4. MÓDULOS IMPLEMENTADOS

> Preenchido pela skill `diffops-develop` a cada entrega. Formato:
> **Módulo** — arquivos-chave — como testar — observações

- **Scaffold Laravel 12 (U1 Fase B)** — árvore oficial `laravel/laravel 12.66.0` no root do repo (composer create-project via tar; `.gitignore` raiz e internos recriados do scaffold oficial); Pest 3.8 instalado (substitui PHPUnit como runner padrão); smoke test em `tests/Feature/ScaffoldSmokeTest.php` (boot 12.x, GET / 200, manifest.json do Vite). Como testar: `php artisan test` → 5 testes verdes.
- **Integração Inertia/React/Tailwind (U2 Fase B)** — `inertiajs/inertia-laravel` ^3.3 (composer) + `@inertiajs/react` 3.6.1 + `@vitejs/plugin-react` 5.2.0 + React 19.2.8 (npm); stack: `resources/views/app.blade.php` (root `@inertia` + `@vite`), `resources/js/app.jsx` (bootstrap Inertia), `resources/js/Pages/Welcome.jsx` (header mínimo TACTICAL OPS: título `DiffOps` + subtítulo pt-BR, paleta obsidian/asphalt/bone/comms-cyan), `app/Http/Middleware/HandleInertiaRequests.php` registrado no grupo `web` em `bootstrap/app.php`, `config/inertia.php` publicado (path ajustado para `resources/js/Pages`), `routes/web.php` → `Inertia::render('Welcome', ['appName' => 'DiffOps'])`, Tailwind 4 já vinha no skeleton (`@import "tailwindcss"` + plugin `@tailwindcss/vite`; adicionado `@source '../**/*.jsx'`). Como testar: `php artisan test` → 7 testes verdes (smoke + 2 Inertia).
- **Config Supabase Postgres + ambiente de teste (U3 Fase B)** — `.env.example` minimalista versionado (nomes + placeholders vazios, `DB_CONNECTION=pgsql`, host `db.qkrsrfrlwclzloqjisdr.supabase.co`, `DB_SSLMODE=require`, redis para cache/session/queue) + `.env` gerado via `cp` + `key:generate` (não versionado); `config/database.php`: `'default' => env('DB_CONNECTION', 'pgsql')` e pgsql `'sslmode' => env('DB_SSLMODE', 'require')`; `phpunit.xml` já forçava sqlite/:memory:/array/sync (scaffold Laravel 12.66 — sem mudança necessária). Como testar: `php artisan test` → 12 testes verdes (5 novos `EnvConfigTest`: default pgsql no app env, sqlite :memory: na suíte, host supabase.co, sslmode require, offline sync/array).
- **Stack docker-compose totalmente conteinerizada (U4 Fase B)** — `docker-compose.yml` com 5 serviços: **app** (build `.docker/app.Dockerfile`, php:8.3-fpm-alpine + pdo_pgsql/pgsql/pdo_sqlite/pcntl/posix + composer na imagem, vendor em cache com `--no-scripts` e install final, bind `.:/var/www`, `command: php-fpm`, entrypoint `.docker/entrypoint.sh` que ajusta permissões do storage em runtime, expõe 9000, depende de redis); **nginx** (nginx:alpine, volumes `./:/var/www:ro` + `.docker/nginx/default.conf:ro`, ports `8080:80`, depende de app + node `service_completed_successfully`); **node** (node:24-alpine, one-shot `npm install && npm run build`, `restart: "no"`, profiles default — compila assets e sai); **redis** (redis:7-alpine, healthcheck `redis-cli ping`, ports `6379:6379`); **horizon** (build `.docker/horizon.Dockerfile`, php:8.3-cli-alpine com mesmas extensões, sem COPY do código (volume bind), `command: composer install && php artisan horizon`, depende de redis healthy, `restart: unless-stopped`). Config: `.env`/`.env.example` → `REDIS_HOST=redis` e `APP_URL=http://localhost:8080`; `.dockerignore` na raiz (.git, .opencode, node_modules, vendor, .env*, public/build, storage/logs). Novos testes de contrato em `tests/Feature/DockerComposeContractTest.php` (4 testes: 5 serviços, porta 8080:80, extensões nos Dockerfiles, REDIS_HOST=redis). Como testar: `php artisan test` → 16 verdes; `docker compose up -d --build` → `curl localhost:8080/` 200; `docker compose exec app php artisan test` → 16 verdes DENTRO do container (prova dos drivers).

---

## 5. DESVIOS DO BLUEPRINT

> Registrar aqui TODA divergência entre o código real e o `DiffOps.md`, com motivo.

- **`php artisan inertia:install react` não existe no inertia-laravel v3** (comando removido) → instalação manual equivalente: `php artisan inertia:middleware` + registro do `HandleInertiaRequests` no grupo `web` + npm `@inertiajs/react` + `@vitejs/plugin-react` + `vite.config.js` com plugin react e input `resources/js/app.jsx` + `app.blade.php` + `Welcome.jsx` (o plano já previa este caminho alternativo).
- **Plano U3 presumia skeleton com default mysql e phpunit.xml sem overrides** — na real, o scaffold Laravel 12.66 usa `env('DB_CONNECTION', 'sqlite')` e o `phpunit.xml` JÁ força sqlite/:memory:/array/sync. Consequência: (1) os testes de "sqlite na suíte" e "offline" nasceram verdes (não red); (2) o teste do default pgsql precisa simular o app env (limpando temporariamente `DB_CONNECTION` e relendo `config/database.php`) porque o override do phpunit.xml esconderia o fallback.
- **Plano U4 presumia horizon e predis já presentes no composer** — o scaffold da U1 não os inclui. Adicionados na U4 (`composer require laravel/horizon predis/predis`), pois o serviço horizon executa `php artisan horizon` (comando do pacote) e o `.env` define `REDIS_CLIENT=predis` (client que precisa do pacote predis). Registrado como decisão D10.
- **Entrypoint extra no app.Dockerfile (além do plano)** — o plano pedia só `COPY . .` + `composer install` + `CMD php-fpm`, mas o bind mount `.:/var/www` deixa o storage com dono UID 1000 (host) enquanto o php-fpm roda como www-data (UID 82) → `tempnam()` falha e o Laravel retorna 500. Solução: `.docker/entrypoint.sh` executado ANTES do `php-fpm` fazendo `chmod -R a+rwX /var/www/storage /var/www/bootstrap/cache` (permissões universais, compatíveis com host E container; o `command: php-fpm` do compose é preservado como argumento do entrypoint via `exec "$@"`).

---

## 6. ARMADILHAS & APRENDIZADOS

> Registro contínuo de problemas encontrados e soluções (rate limits, config, bugs).

- **`rsync` ausente** no ambiente → substituído por `tar -C <src> -cf - . | tar -C <dest> -xf -` com os mesmos `--exclude` (Fase A intacta, verificado via `git diff`).
- **`create-project` não entrega os `.gitignore` internos** (bootstrap/cache, database, storage/*) → sem eles, `packages.php`/`services.php`/`database.sqlite`/views compiladas seriam versionados. Recriados manualmente a partir do repo oficial `laravel/laravel@12.x`.
- **Scaffold Laravel 12 usa PHPUnit**; para Pest é preciso `composer require pestphp/pest pestphp/pest-plugin-laravel --dev` e criar `tests/Pest.php` manualmente (`php artisan pest:install` não existe no plugin-laravel 3.2).
- **Driver sqlite ausente localmente** → `php artisan migrate` falha no host (aviso `could not find driver`) durante o create-project; irrelevante para testes (`:memory:` só é usado se houver DB, e os testes U1 não tocam banco).
- **`@vitejs/plugin-react@6` exige Vite 8** (peer dep) mas o skeleton usa Vite 7 → fixar `@vitejs/plugin-react@^5` (5.2.0), compatível com Vite 7.
- **Path das páginas Inertia no config publicado é `resource_path('js/pages')` (minúsculo)** e o dir real é `resources/js/Pages` (maiúsculo, convenção React) → `assertInertia` falhava com "page component does not exist" até ajustar o path no `config/inertia.php` publicado.
- **Testar fallback de `config/database.php` sob override do phpunit.xml** — `env('DB_CONNECTION', 'pgsql')` nunca é observável via `config()` na suíte porque o phpunit.xml força sqlite; solução: remover temporariamente `DB_CONNECTION` de `$_ENV`/`$_SERVER`/`getenv()`, `require` o arquivo de config e restaurar em `finally` (teste isolado, sem efeito colateral na suíte).
- **Testes de config rodam sem `pdo_sqlite`/`pdo_pgsql`** — a U3 valida apenas `config()`, nunca conecta; quando a U4 trouxer docker-compose (php-fpm com drivers), `migrate` e testes que tocam banco passam a rodar no container.
- **500 `tempnam(): file created in the system's temporary directory` no container** — causa: permissões do storage no bind mount (dono UID 1000 vs php-fpm www-data 82). Solução: entrypoint com `chmod -R a+rwX storage bootstrap/cache` antes do fpm. Detalhe: se o entrypoint fizer `chown` para www-data (82), o HOST perde acesso aos arquivos (precisa `sudo chown` para reverter) — por isso usamos `chmod` universal em vez de `chown`.
- **`npm install` dentro do container reescreve o `name` do package-lock.json para `www`** — o working_dir do serviço node é `/var/www`; o npm usa o nome do diretório quando o `name` não bate com a raiz. Correção manual: reverter `"name": "www"` → `"name": "DiffOps"` no lock após o build (não versionar o artefato).
- **502 do nginx após `docker compose up -d --build` recriar só o app** — o nginx cacheia o IP resolvido do serviço `app`; quando o container app é recriado (novo IP), o upstream fica com endereço morto. Solução: `docker compose restart nginx` (re-resolve o DNS do compose). Comportamento normal do Docker DNS + cache do nginx.
- **`docker compose exec app` roda como root** — os testes dentro do container passam porque o Laravel não depende de permissões para a suíte, mas a escrita em storage pelo app web roda como www-data; o entrypoint resolve isso antes do fpm.

---

## 7. PRÓXIMOS PASSOS (backlog priorizado)

1. **Fase B** — Scaffold Laravel 12 + Inertia/React/Tailwind + docker-compose ✅ + migrations/RLS/triggers + models/enums (test-first via pipeline)
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
| 2026-08-15 | Política de merge via PR em todas as skills/docs (D11): ORCHESTRATOR abre a PR, usuário revisa e mergeia, agente nunca mergeia; decisões D9–D12 registradas | opencode (build) | B |
| 2026-08-16 | Fase B U4 concluída (branch `@carlosegoulart/02/feat/foundation`, commit `76cf522`): docker-compose totalmente conteinerizado — app (php:8.3-fpm-alpine + pdo_pgsql/pgsql/pdo_sqlite/pcntl/posix + composer + entrypoint de permissões), nginx (alpine, `8080:80`, try_files Laravel, /build e /storage), node (one-shot npm install + build, exit 0), redis (7-alpine healthy), horizon (php:8.3-cli + composer install + artisan horizon, started com sucesso); `.env`/`.env.example` → `REDIS_HOST=redis` e `APP_URL=http://localhost:8080`; `.dockerignore`; dependências novas `laravel/horizon ^5.48` + `predis/predis ^3.6` (D10); `DockerComposeContractTest` com 4 testes de contrato (red→green); suíte 16 verdes local E dentro do container; `docker compose config` válido; curl `localhost:8080/` → 200 (assets compilados); containers ao final: app/nginx/redis/horizon Up, node Exited(0); desvios: horizon+predis ausentes do scaffold (adicionados), entrypoint extra para permissões do storage (500 tempnam), lock do npm reescrito para `www` (revertido), 502 pós-recreate resolvido com restart do nginx | opencode (builder) | B |
| 2026-08-15 | Fase B U3 concluída (branch `@carlosegoulart/02/feat/foundation`, commit `7593d3c`): `.env.example` minimalista com Supabase Postgres (pgsql, host `db.qkrsrfrlwclzloqjisdr.supabase.co`, `DB_SSLMODE=require`, placeholders vazios) + `.env` local gerado (`key:generate`, não versionado); `config/database.php`: default `pgsql` e sslmode `require`; `phpunit.xml` já forçava sqlite/:memory:/array/sync (sem mudança); novo `tests/Unit/EnvConfigTest.php` (5 testes) validando config pgsql/Supabase e suíte offline; suíte 12 testes verdes (29 assertions); desvios: skeleton 12.66 já era sqlite (não mysql) e phpunit já forçava overrides → 2 testes nasceram verdes; teste do default pgsql simula app env limpando `DB_CONNECTION` | opencode (builder) | B |
| 2026-08-15 | Fase B U2 concluída (branch `@carlosegoulart/02/feat/foundation`, commit `1ab382d`): Inertia Laravel ^3.3 + React 19 + Tailwind 4 integrados; app.blade.php root, app.jsx bootstrap, Welcome.jsx (header TACTICAL OPS `DiffOps`), HandleInertiaRequests no grupo web, config/inertia.php publicado (path `resources/js/Pages`), rota `/` → Inertia::render('Welcome', appName='DiffOps'); smoke suite ampliada para 7 testes verdes (2 Inertia novos); desvio: `inertia:install` removido no v3 → instalação manual | opencode (builder) | B |
| 2026-08-15 | Fase B U1 concluída: scaffold Laravel 12.66 no root (branch `@carlosegoulart/02/feat/foundation`, commits 8d134b7 + 7dcbaf3); Pest 3.8 + tests/Pest.php; ScaffoldSmokeTest verde (5 testes); pdo_pgsql/pdo_sqlite ausentes localmente; rsync→tar por indisponibilidade | opencode (builder) | B |
| 2026-08-15 | Fase A concluída: branch `@carlosegoulart/01/chore/setup-foundations` com 5 commits atômicos (DiffOps.md v2.0, DiffOps-develop.md, agentes, skills, AGENTS.md+opencode.json); identidade git local: CarlosEGoulart / goulart193@gmail.com | opencode (build) | A |
| 2026-08-15 | Criação do documento vivo; definição de Fase A (mecanismos: agentes, skills, AGENTS.md, opencode.json); blueprint final gravado em DiffOps.md | opencode (build) | A |
