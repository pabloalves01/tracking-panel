import ModulePage from '@/pages/modules/ModulePage'
import { flattenNavigation } from '@/navigation/sidebarNavigation'

/**
 * Caminhos que já têm página própria e não devem receber a página genérica.
 * Empresas fica em `system/systemRoutes`; rastreamento é a tela do mapa.
 */
const ownPages = ['sistema/empresas', 'operacoes/rastreamento']

/**
 * Uma rota por seção da navegação (exceto o Dashboard, que é a Home).
 * O conteúdo da página vem dos filhos definidos na navegação.
 */
export default flattenNavigation()
    .filter((page) => page.path !== '/app' && !ownPages.includes(page.path))
    .map((page) => ({
        path: page.path,
        element: (
            <ModulePage
                title={page.title}
                path={page.path}
                children={page.children}
            />
        ),
    }))
