import { Outlet } from 'react-router'

import { AppFooter } from '@/components/layout/app/AppFooter'
import { FuelStationHeader } from '@/components/layout/fuelStation/FuelStationHeader'
import { FuelStationSidebar } from '@/components/layout/fuelStation/FuelStationSidebar'
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar'
import { TooltipProvider } from '@/components/ui/tooltip'

export function FuelStationLayout() {
    return (
        <TooltipProvider>
            <SidebarProvider>
                <FuelStationSidebar />
                <SidebarInset className="min-h-svh">
                    <FuelStationHeader />
                    <div className="flex min-h-0 flex-1 flex-col overflow-auto p-6">
                        <Outlet />
                    </div>
                    <AppFooter />
                </SidebarInset>
            </SidebarProvider>
        </TooltipProvider>
    )
}
