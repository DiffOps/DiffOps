---
name: diffops-develop
description: Use ao CONCLUIR qualquer tarefa de desenvolvimento/build no projeto DiffOps para atualizar ou corrigir o arquivo DiffOps-develop.md. Palavras-gatilho: diffops-develop, documentar, atualizar doc, registro, changelog, documentação de desenvolvimento.
---

# Manutenção do DiffOps-develop.md

O `DiffOps-develop.md` é o **documento vivo** que mantém a integridade das informações do projeto entre ambientes e agentes. Após QUALQUER trabalho de desenvolvimento, ele DEVE ser atualizado — não é opcional.

## 1. Quando usar
- Ao concluir uma tarefa (feature, correção, refactor, config).
- Quando uma decisão de arquitetura for tomada ou alterada.
- Quando um problema/armadilha for descoberto e resolvido.
- Quando o estado do projeto mudar (nova fase, módulo implementado, teste quebrando).

## 2. Como atualizar (seção por seção)

| Seção do doc | O que registrar |
|---|---|
| 1. Status | Atualizar fase atual, checklist funcional (✅/⬜), próximo passo |
| 2. Setup do ambiente | Comandos, serviços, variáveis `.env` usadas (nomes, NUNCA valores) |
| 3. Decisões | NOVAS decisões com rationale; nunca apagar decisões antigas |
| 4. Módulos implementados | Módulo, arquivos-chave, como testar, observações |
| 5. Desvios do blueprint | Divergências do código real vs `DiffOps.md` + motivo |
| 6. Armadilhas | Problema → solução (rate limits, bugs de config, gotchas) |
| 7. Próximos passos | Backlog reordenado conforme aprendizado |
| 8. Histórico | **Sempre no topo da tabela**, uma linha por mudança: data, o que mudou, agente |

## 3. Regras de integridade
1. **Histórico é append-only** — nunca editar ou apagar linhas antigas da seção 8.
2. Nunca incluir chaves, tokens, senhas ou segredos (somente nomes de variáveis `.env`).
3. Sincronizar com `DiffOps.md`: se a realidade mudar, corrigir o doc vivo e (se for divergência de blueprint) registrar na seção 5.
4. Registrar o que foi **efetivamente feito**, não intenções.
5. Sempre conferir se a documentação anterior continua válida após a mudança (corrigir se necessário).

## 4. Checklist final
- [ ] Status atualizado
- [ ] Módulos/desvios/armadilhas refletem o trabalho real
- [ ] Histórico com nova linha no topo
- [ ] Sem segredos
