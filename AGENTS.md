# AGENTS.md — Regras Globais do DiffOps

Este arquivo é carregado automaticamente por todos os agentes do opencode neste projeto.

## O projeto
- **DiffOps**: SaaS de triagem tática, auditoria de segurança e análise preditiva de Pull Requests do GitHub (TCC).
- **Blueprint master**: `DiffOps.md` (visão, arquitetura, features, decisões — fonte principal).
- **Documento vivo**: `DiffOps-develop.md` (estado real do desenvolvimento).

## Regras obrigatórias (todas as tarefas de build)

1. **Pipeline**: toda tarefa de desenvolvimento segue a skill `pipeline` — PLANNER → BUILDER → TESTER → ORCHESTRATOR. Use os subagentes correspondentes (`.opencode/agent/`).
2. **Test-first**: use a skill `tdd` — mockup primeiro, testes red, implementação green, refactor. Nenhuma feature é entregue sem testes.
3. **Documentação viva**: ao concluir qualquer trabalho, use a skill `diffops-develop` para atualizar/corrigir o `DiffOps-develop.md` (histórico append-only, sem segredos).
4. **Git**: use a skill `git-workflow` — branches `@<user>/<num>/<tipo>/<descricao>`, Conventional Commits **atômicos** (1 unidade lógica = 1 commit, suíte verde).
5. **Push**: NUNCA executar `git push` sem autorização explícita do usuário.
6. **Main**: nunca commitar/mergear diretamente em `main`. **Merge em `main` é EXCLUSIVAMENTE via Pull Request**: o ORCHESTRATOR abre a PR (base `main`) após o push autorizado, e o **usuário revisa e mergeia** — nenhum agente mergeia.
7. **PR com descrição**: TODA PR (aberta pelo ORCHESTRATOR via `gh` OU manualmente pelo usuário) DEVE ter **descrição apresentando as alterações feitas** (resumo das mudanças, unidades entregues, validação/testes, como testar). Se o ORCHESTRATOR não puder abrir a PR (ex.: `gh` sem autenticação), ele **deve entregar no chat o título + corpo completo da descrição** e instruir explicitamente o usuário a colá-los antes do merge — a entrega da PR só é concluída com a descrição fornecida.
7. **Segredos**: nunca escrever chaves/tokens/credenciais em arquivos versionados; usar apenas variáveis `.env` (por nome).
8. **Arquitetura**: seguir o padrão Laravel 12 (diretórios do scaffold — `DiffOps.md` §5). Não criar estruturas customizadas.
9. **Design**: seguir o Design System TACTICAL OPS (`DiffOps.md` §7) em web e mobile.
10. **Idioma**: comunicar-se com o usuário em pt-BR.

## Decisões registradas (não reabrir sem necessidade)
- Auth: JWT stateless via Supabase (web e mobile) — `VerifySupabaseJwt`.
- Supabase Cloud free (`qkrsrfrlwclzloqjisdr.supabase.co`) como fonte única de DB/Auth/Realtime.
- Laravel é o único escritor; service role exclusiva no servidor; clientes só leem.
- IA: OpenRouter (DeepSeek free + fallbacks) com heurística local como âncora/fallback.
- Testes: Pest (backend) · Vitest/RTL (web) · jest-expo/RNTL (mobile).
- Merge: exclusivamente via Pull Request; agente abre a PR, usuário revisa e mergeia.
