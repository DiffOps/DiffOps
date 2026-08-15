# DiffOps-develop.md — Documento Vivo de Desenvolvimento

> Este documento é a **fonte da verdade do estado atual do desenvolvimento** do DiffOps.
> Mantido obrigatoriamente pela skill `diffops-develop` após todo trabalho de build.
> O blueprint master (visão, arquitetura, features, decisões) vive em `DiffOps.md`.

---

## 1. STATUS DO PROJETO

| Campo | Valor |
|---|---|
| Fase atual | **A — Mecanismos** (concluída) |
| Próxima fase | B — Fundação (Laravel 12 + Inertia/Tailwind + docker-compose + migrations) |
| Stack | Laravel 12 · Inertia/React/Tailwind · Expo/NativeWind · Supabase Cloud · Redis/Horizon · OpenRouter |
| Supabase | projeto `qkrsrfrlwclzloqjisdr.supabase.co` (DB + Auth + Realtime) |
| Testes | Pest · Vitest/RTL · jest-expo/RNTL |
| Pipeline | PLANNER → BUILDER → TESTER → ORCHESTRATOR (skill `pipeline`) |

**Checklist funcional (concluído ✅ / pendente ⬜):**
- ⬜ Scaffold Laravel 12 + Inertia/React + Tailwind
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
_(preenchidos conforme a Fase B for executada — ex.: `composer create-project`, `docker compose up`, `php artisan migrate`, `php artisan test`…)_

### Serviços locais
- docker-compose: php-fpm, nginx, redis, horizon worker (a definir na Fase B)

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

- _(nada implementado ainda — Fase A é a criação dos mecanismos de desenvolvimento)_

---

## 5. DESVIOS DO BLUEPRINT

> Registrar aqui TODA divergência entre o código real e o `DiffOps.md`, com motivo.

- _(nenhum desvio registrado)_

---

## 6. ARMADILHAS & APRENDIZADOS

> Registro contínuo de problemas encontrados e soluções (rate limits, config, bugs).

- _(vazio)_

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
| 2026-08-15 | Fase A concluída: branch `@carlosegoulart/01/chore/setup-foundations` com 5 commits atômicos (DiffOps.md v2.0, DiffOps-develop.md, agentes, skills, AGENTS.md+opencode.json); identidade git local: CarlosEGoulart / goulart193@gmail.com | opencode (build) | A |
| 2026-08-15 | Criação do documento vivo; definição de Fase A (mecanismos: agentes, skills, AGENTS.md, opencode.json); blueprint final gravado em DiffOps.md | opencode (build) | A |
