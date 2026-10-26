import { Building2, LayoutDashboardIcon, UsersIcon } from 'lucide-react'
import { Link, useLocation } from 'react-router'

import {
    Sidebar,
    SidebarContent,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarRail,
} from '@/components/ui/sidebar'
import { useSession } from '@/lib/auth/session'

export function StationSidebar() {
    const { pathname } = useLocation()
    const home = pathname === '/posto'
    const companies = pathname === '/posto/empresas'
    const users = pathname === '/posto/usuarios'
    const { user } = useSession()
    const isAdmin = user?.current_role === 'admin'

    return (
        <Sidebar collapsible="icon">
            <SidebarHeader>
                <Link to="/posto" className="flex items-center justify-center px-2 py-2">
                    <img src="/brand/logo-light.svg" alt="Logiv" className="h-10 w-auto dark:hidden" />
                    <img src="/brand/logo-mono.svg" alt="" className="hidden h-10 w-auto dark:block" />
                </Link>
            </SidebarHeader>

            <SidebarContent>
                <SidebarGroup className="px-0">
                    <SidebarGroupLabel className="px-5">GERAL</SidebarGroupLabel>
                    <SidebarMenu className="gap-2 px-2">
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                isActive={home}
                                tooltip="Início"
                                render={<Link to="/posto" />}
                            >
                                <LayoutDashboardIcon />
                                <span>Início</span>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                isActive={companies}
                                tooltip="Empresas"
                                render={<Link to="/posto/empresas" />}
                            >
                                <Building2 />
                                <span>Empresas</span>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup>
                {isAdmin ? (
                    <SidebarGroup className="px-0">
                        <div className="mb-2 border-t border-sidebar-border" />
                        <SidebarGroupLabel className="px-5">ADMINISTRAÇÃO</SidebarGroupLabel>
                        <SidebarMenu className="gap-2 px-2">
                            <SidebarMenuItem>
                                <SidebarMenuButton
                                    isActive={users}
                                    tooltip="Usuários"
                                    render={<Link to="/posto/usuarios" />}
                                >
                                    <UsersIcon />
                                    <span>Usuários</span>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroup>
                ) : null}
            </SidebarContent>
            <SidebarRail />
        </Sidebar>
    )
}
