import ModulePage from '@/pages/modules/ModulePage'
import { flattenNavigation } from '@/navigation/sidebarNavigation'

/**
 * Uma rota por seção da navegação (exceto o Dashboard, que é a Home).
 * O conteúdo da página vem dos filhos definidos na navegação.
 */
export default flattenNavigation()
    .filter((page) => page.path !== '/app')
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
