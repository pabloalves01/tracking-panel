# PR 2 — camada de acesso: onde parei

**Estado: incompleto e não executável.** `routes/api.php` e `bootstrap/app.php`
ainda referenciam classes apagadas, então a aplicação não sobe neste branch.
O `feature/entity-registry` continua íntegro — este trabalho está isolado aqui.

## Decisões já tomadas (não revisitar)

- `companies` + `fuel_stations` → **`organizations`** com `type` (enum aberto:
  um fornecedor com painel é um `case` novo, não uma tabela nova)
- `company_user` + `fuel_station_user` → **`memberships`**, sem coluna de papel
- Convites passam a ter `organization_id`: some o `xor` com CHECK no pg e
  triggers no sqlite
- Token Sanctum: uma coluna `organization_id` no lugar de duas
- **Sem papéis.** Todo membro tem o mesmo acesso. `Membership::OWNER/ADMIN/
  MEMBER`, `MembershipPolicy`, `CompanyFuelStationPolicy`, `MembershipScope` e
  `CompanyOwnershipRepair` foram removidos
- Remoção de membro é soft delete nos dois casos (antes: soft na empresa,
  física no posto)
- Nomenclatura de produto: "contexto" → **"organização"**

## Pronto

| Área | Arquivos |
|---|---|
| Migrations | `organizations`, `memberships`, `invitations`, `fuel_supply_links`, `personal_access_tokens`, `audit_logs` |
| Models | `Organization`, `Membership`, `FuelSupplyLink`, `Invitation`, `User`, `AuditLog` |
| Enum | `App\Enums\OrganizationType` |
| Contexto | `App\Support\CurrentOrganization`, `EnsureOrganization` (um middleware no lugar de dois) |
| Serviços | `InvitationService` 591→421, `MemberService` 267→125, `Organization\FuelStationSignupService`, `Organization\FuelSupplyLinkService` |
| Controllers | `Auth\AuthController`, `Auth\InvitationAcceptController`, `Access\MemberController`, `Access\InvitationController` |
| Resources | `OrganizationResource`, `Access\MemberResource`, `Access\InvitationResource` |

## Falta — backend

1. **`routes/api.php`** — reescrever inteiro. Um grupo de membros/convites em
   vez de três. Middleware: `organization`, `organization:company`,
   `organization:fuel_station`
2. **`bootstrap/app.php`** — aliases: trocar `ensure.company`/`ensure.station`
   por `organization` apontando para `EnsureOrganization`
3. **`Registry\FuelStationLinkController`** — reescrever sobre
   `FuelSupplyLinkService`; `StoreCompanyFuelStationRequest` idem
4. **Controller de posto** — `StationController`/`StationCompanyController`
   foram apagados; recriar sobre `FuelSupplyLinkService`
5. **Sistema > Organizações** — `System\Company\*` foi apagado; recriar como
   CRUD de `organizations` para o admin do sistema
6. **Templates de e-mail** — `resources/views/mail/invitation*.blade.php` ainda
   têm o bloco "SEU ACESSO / {{ $roleLabel }}". Sugestão: trocar pelo tipo da
   organização (Empresa/Posto), que continua sendo contexto útil
7. **`DemoAccessSeeder`** — reescrever sobre organizations/memberships
8. **Testes** — `tests/Feature/` inteiro assume papéis e as tabelas antigas

## Falta — frontend (nada começado)

| Hoje | Vira |
|---|---|
| `SelectContextPage` em `/auth/contextos` | `SelectOrganizationPage` em `/auth/organizacoes` |
| `ContextSwitcher` | `OrganizationSwitcher` |
| `RequireCompany` + `RequireFuelStation` | `RequireOrganization` |
| `RequireManager` | remover (não há papéis) |
| `helpers/labels/roleLabel.js` | remover |
| `lib/auth/session.js`, `stationSession.js` | um só, sobre `/auth/me` |
| Selects e colunas de papel em `MembersPage`, `InviteFormPage`, `CompanyMembersPage`, `StationMembersPage`, `StationInvitePage` | remover |

A API já devolve o que o client precisa resolvido: `GET /auth/me` traz
`current_organization`, `organizations` e `invitations` de uma vez, e
`POST /auth/organization` devolve `redirect_to` — então a troca de organização
deixa de precisar de `location.assign`.

## Pendências herdadas que não entraram ainda

- Códigos de verificação: dois caminhos de emissão e três camadas de limite
  (ADR-004). O teto de palpites por e-mail ainda permite que um terceiro trave
  a recuperação de senha de outra pessoa por até 1h
- Envio síncrono do código (hoje o código viaja em claro na fila)
