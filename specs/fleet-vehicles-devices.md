# ADR-001: Frota — veículos, implementos e rastreadores

**Status:** Aceito — implementado
**Data:** 2026-10-28
**Decisores:** Pablo

## Contexto

Este é o cadastro sobre o qual o rastreamento vai ser construído. No logiv-server ele cresceu por acréscimo, e ficou assim:

- **`veiculos` com mais de 60 colunas.** A tabela juntou de tudo:
  - placas de carreta (`placa_dois`, `placa_tres`);
  - rastreador (`rastreador_imei`…);
  - dois donos (`proprietario_veiculo_id` e `proprietario_id`);
  - PIX do titular;
  - campos da Repom.
- **Carretas em `veiculos_carretas`.** A tabela repete placa, RENAVAM, chassi e ANTT. O vínculo com o cavalo é uma FK sem histórico.
- **Rastreador como filho do veículo** (`veiculo_id` obrigatório, `cascadeOnDelete`):
  - não existe rastreador em estoque;
  - excluir o veículo apaga o rastreador;
  - nada impede o mesmo IMEI em dois veículos;
  - o modelo é uma constante PHP.
- **Traccar chamado de dentro do controller**, no meio da transação.
- **Classificação em textos livres** (`classificacao`, `categoria_veiculo`), comparada com `strtolower` na Repom.
- **ANTT no veículo e na carreta.** O RNTRC, porém, é do transportador.

Requisitos:

- **Rastreadores da LogIV no futuro.** Serão instalados com contrato, e uma empresa poderá usar, pagando, o que já estiver no caminhão dela.
- **Rastreador próprio.** A empresa continua podendo cadastrar o seu.
- **Exclusividade.** Um rastreador fica em um veículo por vez.
- **Proprietário e responsável no veículo.** As receitas e as despesas do veículo vão para o responsável, e o PIX dele é por onde se paga.

## Decisão

```
vehicle_types (catálogo) ─┐   body_types (catálogo)
                          │        │
vehicles ─────────────────┴────────┘
 │  owner_id ─────────► vehicle_owners ──┐
 │  responsible_id ───► vehicle_responsibles ──┼─► entities (pessoa, PIX)
 │  carrier_id ───────► carriers (RNTRC, TAC/ETC/CTC) ──┘
 ├─ vehicle_couplings      automotor ⇄ implemento, posição, período
 └─ device_installations   rastreador ⇄ veículo, principal|reserva, período
devices ─────────► device_models (catálogo)
```

### Veículos

**Uma tabela `vehicles` para automotor e implemento.** O implemento tem os mesmos documentos do automotor, recebe rastreador e é tratado igual no MDF-e.

**O grupo sai do tipo, sem coluna `kind` no veículo.** `vehicle_types` traz `kind` e `category`, e `vehicles.vehicle_type_id` aponta para ele. Na tela se escolhe só o tipo, e o grupo e a categoria aparecem abaixo. Não há dois campos que possam se contradizer.

**`kind` vale `motor_vehicle` ou `trailer`.** A escolha foi por `motor_vehicle` e não `traction`:

- é o termo do CTB ("veículo automotor");
- inclui carro e moto, que não tracionam nada;
- no engate, quem puxa é sempre um automotor, e quem é puxado é sempre um implemento. A validação é por grupo.

**Tipos de veículo**, catálogo global da LogIV. `tpRod` é o código do MDF-e, preenchido só onde a equivalência é direta:

| Tipo | Grupo | `tpRod` |
|---|---|---|
| Cavalo mecânico | automotor | 03 |
| Caminhão truck | automotor | 01 |
| Caminhão toco | automotor | 02 |
| Caminhão bitruck | automotor | — |
| Van | automotor | 04 |
| Utilitário | automotor | 05 |
| Automóvel | automotor | — |
| Motocicleta | automotor | — |
| Semirreboque | implemento | — |
| Reboque | implemento | — |
| Dolly | implemento | — |

Implemento não tem `tpRod` no MDF-e.

**Carrocerias** (`body_types`), catálogo global, com `mdfe_code` = `tpCar` onde é direto:

- aberta (01);
- baú (02);
- baú frigorífico (02);
- graneleira (03);
- porta-contêiner (04);
- sider (05);
- tanque, basculante, prancha, cegonha e boiadeira, sem código.

`body_type_id` é nulo quando não se aplica, como no cavalo mecânico (`tpCar` 00). Carroceria não vira veículo.

**Frota** (`fleet_type`): própria, agregado ou terceiro.

**Documento:** placa (normalizada, única por empresa entre as não excluídas), UF, RENAVAM (com dígito verificador; o de 9 dígitos ganha zeros), chassi (17, sem I, O e Q) e validade do CRLV.

**Fabricação:** marca, modelo, ano de fabricação, ano do modelo e cor. A cor segue a tabela do Denatran e aparece com amostra.

### Pesos

Os valores são os nominais do CRLV. O peso de cada carga é da viagem.

| Coluna | O que é |
|---|---|
| `axle_count` | eixos |
| `tare_weight_kg` | tara |
| `load_capacity_kg` | lotação (MDF-e `capKG`) |
| `gross_weight_kg` | **PBT homologado.** O valor impresso no documento é o que vale para fiscalização e nem sempre é tara + lotação, por isso é guardado. |
| `max_traction_weight_kg` | CMT, só para automotor |
| `volume_capacity_m3` | MDF-e `capM3` |

### Pessoas ligadas ao veículo

| Coluna | Papel | Para quê |
|---|---|---|
| `owner_id` (obrigatório) | proprietário | quem consta no CRLV |
| `responsible_id` (obrigatório) | responsável | a quem vão receitas e despesas |
| `carrier_id` (opcional) | transportador | em cujo RNTRC o veículo roda, quando não é o da própria empresa |

- **PIX não é copiado para o veículo.** Ele é da pessoa (`entities.pix_key`), e a API do veículo devolve o do responsável. A tela avisa quando o responsável não tem chave.
- **RNTRC, categoria (TAC/ETC/CTC) e validade ficam em `carriers`.** Isso vale para o transportador, seja empresa, cooperativa ou autônomo.
  - O autônomo que transporta com o próprio caminhão é proprietário e transportador: a mesma pessoa nos dois papéis.
  - No arrendamento, o transportador é o arrendatário.
  - Por isso não há o campo genérico "proprietário na ANTT".

### Engates (`vehicle_couplings`)

- Cada engate é uma linha: `traction_vehicle_id`, `trailer_vehicle_id`, `position`, `coupled_at`, `uncoupled_at`.
- Desengatar fecha a linha, e engatar de novo abre outra.
- Índices únicos parciais (`WHERE uncoupled_at IS NULL`):
  - um engate ativo por implemento;
  - um implemento por posição de cada automotor.
- Na aplicação:
  - quem puxa é automotor e quem é puxado é implemento;
  - nenhum veículo engata em si mesmo;
  - implemento engatado em outro veículo é recusado (409);
  - as linhas ficam travadas (`lockForUpdate`) durante a gravação.
- As datas são do servidor no momento da operação. Com o índice ativo único, os períodos de um implemento não se sobrepõem.
- Trocar o tipo para o outro grupo desfaz os engates.
- `GET /fleet/vehicles/{uuid}/couplings` devolve o histórico.

### Rastreadores (`devices`, `device_installations`, `device_models`)

**O rastreador é um cadastro próprio** e existe sem veículo:

- modelo do catálogo global;
- IMEI (texto, só dígitos);
- `tracking_id` opcional;
- número do chip e ICCID.

**Identificador no Traccar.** Alguns protocolos, como o Suntech, se identificam por número de série em vez do IMEI. O `uniqueId` no Traccar é o `tracking_id` ou, sem ele, o IMEI.

**IMEI e `tracking_id` são únicos no sistema todo.** O Traccar é um só, e o rastreador da LogIV vai ser achado de qualquer empresa. A checagem cruza as duas colunas. Para outra empresa, a mensagem diz só que o identificador já existe.

**Instalação.** Cada instalação é uma linha: `device_id`, `vehicle_id`, `role` (`primary` | `backup`), `installed_at`, `removed_at`.

- Índices únicos parciais:
  - uma instalação ativa por rastreador;
  - um principal ativo por veículo.
- Retirar fecha a linha, e reinstalar abre outra.
- A transferência fecha uma e abre a outra na mesma transação.
- O vínculo atual não é duplicado em `devices`: ele é a instalação aberta.
- `GET /fleet/devices/{uuid}/installations` devolve o histórico.

**A integração com o Traccar não está feita.** Os pontos estão marcados com `TODO(traccar)` em `DeviceInstallationService` (instalar, retirar) e nos services de rastreador e veículo (editar rastreador, trocar placa). Quando entrar, deve ser job depois do commit, com nova tentativa.

**Chip.** Fica no próprio rastreador. A limitação é que a troca de chip sobrescreve número e ICCID, sem histórico. Se o controle de chips (operadora, fatura, troca) virar requisito, entra `sim_cards` com instalação no rastreador, no mesmo padrão.

## Opções consideradas

| Decisão | Escolhida | Alternativa | Por quê |
|---|---|---|---|
| Implementos | mesma tabela | tabela própria (logiv-server) | mesmos documentos, rastreáveis, uma fonte para o MDF-e |
| Grupo do veículo | do tipo (`vehicle_types.kind`) | coluna `kind` | sem contradição possível entre dois campos |
| Tipos e carrocerias | tabela-catálogo | enum PHP | carregam código fiscal e crescem sem deploy |
| Rastreador | cadastro + instalação | colunas no veículo / filho do veículo | estoque, histórico, transferência, um veículo por vez |
| IMEI | único global | único por empresa | `uniqueId` do Traccar; rastreador global |
| RNTRC | no transportador | no veículo / no proprietário | é registro do transportador; cobre arrendamento |
| PIX | da pessoa | colunas no veículo | uma chave só, sem cópia desatualizada |

## Consequências

**Ficam mais fáceis:**

- MDF-e e CIOT, porque tração e reboque vêm do mesmo lugar;
- histórico de composição e de rastreador;
- rastrear implemento;
- transferir rastreador.

**Ficam mais difíceis:**

- a request do veículo valida conforme o grupo do tipo;
- novos tipos, carrocerias e modelos dependem da LogIV. Ainda não há tela para isso, só migration.

## Pendências reais

- **Traccar:** criar e atualizar o device, o grupo da empresa e o SMS de troca de servidor (`TODO(traccar)`).
- **Rastreador global:**
  - o dono passa a ser a organização LogIV;
  - o uso por outra empresa vira `device_subscriptions` (empresa, veículo, vigência, plano);
  - a busca é pela placa.
- **Tela de catálogos** para o admin do sistema (tipos, carrocerias, modelos).
- **Documentos do veículo:** foto do CRLV, extração por IA (há o `VeiculoCrlvExtractionResolver` no logiv-server) e alerta de vencimento.
- **Tag de pedágio, centro de resultado e odômetro.** O odômetro deve vir do rastreador.
- **Rótulo "Transportadora".** Agora também cobre o autônomo (TAC). Talvez mude para "Transportador".
