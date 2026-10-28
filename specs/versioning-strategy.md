# Estratégia de Versionamento — LogIV Tracking

**Status:** Proposta  
**Data:** 2026-10-28

---

## 1. Situação atual

O footer mostra `versão 1.0 (d46498f)`, ambos hardcoded em `sidebarNavigation.js`. Não há tags no repositório, nem CI/CD configurado. Qualquer atualização exige mexer no código manualmente.

---

## 2. Padrão adotado: Semantic Versioning (SemVer)

Formato: **`MAJOR.MINOR.PATCH`**

| Componente | Quando incrementa | Exemplo |
|---|---|---|
| **MAJOR** | Mudança que quebra compatibilidade (API, estrutura de dados, fluxo do usuário muda radicalmente) | `1.0.0` → `2.0.0` |
| **MINOR** | Funcionalidade nova, sem quebrar o que existe | `1.0.0` → `1.1.0` |
| **PATCH** | Correção de bug, ajuste visual, melhoria interna | `1.1.0` → `1.1.1` |

Referência: [semver.org](https://semver.org)

### Pré-releases (opcional)

Para builds de teste ou staging:

```
1.2.0-beta.1
1.2.0-rc.1
```

---

## 3. Fonte da verdade: Git Tags

A versão vive no Git, não no banco de dados. Cada release é uma **annotated tag**:

```bash
git tag -a v1.0.0 -m "Release 1.0.0 — cadastro de entidades e acesso multi-empresa"
git push origin v1.0.0
```

### Por que tags e não tabela?

| Critério | Git Tags | Tabela no banco |
|---|---|---|
| Rastreabilidade | Tag aponta para um commit exato | Precisa de coluna `commit_hash` manual |
| Reprodutibilidade | `git checkout v1.0.0` reconstrói o estado | Banco não reconstrói código |
| Tooling | GitHub Releases, CI/CD, changelog automático | Nada pronto |
| Complexidade | Zero infra | Migration, model, seed, manutenção |
| Multi-tenant | A versão é do sistema, não da empresa | Tabela fica ociosa no schema |

**Conclusão:** tabela de versão no banco não faz sentido para esse caso. A versão é do software, não um dado do negócio.

---

## 4. Como o footer lê a versão

### 4.1 Build-time injection via Vite

O Vite injeta a versão e o hash do commit no build. Nenhum arquivo é alterado manualmente.

**`client/vite.config.js`**

```js
import { execSync } from 'node:child_process'

const version = process.env.APP_VERSION
    ?? execSync('git describe --tags --always 2>/dev/null').toString().trim()
    ?? '0.0.0-dev'

const revision = execSync('git rev-parse --short HEAD 2>/dev/null').toString().trim()

export default defineConfig({
    define: {
        __APP_VERSION__: JSON.stringify(version),
        __APP_REVISION__: JSON.stringify(revision),
    },
    // ... resto da config
})
```

**`client/src/navigation/sidebarNavigation.js`**

```js
export const app = {
    footerName: 'logiv',
    version: __APP_VERSION__,
    revision: __APP_REVISION__,
}
```

**Resultado no footer:** `versão 1.2.0 (a3f8c1d)` — sempre correto, sem tocar no código.

### 4.2 Em dev local

Sem tags, `git describe --always` retorna o hash curto:  
`versão a3f8c1d (a3f8c1d)` — sem problema, é desenvolvimento.

Com uma tag no histórico:  
`versão 1.0.0-12-ga3f8c1d (a3f8c1d)` — 12 commits depois da v1.0.0.

---

## 5. Fluxo de release

### 5.1 Desenvolvimento normal

```
main
  └── PR #30: feat: cadastro de veículos
  └── PR #31: fix: switch não atualiza header
  └── PR #32: feat: dashboard operacional
```

Merges em `main` via PR. Nenhuma tag nesse momento.

### 5.2 Criar uma release

Quando um conjunto de funcionalidades está pronto e testado:

```bash
# 1. Garantir que main está atualizado
git checkout main
git pull origin main

# 2. Criar a tag anotada
git tag -a v1.1.0 -m "Release 1.1.0

- Cadastro de veículos e implementos
- Correção do switch de organização
- Dashboard operacional"

# 3. Publicar
git push origin v1.1.0
```

### 5.3 GitHub Release (recomendado)

Ao publicar a tag, criar uma Release no GitHub com:
- **Título:** `v1.1.0`
- **Descrição:** changelog (pode ser gerado automaticamente pelo GitHub)
- **Marcar como latest**

Isso cria uma página pública da release com download do source code e o changelog completo.

---

## 6. GitHub Action: automação futura

Quando houver CI/CD, um workflow simples roda no push de tag:

**`.github/workflows/release.yml`**

```yaml
name: Release

on:
  push:
    tags: ['v*']

jobs:
  release:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Create GitHub Release
        uses: softprops/action-gh-release@v2
        with:
          generate_release_notes: true

      # Futuro: build, deploy para staging/produção
      # - name: Build
      #   run: cd client && npm ci && npm run build
      #   env:
      #     APP_VERSION: ${{ github.ref_name }}
```

O `APP_VERSION` é passado para o Vite como variável de ambiente — o `vite.config.js` já lê `process.env.APP_VERSION` com prioridade sobre `git describe`.

---

## 7. Convenção de commits → changelog automático

Os commits semânticos que já usamos (`feat:`, `fix:`, `refactor:`) permitem gerar changelog automaticamente:

| Prefixo | Seção no changelog |
|---|---|
| `feat:` | ✨ Novidades |
| `fix:` | 🐛 Correções |
| `refactor:` | ♻️ Melhorias internas |
| `chore:` | (oculto ou em "Outros") |

Ferramentas que leem isso:
- **GitHub Release notes** — botão "Generate release notes" na UI
- **git-cliff** — gera CHANGELOG.md a partir das tags
- **conventional-changelog** — mesmo propósito, ecossistema Node

Nenhum precisa ser adotado agora. Os commits semânticos já nos preparam.

---

## 8. Versionamento do backend (API)

O backend não aparece no footer, mas convém versionar junto:

### Opção recomendada: mesma tag, mesmo repo

Como é monorepo, **uma tag versiona tudo**. `v1.1.0` significa que o par `api/` + `client/` naquele commit é a v1.1.0.

### Endpoint de health (opcional)

```php
// routes/api.php
Route::get('/health', fn () => response()->json([
    'status'  => 'ok',
    'version' => config('app.version'),
]));
```

```php
// config/app.php
'version' => env('APP_VERSION', 'dev'),
```

Útil para monitoramento e debugging em produção.

---

## 9. Quando criar cada tipo de versão

| Situação | Ação |
|---|---|
| Sprint concluída com features novas | `v1.1.0`, `v1.2.0` |
| Bug crítico em produção | Corrige em `main`, `v1.1.1` |
| Refatoração grande sem feature visível | `v1.1.1` (patch) ou nem tag — depende se vai para produção |
| Mudança estrutural no banco/API | `v2.0.0` (major) |
| Primeiro deploy público | `v1.0.0` |
| Build de teste/homologação | `v1.2.0-beta.1` (pré-release) |

---

## 10. Resumo da estrutura

```
Repositório
├── Tags Git (v1.0.0, v1.1.0, ...)     ← fonte da verdade
├── GitHub Releases                      ← changelog e histórico público
├── client/vite.config.js               ← injeta versão + hash no build
├── client/src/navigation/              ← lê __APP_VERSION__ e __APP_REVISION__
├── .github/workflows/release.yml       ← automação futura
└── api/config/app.php                  ← versão disponível no backend
```

**Nenhuma tabela no banco. Nenhum arquivo editado manualmente a cada release.**

---

## 11. Próximo passo

Quando essa proposta for aprovada:

1. Criar a primeira tag `v0.1.0` (estamos em desenvolvimento, não `v1.0.0`)
2. Alterar `vite.config.js` para injetar versão e hash
3. Remover os valores hardcoded de `sidebarNavigation.js`
4. Ajustar o `AppFooter` se necessário

Estimativa: ~15 minutos de implementação.
