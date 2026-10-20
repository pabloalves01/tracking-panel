import { CompanySwitcher } from '@/components/layout/CompanySwitcher'
import { ThemeToggle } from '@/components/layout/ThemeToggle'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Separator } from '@/components/ui/separator'
import { SidebarTrigger } from '@/components/ui/sidebar'
import { useSession } from '@/lib/auth/session'

function getInitials(name) {
    const parts = String(name ?? '').trim().split(/\s+/).filter(Boolean)
    if (parts.length === 0) return ''
    const first = parts[0][0]
    const last = parts.length > 1 ? parts[parts.length - 1][0] : ''
    return (first + last).toUpperCase()
}

export function AppHeader() {
    const { user } = useSession()

    return (
        <header className="flex h-14 shrink-0 items-center gap-2 border-b px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger />
                <Separator orientation="vertical" className="data-vertical:h-4" />
            </div>

            <div className="ml-auto flex items-center gap-3">
                <CompanySwitcher />
                <ThemeToggle />
                <Avatar>
                    <AvatarFallback>{getInitials(user.name)}</AvatarFallback>
                </Avatar>
                <span className="grid text-left text-sm leading-tight">
                    <span className="truncate font-medium">{user.name}</span>
                    <span className="truncate text-xs text-muted-foreground">
                        {user.email}
                    </span>
                </span>
            </div>
        </header>
    )
}
