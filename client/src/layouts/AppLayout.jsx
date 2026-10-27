import { Outlet } from 'react-router'

import { AppFooter } from '@/components/layout/app/AppFooter'
import { AppHeader } from '@/components/layout/app/AppHeader'
import { AppSidebar } from '@/components/layout/app/AppSidebar'
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar'
import { TooltipProvider } from '@/components/ui/tooltip'

export function AppLayout() {
    return (
        <TooltipProvider>
            <SidebarProvider>
                <AppSidebar />
                <SidebarInset className="min-h-svh">
                    <AppHeader />
                    <div className="flex min-h-0 flex-1 flex-col overflow-auto p-6">
                        <Outlet />
                    </div>
                    <AppFooter />
                </SidebarInset>
            </SidebarProvider>
        </TooltipProvider>
    )
}
