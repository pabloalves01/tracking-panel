import EntityFormPage from '@/pages/registry/EntityFormPage'
import EntityListPage from '@/pages/registry/EntityListPage'

/**
 * As sete telas de cadastro, em três rotas.
 *
 * O papel é o segmento `:role` da URL — "motoristas", "clientes" — e é
 * resolvido em `lib/registry/roles`. Antes eram sete arquivos de rota
 * apontando para catorze páginas que diferiam no nome do papel.
 */
export default [
    { path: ':role', element: <EntityListPage /> },
    { path: ':role/novo', element: <EntityFormPage /> },
    { path: ':role/:uuid/editar', element: <EntityFormPage /> },
]
