import { useState } from 'react'
import { ChevronRightIcon } from 'lucide-react'
import { Link, useLocation } from 'react-router'

import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible'
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
import { useSession } from '@/lib/auth/session'
import { app, joinPath, navigation } from '@/navigation/sidebarNavigation'

function visibleGroupsFor({ currentCompany, user }) {
    return navigation.filter((group) => {
        if (group.access === 'company') return Boolean(currentCompany)
        if (group.access === 'system') return Boolean(user?.is_system_admin)
        return true
    })
}

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

/** Deixa abertos os itens que contêm a rota atual. */
function openItemsForPath(pathname) {
    const open = {}
    const relative = pathname.replace(/^\/app\/?/, '')
    if (!relative) return open

    let current = ''
    for (const segment of relative.split('/')) {
        current = current ? `${current}/${segment}` : segment
        open[current] = true
    }

    return open
}

export function AppSidebar() {
    const { pathname } = useLocation()
    const { state, setOpen } = useSidebar()
    const { user, currentCompany } = useSession()
    const groups = visibleGroupsFor({ currentCompany, user })
    const [openItems, setOpenItems] = useState(() => openItemsForPath(pathname))

    function toggleItem(itemPath) {
        if (state === 'collapsed') setOpen(true)
        setOpenItems((current) => ({ ...current, [itemPath]: !current[itemPath] }))
    }

    return (
        <Sidebar collapsible="icon">
            <SidebarHeader>
                <Link to="/app" className="flex items-center justify-center px-2 py-2">
                    {/* Colorida no light, só laranja no dark. */}
                    <img src="/brand/logo-color.svg" alt={app.name} className="h-10 w-auto dark:hidden" />
                    <img src="/brand/logo-mono.svg" alt="" className="hidden h-10 w-auto dark:block" />
                </Link>
            </SidebarHeader>

            <SidebarContent>
                {groups.map((group, index) => (
                    <SidebarGroup key={group.label} className="px-0">
                        {index > 0 ? <div className="mb-2 border-t border-sidebar-border" /> : null}
                        <SidebarGroupLabel className="px-5">{group.label}</SidebarGroupLabel>
                        <SidebarMenu className="gap-2 px-2">
                            {group.items.map((item) => (
                                <NavItem
                                    key={item.path}
                                    item={item}
                                    fullPath={item.path}
                                    depth={0}
                                    pathname={pathname}
                                    openItems={openItems}
                                    onToggle={toggleItem}
                                />
                            ))}
                        </SidebarMenu>
                    </SidebarGroup>
                ))}
            </SidebarContent>
            <SidebarRail />
        </Sidebar>
    )
}

/**
 * Item da navegação em qualquer nível. A linha inteira é um link:
 * navega para a página da seção e, se houver filhos, expande/recolhe.
 * No nível 0 usa os componentes de menu; nos demais, os de submenu.
 */
function NavItem({ item, fullPath, depth, pathname, openItems, onToggle }) {
    const Icon = item.icon
    const hasChildren = Boolean(item.children?.length)
    const active = isCurrentPath(fullPath, pathname)
    const open = Boolean(openItems[fullPath])

    const Item = depth === 0 ? SidebarMenuItem : SidebarMenuSubItem
    const Button = depth === 0 ? SidebarMenuButton : SidebarMenuSubButton

    const button = (
        <Button
            isActive={active}
            className={active ? 'font-semibold' : undefined}
            render={<Link to={sidebarHref(fullPath)} />}
            onClick={hasChildren ? () => onToggle(fullPath) : undefined}
            {...(depth === 0 ? { tooltip: item.title } : {})}
        >
            {Icon ? <Icon /> : null}
            <span className="truncate">{item.title}</span>
            {hasChildren ? (
                <ChevronRightIcon
                    className={`ml-auto size-4 shrink-0 transition-transform group-data-[collapsible=icon]:hidden ${open ? 'rotate-90' : ''}`}
                />
            ) : null}
        </Button>
    )

    if (!hasChildren) {
        return <Item>{button}</Item>
    }

    return (
        <Item>
            <Collapsible open={open}>
                {button}
                <CollapsibleContent>
                    <SidebarMenuSub className="mt-1 gap-1.5">
                        {item.children.map((child) => (
                            <NavItem
                                key={child.path}
                                item={child}
                                fullPath={joinPath(fullPath, child.path)}
                                depth={depth + 1}
                                pathname={pathname}
                                openItems={openItems}
                                onToggle={onToggle}
                            />
                        ))}
                    </SidebarMenuSub>
                </CollapsibleContent>
            </Collapsible>
        </Item>
    )
}
