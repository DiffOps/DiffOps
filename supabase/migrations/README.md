# Supabase migrations (reservado — decisão D9)

Este diretório é **reservado** no DiffOps. Nenhum SQL solto deve ser criado
aqui sem antes registrar a decisão no `DiffOps-develop.md`.

## O que chega aqui (e quando)

- **U6**: RLS (Row Level Security), triggers e Realtime enablers, aplicados via
  migrations Laravel com guarda por driver (`Schema::getConnection()->getDriverName()`
  === `'pgsql'`), garantindo que a suíte offline (sqlite) continue verde.

## Por que não usamos migrations do Supabase CLI

- Laravel é o **único escritor** do banco (decisão registrada no blueprint).
- Service role vive exclusivamente no servidor; clientes apenas leem.
- A suíte de testes roda offline em sqlite — SQL pgsql só pode existir
  atrás de guarda de driver, nunca solto neste diretório.

## Regras

1. Sem `.sql` solto aqui.
2. Sem segredos (`SUPABASE_SERVICE_ROLE` etc.) em arquivos versionados.
3. Alterações em RLS/triggers/Realtime entram por migration Laravel
   driver-guardada + commit atômico, com testes de contrato.
