import { LogOut, User } from 'lucide-react'
import { useNavigate } from 'react-router'

import { CompanySwitcher } from '@/components/layout/app/CompanySwitcher'
import { ThemeToggle } from '@/components/layout/ThemeToggle'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Separator } from '@/components/ui/separator'
import { SidebarTrigger } from '@/components/ui/sidebar'
import { logout } from '@/lib/auth/auth'
import { useSession } from '@/lib/auth/session'
import { notifyError } from '@/lib/notify'

function getInitials(name) {
    const parts = String(name ?? '').trim().split(/\s+/).filter(Boolean)
    if (parts.length === 0) return ''
    const first = parts[0][0]
    const last = parts.length > 1 ? parts[parts.length - 1][0] : ''
    return (first + last).toUpperCase()
}

export function AppHeader() {
    const { user } = useSession()
    const navigate = useNavigate()

    async function handleLogout() {
        try {
            await logout()
            navigate('/auth/login', { replace: true })
        } catch (error) {
            notifyError(error)
        }
    }

    return (
        <header className="flex h-14 shrink-0 items-center gap-2 border-b px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger />
                <Separator orientation="vertical" className="data-vertical:h-4" />
            </div>

            <div className="ml-auto flex items-center gap-3">
                <CompanySwitcher />
                <ThemeToggle />
                <DropdownMenu>
                    <DropdownMenuTrigger
                        render={
                            <button
                                type="button"
                                className="flex items-center gap-2 rounded-lg px-1 py-1 transition-colors hover:bg-muted"
                            >
                                <Avatar>
                                    <AvatarFallback>{getInitials(user.name)}</AvatarFallback>
                                </Avatar>
                                <span className="grid text-left text-sm leading-tight">
                                    <span className="truncate font-medium">{user.name}</span>
                                    <span className="truncate text-xs text-muted-foreground">
                                        {user.email}
                                    </span>
                                </span>
                            </button>
                        }
                    />
                    <DropdownMenuContent align="end" className="w-56">
                        <DropdownMenuGroup>
                            <DropdownMenuItem onClick={() => navigate('/app/perfil')}>
                                <User />
                                Meu perfil
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem variant="destructive" onClick={handleLogout}>
                                <LogOut />
                                Sair
                            </DropdownMenuItem>
                        </DropdownMenuGroup>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </header>
    )
}
