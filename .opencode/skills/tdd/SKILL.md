---
name: tdd
description: Use em TODO trabalho de código no projeto DiffOps (features, telas, controllers, jobs, serviços, correções). Enforce test-first development: mockup → testes red → implementação green → refactor. Palavras-gatilho: tdd, test-first, testes, mockup, red, green, suíte, cobertura.
---

# Test-First Development (TDD) — DiffOps

O DiffOps é desenvolvido com **testes antes do código**. Um trabalho só é considerado pronto quando:
1. O mockup (contrato visual/estático) existe.
2. Os testes foram escritos ANTES da implementação e comprovadamente falhavam (red).
3. A implementação faz a suíte passar (green).
4. O código foi refatorado mantendo verde.

## 1. Fluxo obrigatório por unidade de trabalho

### a) Mockup primeiro
- Tela/componente: crie a versão estática com dados fake (hardcoded) — é o contrato visual.
- Backend: defina fixtures/factories e o formato de resposta esperado.
- O mockup roda e é testável antes de existir qualquer integração real.

### b) Testes red
- Escreva os testes do plano (PLANNER) antes do código de produção.
- Rode e confirme que falham pelo motivo certo (comportamento ausente).
- Suítes:
  - Backend: **Pest** (`tests/Unit`, `tests/Feature`) — `php artisan test`.
  - Web: **Vitest + React Testing Library** — `npm run test`.
  - Mobile: **jest-expo + React Native Testing Library**.

### c) Implementação green
- Implemente o mínimo necessário até a suíte passar.
- Use `Http::fake()` para serviços externos (GitHub API, OpenRouter) nos testes.

### d) Refactor
- Limpe duplicação, siga a arquitetura padrão Laravel 12 (`DiffOps.md` §5) e o design system TACTICAL OPS (§7).
- Re-execute a suíte: verde no final.

## 2. Regras
- Nenhuma feature entra sem teste correspondente.
- TESTER bloqueia entregas com teste vermelho.
- Commits atômicos: testes + implementação da mesma unidade lógica no mesmo commit (suíte verde em cada commit).
- Testes de integração externa (GitHub/IA) sempre mockados; nenhum teste depende de rede real.

## 3. Padrões de teste exigidos
- **HMAC:** assinatura correta/incorreta, comparador de tempo constante.
- **JWT:** assinatura inválida, expirado, `alg` errado, rotação de chaves (JWKS).
- **Webhook:** idempotência, eventos (opened/synchronize/closed), resposta imediata.
- **OpenRouterService:** 429/5xx com retry, JSON inválido → reparo, fallback heurístico.
- **Heurística:** cada regra (H1–H4) com casos positivos e negativos.
- **Telas:** mockup renderizado, estados (loading/vazio/dados), interações.
