import { Outlet } from 'react-router'

import { AppFooter } from '@/components/layout/AppFooter'
import { StationHeader } from '@/components/layout/StationHeader'
import { StationSidebar } from '@/components/layout/StationSidebar'
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar'
import { TooltipProvider } from '@/components/ui/tooltip'

export function StationLayout() {
    return (
        <TooltipProvider>
            <SidebarProvider>
                <StationSidebar />
                <SidebarInset className="min-h-svh">
                    <StationHeader />
                    <div className="flex min-h-0 flex-1 flex-col overflow-auto p-6">
                        <Outlet />
                    </div>
                    <AppFooter />
                </SidebarInset>
            </SidebarProvider>
        </TooltipProvider>
    )
}
