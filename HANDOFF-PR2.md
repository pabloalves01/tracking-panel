# PR 2 — camada de acesso sobre `organizations`

**Estado: completo e compilando.** Backend e frontend fechados. O que
continua pendente está no fim, e é trabalho de um PR 3.

## Decisões (fechadas)

- `companies` + `fuel_stations` → **`organizations`** com `type` (enum aberto:
  um fornecedor com painel é um `case` novo, não uma tabela nova)
- `company_user` + `fuel_station_user` → **`memberships`**, sem coluna de papel
- Convites com `organization_id`: some o `xor` com CHECK no pg e triggers no sqlite
- Token Sanctum: uma coluna `organization_id` no lugar de duas
- **Sem papéis.** Todo membro tem o mesmo acesso
- Remoção de membro é soft delete nos dois casos
- Nomenclatura de produto: "contexto" → **"organização"**

## O que mudou para quem usa

| Antes | Agora |
|---|---|
| `/auth/contextos`, "Selecionar contexto" | `/auth/organizacoes`, "Escolha onde entrar" |
| Empresas e postos em listas separadas | Uma lista só, com selo de tipo |
| Trocar de contexto recarregava a página | Navegação normal, sem piscar |
| Telas de usuários separadas para empresa e posto | A mesma tela, pela organização ativa |
| Select de papel no convite | Só o e-mail |

## API

Resolvida de uma vez, para o client não remontar estado:

```
GET  /auth/me             user, current_organization, organizations, invitations
GET  /auth/organizations  organizations + invitations
POST /auth/organization   troca; devolve redirect_to
```

Membros e convites valem para qualquer tipo de organização:

```
/organization/members       GET, suspend, reactivate, DELETE
/organization/invitations   GET, POST, resend, DELETE
```

## Números

| | Antes | Depois |
|---|---|---|
| `InvitationService` | 591 linhas, 31 métodos | 421, sem métodos gêmeos |
| `MemberService` | 267 | 125 |
| Middlewares de contexto | 2 | 1 |
| Conjuntos de rotas de membros/convites | 3 | 1 |
| Guards no client | 3 (`RequireCompany`, `RequireFuelStation`, `RequireManager`) | 1 |

## Pendente — PR 3

1. **Testes.** `tests/Feature/` inteiro assume papéis e as tabelas antigas
   (`company_user`, `fuel_station_user`, `CompanyFuelStation`). É reescrita,
   não ajuste.

2. **Códigos de verificação (ADR-004).** Continuam como estavam:
   - dois caminhos de emissão (`issueInvitation` síncrono × `issueOpaque` com
     linha sem hash e job `afterResponse`)
   - três camadas de limite, uma delas permitindo que um terceiro trave a
     recuperação de senha de outra pessoa por até 1h
   - o código viaja em claro na fila

3. **Validar subindo a aplicação.** O container tem PHP 8.3 e as dependências
   exigem 8.4, então aqui só foi possível validar estaticamente: lint em todos
   os arquivos, nenhuma classe importada faltando, e as 59 rotas apontando para
   métodos que existem. Rodar `php artisan migrate:fresh --seed` numa máquina
   com PHP 8.4 é o próximo passo.

## Contas de demonstração

Senha: `mesquita2016`. Cobrem os caminhos que o seletor precisa mostrar.

| Conta | Situação |
|---|---|
| `logiv@logiv.com.br` | admin do sistema, sem organização |
| `ana@melo.test` | duas organizações — cai no seletor |
| `bruno@melo.test` | uma só — entra direto na empresa |
| `carla@posto.test` | uma só — entra direto no posto |
| `eduardo@posto.test` | Posto Norte, com pedido de convênio pendente |
| `diego@melo.test` | sem conta; convite em `/auth/convite/convite-demo-diego` |
