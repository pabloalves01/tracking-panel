import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Separator } from '@/components/ui/separator'
import { SidebarTrigger } from '@/components/ui/sidebar'
import { currentUser } from '@/navigation/sidebarNavigation'

export function AppHeader() {
    return (
        <header className="flex h-14 shrink-0 items-center gap-2 border-b px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger />
                <Separator orientation="vertical" className="data-vertical:h-4" />
            </div>

            <div className="ml-auto flex items-center gap-2">
                <Avatar>
                    <AvatarFallback>{currentUser.initials}</AvatarFallback>
                </Avatar>
                <span className="grid text-left text-sm leading-tight">
                    <span className="truncate font-medium">{currentUser.name}</span>
                    <span className="truncate text-xs text-muted-foreground">
                        {currentUser.email}
                    </span>
                </span>
            </div>
        </header>
    )
}
