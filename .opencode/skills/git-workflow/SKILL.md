---
name: git-workflow
description: Use para QUALQUER operação git no projeto DiffOps (criar branch, commitar, revisar commits, preparar push). Padroniza branches @user/num/tipo/descricao, Conventional Commits atômicos e a política de push com aprovação do usuário. Palavras-gatilho: branch, commit, push, git, conventional commits, atômico, mensagem de commit.
---

# Git Workflow — DiffOps

Padronização obrigatória de branches, commits e push no projeto.

## 1. Branches
Formato: `@<user>/<num-sequencial>/<tipo>/<descricao>`

- `<user>`: username do GitHub do autor da tarefa.
- `<num-sequencial>`: número sequencial da tarefa (sem issue → contador incremental).
- `<tipo>`: `feat | fix | chore | docs | refactor | test | perf | style | build | ci`.
- `<descricao>`: kebab-case curta.

Exemplos:
```
@carloseduardo/01/feat/recon-feed
@carloseduardo/02/fix/verify-jwt-clock-skew
@carloseduardo/03/chore/docker-compose
```

Regras: nunca commitar em `main`; branches sempre derivadas de `main` atualizada.

## 2. Commits (Conventional Commits + atomicidade)

Formato: `<tipo>(<escopo>): <assunto>`

- Escopo = classe/área (ex.: `UserController`, `TacticalFeed`, `VerifySupabaseJwt`).
- `!` para breaking change; corpo (mensagem estendida) quando o "porquê" importa.
- **Atomicidade: 1 commit = 1 unidade lógica completa, com suíte verde.**

Exemplos:
```
feat(UserController): create controller
feat(UserController): add register
feat(UserController): add login
feat(UserController): add logout
feat(TacticalFeed): build header
feat(TacticalFeed): build menu
feat(TacticalFeed): build body
fix(VerifySupabaseJwt): handle key rotation
docs(DiffOps-develop): record design decision D8
```

Regras:
- Testes da unidade entram no MESMO commit da implementação (cada commit mantém a suíte verde).
- Commit gordo (múltiplas unidades) DEVE ser dividido (`git add -p`/`git reset` + recomit).
- Nunca commitar arquivos de ambiente/segredo (`.env`, chaves, `.env.production`).

## 3. Push (política de aprovação)
- **NUNCA** executar `git push` sem autorização explícita do usuário.
- O fluxo: ORCHESTRATOR prepara branch + commits → apresenta resumo → **usuário testa → autoriza → push**.
- Após push autorizado, merge em `main` via PR revisada pelo usuário.

## 4. Verificação antes de finalizar
- [ ] Branch no padrão `@user/num/tipo/descricao`
- [ ] `git log --oneline`: 1 unidade lógica por commit, mensagens convencionais
- [ ] `git status` limpo; sem segredos na árvore
- [ ] Suíte de testes verde no HEAD
