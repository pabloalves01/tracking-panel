import { app } from '@/navigation/sidebarNavigation'

export function AppFooter() {
    return (
        <footer className="flex h-10 shrink-0 items-center justify-between border-t px-4 text-xs text-muted-foreground">
            <span>{app.footerName}</span>
            <span>
                versão {app.version} ({app.revision})
            </span>
        </footer>
    )
}
