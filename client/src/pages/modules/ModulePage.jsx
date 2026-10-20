import { ChevronRightIcon } from 'lucide-react'
import { Link } from 'react-router'

import { LayoutToolbar } from '@/components/common/LayoutToolbar'
import { flattenNavigation, joinPath } from '@/navigation/sidebarNavigation'

/**
 * Página de uma seção do menu. Lista os itens que existem dentro dela,
 * no mesmo formato de hub: cada card leva para a próxima seção.
 * `path` é o caminho completo depois de `/app` (ex.: 'financeiro/transacoes').
 */
export default function ModulePage({ title, description, path, children = [] }) {
    const pages = flattenNavigation()
    const breadcrumbs = buildBreadcrumbs(path, pages)

    return (
        <>
            <LayoutToolbar breadcrumbs={breadcrumbs} />

            <header className="mb-6">
                <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
                {description ? (
                    <p className="mt-1 text-sm text-muted-foreground">{description}</p>
                ) : null}
            </header>

            {children.length > 0 ? (
                <div className="grid gap-4 sm:grid-cols-2">
                    {children.map((child) => {
                        const Icon = child.icon

                        return (
                            <Link
                                key={child.path}
                                to={`/app/${joinPath(path, child.path)}`}
                                className="rounded-xl border bg-card p-5 transition-colors hover:bg-muted/50"
                            >
                                <div className="flex items-center gap-3">
                                    {Icon ? (
                                        <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                                            <Icon className="size-4" />
                                        </span>
                                    ) : null}
                                    <span className="font-medium">{child.title}</span>
                                    <ChevronRightIcon className="ml-auto size-4 text-muted-foreground" />
                                </div>
                            </Link>
                        )
                    })}
                </div>
            ) : (
                <div className="rounded-xl border bg-card p-10 text-center text-sm text-muted-foreground">
                    {title}
                </div>
            )}
        </>
    )
}

function buildBreadcrumbs(path, pages) {
    const crumbs = [{ text: 'Início', to: '/app' }]
    const segments = path.split('/')

    segments.forEach((_, index) => {
        const current = segments.slice(0, index + 1).join('/')
        const page = pages.find((item) => item.path === current)
        const isLast = index === segments.length - 1

        crumbs.push({
            text: page?.title ?? current,
            to: isLast ? undefined : `/app/${current}`,
        })
    })

    return crumbs
}
