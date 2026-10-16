import { useState } from 'react'
import { ChevronRightIcon, ShipIcon } from 'lucide-react'
import { Link, useLocation } from 'react-router'

import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible'
import {
    Sidebar,
    SidebarContent,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    SidebarRail,
    useSidebar,
} from '@/components/ui/sidebar'
import { app, modules } from '@/navigation/sidebarNavigation'

function sidebarHref(path) {
    const clean = path.replace(/\/+$/, '')

    if (clean === '' || clean === '/app') {
        return '/app'
    }

    if (clean.startsWith('/app/')) {
        return clean
    }

    return `/app/${clean.replace(/^\/+/, '')}`
}

function isCurrentPath(path, pathname) {
    const href = sidebarHref(path)

    if (href === '/app') {
        return pathname === '/app'
    }

    return pathname === href || pathname.startsWith(`${href}/`)
}

export function AppSidebar() {
    const { pathname } = useLocation()
    const { state, setOpen } = useSidebar()
    const [openModule, setOpenModule] = useState(() =>
        modules.find((module) => module.items.some((item) => isCurrentPath(item.path, pathname)))?.title ?? null,
    )

    function expandSidebar() {
        if (state === 'collapsed') {
            setOpen(true)
        }
    }

    return (
        <Sidebar collapsible="icon">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            tooltip={app.name}
                            render={<Link to="/app" />}
                            onClick={expandSidebar}
                        >
                            <span className="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                                <ShipIcon />
                            </span>
                            <span className="grid flex-1 text-left text-sm leading-tight">
                                <span className="truncate font-medium">{app.name}</span>
                                <span className="truncate text-xs text-muted-foreground">
                                    Rastreamento
                                </span>
                            </span>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <SidebarGroup>
                    <SidebarGroupLabel>Módulos</SidebarGroupLabel>
                    <SidebarMenu>
                        {modules.map((module) => {
                            const ModuleIcon = module.icon
                            const moduleIsActive = module.items.some((item) =>
                                isCurrentPath(item.path, pathname),
                            )

                            return (
                                <SidebarMenuItem key={module.title}>
                                    <Collapsible
                                        open={openModule === module.title}
                                        onOpenChange={(next) => {
                                            if (state === 'collapsed') {
                                                expandSidebar()
                                                setOpenModule(module.title)
                                                return
                                            }

                                            setOpenModule(next ? module.title : null)
                                        }}
                                        className="group/collapsible"
                                    >
                                        <CollapsibleTrigger
                                            render={
                                                <SidebarMenuButton
                                                    tooltip={module.title}
                                                    isActive={moduleIsActive}
                                                />
                                            }
                                        >
                                            <ModuleIcon />
                                            <span>{module.title}</span>
                                            <ChevronRightIcon className="ml-auto transition-transform group-data-open/collapsible:rotate-90" />
                                        </CollapsibleTrigger>
                                        <CollapsibleContent>
                                            <SidebarMenuSub>
                                                {module.items.map((item) => {
                                                    const ItemIcon = item.icon

                                                    return (
                                                        <SidebarMenuSubItem key={item.path}>
                                                            <SidebarMenuSubButton
                                                                isActive={isCurrentPath(item.path, pathname)}
                                                                render={<Link to={sidebarHref(item.path)} />}
                                                            >
                                                                <ItemIcon />
                                                                <span>{item.title}</span>
                                                            </SidebarMenuSubButton>
                                                        </SidebarMenuSubItem>
                                                    )
                                                })}
                                            </SidebarMenuSub>
                                        </CollapsibleContent>
                                    </Collapsible>
                                </SidebarMenuItem>
                            )
                        })}
                    </SidebarMenu>
                </SidebarGroup>
            </SidebarContent>
            <SidebarRail />
        </Sidebar>
    )
}
