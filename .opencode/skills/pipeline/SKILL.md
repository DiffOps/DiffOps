---
name: pipeline
description: Use ao iniciar QUALQUER tarefa de desenvolvimento no projeto DiffOps (features, telas, backend, correções). Orquestra a sequência de agentes PLANNER → BUILDER → TESTER → ORCHESTRATOR. Palavras-gatilho: pipeline, planner, builder, tester, orchestrator, desenvolver, implementar, feature, tela, endpoint, job, serviço, correção.
---

# Pipeline de Desenvolvimento DiffOps

Toda tarefa de desenvolvimento no DiffOps segue uma sequência obrigatória de agentes. Nenhuma etapa pode ser pulada.

## 1. Sequência

```
PLANNER ──plano──▶ BUILDER ──código+testes──▶ TESTER ──reprovado──▶ BUILDER (correção)
                                                   │ aprovado
                                                   ▼
                                          ORCHESTRATOR ── branch+commits+sumário ──▶ USUÁRIO
                                                                  │ testa / autoriza
                                                                  ▼
                                                              PUSH (somente autorizado)
```

## 2. Etapas e handoffs

### PLANNER (subagent `planner`)
- Lê `DiffOps.md` (blueprint) e `DiffOps-develop.md` (estado atual).
- Define: escopo, mockups, testes red, critérios de aceitação, plano de commits atômicos.
- **Handoff ao BUILDER:** o plano completo, sem decisões em aberto.

### BUILDER (subagent `builder`)
- Executa o plano em test-first (skill `tdd`): mockup → testes red → implementação green → refactor.
- Commita atomicamente a cada unidade lógica (skill `git-workflow`). Nunca faz push.
- Ao final, aplica a skill `diffops-develop` para atualizar o documento vivo.
- **Handoff ao TESTER:** código + testes + lista de commits.

### TESTER (subagent `tester`)
- Executa as suítes completas (Pest / Vitest / jest-expo) e valida as regras tdd.
- Emite laudo APROVADO ou REPROVADO com evidências.
- **REPROVADO →** retorna ao BUILDER com o laudo (loop até green).
- **APROVADO →** handoff ao ORCHESTRATOR.

### ORCHESTRATOR (subagent `orchestrator`)
- Cria/valida a branch `@<user>/<num>/<tipo>/<descricao>`.
- Verifica a atomicidade dos commits (`git log`); divide commits gordos; ajusta mensagens Conventional.
- Verifica que nenhum segredo foi versionado.
- **NUNCA faz push.** Entrega ao usuário o resumo para teste e autorização.

## 3. Regras de bloqueio
1. TESTER não aprova com teste vermelho.
2. BUILDER não pula mockups ou testes.
3. ORCHESTRATOR não faz push e não commita em `main`.
4. PLANNER não edita arquivos.
5. Nenhum agente altera `DiffOps.md` sem aprovação do usuário.

## 4. Notas
- Após cada entrega concluída (com push autorizado ou não), registrar o resultado no `DiffOps-develop.md` (skill `diffops-develop`).
- Em tarefas pequenas (1 unidade), as etapas podem ser enxutas, mas a sequência e os bloqueios permanecem.
