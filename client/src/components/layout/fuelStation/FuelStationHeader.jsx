import { LogOut } from 'lucide-react'
import { useNavigate } from 'react-router'

import { ThemeToggle } from '@/components/layout/ThemeToggle'
import { OrganizationSwitcher } from '@/components/layout/OrganizationSwitcher'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
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

export function FuelStationHeader() {
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

            <div className="ml-auto flex min-w-0 items-center gap-2 sm:gap-3">
                <OrganizationSwitcher />
                <ThemeToggle />
                <DropdownMenu>
                    <DropdownMenuTrigger
                        render={
                            <button
                                type="button"
                                aria-label={`Conta de ${user.name}`}
                                className="flex shrink-0 items-center gap-2 rounded-lg px-1 py-1 transition-colors hover:bg-muted"
                            >
                                <Avatar>
                                    <AvatarFallback>{getInitials(user.name)}</AvatarFallback>
                                </Avatar>
                                <span className="hidden text-left text-sm leading-tight md:grid">
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
                            {/* No celular o nome e o e-mail saem do cabeçalho
                                e aparecem aqui, para a pessoa saber em que
                                conta está. */}
                            <DropdownMenuLabel className="md:hidden">
                                <span className="block truncate text-sm text-foreground">{user.name}</span>
                                <span className="block truncate font-normal">{user.email}</span>
                            </DropdownMenuLabel>
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
